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
$id = isset($data['id']) ? (int)$data['id'] : 0;
$status = isset($data['status']) ? $conn->real_escape_string($data['status']) : '';

$allowed_status = ['pending', 'confirmed', 'assigned', 'completed', 'cancelled'];

if ($id == 0 || !in_array($status, $allowed_status)) {
    echo json_encode(["status" => "error", "message" => "Invalid ID or Status"]);
    exit();
}

// 4. Update Status
if ($conn->query("UPDATE orders SET status = '$status' WHERE id = $id")) {
    echo json_encode(["status" => "success", "message" => "Order Status Updated to $status"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update"]);
}
$conn->close();
?>