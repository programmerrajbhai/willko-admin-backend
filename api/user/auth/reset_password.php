<?php
// File: api/user/auth/reset_password.php

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

$otp = isset($data['otp']) ? trim($data['otp']) : '';
$new_password = isset($data['new_password']) ? trim($data['new_password']) : '';

if (empty($login_id) || empty($otp) || empty($new_password)) {
    echo json_encode(["status" => "error", "message" => "All fields are required!"]);
    exit();
}

$login_id = $conn->real_escape_string($login_id);
$otp = $conn->real_escape_string($otp);

// 1. Verify OTP & Check Expiry (MySQL Time)
$sql = "SELECT id FROM users 
        WHERE (phone = '$login_id' OR email = '$login_id') 
        AND otp_code = '$otp' 
        AND otp_expiry > NOW() 
        AND role = 'user' 
        LIMIT 1";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $user_id = $user['id'];

    // 2. Hash Password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // 3. Update Password & Clear OTP
    $updateSql = "UPDATE users SET password = '$hashed_password', otp_code = NULL, otp_expiry = NULL WHERE id = $user_id";

    if ($conn->query($updateSql)) {
        echo json_encode(["status" => "success", "message" => "Password Reset Successful! Login now."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database Update Failed!"]);
    }

} else {
    // ডিবাগিং এর জন্য বিস্তারিত মেসেজ (প্রয়োজনে পরে মুছে দিও)
    // চেক করা হচ্ছে আসলে ইউজার আছে কিনা, নাকি OTP ভুল
    $checkUser = $conn->query("SELECT otp_expiry FROM users WHERE phone = '$login_id' OR email = '$login_id'");
    if($checkUser->num_rows > 0) {
        $userData = $checkUser->fetch_assoc();
        // যদি ডাটা থাকে কিন্তু OTP না মিলে
        echo json_encode([
            "status" => "error", 
            "message" => "Invalid or Expired OTP!",
            "debug_info" => "System Time: " . date("Y-m-d H:i:s") // সার্ভার টাইম দেখার জন্য
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "User not found!"]);
    }
}

$conn->close();
?>