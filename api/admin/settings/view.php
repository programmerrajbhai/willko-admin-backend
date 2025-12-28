<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// DB & Auth
$current_dir = __DIR__;
$api_dir = dirname(dirname(dirname($current_dir))); 
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

$result = $conn->query("SELECT * FROM settings WHERE id=1");
$settings = $result->fetch_assoc();

echo json_encode(["status" => "success", "data" => $settings]);
$conn->close();
?>