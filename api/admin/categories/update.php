<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. DB Connection
$current_dir = __DIR__;
$api_dir = dirname(dirname($current_dir));
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// 2. Auth Check
$headers = getallheaders();
$token = isset($headers['Authorization']) ? $headers['Authorization'] : '';
if (stripos($token, 'Bearer ') === 0) $token = substr($token, 7);

$token = $conn->real_escape_string($token);
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized Access"]);
    exit();
}

// 3. Input Validation
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$name = isset($_POST['name']) ? $conn->real_escape_string(trim($_POST['name'])) : '';

if ($id == 0 || empty($name)) {
    echo json_encode(["status" => "error", "message" => "ID and Name are required"]);
    exit();
}

// 4. Handle Image Update
$imageUpdateSql = "";
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {
        $upload_dir = $api_dir . '/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $newImageName = time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;
        
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $upload_dir . $newImageName)) {
            $imageUpdateSql = ", image = '$newImageName'";
        }
    }
}

// 5. Update Query
$sql = "UPDATE categories SET name = '$name' $imageUpdateSql WHERE id = $id";

if ($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Category Updated Successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update"]);
}
$conn->close();
?>