<?php
include '../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Only POST method allowed"]);
    exit();
}

// ১. হেডার থেকে টোকেন নেওয়া
$headers = getallheaders();
$token = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (strpos($token, 'Bearer ') === 0) {
    $token = substr($token, 7);
}

if (empty($token)) {
    echo json_encode(["status" => "error", "message" => "Unauthorized! Token missing."]);
    exit();
}

// ২. ইনপুট নেওয়া
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['new_password'])) {
    echo json_encode(["status" => "error", "message" => "New Password is required"]);
    exit();
}

$new_password = $conn->real_escape_string($data['new_password']);
$token = $conn->real_escape_string($token);

// ৩. টোকেন চেক এবং পাসওয়ার্ড আপডেট
$sql = "UPDATE users SET password = '$new_password' WHERE auth_token = '$token'";

if ($conn->query($sql) === TRUE) {
    // চেক করা আসলেই কোনো রো আপডেট হলো কিনা (টোকেন ভুল হলে আপডেট হবে না)
    if ($conn->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Password updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid Token or Same Password provided"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Database Error"]);
}

$conn->close();
?>