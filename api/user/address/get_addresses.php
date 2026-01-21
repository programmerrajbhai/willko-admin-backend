<?php
// File: api/user/address/get_addresses.php

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

// 5. Fetch Addresses
$sql = "SELECT id, label, address, latitude, longitude FROM user_addresses WHERE user_id = $user_id ORDER BY id DESC";
$result = $conn->query($sql);

$addresses = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // ফ্লোট ভ্যালু ঠিক করা (ম্যাপের জন্য জরুরি)
        $row['latitude'] = (float)$row['latitude'];
        $row['longitude'] = (float)$row['longitude'];
        $addresses[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $addresses]);
} else {
    // কোনো এড্রেস সেভ করা না থাকলে খালি লিস্ট রিটার্ন করবে
    echo json_encode(["status" => "success", "message" => "No saved addresses found", "data" => []]);
}

$conn->close();
?>