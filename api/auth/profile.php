<?php
include '../../db.php';

// ১. হেডার থেকে টোকেন নেওয়া
$headers = getallheaders();
$token = isset($headers['Authorization']) ? $headers['Authorization'] : '';

// টোকেন "Bearer " দিয়ে শুরু হলে সেটা ক্লিন করা
if (strpos($token, 'Bearer ') === 0) {
    $token = substr($token, 7);
}

if (empty($token)) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access! Token missing."]);
    exit();
}

// ২. টোকেন দিয়ে ইউজার খোঁজা
$token = $conn->real_escape_string($token);
$sql = "SELECT id, name, email, phone, role, status FROM users WHERE auth_token = '$token'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo json_encode(["status" => "success", "data" => $user]);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid Token! Please login again."]);
}

$conn->close();
?>