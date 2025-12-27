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

// 3. Data Retrieval (Hybrid: JSON or POST)
$jsonData = json_decode(file_get_contents("php://input"), true);

// Helper function to get data from either POST or JSON
function getValue($key, $postArr, $jsonArr) {
    if (isset($postArr[$key])) return $postArr[$key];
    if (isset($jsonArr[$key])) return $jsonArr[$key];
    return null;
}

$id = (int)getValue('id', $_POST, $jsonData);

if ($id == 0) {
    echo json_encode(["status" => "error", "message" => "Service ID is required"]);
    exit();
}

// Fetch existing data
$existing = $conn->query("SELECT * FROM services WHERE id = $id")->fetch_assoc();
if (!$existing) {
    echo json_encode(["status" => "error", "message" => "Service not found"]);
    exit();
}

// 4. Update Variables (Check New Input -> Fallback to Existing)
$category_id = getValue('category_id', $_POST, $jsonData) ?? $existing['category_id'];
$raw_name = getValue('name', $_POST, $jsonData);
$name = $raw_name ? $conn->real_escape_string($raw_name) : $existing['name'];

$price = getValue('price', $_POST, $jsonData) ?? $existing['price'];
$discount = getValue('discount_price', $_POST, $jsonData) ?? $existing['discount_price'];

$raw_desc = getValue('description', $_POST, $jsonData);
$desc = $raw_desc ? $conn->real_escape_string($raw_desc) : $existing['description'];

$raw_duration = getValue('duration', $_POST, $jsonData);
$duration = $raw_duration ? $conn->real_escape_string($raw_duration) : $existing['duration'];

// 5. Image Update Logic (Only works with Form-Data)
$imageSql = "";
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {
        $upload_dir = $api_dir . '/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        // Delete old image
        if (!empty($existing['image']) && file_exists($upload_dir . $existing['image'])) {
            unlink($upload_dir . $existing['image']);
        }

        $newImage = time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $upload_dir . $newImage)) {
            $imageSql = ", image = '$newImage'";
        }
    }
}

// 6. Update Query
$sql = "UPDATE services SET 
        category_id='$category_id', 
        name='$name', 
        price='$price', 
        discount_price='$discount', 
        description='$desc', 
        duration='$duration' 
        $imageSql 
        WHERE id=$id";

if ($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Service Updated Successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Database Error"]);
}
$conn->close();
?>