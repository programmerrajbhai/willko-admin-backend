<?php
// File: api/provider/home/toggle_status.php

// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Database Connection
$db_loaded = false;
$possible_paths = [
    __DIR__ . '/../../../db.php', 
    __DIR__ . '/../../db.php', 
    $_SERVER['DOCUMENT_ROOT'] . '/willko-admin-backend/db.php'
];

foreach ($possible_paths as $path) { 
    if (file_exists($path)) { 
        include $path; 
        $db_loaded = true; 
        break; 
    } 
}

if (!$db_loaded) { 
    http_response_code(500); 
    echo json_encode(["status" => "error", "message" => "Database connection failed"]); 
    exit(); 
}

// 3. Auth Helper
function getBearerToken() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) $headers = trim($_SERVER["Authorization"]);
    elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    elseif (function_exists('apache_request_headers')) {
        $req = apache_request_headers();
        $req = array_combine(array_map('ucwords', array_keys($req)), array_values($req));
        if (isset($req['Authorization'])) $headers = trim($req['Authorization']);
    }
    if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) return $matches[1];
    return null;
}

$token = getBearerToken();
if (!$token) { 
    http_response_code(401); 
    echo json_encode(["status" => "error", "message" => "Unauthorized"]); 
    exit(); 
}

// 4. Input Validation
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['status'])) {
    echo json_encode(["status" => "error", "message" => "Status is required"]);
    exit();
}

// Ensure status is 0 or 1
$status = ($data['status'] == true || $data['status'] == 1 || $data['status'] == "1") ? 1 : 0;

// 5. Update Query
// আমরা সরাসরি users টেবিল আপডেট করছি টোকেন দিয়ে
$sql = "UPDATE users SET is_online = ? WHERE auth_token = ? AND role = 'provider'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $status, $token);

if ($stmt->execute()) {
    // affected_rows চেক করছি না কারণ সেইম স্ট্যাটাস পাঠালে 0 রিটার্ন করে, যা এরর না
    echo json_encode([
        "status" => "success", 
        "message" => "Status updated successfully",
        "new_status" => $status
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Update Failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>