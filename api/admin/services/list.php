<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 1. Database Connection
$current_dir = __DIR__;
$api_dir = dirname(dirname($current_dir));
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// 2. Auth Check
$headers = getallheaders();
$token = isset($headers['Authorization']) ? $headers['Authorization'] : '';
if (stripos($token, 'Bearer ') === 0) $token = substr($token, 7);

$token = $conn->real_escape_string($token);
$checkAdmin = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'");

if ($checkAdmin->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized Access"]);
    exit();
}

// 3. Fetch Services
$sql = "SELECT services.*, categories.name as category_name 
        FROM services 
        LEFT JOIN categories ON services.category_id = categories.id 
        ORDER BY services.id DESC";

$result = $conn->query($sql);
$services = [];

while ($row = $result->fetch_assoc()) {
    if (!empty($row['image'])) {
        $row['image_url'] = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/" . $row['image'];
    } else {
        $row['image_url'] = null;
    }
    // Number format fix
    $row['price'] = (float)$row['price'];
    $row['discount_price'] = (float)$row['discount_price'];
    
    $services[] = $row;
}

echo json_encode(["status" => "success", "data" => $services]);
$conn->close();
?>