<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// DB & Auth (Include Logic)
$current_dir = __DIR__;
$api_dir = dirname(dirname($current_dir));
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// Auth Check...
$headers = getallheaders();
$token = isset($headers['Authorization']) ? substr($headers['Authorization'], 7) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id == 0) {
    echo json_encode(["status" => "error", "message" => "Provider ID required"]);
    exit();
}

// Get Existing
$existing = $conn->query("SELECT * FROM users WHERE id = $id AND role='provider'")->fetch_assoc();
if (!$existing) {
    echo json_encode(["status" => "error", "message" => "Provider not found"]);
    exit();
}

$name = $_POST['name'] ?? $existing['name'];
$phone = $_POST['phone'] ?? $existing['phone'];
$status = $_POST['status'] ?? $existing['status']; // active/inactive

// Image Update
$imageSql = "";
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $newImage = time() . "." . $ext;
    move_uploaded_file($_FILES["image"]["tmp_name"], $api_dir . '/uploads/' . $newImage);
    $imageSql = ", image = '$newImage'";
}

$sql = "UPDATE users SET name='$name', phone='$phone', status='$status' $imageSql WHERE id=$id";

if ($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Provider Updated"]);
} else {
    echo json_encode(["status" => "error", "message" => "Update Failed"]);
}
$conn->close();
?>