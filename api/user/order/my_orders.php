<?php
// File: api/user/order/my_orders.php

// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 2. Database Connection
$current_dir = __DIR__;
$possible_paths = [
    __DIR__ . '/../../../db.php', 
    __DIR__ . '/../../db.php',
];

$db_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        include $path;
        $db_loaded = true;
        break;
    }
}

if (!$db_loaded) {
    echo json_encode(["status" => "error", "message" => "Database connection failed!"]);
    exit();
}

// 3. Auth Helper (Check User)
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
    echo json_encode(["status" => "error", "message" => "Unauthorized! Token missing."]);
    exit();
}

$token = $conn->real_escape_string($token);
$userCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'user'");

if ($userCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Token!"]);
    exit();
}

$user_id = $userCheck->fetch_assoc()['id'];

// 4. Fetch Orders
$sql = "SELECT id, final_total, schedule_date, schedule_time, status, created_at 
        FROM bookings 
        WHERE user_id = $user_id 
        ORDER BY id DESC";

$result = $conn->query($sql);

$active_orders = [];
$past_orders = [];

if ($result->num_rows > 0) {
    while ($order = $result->fetch_assoc()) {
        
        // Fetch Items for this order (To show service names)
        $order_id = $order['id'];
        $items_sql = "SELECT service_name, quantity FROM booking_items WHERE booking_id = $order_id";
        $items_res = $conn->query($items_sql);
        
        $items = [];
        $service_names = []; // For quick display
        while ($item = $items_res->fetch_assoc()) {
            $items[] = $item;
            $service_names[] = $item['service_name'];
        }

        // Add extra details to order object
        $order['service_list'] = implode(", ", $service_names); // Example: "AC Repair, Sofa Cleaning"
        $order['items'] = $items;
        $order['final_total'] = (float)$order['final_total'];

        // 5. Categorize (Active vs History)
        // Active: Pending, Confirmed, Accepted, On_way, Working
        // History: Completed, Cancelled, Rejected
        if (in_array($order['status'], ['pending', 'confirmed', 'accepted', 'on_way', 'working'])) {
            $active_orders[] = $order;
        } else {
            $past_orders[] = $order;
        }
    }
}

echo json_encode([
    "status" => "success",
    "data" => [
        "active_orders" => $active_orders,
        "history_orders" => $past_orders
    ]
]);

$conn->close();
?>