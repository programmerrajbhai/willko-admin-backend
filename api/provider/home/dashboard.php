<?php
// ১. সেটিংস এবং হেডার
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ২. স্মার্ট ডাটাবেস কানেকশন
$db_loaded = false;
$possible_paths = [
    __DIR__ . '/../../../db.php', 
    __DIR__ . '/../../db.php', 
    $_SERVER['DOCUMENT_ROOT'] . '/willko-admin-backend/db.php'
];

foreach ($possible_paths as $path) { 
    if (file_exists($path)) { 
        include $path; 
        $db_loaded = true; 
        break; 
    } 
}

if (!$db_loaded) { 
    http_response_code(500); 
    echo json_encode(["status" => "error", "message" => "Database connection failed"]); 
    exit(); 
}

// ৩. সিকিউর টোকেন হ্যান্ডলিং
function getBearerToken() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) $headers = trim($_SERVER["Authorization"]);
    elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    elseif (function_exists('apache_request_headers')) {
        $req = apache_request_headers();
        $req = array_combine(array_map('ucwords', array_keys($req)), array_values($req));
        if (isset($req['Authorization'])) $headers = trim($req['Authorization']);
    }
    if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) return $matches[1];
    return null;
}

$token = getBearerToken();
if (!$token) { 
    http_response_code(401); 
    echo json_encode(["status" => "error", "message" => "Unauthorized Access"]); 
    exit(); 
}

// ৪. প্রোভাইডার ভেরিফিকেশন
// users টেবিল থেকে সব ডাটা আনা হচ্ছে
$stmt = $conn->prepare("SELECT id, name, email, phone, profile_image, is_online, rating, balance, category FROM users WHERE auth_token = ? AND role = 'provider'");
$stmt->bind_param("s", $token);
$stmt->execute();
$userRes = $stmt->get_result();

if ($userRes->num_rows == 0) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid Token"]);
    exit();
}

$provider = $userRes->fetch_assoc();
$provider_id = $provider['id'];

// ৫. ড্যাশবোর্ড স্ট্যাটাস ক্যালকুলেশন (আপনার দেওয়া লজিক)
$statsSql = "SELECT 
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as total_jobs_done,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests,
    COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as total_earnings,
    COALESCE(SUM(CASE WHEN status = 'completed' AND DATE(booking_date) = CURDATE() THEN amount ELSE 0 END), 0) as today_earnings
    FROM bookings 
    WHERE provider_id = ?";

$statsStmt = $conn->prepare($statsSql);
$statsStmt->bind_param("i", $provider_id);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

// ৬. গ্রাফের জন্য ডাটা (Flutter এর সাথে মিলিয়ে key name ঠিক করা হয়েছে)
$chartSql = "SELECT DATE(booking_date) as date, SUM(amount) as income 
             FROM bookings 
             WHERE provider_id = ? AND status = 'completed' AND booking_date >= DATE(NOW()) - INTERVAL 7 DAY
             GROUP BY DATE(booking_date) 
             ORDER BY DATE(booking_date) ASC";
$chartStmt = $conn->prepare($chartSql);
$chartStmt->bind_param("i", $provider_id);
$chartStmt->execute();
$chartRes = $chartStmt->get_result();

$chart_data = [];
while ($row = $chartRes->fetch_assoc()) {
    $chart_data[] = [
        "day_name" => date("D", strtotime($row['date'])), // Sat, Sun (Flutter এই কি-ওয়ার্ড খুঁজছে)
        "date" => date("d M", strtotime($row['date'])),   // আপনার রাখা ডেট ফরম্যাট
        "income" => (float)$row['income']
    ];
}

// ৭. রিসেন্ট অ্যাক্টিভিটি
$recentSql = "SELECT id, service_name, status, amount, booking_date 
              FROM bookings 
              WHERE provider_id = ? 
              ORDER BY booking_date DESC LIMIT 5";
$recentStmt = $conn->prepare($recentSql);
$recentStmt->bind_param("i", $provider_id);
$recentStmt->execute();
$recentRes = $recentStmt->get_result();

$recent_activities = [];
while ($row = $recentRes->fetch_assoc()) {
    $recent_activities[] = [
        "booking_id" => $row['id'], // Flutter use
        "job_id" => $row['id'],     // Backup
        "service_name" => $row['service_name'], // Flutter use
        "amount" => $row['amount'],
        "status" => strtolower($row['status']), // completed/pending
        "booking_date" => date("d M, h:i A", strtotime($row['booking_date']))
    ];
}

// ৮. ফাইনাল রেসপন্স তৈরি
// এখানে আমি আপনার 'provider_info' রেখেছি, আবার Flutter এর জন্য 'data' ব্লকও যোগ করেছি।
$response = [
    "status" => "success",
    
    // ✅ Flutter App এই 'data' ব্লক থেকে স্ট্যাটাস আপডেট করে
    "data" => [
        "is_online" => (int)$provider['is_online'],      
        "today_income" => (float)$stats['today_earnings'],
        "total_jobs" => (int)$stats['total_jobs_done'],
        "rating" => (float)$provider['rating'],
        "provider_name" => $provider['name']
    ],

    // ✅ আপনার রিকোয়েস্ট করা সম্পূর্ণ প্রোভাইডার ইনফো (ফিউচার ইউজের জন্য)
    "provider_info" => [
        "id" => $provider['id'],
        "name" => $provider['name'],
        "image" => $provider['profile_image'],
        "category" => $provider['category'],
        "rating" => (float)$provider['rating'],
        "is_online" => (int)$provider['is_online'],
        "wallet_balance" => (float)$provider['balance']
    ],

    // ✅ আপনার রিকোয়েস্ট করা স্ট্যাটস
    "stats" => [
        "today_income" => (float)$stats['today_earnings'],
        "total_income" => (float)$stats['total_earnings'],
        "total_jobs" => (int)$stats['total_jobs_done'],
        "pending_req" => (int)$stats['pending_requests']
    ],

    // চার্ট এবং লিস্ট
    "chart_data" => $chart_data,
    "recent_activities" => $recent_activities
];

echo json_encode($response);

$conn->close();
?>