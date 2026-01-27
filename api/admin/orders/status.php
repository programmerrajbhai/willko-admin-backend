<?php
// File: api/admin/orders/status.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. ROBUST DATABASE CONNECTION
$current_dir = __DIR__;
$possible_paths = [
    __DIR__ . '/../../../db.php', 
    __DIR__ . '/../../db.php',
];

$db_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        include_once $path;
        $db_loaded = true;
        break;
    }
}

if (!$db_loaded) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// 2. Auth Helper
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
    echo json_encode(["status" => "error", "message" => "Unauthorized Access"]);
    exit();
}

$authStmt = $conn->prepare("SELECT id FROM users WHERE auth_token = ? AND role = 'admin' LIMIT 1");
$authStmt->bind_param("s", $token);
$authStmt->execute();
if ($authStmt->get_result()->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Permission Denied"]);
    exit();
}

// 3. Update Status Logic
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['order_id']) && isset($data['status'])) {
    
    $order_id = (int)$data['order_id'];
    $status = strtolower(trim($data['status']));

    // Valid Status List
    $allowed_status = [
        'pending', 'confirmed', 'assigned', 'on_way', 
        'started', 'completed', 'cancelled', 'rejected'
    ];

    if ($order_id == 0 || !in_array($status, $allowed_status)) {
        echo json_encode(["status" => "error", "message" => "Invalid ID or Status value"]);
        exit();
    }

    // Secure Update Query (Using 'bookings' table)
    $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $order_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Order Status Updated to " . ucfirst($status)]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database Update Failed: " . $conn->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Incomplete Data. Required: order_id, status"]);
}

$conn->close();
?>