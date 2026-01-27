<?php
// File: api/admin/providers/add.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// DB Connect
$current_dir = __DIR__;
$possible_paths = [__DIR__ . '/../../../db.php', __DIR__ . '/../../db.php'];
foreach ($possible_paths as $path) { if (file_exists($path)) { include_once $path; break; } }

// Auth Check
$headers = getallheaders();
$token = isset($headers['Authorization']) ? trim(str_replace("Bearer ", "", $headers['Authorization'])) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]); exit();
}

// Validation
if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['password']) || empty($_POST['phone'])) {
    echo json_encode(["status" => "error", "message" => "Required fields missing"]); exit();
}

$name = $conn->real_escape_string($_POST['name']);
$email = $conn->real_escape_string($_POST['email']);
$phone = $conn->real_escape_string($_POST['phone']);
$address = isset($_POST['address']) ? $conn->real_escape_string($_POST['address']) : '';
$lat = isset($_POST['latitude']) ? (float)$_POST['latitude'] : 0.0;
$lng = isset($_POST['longitude']) ? (float)$_POST['longitude'] : 0.0;
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

// Check Duplicate
if ($conn->query("SELECT id FROM users WHERE email = '$email' OR phone = '$phone'")->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Email or Phone already exists"]); exit();
}

// Image Upload
$imageName = "";
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $upload_dir = '../../uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $imageName = time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;
    move_uploaded_file($_FILES["image"]["tmp_name"], $upload_dir . $imageName);
}

// Insert
$sql = "INSERT INTO users (name, email, phone, password, role, address, latitude, longitude, image, status) 
        VALUES ('$name', '$email', '$phone', '$password', 'provider', '$address', '$lat', '$lng', '$imageName', 'active')";

if ($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Provider added successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
}
$conn->close();
?>