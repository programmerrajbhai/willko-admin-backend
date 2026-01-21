<?php
// File: api/user/review/submit_review.php

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

// 3. Auth Helper
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

if (empty($data['booking_id']) || empty($data['rating'])) {
    echo json_encode(["status" => "error", "message" => "Booking ID and Rating (1-5) required"]);
    exit();
}

$booking_id = (int)$data['booking_id'];
$rating = (int)$data['rating'];
$comment = isset($data['comment']) ? $conn->real_escape_string($data['comment']) : '';

// 5. Validation
// A. অর্ডারটি এই ইউজারের কিনা এবং প্রভাইডার অ্যাসাইন করা আছে কিনা
$checkOrder = $conn->query("SELECT status, provider_id FROM bookings WHERE id = $booking_id AND user_id = $user_id");

if ($checkOrder->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Order not found!"]);
    exit();
}

$order = $checkOrder->fetch_assoc();

// B. অর্ডার স্ট্যাটাস চেক (শুধুমাত্র completed হলেই রিভিউ দেওয়া যাবে)
if ($order['status'] !== 'completed') {
    echo json_encode(["status" => "error", "message" => "You can only review completed orders."]);
    exit();
}

// C. ডুপ্লিকেট রিভিউ চেক (এক অর্ডারে একবারই রিভিউ দেওয়া যাবে)
$checkReview = $conn->query("SELECT id FROM reviews WHERE booking_id = $booking_id");
if ($checkReview->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "You have already reviewed this order."]);
    exit();
}

$provider_id = $order['provider_id'];

// 6. Insert Review
$sql = "INSERT INTO reviews (booking_id, user_id, provider_id, rating, comment) 
        VALUES ($booking_id, $user_id, $provider_id, $rating, '$comment')";

if ($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Review submitted successfully!"]);
    
    // Optional: প্রভাইডারের এভারেজ রেটিং আপডেট করার কোড এখানে লেখা যেতে পারে
} else {
    echo json_encode(["status" => "error", "message" => "Database Error"]);
}

$conn->close();
?>