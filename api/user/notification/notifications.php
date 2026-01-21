<?php
// File: api/user/notification/notifications.php

// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

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
    echo json_encode(["status" => "error", "message" => "Database connection failed!"]);
    exit();
}

// 3. Auth Helper (Check User)
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
    echo json_encode(["status" => "error", "message" => "Unauthorized!"]);
    exit();
}

$token = $conn->real_escape_string($token);
$userCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'user'");

if ($userCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Token!"]);
    exit();
}
$user_id = $userCheck->fetch_assoc()['id'];

// 4. Fetch Notifications
$sql = "SELECT id, title, message, type, is_read, created_at 
        FROM notifications 
        WHERE user_id = $user_id 
        ORDER BY id DESC";

$result = $conn->query($sql);

$notifications = [];
$unread_count = 0;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Unread কাউন্ট করা (Frontend-এ ব্যাজ দেখানোর জন্য কাজে লাগতে পারে)
        if ($row['is_read'] == 0) {
            $unread_count++;
        }
        $notifications[] = $row;
    }
    
    echo json_encode([
        "status" => "success", 
        "unread_count" => $unread_count,
        "data" => $notifications
    ]);
} else {
    echo json_encode([
        "status" => "success", 
        "message" => "No notifications found", 
        "unread_count" => 0,
        "data" => []
    ]);
}

$conn->close();
?>