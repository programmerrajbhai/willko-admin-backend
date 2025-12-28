<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

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
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id == 0) {
    echo json_encode(["status" => "error", "message" => "Order ID required"]);
    exit();
}

// 4. Fetch Order Details
$sql = "SELECT orders.*, 
               users.name as customer_name, users.phone as customer_phone, users.email as customer_email,
               services.name as service_name, services.price as service_price
        FROM orders
        LEFT JOIN users ON orders.user_id = users.id
        LEFT JOIN services ON orders.service_id = services.id
        WHERE orders.id = $id";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo json_encode(["status" => "success", "data" => $result->fetch_assoc()]);
} else {
    echo json_encode(["status" => "error", "message" => "Order not found"]);
}
$conn->close();
?>