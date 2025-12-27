<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. Database Connection
$current_dir = __DIR__;
$api_dir = dirname(dirname($current_dir));
$path_in_api = $api_dir . '/db.php';
$path_in_root = dirname($api_dir) . '/db.php';

if (file_exists($path_in_api)) include $path_in_api;
elseif (file_exists($path_in_root)) include $path_in_root;
else die(json_encode(["status" => "error", "message" => "Database connection failed!"]));

// 2. Auth Helper
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// 3. Security Check
$headers = getallheaders();
$token = isset($headers['Authorization']) ? $headers['Authorization'] : '';
if (stripos($token, 'Bearer ') === 0) $token = substr($token, 7);

$token = $conn->real_escape_string($token);
$checkToken = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'");

if ($checkToken->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized! Please Login."]);
    exit();
}

// 4. Input Validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Only POST method allowed"]);
    exit();
}

$name = isset($_POST['name']) ? $conn->real_escape_string(trim($_POST['name'])) : '';

if (empty($name)) {
    echo json_encode(["status" => "error", "message" => "Category Name is required"]);
    exit();
}

// 5. Secure Image Upload
$imageName = "";
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $filename = $_FILES['image']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        echo json_encode(["status" => "error", "message" => "Invalid image format. Only JPG, PNG, WEBP allowed."]);
        exit();
    }

    $upload_dir = $api_dir . '/uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    // Random unique name to prevent overwriting and guessing
    $imageName = time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;
    
    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $upload_dir . $imageName)) {
        echo json_encode(["status" => "error", "message" => "Image upload failed (Check Permissions)"]);
        exit();
    }
}

// 6. Insert Data
$sql = "INSERT INTO categories (name, image) VALUES ('$name', '$imageName')";

if ($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Category Added Successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database Error"]);
}
$conn->close();
?>