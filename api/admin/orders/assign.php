<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. DB Connection
$current_dir = __DIR__;
$api_dir = dirname(dirname($current_dir));
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// 2. Auth Check
$headers = getallheaders();
$token = isset($headers['Authorization']) ? $headers['Authorization'] : '';
if (stripos($token, 'Bearer ') === 0) $token = substr($token, 7);
$token = $conn->real_escape_string($token);

if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized Access"]);
    exit();
}

// 3. Input Validation
$data = json_decode(file_get_contents("php://input"), true);
$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;
$provider_id = isset($data['provider_id']) ? (int)$data['provider_id'] : 0;

if ($order_id == 0 || $provider_id == 0) {
    echo json_encode(["status" => "error", "message" => "Order ID and Provider ID required"]);
    exit();
}

// 4. Update Query (Status becomes 'assigned')
$sql = "UPDATE orders SET provider_id = '$provider_id', status = 'assigned' WHERE id = '$order_id'";

if ($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Provider Assigned Successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database Error"]);
}
$conn->close();
?>