<?php
// File: api/user/address/delete_address.php

// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 2. Database Connection
$current_dir = __DIR__;
$possible_paths = [
    __DIR__ . '/../../../db.php', 
    __DIR__ . '/../../db.php',
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

// 3. Auth Helper Function (Token Check)
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

// 4. Input Handling
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['address_id'])) {
    echo json_encode(["status" => "error", "message" => "Address ID is required!"]);
    exit();
}

$address_id = (int)$data['address_id'];

// 5. Delete Action (Security Check: user_id must match)
$sql = "DELETE FROM user_addresses WHERE id = $address_id AND user_id = $user_id";

if ($conn->query($sql)) {
    if ($conn->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Address deleted successfully"]);
    } else {
        // যদি আইডি না মিলে বা অন্য কারো এড্রেস ডিলিট করতে চায়
        echo json_encode(["status" => "error", "message" => "Address not found or permission denied"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Database Error"]);
}

$conn->close();
?>