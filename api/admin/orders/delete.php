<?php
// File: api/admin/orders/delete.php

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

// 3. Auth Helper
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

// 4. Admin Auth Check
$token = getBearerToken();
if (!$token) {
    echo json_encode(["status" => "error", "message" => "Unauthorized Access"]);
    exit();
}

// Prepared Statement for Admin Verification
$authStmt = $conn->prepare("SELECT id FROM users WHERE auth_token = ? AND role = 'admin' LIMIT 1");
$authStmt->bind_param("s", $token);
$authStmt->execute();
if ($authStmt->get_result()->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Permission Denied"]);
    exit();
}

// 5. Delete Logic
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['id'])) {
    
    $order_id = (int)$data['id'];

    if ($order_id == 0) {
        echo json_encode(["status" => "error", "message" => "Invalid Order ID"]);
        exit();
    }

    // Secure Delete Query (Using 'bookings' table)
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $order_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            
            // Optional: রিলেটেড ডাটাও ডিলিট করা যেতে পারে (যদি ডাটাবেসে CASCADE অন না থাকে)
            $conn->query("DELETE FROM booking_items WHERE booking_id = $order_id");

            echo json_encode(["status" => "success", "message" => "Order Deleted Successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Order Not Found or Already Deleted"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Database Error: " . $conn->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Order ID required"]);
}

$conn->close();
?>