<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// DB & Auth
$current_dir = __DIR__;
$api_dir = dirname(dirname($current_dir));
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// Auth Check
$headers = getallheaders();
$token = isset($headers['Authorization']) ? substr($headers['Authorization'], 7) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// Validation
if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['password']) || empty($_POST['phone'])) {
    echo json_encode(["status" => "error", "message" => "Name, Email, Password, Phone required"]);
    exit();
}

$name = $conn->real_escape_string($_POST['name']);
$email = $conn->real_escape_string($_POST['email']);
$phone = $conn->real_escape_string($_POST['phone']);
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

// Check Duplicate Email
if ($conn->query("SELECT id FROM users WHERE email = '$email'")->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Email already exists"]);
    exit();
}

// Image Upload
$imageName = "";
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $upload_dir = $api_dir . '/uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    $imageName = time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;
    move_uploaded_file($_FILES["image"]["tmp_name"], $upload_dir . $imageName);
}

// Insert Provider
$sql = "INSERT INTO users (name, email, phone, password, role, image, status) 
        VALUES ('$name', '$email', '$phone', '$password', 'provider', '$imageName', 'active')";

if ($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Provider Created Successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "DB Error"]);
}
$conn->close();
?>