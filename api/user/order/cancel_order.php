<?php
// File: api/user/order/cancel_order.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// ==============================================
// ✅ 1. ROBUST DATABASE CONNECTION
// ==============================================
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
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed!"]);
    exit();
}

// 2. Auth Check
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
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$token = $conn->real_escape_string($token);
$userCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND status = 'active'");

if ($userCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Token"]);
    exit();
}
$user_id = $userCheck->fetch_assoc()['id'];

// 3. Cancel Logic
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->order_id) || empty($data->order_id)) {
    echo json_encode(["status" => "error", "message" => "Order ID required"]);
    exit();
}

$order_id = (int)$data->order_id;
$reason = isset($data->reason) ? $conn->real_escape_string($data->reason) : "Changed my mind";

// Check order status
$checkSql = "SELECT status FROM bookings WHERE id = $order_id AND user_id = $user_id";
$checkRes = $conn->query($checkSql);

if ($checkRes->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Order not found"]);
    exit();
}

$currentStatus = strtolower($checkRes->fetch_assoc()['status']);

// শুধুমাত্র এই স্ট্যাটাসগুলো ক্যানসেল করা যাবে
if (in_array($currentStatus, ['completed', 'cancelled', 'rejected'])) {
    echo json_encode(["status" => "error", "message" => "Cannot cancel this order (Current status: $currentStatus)"]);
    exit();
}

// ✅ UPDATE STATUS
$updateSql = "UPDATE bookings SET status = 'cancelled', cancel_reason = '$reason' WHERE id = $order_id";

if ($conn->query($updateSql)) {
    echo json_encode(["status" => "success", "message" => "Order cancelled successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database Update Failed: " . $conn->error]);
}

$conn->close();
?>