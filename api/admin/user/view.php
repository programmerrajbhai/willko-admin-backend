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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id == 0) {
    echo json_encode(["status" => "error", "message" => "User ID required"]);
    exit();
}

// Fetch User Details
$sql = "SELECT id, name, email, phone, image, status, created_at 
        FROM users WHERE id = $id AND role = 'user'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // Image URL
    if (!empty($user['image'])) {
        $user['image_url'] = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/" . $user['image'];
    }

    // Additional Stats (Total Spent & Orders)
    $statsSql = "SELECT COUNT(*) as order_count, SUM(total_price) as total_spent 
                 FROM orders WHERE user_id = $id AND status = 'completed'";
    $stats = $conn->query($statsSql)->fetch_assoc();
    
    $user['stats'] = [
        "total_orders" => $stats['order_count'] ? (int)$stats['order_count'] : 0,
        "total_spent" => $stats['total_spent'] ? (float)$stats['total_spent'] : 0.00
    ];

    echo json_encode(["status" => "success", "data" => $user]);
} else {
    echo json_encode(["status" => "error", "message" => "User not found"]);
}
$conn->close();
?>