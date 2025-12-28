<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// DB & Auth
$current_dir = __DIR__;
$api_dir = dirname(dirname($current_dir));
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// Auth Check (Admin Only)
$headers = getallheaders();
$token = isset($headers['Authorization']) ? substr($headers['Authorization'], 7) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// Fetch Providers
$sql = "SELECT id, name, email, phone, image, status, created_at FROM users WHERE role = 'provider' ORDER BY id DESC";
$result = $conn->query($sql);

$providers = [];
while ($row = $result->fetch_assoc()) {
    // Image URL logic
    if (!empty($row['image'])) {
        $row['image_url'] = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/" . $row['image'];
    }
    $providers[] = $row;
}

echo json_encode(["status" => "success", "data" => $providers]);
$conn->close();
?>