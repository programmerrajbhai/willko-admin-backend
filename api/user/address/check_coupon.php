<?php
// File: api/user/coupon/check_coupon.php

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
    echo json_encode(["status" => "error", "message" => "Database connection failed! db.php not found."]);
    exit();
}

// 3. Auth Check (User logged in check)
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
    echo json_encode(["status" => "error", "message" => "Unauthorized! Please login."]);
    exit();
}

// Token Verification
$token = $conn->real_escape_string($token);
$userCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'user'");
if ($userCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Token!"]);
    exit();
}

// 4. Input Handling
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['coupon_code'])) {
    echo json_encode(["status" => "error", "message" => "Coupon code is required to check"]);
    exit();
}

$code = $conn->real_escape_string(trim($data['coupon_code']));
$order_amount = isset($data['total_amount']) ? (float)$data['total_amount'] : 0.0; // অর্ডারের মেইন অ্যামাউন্ট

// 5. Coupon Validation
$sql = "SELECT * FROM coupons 
        WHERE code = '$code' 
        AND status = 'active' 
        AND valid_until >= CURDATE()";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $coupon = $result->fetch_assoc();
    
    // ডিসকাউন্ট ক্যালকুলেশন
    $discount = 0.0;
    
    if ($coupon['discount_type'] == 'fixed') {
        $discount = (float)$coupon['discount_amount'];
    } elseif ($coupon['discount_type'] == 'percent') {
        $discount = ($order_amount * (float)$coupon['discount_amount']) / 100;
    }
    
    // ডিসকাউন্ট যেন টোটাল বিলের বেশি না হয়ে যায়
    if ($order_amount > 0 && $discount > $order_amount) {
        $discount = $order_amount;
    }

    echo json_encode([
        "status" => "success", 
        "message" => "Coupon Applied Successfully",
        "data" => [
            "code" => $coupon['code'],
            "discount_amount" => round($discount, 2),
            "new_total" => max(0, $order_amount - $discount)
        ]
    ]);

} else {
    echo json_encode(["status" => "error", "message" => "Invalid or Expired Coupon Code"]);
}

$conn->close();
?>