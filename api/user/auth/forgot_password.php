<?php
// File: api/user/auth/forgot_password.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// Database Connection
$current_dir = __DIR__;
$possible_paths = [
    __DIR__ . '/../../../db.php', 
    __DIR__ . '/../../db.php',
];

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        include $path;
        break;
    }
}

// Input Handling
$data = json_decode(file_get_contents("php://input"), true);

$login_id = "";
if (!empty($data['login_id'])) $login_id = trim($data['login_id']);
elseif (!empty($data['phone'])) $login_id = trim($data['phone']);

if (empty($login_id)) {
    echo json_encode(["status" => "error", "message" => "Phone or Email required!"]);
    exit();
}

$login_id = $conn->real_escape_string($login_id);

// Check User
$sql = "SELECT id FROM users WHERE (phone = '$login_id' OR email = '$login_id') AND role = 'user' LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $user_id = $user['id'];

    // Generate OTP
    $otp = rand(1000, 9999);
    
    // 🔥 FIX: PHP টাইম বাদ দিয়ে সরাসরি MySQL টাইম ব্যবহার করা হচ্ছে
    // এতে সার্ভার টাইমজোন নিয়ে কোনো ঝামেলা হবে না
    $updateSql = "UPDATE users SET otp_code = '$otp', otp_expiry = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = $user_id";

    if ($conn->query($updateSql)) {
        echo json_encode([
            "status" => "success", 
            "message" => "OTP sent successfully.",
            "dev_otp" => $otp // টেস্ট করার জন্য
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to save OTP."]);
    }

} else {
    echo json_encode(["status" => "error", "message" => "User not found!"]);
}

$conn->close();
?>