<?php
// File: api/admin/marketing/coupons_list.php

// 1. Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 2. Database Connection (Smart Path)
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

// 3. Auth Check (Admin Only)
// এই অংশটি যোগ করা খুবই জরুরি
$headers = getallheaders();
$token = isset($headers['Authorization']) ? $headers['Authorization'] : '';
if (strpos($token, 'Bearer ') === 0) $token = substr($token, 7);

$adminCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'");
if ($adminCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access! Admin only."]);
    exit();
}

// 4. Fetch Coupons
$sql = "SELECT * FROM coupons ORDER BY id DESC";
$result = $conn->query($sql);

$coupons = [];
while ($row = $result->fetch_assoc()) {
    $coupons[] = $row;
}

echo json_encode(["status" => "success", "data" => $coupons]);

$conn->close();
?>