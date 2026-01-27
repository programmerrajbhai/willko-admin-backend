<?php
// File: api/admin/orders/assign.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// ✅ ROBUST DATABASE CONNECTION
$possible_paths = [__DIR__ . '/../../../db.php', __DIR__ . '/../../db.php'];
$db_loaded = false;
foreach ($possible_paths as $path) { if (file_exists($path)) { include_once $path; $db_loaded = true; break; } }
if (!$db_loaded) { echo json_encode(["status" => "error", "message" => "Database connection failed"]); exit(); }

// Auth Check
$headers = null;
if (isset($_SERVER['Authorization'])) $headers = trim($_SERVER["Authorization"]);
elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
elseif (function_exists('apache_request_headers')) { $req = apache_request_headers(); if (isset($req['Authorization'])) $headers = trim($req['Authorization']); }
$token = (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) ? $matches[1] : null;

if (!$token || $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]); exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['order_id']) || empty($data['provider_id'])) {
    echo json_encode(["status" => "error", "message" => "Missing IDs"]);
    exit();
}

$order_id = (int)$data['order_id'];
$provider_id = (int)$data['provider_id'];

// Check Status
$orderCheck = $conn->query("SELECT status FROM bookings WHERE id = $order_id");
if ($orderCheck->num_rows == 0) { echo json_encode(["status" => "error", "message" => "Order not found"]); exit(); }
$status = strtolower($orderCheck->fetch_assoc()['status']);

if (in_array($status, ['completed', 'cancelled'])) {
    echo json_encode(["status" => "error", "message" => "Cannot assign to a $status order"]);
    exit();
}

// Transaction
$conn->begin_transaction();
try {
    $otp = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare("UPDATE bookings SET provider_id = ?, status = 'assigned', otp = ? WHERE id = ?");
    $stmt->bind_param("isi", $provider_id, $otp, $order_id);
    
    if (!$stmt->execute()) throw new Exception("Failed to assign");

    // Notification
    $msg = "You have been assigned a new job (Order #$order_id)";
    $notif = $conn->prepare("INSERT INTO notifications (provider_id, title, message, type) VALUES (?, 'New Job', ?, 'job_assigned')");
    $notif->bind_param("is", $provider_id, $msg);
    $notif->execute();

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Provider Assigned Successfully"]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
$conn->close();
?>