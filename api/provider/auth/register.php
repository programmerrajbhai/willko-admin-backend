<?php
// 1. Error Reporting অন করা
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 2. Database কানেকশন ফাইল খোঁজা
$current_dir = __DIR__; 
$possible_paths = [
    __DIR__ . '/../../db.php',       
    __DIR__ . '/../../../db.php',    
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
    die(json_encode(["status" => "error", "message" => "Database file not found!"]));
}

// 3. ইনপুট নেওয়া
$data = json_decode(file_get_contents("php://input"), true);

// ইনপুট ভ্যালিডেশন (Email সহ)
if (empty($data['name']) || empty($data['phone']) || empty($data['email']) || empty($data['password']) || empty($data['category'])) {
    echo json_encode(["status" => "error", "message" => "Name, Phone, Email, Password & Category required!"]);
    exit();
}

$name = $conn->real_escape_string(trim($data['name']));
$phone = $conn->real_escape_string(trim($data['phone']));
$email = $conn->real_escape_string(trim($data['email'])); // ইমেইল ইনপুট
$password = password_hash(trim($data['password']), PASSWORD_DEFAULT);
$category = $conn->real_escape_string(trim($data['category']));
$nid = isset($data['nid']) ? $conn->real_escape_string(trim($data['nid'])) : '';

// 4. ডুপ্লিকেট চেক (ফোন বা ইমেইল আগে আছে কিনা)
$checkQuery = "SELECT id FROM users WHERE phone = '$phone' OR email = '$email'";
$checkResult = $conn->query($checkQuery);

if ($checkResult && $checkResult->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Phone or Email already registered!"]);
    exit();
}

// 5. ডাটা ইনসার্ট (Email সহ)
$sql = "INSERT INTO users (name, email, phone, password, role, category, nid_number, status, is_online) 
        VALUES ('$name', '$email', '$phone', '$password', 'provider', '$category', '$nid', 'pending', 0)";

if ($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Registration successful! Please login."]);
} else {
    echo json_encode(["status" => "error", "message" => "Registration Error: " . $conn->error]);
}
$conn->close();
?>