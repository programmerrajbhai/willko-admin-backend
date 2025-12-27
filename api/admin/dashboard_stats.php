<?php
// 1. Debugging ON
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

// 3. Helper Headers
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

// ================== SECURITY CHECK ==================
$headers = getallheaders();
$token = isset($headers['Authorization']) ? $headers['Authorization'] : '';
if (strpos($token, 'Bearer ') === 0) $token = substr($token, 7);

if (empty($token)) {
    echo json_encode(["status" => "error", "message" => "Unauthorized! Token missing."]); exit();
}

$token = $conn->real_escape_string($token);
$authCheck = $conn->query("SELECT role FROM users WHERE auth_token = '$token' LIMIT 1");

if (!$authCheck || $authCheck->num_rows == 0 || $authCheck->fetch_assoc()['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Access Denied or Invalid Token!"]); exit();
}


// ---------------------------------------------------------
// 📅 1. DATE FILTER LOGIC (FIXED)
// ---------------------------------------------------------
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// কন্ডিশন ১: সাধারণ কুয়েরির জন্য (যেখানে টেবিল জয়েন নেই)
$dateConditionSimple = " AND created_at BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";

// কন্ডিশন ২: জয়েন কুয়েরির জন্য (যেখানে orders টেবিলকে 'o' বলা হয়েছে)
// এখানে আমরা স্পষ্টভাবে 'o.created_at' বলে দিচ্ছি যাতে Ambiguous এরর না আসে
$dateConditionAlias = " AND o.created_at BETWEEN '$startDate 00:00:00' AND '$endDate 23:59:59'";


// ---------------------------------------------------------
// 📊 2. MAIN STATS
// ---------------------------------------------------------
$sqlStats = "SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN status = 'assigned' THEN 1 END) as active_orders, 
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
                SUM(CASE WHEN status = 'completed' THEN total_price ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status = 'completed' THEN (total_price * 0.15) ELSE 0 END) as net_profit
             FROM orders 
             WHERE 1=1 $dateConditionSimple"; // Fixed

$statsResult = $conn->query($sqlStats);
if (!$statsResult) { echo json_encode(["status" => "error", "message" => "Stats Query Error: " . $conn->error]); exit(); }
$stats = $statsResult->fetch_assoc();


// ---------------------------------------------------------
// 📈 3. CHART DATA
// ---------------------------------------------------------
$sqlChart = "SELECT DATE(created_at) as date, SUM(total_price) as sales
             FROM orders 
             WHERE status = 'completed' $dateConditionSimple
             GROUP BY DATE(created_at)
             ORDER BY date ASC";
$chartRes = $conn->query($sqlChart);
$chartData = [];
if($chartRes) {
    while ($row = $chartRes->fetch_assoc()) {
        $chartData[] = ["date" => date("d M", strtotime($row['date'])), "sales" => (float)$row['sales']];
    }
}


// ---------------------------------------------------------
// 🏆 4. TOP PERFORMERS (FIXED AMBIGUOUS ERROR)
// ---------------------------------------------------------
// Top Service
$sqlTopService = "SELECT s.name, COUNT(o.id) as total_sold 
                  FROM orders o JOIN services s ON o.service_id = s.id
                  WHERE o.status = 'completed' $dateConditionAlias 
                  GROUP BY o.service_id ORDER BY total_sold DESC LIMIT 1";
                  // উপরে খেয়াল করুন: $dateConditionAlias ব্যবহার করেছি

$topServiceRes = $conn->query($sqlTopService);
if (!$topServiceRes) { 
    // ডিবাগিং: যদি এখনো এরর হয়, মেসেজ দেখাবে
    echo json_encode(["status" => "error", "message" => "Top Service Query Error: " . $conn->error]); exit(); 
}
$topService = ($topServiceRes->num_rows > 0) ? $topServiceRes->fetch_assoc() : null;


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
// Users টেবিলের জন্য Simple Condition ঠিক আছে
$newCustomers = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'user' $dateConditionSimple")->fetch_row()[0] ?? 0;
$newProviders = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'provider' $dateConditionSimple")->fetch_row()[0] ?? 0;
$totalCustomers = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetch_row()[0] ?? 0;
$totalProviders = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'provider'")->fetch_row()[0] ?? 0;

$dueRes = $conn->query("SELECT SUM(current_due) FROM users WHERE role = 'provider'");
$marketDue = $dueRes ? ($dueRes->fetch_row()[0] ?? 0) : 0;


// ---------------------------------------------------------
// 🕒 6. RECENT ORDERS
// ---------------------------------------------------------
$sqlRecent = "SELECT o.id, s.name as service_name, u.name as customer_name, 
              o.total_price, o.status, o.created_at
              FROM orders o
              LEFT JOIN users u ON o.user_id = u.id
              LEFT JOIN services s ON o.service_id = s.id
              ORDER BY o.created_at DESC LIMIT 5";
              
$recentRes = $conn->query($sqlRecent);
$recentOrders = [];
if($recentRes) {
    while($row = $recentRes->fetch_assoc()) { $recentOrders[] = $row; }
}


// ================== FINAL RESPONSE ==================
echo json_encode([
    "status" => "success",
    "filter" => ["start_date" => $startDate, "end_date" => $endDate],
    "dashboard" => [
        "cards" => [
            "total_revenue" => (float)$stats['total_revenue'],
            "net_profit" => (float)$stats['net_profit'],
            "total_orders" => (int)$stats['total_orders'],
            "pending_orders" => (int)$stats['pending_orders'],
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