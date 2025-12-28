<?php
// 1. Debugging ON (ডেভেলপমেন্টের সময় চালু রাখুন)
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 2. Strong DB Connection
$current_dir = __DIR__;
$root_path = dirname(dirname($current_dir)); 
$api_path = dirname($current_dir); 

if (file_exists($root_path . '/db.php')) {
    include $root_path . '/db.php';
} elseif (file_exists($api_path . '/db.php')) {
    include $api_path . '/db.php';
} else {
    echo json_encode(["status" => "error", "message" => "Database file not found."]);
    exit();
}

if (!isset($conn)) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// 3. Helper Function for Headers
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// ================== SECURITY CHECK (AUTH) ==================
$headers = getallheaders();
$token = isset($headers['Authorization']) ? $headers['Authorization'] : '';
if (strpos($token, 'Bearer ') === 0) $token = substr($token, 7);

if (empty($token)) {
    echo json_encode(["status" => "error", "message" => "Unauthorized! Token missing."]); exit();
}

$token = $conn->real_escape_string($token);
// চেক করা হচ্ছে ইউজার অ্যাডমিন কিনা
$authCheck = $conn->query("SELECT role FROM users WHERE auth_token = '$token' LIMIT 1");

if (!$authCheck || $authCheck->num_rows == 0 || $authCheck->fetch_assoc()['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Access Denied! Admin only."]); exit();
}


// ---------------------------------------------------------
// 📅 1. SMART DATE FILTER LOGIC (UPDATED)
// ---------------------------------------------------------
// Flutter অ্যাপ থেকে আসা 'filter' প্যারামিটার চেক করা
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'monthly';

// ডিফল্ট ভ্যালু
$groupBy = "DATE(created_at)"; 
$dateFormatSQL = "%d %b"; // Example: 25 Dec

if ($filter == 'weekly') {
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate = date('Y-m-d');
} elseif ($filter == 'yearly') {
    $startDate = date('Y-m-d', strtotime('-1 year')); // গত ১ বছর
    $endDate = date('Y-m-d');
    
    // ১ বছরের ডাটা হলে মাস অনুযায়ী গ্রুপ হবে (Jan, Feb, Mar...)
    $groupBy = "DATE_FORMAT(created_at, '%Y-%m')"; 
    $dateFormatSQL = "%b %Y"; // Example: Dec 2023
} else {
    // Default: Monthly (Last 30 days)
    $startDate = date('Y-m-d', strtotime('-30 days'));
    $endDate = date('Y-m-d');
}

// ম্যানুয়াল ডেট রেঞ্জ থাকলে সেটা প্রায়োরিটি পাবে
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
}

// কন্ডিশন ১: সাধারণ কুয়েরির জন্য
$dateConditionSimple = " AND created_at BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";

// কন্ডিশন ২: জয়েন কুয়েরির জন্য (Orders টেবিল alias 'o')
$dateConditionAlias = " AND o.created_at BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";


// ---------------------------------------------------------
// 📊 2. MAIN STATS (CARDS)
// ---------------------------------------------------------
$sqlStats = "SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN status = 'assigned' THEN 1 END) as active_orders, 
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN total_price ELSE 0 END), 0) as total_revenue,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN (total_price * 0.15) ELSE 0 END), 0) as net_profit
             FROM orders 
             WHERE 1=1 $dateConditionSimple"; 

$statsResult = $conn->query($sqlStats);
if (!$statsResult) { echo json_encode(["status" => "error", "message" => "Stats Error: " . $conn->error]); exit(); }
$stats = $statsResult->fetch_assoc();


// ---------------------------------------------------------
// 📈 3. SMART CHART DATA (DYNAMIC GROUPING)
// ---------------------------------------------------------
// এখানে $groupBy ভেরিয়েবল ব্যবহার করা হয়েছে যা ফিল্টার অনুযায়ী চেঞ্জ হয়
$sqlChart = "SELECT DATE_FORMAT(created_at, '$dateFormatSQL') as date_label, 
                    SUM(total_price) as sales
             FROM orders 
             WHERE status = 'completed' $dateConditionSimple
             GROUP BY $groupBy
             ORDER BY created_at ASC";

