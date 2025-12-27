<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// DB & Auth included (path logic same as above)
$current_dir = __DIR__;
$api_dir = dirname(dirname(dirname($current_dir))); 
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// Auth Check
$headers = getallheaders();
$token = isset($headers['Authorization']) ? substr($headers['Authorization'], 7) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]); exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$code = $data['code'] ?? '';
$amount = $data['amount'] ?? 0;
$type = $data['type'] ?? 'fixed'; // fixed or percent
$valid_until = $data['valid_until'] ?? ''; // YYYY-MM-DD

if (empty($code) || empty($amount) || empty($valid_until)) {
    echo json_encode(["status" => "error", "message" => "All fields required"]); exit();
}

$sql = "INSERT INTO coupons (code, discount_amount, discount_type, valid_until) 
        VALUES ('$code', $amount, '$type', '$valid_until')";

if ($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Coupon Created"]);
} else {
    echo json_encode(["status" => "error", "message" => "Duplicate Code or DB Error"]);
}
$conn->close();
?>