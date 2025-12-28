<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// DB Setup
$current_dir = __DIR__;
$api_dir = dirname(dirname(dirname($current_dir))); 
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// Auth Check (সংক্ষেপে)
$headers = getallheaders();
$token = isset($headers['Authorization']) ? substr($headers['Authorization'], 7) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]); exit();
}

// Image Upload
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $imageName = "banner_" . time() . "." . $ext;
    move_uploaded_file($_FILES["image"]["tmp_name"], $api_dir . '/uploads/' . $imageName);

    if ($conn->query("INSERT INTO banners (image) VALUES ('$imageName')")) {
        echo json_encode(["status" => "success", "message" => "Banner Uploaded"]);
    } else {
        echo json_encode(["status" => "error", "message" => "DB Error"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Image required"]);
}
$conn->close();
?>