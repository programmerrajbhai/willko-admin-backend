<?php
// File: api/user/address/add.php

// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 2. Database Connection (Smart Path Logic)
$current_dir = __DIR__;
$possible_paths = [
    __DIR__ . '/../../../db.php', // Root folder
    __DIR__ . '/../../db.php',    // API folder
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
    echo json_encode(["status" => "error", "message" => "Database connection failed! db.php not found."]);
    exit();
}

// 3. Auth Helper Function
function getBearerToken() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) $headers = trim($_SERVER["Authorization"]);
    elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    elseif (function_exists('apache_request_headers')) {
        $req = apache_request_headers();
        if (isset($req['Authorization'])) $headers = trim($req['Authorization']);
    }
    
    if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) return $matches[1];
    return null;
}

// 4. Token Check
$token = getBearerToken();
if (!$token) {
    echo json_encode(["status" => "error", "message" => "Unauthorized! Token missing."]);
    exit();
}

$token = $conn->real_escape_string($token);
$userCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'user'");

if ($userCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Token! Please login again."]);
    exit();
}

$user_id = $userCheck->fetch_assoc()['id'];

// 5. Input Handling
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['label']) || empty($data['address'])) {
    echo json_encode(["status" => "error", "message" => "Label (Home/Office) and Address are required!"]);
    exit();
}

$label = $conn->real_escape_string(trim($data['label']));
$address = $conn->real_escape_string(trim($data['address']));
$lat = isset($data['latitude']) ? (float)$data['latitude'] : 0.0;
$lng = isset($data['longitude']) ? (float)$data['longitude'] : 0.0;

// 6. Insert Address
$sql = "INSERT INTO user_addresses (user_id, label, address, latitude, longitude) 
        VALUES ('$user_id', '$label', '$address', '$lat', '$lng')";

if ($conn->query($sql)) {
    echo json_encode([
        "status" => "success", 
        "message" => "Address saved successfully",
        "address_id" => $conn->insert_id
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $conn->error]);
}

$conn->close();
?>