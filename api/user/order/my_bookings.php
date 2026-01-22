<?php
// File: api/user/order/my_bookings.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// ==============================================
// ✅ 1. ROBUST DATABASE CONNECTION (FIXED HERE)
// ==============================================
$current_dir = __DIR__;
$possible_paths = [
    __DIR__ . '/../../../db.php', // WillkoServiceApi/db.php (Root)
    __DIR__ . '/../../db.php',    // WillkoServiceApi/api/db.php
];

$db_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        include_once $path;
        $db_loaded = true;
        break;
    }
}

// যদি ডাটাবেস ফাইল না পাওয়া যায়
if (!$db_loaded) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed: db.php not found"]);
    exit();
}

// ==============================================
// 2. Token Verification Helper
// ==============================================
function getBearerToken() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) $headers = trim($_SERVER["Authorization"]);
    elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    elseif (function_exists('apache_request_headers')) {
        $req = apache_request_headers();
        if (isset($req['Authorization'])) $headers = trim($req['Authorization']);
    }
    if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) return $matches[1];
    return null;
}

$token = getBearerToken();
if (!$token) {
    echo json_encode(["status" => "unauthorized", "message" => "Please login."]);
    exit();
}

// Token Check
$token = $conn->real_escape_string($token);
$userCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND status = 'active' LIMIT 1");

if ($userCheck->num_rows == 0) {
    echo json_encode(["status" => "unauthorized", "message" => "Session expired."]);
    exit();
}

$user_id = $userCheck->fetch_assoc()['id'];

// ==============================================
// 3. Fetch Bookings
// ==============================================
$sql = "SELECT 
            b.id, b.final_total, b.schedule_date, b.schedule_time, b.status, b.created_at,
            (SELECT service_name FROM booking_items WHERE booking_id = b.id LIMIT 1) as main_service,
            (SELECT COUNT(*) FROM booking_items WHERE booking_id = b.id) as total_items
        FROM bookings b 
        WHERE b.user_id = $user_id 
        ORDER BY b.created_at DESC";

$result = $conn->query($sql);

$active_orders = [];
$history_orders = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        
        $status = strtolower($row['status']);
        
        // Data Structure for App
        $orderData = [
            "id" => $row['id'],
            "booking_id_str" => "#ORD-" . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
            "service_name" => $row['main_service'] . ($row['total_items'] > 1 ? " & more" : ""),
            "date" => date("d M, Y", strtotime($row['schedule_date'])),
            "time" => $row['schedule_time'],
            "price" => "SAR " . number_format($row['final_total'], 0),
            "status" => ucfirst($status),
            "raw_status" => $status
        ];

        // Categorize Logic
        if (in_array($status, ['pending', 'confirmed', 'assigned', 'on_way', 'started'])) {
            $active_orders[] = $orderData;
        } else {
            // completed, cancelled, rejected
            $history_orders[] = $orderData;
        }
    }
}

echo json_encode([
    "status" => "success",
    "data" => [
        "active" => $active_orders,
        "history" => $history_orders
    ]
]);

$conn->close();
?>