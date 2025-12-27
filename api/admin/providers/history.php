<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// DB & Auth
$current_dir = __DIR__;
$api_dir = dirname(dirname($current_dir));
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// Auth Check
$headers = getallheaders();
$token = isset($headers['Authorization']) ? substr($headers['Authorization'], 7) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$provider_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($provider_id == 0) {
    echo json_encode(["status" => "error", "message" => "Provider ID required"]);
    exit();
}

// Fetch Orders for this provider
$sql = "SELECT orders.*, 
        services.name as service_name, 
        users.name as customer_name, users.phone as customer_phone
        FROM orders 
        LEFT JOIN services ON orders.service_id = services.id
        LEFT JOIN users ON orders.user_id = users.id
        WHERE orders.provider_id = $provider_id
        ORDER BY orders.id DESC";

$result = $conn->query($sql);
$history = [];
$total_income = 0;

while ($row = $result->fetch_assoc()) {
    $history[] = $row;
    if ($row['status'] == 'completed') {
        $total_income += $row['total_price'];
    }
}

echo json_encode([
    "status" => "success", 
    "total_income" => $total_income,
    "data" => $history
]);
$conn->close();
?>