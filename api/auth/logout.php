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

if (!empty($token)) {
    $token = $conn->real_escape_string($token);
    // ডাটাবেস থেকে টোকেন নাল (NULL) করে দেওয়া
    $conn->query("UPDATE users SET auth_token = NULL WHERE auth_token = '$token'");
}

echo json_encode(["status" => "success", "message" => "Logged out successfully"]);

$conn->close();
?>