<?php
// File: api/admin/providers/update.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// DB Connect
$current_dir = __DIR__;
$possible_paths = [__DIR__ . '/../../../db.php', __DIR__ . '/../../db.php'];
foreach ($possible_paths as $path) { if (file_exists($path)) { include_once $path; break; } }

// Auth Check (Skipped for brevity, assume strictly implemented)
$headers = getallheaders();
$token = isset($headers['Authorization']) ? trim(str_replace("Bearer ", "", $headers['Authorization'])) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]); exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id == 0) { echo json_encode(["status" => "error", "message" => "ID missing"]); exit(); }

$name = $conn->real_escape_string($_POST['name']);
$phone = $conn->real_escape_string($_POST['phone']);
$address = $conn->real_escape_string($_POST['address']);
$lat = (float)$_POST['latitude'];
$lng = (float)$_POST['longitude'];
$status = $conn->real_escape_string($_POST['status']);

// Image Logic
$imageSql = "";
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $upload_dir = '../../uploads/';
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $imageName = time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;
    move_uploaded_file($_FILES["image"]["tmp_name"], $upload_dir . $imageName);
    $imageSql = ", image = '$imageName'";
}

// Update Query
$sql = "UPDATE users SET 
        name='$name', phone='$phone', address='$address', latitude='$lat', longitude='$lng', status='$status' 
        $imageSql 
        WHERE id=$id AND role='provider'";

if ($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Provider updated successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Update failed"]);
}
$conn->close();
?>