<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// DB & Auth Check (Same as add.php)
$current_dir = __DIR__;
$api_dir = dirname(dirname(dirname($current_dir))); 
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

$headers = getallheaders();
$token = isset($headers['Authorization']) ? substr($headers['Authorization'], 7) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) exit();

$data = json_decode(file_get_contents("php://input"), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;

// Get image name to delete file
$img = $conn->query("SELECT image FROM banners WHERE id=$id")->fetch_assoc();
if ($img) {
    if (file_exists($api_dir . '/uploads/' . $img['image'])) {
        unlink($api_dir . '/uploads/' . $img['image']); // Delete actual file
    }
    $conn->query("DELETE FROM banners WHERE id=$id");
    echo json_encode(["status" => "success", "message" => "Banner Deleted"]);
} else {
    echo json_encode(["status" => "error", "message" => "Banner not found"]);
}
$conn->close();
?>