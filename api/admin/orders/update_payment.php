<?php
// File: api/admin/orders/update_payment.php

// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// ==============================================
// ✅ 2. ROBUST DATABASE CONNECTION
// ==============================================
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
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// 3. Auth Check (Admin Only)
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

// Verify Admin
$authStmt = $conn->prepare("SELECT id FROM users WHERE auth_token = ? AND role = 'admin' LIMIT 1");
$authStmt->bind_param("s", $token);
$authStmt->execute();
if ($authStmt->get_result()->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Permission Denied"]);
    exit();
}

// 4. Update Logic
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['order_id']) && isset($data['payment_status'])) {
    
    $id = (int)$data['order_id'];
    $status = strtolower(trim($data['payment_status'])); // paid / unpaid

    // Validate Status
    if (!in_array($status, ['paid', 'unpaid'])) {
        echo json_encode(["status" => "error", "message" => "Invalid payment status (Use 'paid' or 'unpaid')"]);
        exit();
    }

    // Secure Update Query (Using 'bookings' table)
    $stmt = $conn->prepare("UPDATE bookings SET payment_status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Payment status updated to " . ucfirst($status)]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Incomplete data. Required: order_id, payment_status"]);
}

$conn->close();
?>