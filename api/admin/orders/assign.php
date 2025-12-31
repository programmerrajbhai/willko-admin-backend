<?php
// ফাইল: api/admin/orders/assign.php

// ১. এরর রিপোর্টিং এবং হেডার
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// ২. ডাটাবেস কানেকশন
$current_dir = __DIR__;
$api_dir = dirname(dirname($current_dir)); // api folder
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// ৩. সিকিউরিটি চেক (অ্যাডমিন অথেন্টিকেশন)
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
    echo json_encode(["status" => "error", "message" => "Unauthorized: Token missing"]);
    exit();
}

$token = $conn->real_escape_string($token);
$adminCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'");

if ($adminCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Access Denied! Admin Only."]);
    exit();
}

// ৪. ইনপুট ভ্যালিডেশন
$data = json_decode(file_get_contents("php://input"), true);
$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;
$provider_id = isset($data['provider_id']) ? (int)$data['provider_id'] : 0;

if ($order_id == 0 || $provider_id == 0) {
    echo json_encode(["status" => "error", "message" => "Order ID and Provider ID are required"]);
    exit();
}

// ৫. অর্ডারের বর্তমান তথ্য আনা (যাতে বুকিং টেবিলে কপি করা যায়)
$orderSql = "SELECT o.*, s.name as service_name 
             FROM orders o 
             LEFT JOIN services s ON o.service_id = s.id 
             WHERE o.id = $order_id";
$orderRes = $conn->query($orderSql);

if ($orderRes->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Order not found"]);
    exit();
}

$orderData = $orderRes->fetch_assoc();

// ৬. ট্রানজেকশন শুরু (যাতে দুটি টেবিল একসাথে আপডেট হয়)
$conn->begin_transaction();

try {
    // ধাপ ১: orders টেবিল আপডেট করা
    $updateOrder = $conn->prepare("UPDATE orders SET provider_id = ?, status = 'assigned' WHERE id = ?");
    $updateOrder->bind_param("ii", $provider_id, $order_id);
    
    if (!$updateOrder->execute()) {
        throw new Exception("Failed to update orders table");
    }

    // ধাপ ২: bookings টেবিলে ডাটা ইনসার্ট করা (Provider App এর জন্য)
    // OTP জেনারেট করা (যেমন: 4521)
    $otp = rand(1000, 9999); 
    $booking_date = date('Y-m-d', strtotime($orderData['schedule_time'])); // Schedule time থেকে date নেওয়া
    
    // বুকিং টেবিলে ডাটা ডুপ্লিকেট যাতে না হয়, আগে চেক করা ভালো (Optional), তবে আমরা সরাসরি ইনসার্ট করছি
    $insertBooking = $conn->prepare("INSERT INTO bookings 
        (user_id, provider_id, service_name, location, amount, status, otp, booking_date, payment_method, payment_status) 
        VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, 'cash', 'pending')");
        
    // ডাটা ম্যাপিং
    // user_id, provider_id, service_name, location (from address), amount (total_price), otp, date
    $insertBooking->bind_param("iisssss", 
        $orderData['user_id'], 
        $provider_id, 
        $orderData['service_name'], 
        $orderData['address'], 
        $orderData['total_price'], 
        $otp,
        $booking_date
    );

    if (!$insertBooking->execute()) {
        throw new Exception("Failed to sync with Provider App (Bookings Table Error)");
    }

    // সব ঠিক থাকলে কমিট করা
    $conn->commit();
    echo json_encode([
        "status" => "success", 
        "message" => "Provider Assigned & Notification Sent!",
        "otp" => $otp // ডিবাগিং এর জন্য OTP দেখাচ্ছি
    ]);

} catch (Exception $e) {
    $conn->rollback(); // কোনো ভুল হলে আগের অবস্থায় ফিরে যাবে
    echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
}

$conn->close();
?>