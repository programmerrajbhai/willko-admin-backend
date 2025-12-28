<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// DB & Auth
$current_dir = __DIR__;
$api_dir = dirname(dirname(dirname($current_dir))); // api folder
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// Auth Check
$headers = getallheaders();
$token = isset($headers['Authorization']) ? substr($headers['Authorization'], 7) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// Fetch Providers with Due Amount > 0
$sql = "SELECT id, name, phone, email, image, current_due 
        FROM users 
        WHERE role = 'provider' AND current_due > 0 
        ORDER BY current_due DESC";

$result = $conn->query($sql);
$due_list = [];
$total_due = 0;

while ($row = $result->fetch_assoc()) {
    // Image URL
    if (!empty($row['image'])) {
        $row['image_url'] = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/" . $row['image'];
    }
    $due_list[] = $row;
    $total_due += $row['current_due'];
}

echo json_encode([
    "status" => "success", 
    "total_market_due" => $total_due,
    "data" => $due_list
]);
$conn->close();
?>