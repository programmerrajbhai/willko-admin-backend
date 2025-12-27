<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// DB Setup
$current_dir = __DIR__;
$api_dir = dirname(dirname(dirname($current_dir))); 
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

$result = $conn->query("SELECT * FROM banners ORDER BY id DESC");
$banners = [];

while ($row = $result->fetch_assoc()) {
    $row['image_url'] = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/" . $row['image'];
    $banners[] = $row;
}

echo json_encode(["status" => "success", "data" => $banners]);
$conn->close();
?>