<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// DB Setup
$current_dir = __DIR__;
$api_dir = dirname(dirname(dirname($current_dir))); 
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

$result = $conn->query("SELECT * FROM coupons ORDER BY id DESC");
$coupons = [];

while ($row = $result->fetch_assoc()) {
    $coupons[] = $row;
}

echo json_encode(["status" => "success", "data" => $coupons]);
$conn->close();
?>