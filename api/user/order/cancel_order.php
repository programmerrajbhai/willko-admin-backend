<?php
// File: api/user/order/cancel_order.php

// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

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
    echo json_encode(["status" => "error", "message" => "Unauthorized!"]);
    exit();
}

$token = $conn->real_escape_string($token);
$userCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'user'");

if ($userCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Token!"]);
    exit();
}
$user_id = $userCheck->fetch_assoc()['id'];

// 4. Input Handling
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['order_id'])) {
    echo json_encode(["status" => "error", "message" => "Order ID is required"]);
    exit();
}

$order_id = (int)$data['order_id'];
$reason = isset($data['reason']) ? $conn->real_escape_string($data['reason']) : 'Changed my mind';

// 5. Check Order Status
$checkSql = "SELECT status FROM bookings WHERE id = $order_id AND user_id = $user_id";
$result = $conn->query($checkSql);

if ($result->num_rows > 0) {
    $order = $result->fetch_assoc();

    // শুধুমাত্র 'pending' অবস্থায় থাকলে ক্যানসেল করা যাবে
    if ($order['status'] === 'pending') {
        
        // Update Status to 'cancelled'
        // অপশনাল: আমরা 'cancellation_reason' রাখার জন্য কলাম যোগ করতে পারি, আপাতত শুধু স্ট্যাটাস চেঞ্জ করছি
        $updateSql = "UPDATE bookings SET status = 'cancelled' WHERE id = $order_id";
        
        if ($conn->query($updateSql)) {
            echo json_encode(["status" => "success", "message" => "Order cancelled successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database Error"]);
        }

    } else {
        echo json_encode([
            "status" => "error", 
            "message" => "Order cannot be cancelled. Current status is '" . $order['status'] . "'"
        ]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Order not found or permission denied"]);
}

$conn->close();
?>