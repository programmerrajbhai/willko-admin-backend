<?php
// File: api/user/profile/get_profile.php

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

// 4. Fetch User Data
$sql = "SELECT id, name, email, phone, image, created_at FROM users WHERE auth_token = '$token' AND role = 'user'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // Image URL Formatting
    // যদি ইমেজ থাকে, তাহলে ফুল URL বানিয়ে দিব
    if (!empty($user['image'])) {
        $user['image_url'] = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/profile/" . $user['image'];
    } else {
        $user['image_url'] = null; // অথবা একটি ডিফল্ট ইমেজের লিংক দিতে পারো
    }

    echo json_encode(["status" => "success", "data" => $user]);

} else {
    echo json_encode(["status" => "error", "message" => "User not found or Invalid Token"]);
}

$conn->close();
?>