$chartRes = $conn->query($sqlChart);
$chartData = [];

if($chartRes && $chartRes->num_rows > 0) {
    while ($row = $chartRes->fetch_assoc()) {
        $chartData[] = [
            "date" => $row['date_label'], 
            "sales" => (float)$row['sales']
        ];
    }
} else {
    // ডাটা না থাকলে এম্পটি গ্রাফ যাতে ক্র্যাশ না করে
    $chartData[] = ["date" => date("d M"), "sales" => 0];
}


// ---------------------------------------------------------
// 🏆 4. TOP PERFORMERS
// ---------------------------------------------------------
// Top Service
$sqlTopService = "SELECT s.name, COUNT(o.id) as total_sold 
                  FROM orders o JOIN services s ON o.service_id = s.id
                  WHERE o.status = 'completed' $dateConditionAlias 
                  GROUP BY o.service_id ORDER BY total_sold DESC LIMIT 1";

$topServiceRes = $conn->query($sqlTopService);
$topService = ($topServiceRes && $topServiceRes->num_rows > 0) ? $topServiceRes->fetch_assoc() : null;

// Top Provider
$sqlTopProvider = "SELECT u.name, COUNT(o.id) as tasks_done 
                   FROM orders o JOIN users u ON o.provider_id = u.id
                   WHERE o.status = 'completed' $dateConditionAlias
                   GROUP BY o.provider_id ORDER BY tasks_done DESC LIMIT 1";

$topProviderRes = $conn->query($sqlTopProvider);
$topProvider = ($topProviderRes && $topProviderRes->num_rows > 0) ? $topProviderRes->fetch_assoc() : null;


// ---------------------------------------------------------
// 👥 5. GROWTH & DUE
// ---------------------------------------------------------
// Growth (New users in selected period)
$newCustomers = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'user' $dateConditionSimple")->fetch_row()[0] ?? 0;
$newProviders = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'provider' $dateConditionSimple")->fetch_row()[0] ?? 0;

// Total (All time)
$totalCustomers = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetch_row()[0] ?? 0;
$totalProviders = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'provider'")->fetch_row()[0] ?? 0;

// Market Due (All time)
$dueRes = $conn->query("SELECT SUM(current_due) FROM users WHERE role = 'provider'");
$marketDue = $dueRes ? ($dueRes->fetch_row()[0] ?? 0) : 0;


// ---------------------------------------------------------
// 🕒 6. RECENT ORDERS (LATEST 5)
// ---------------------------------------------------------
$sqlRecent = "SELECT o.id, s.name as service_name, u.name as customer_name, 
              o.total_price, o.status, o.created_at
              FROM orders o
              LEFT JOIN users u ON o.user_id = u.id
              LEFT JOIN services s ON o.service_id = s.id
              ORDER BY o.id DESC LIMIT 5";
              
$recentRes = $conn->query($sqlRecent);
$recentOrders = [];
if($recentRes) {
    while($row = $recentRes->fetch_assoc()) { 
        $recentOrders[] = $row; 
    }
}


// ================== FINAL JSON RESPONSE ==================
echo json_encode([
    "status" => "success",
    "filter_applied" => $filter,
    "date_range" => ["start" => $startDate, "end" => $endDate],
    "dashboard" => [
        "cards" => [
            "total_revenue" => (float)$stats['total_revenue'],
            "net_profit" => (float)$stats['net_profit'],
            "total_orders" => (int)$stats['total_orders'],
            "pending_orders" => (int)$stats['pending_orders'],
            "completed_orders" => (int)$stats['completed_orders'],
            "market_due" => (float)$marketDue
        ],
        "chart_data" => $chartData,
        "growth" => [
            "new_customers" => (int)$newCustomers,
            "new_providers" => (int)$newProviders,
            "total_customers" => (int)$totalCustomers,
            "total_providers" => (int)$totalProviders
        ],
        "top_performers" => [
            "top_service" => $topService ? $topService['name'] : "N/A",
            "top_provider" => $topProvider ? $topProvider['name'] : "N/A"
        ],
        "recent_orders" => $recentOrders
    ]
]);

$conn->close();
?>