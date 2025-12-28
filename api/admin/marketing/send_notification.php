<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// DB Setup
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

$data = json_decode(file_get_contents("php://input"), true);
$title = $data['title'] ?? '';
$body = $data['body'] ?? '';
$target = $data['target'] ?? 'all'; // all, users, providers

if (empty($title) || empty($body)) {
    echo json_encode(["status" => "error", "message" => "Title and Body required"]); exit();
}

// 1. Save to History
$sql = "INSERT INTO notifications (title, body, target_audience) VALUES ('$title', '$body', '$target')";
if ($conn->query($sql)) {
    // 2. Here real FCM code will go later
    echo json_encode(["status" => "success", "message" => "Notification Sent Successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "DB Error"]);
}
$conn->close();
?>