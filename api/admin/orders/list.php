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

// 3. Filter Logic (Optional: ?status=pending)
$statusFilter = isset($_GET['status']) ? "WHERE orders.status = '" . $conn->real_escape_string($_GET['status']) . "'" : "";

// 4. Query with JOINS (Customer Name + Service Name)
$sql = "SELECT orders.*, 
               users.name as customer_name, users.phone as customer_phone,
               services.name as service_name, services.image as service_image
        FROM orders
        LEFT JOIN users ON orders.user_id = users.id
        LEFT JOIN services ON orders.service_id = services.id
        $statusFilter
        ORDER BY orders.id DESC";

$result = $conn->query($sql);
$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

echo json_encode(["status" => "success", "data" => $orders]);
$conn->close();
?>