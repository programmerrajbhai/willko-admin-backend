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

// 3. Get ID
$data = json_decode(file_get_contents("php://input"), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;

if ($id == 0) {
    echo json_encode(["status" => "error", "message" => "Order ID is required"]);
    exit();
}

// 4. Delete Query
if ($conn->query("DELETE FROM orders WHERE id = $id")) {
    if ($conn->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Order Deleted Successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Order Not Found"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Database Error"]);
}
$conn->close();
?>