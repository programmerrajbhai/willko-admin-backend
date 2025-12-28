<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// DB & Auth
$current_dir = __DIR__;
$api_dir = dirname(dirname(dirname($current_dir)));
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// Auth Check
$headers = getallheaders();
$token = isset($headers['Authorization']) ? substr($headers['Authorization'], 7) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// Input Validation
$data = json_decode(file_get_contents("php://input"), true);
$provider_id = isset($data['provider_id']) ? (int)$data['provider_id'] : 0;
$amount = isset($data['amount']) ? (float)$data['amount'] : 0;
$note = isset($data['note']) ? $conn->real_escape_string($data['note']) : '';

if ($provider_id == 0 || $amount <= 0) {
    echo json_encode(["status" => "error", "message" => "Valid Provider ID and Amount required"]);
    exit();
}

// Check if provider exists
$provider = $conn->query("SELECT current_due FROM users WHERE id = $provider_id AND role = 'provider'")->fetch_assoc();
if (!$provider) {
    echo json_encode(["status" => "error", "message" => "Provider not found"]);
    exit();
}

// Transaction Process (Start)
$conn->begin_transaction();

try {
    // 1. Insert Transaction Record
    $sql_trans = "INSERT INTO transactions (provider_id, amount, note) VALUES ($provider_id, $amount, '$note')";
    if (!$conn->query($sql_trans)) throw new Exception("Transaction Failed");

    // 2. Decrease Due Amount from User Table
    $sql_update = "UPDATE users SET current_due = current_due - $amount WHERE id = $provider_id";
    if (!$conn->query($sql_update)) throw new Exception("Balance Update Failed");

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Money Collected Successfully"]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>