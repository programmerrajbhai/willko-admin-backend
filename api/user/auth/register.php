<?php
// File: api/user/auth/register.php

// 1. Error Reporting (ডেভেলপমেন্টের জন্য)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// 3. Database Connection (Smart Path Logic)
$current_dir = __DIR__;
$possible_paths = [
    __DIR__ . '/../../../db.php', // api/user/auth/ -> root/db.php
    __DIR__ . '/../../db.php',    // Fallback
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
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed! db.php not found."]);
    exit();
}

// 4. Input Handling
$data = json_decode(file_get_contents("php://input"), true);

// Validation
if (empty($data['name']) || empty($data['phone']) || empty($data['password'])) {
    echo json_encode(["status" => "error", "message" => "Name, Phone, and Password are required!"]);
    exit();
}

$name = $conn->real_escape_string(trim($data['name']));
$phone = $conn->real_escape_string(trim($data['phone']));
$password = trim($data['password']);
$email = isset($data['email']) ? $conn->real_escape_string(trim($data['email'])) : '';

// 5. Duplicate Check (Phone or Email)
$checkQuery = "SELECT id FROM users WHERE phone = '$phone'";
if (!empty($email)) {
    $checkQuery .= " OR email = '$email'";
}

$checkResult = $conn->query($checkQuery);
if ($checkResult && $checkResult->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "User with this Phone or Email already exists!"]);
    exit();
}

// 6. Insert User
// Password Hashing
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// role = 'user', status = 'active'
$sql = "INSERT INTO users (name, email, phone, password, role, status, created_at) 
        VALUES ('$name', '$email', '$phone', '$hashed_password', 'user', 'active', NOW())";

if ($conn->query($sql)) {
    echo json_encode([
        "status" => "success", 
        "message" => "Registration Successful! Please Login.",
        "user_id" => $conn->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Registration Failed: " . $conn->error]);
}

$conn->close();
?>