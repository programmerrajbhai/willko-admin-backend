<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// DB & Auth
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

// Fetch Reviews with Customer & Provider Names
$sql = "SELECT r.*, 
        u.name as customer_name, 
        p.name as provider_name,
        s.name as service_name
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        LEFT JOIN users p ON r.provider_id = p.id
        LEFT JOIN services s ON r.service_id = s.id
        ORDER BY r.id DESC";

$result = $conn->query($sql);
$reviews = [];

while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}

echo json_encode(["status" => "success", "data" => $reviews]);
$conn->close();
?>