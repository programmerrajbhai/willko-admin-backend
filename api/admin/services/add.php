<?php
// 1. Error Reporting (ডিবাগিং এর জন্য)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 2. Database Connection (Robust Path Logic)
$current_dir = __DIR__;
$api_dir = dirname(dirname($current_dir)); // api folder

// সম্ভাব্য পাথগুলো চেক করা
$possible_paths = [
    $api_dir . '/db.php',       // api/db.php
    dirname($api_dir) . '/db.php' // Root/db.php
];

$db_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        include $path;
        $db_loaded = true;
        break;
    }
}

if (!$db_loaded) {
    die(json_encode([
        "status" => "error", 
        "message" => "Database connection failed! db.php not found.",
        "debug" => "Checked paths: " . implode(", ", $possible_paths)
    ]));
}

// 3. Auth Check
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

$headers = getallheaders();
$token = isset($headers['Authorization']) ? $headers['Authorization'] : '';
if (stripos($token, 'Bearer ') === 0) $token = substr($token, 7);

$token = $conn->real_escape_string($token);
$checkAdmin = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'");

if (!$checkAdmin || $checkAdmin->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized Access"]);
    exit();
}

// 4. Input Validation (Missing Fields Check)
if (empty($_POST['category_id']) || empty($_POST['name']) || empty($_POST['price'])) {
    echo json_encode([
        "status" => "error", 
        "message" => "Required fields missing! Please send: category_id, name, price"
    ]);
    exit();
}

$category_id = (int)$_POST['category_id'];
$name = $conn->real_escape_string(trim($_POST['name']));
$price = (float)$_POST['price'];
$discount_price = isset($_POST['discount_price']) ? (float)$_POST['discount_price'] : 0;
$description = isset($_POST['description']) ? $conn->real_escape_string($_POST['description']) : '';
$duration = isset($_POST['duration']) ? $conn->real_escape_string($_POST['duration']) : '';

// 5. Secure Image Upload
$imageName = "";
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {
        $upload_dir = $api_dir . '/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $imageName = time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;
        
        if (!move_uploaded_file($_FILES["image"]["tmp_name"], $upload_dir . $imageName)) {
            echo json_encode(["status" => "error", "message" => "Image upload failed (Check folder permissions)"]);
            exit();
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid Image Format. Allowed: jpg, png, webp"]);
        exit();
    }
}

// 6. Insert Data
$sql = "INSERT INTO services (category_id, name, price, discount_price, description, duration, image, status) 
        VALUES ('$category_id', '$name', '$price', '$discount_price', '$description', '$duration', '$imageName', 'active')";

if ($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Service Added Successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $conn->error]);
}
$conn->close();
?>