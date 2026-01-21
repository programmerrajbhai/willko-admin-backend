<?php
// File: api/admin/marketing/coupons_add.php

// 1. Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 2. Database Connection (Smart Path)
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

// 3. Auth Check (Admin Token)
$headers = getallheaders();
$token = isset($headers['Authorization']) ? $headers['Authorization'] : '';
if (strpos($token, 'Bearer ') === 0) $token = substr($token, 7);

$adminCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'");
if ($adminCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit();
}

// 4. Input Handling
$data = json_decode(file_get_contents("php://input"), true);

$code = isset($data['code']) ? strtoupper(trim($data['code'])) : '';
$amount = isset($data['amount']) ? (float)$data['amount'] : 0;
$type = isset($data['type']) ? $data['type'] : 'fixed'; // fixed or percent
$valid_until = isset($data['valid_until']) ? $data['valid_until'] : '';

if (empty($code) || empty($amount) || empty($valid_until)) {
    echo json_encode(["status" => "error", "message" => "Code, Amount and Validity Date required"]);
    exit();
}

// Security Clean up
$code = $conn->real_escape_string($code);
$type = $conn->real_escape_string($type);
$valid_until = $conn->real_escape_string($valid_until);

// 5. Duplicate Check
$check = $conn->query("SELECT id FROM coupons WHERE code = '$code'");
if ($check->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Coupon code already exists!"]);
    exit();
}

// 6. Insert Coupon
$sql = "INSERT INTO coupons (code, discount_amount, discount_type, valid_until, status) 
        VALUES ('$code', '$amount', '$type', '$valid_until', 'active')";

if ($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Coupon Created Successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $conn->error]);
}

$conn->close();
?>