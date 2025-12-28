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
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// Fetch Transactions with Provider Name
$sql = "SELECT transactions.*, 
               users.name as provider_name, users.phone as provider_phone 
        FROM transactions 
        LEFT JOIN users ON transactions.provider_id = users.id 
        ORDER BY transactions.id DESC";

$result = $conn->query($sql);
$history = [];

while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

echo json_encode(["status" => "success", "data" => $history]);
$conn->close();
?>