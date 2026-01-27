<?php
// File: api/admin/orders/update_payment.php
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
    http_response_code(500);
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

// Verify Admin
$authStmt = $conn->prepare("SELECT id FROM users WHERE auth_token = ? AND role = 'admin' LIMIT 1");
$authStmt->bind_param("s", $token);
$authStmt->execute();
if ($authStmt->get_result()->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Permission Denied"]);
    exit();
}

// ======================================================
// 3. UNIVERSAL INPUT HANDLING (JSON + FORM DATA)
// ======================================================
$data = [];

// Try to get JSON data
$rawInput = file_get_contents("php://input");
$jsonData = json_decode($rawInput, true);

if (!empty($jsonData)) {
    $data = $jsonData;
} else {
    // Fallback to Form Data ($_POST)
    $data = $_POST;
}

// Debugging (Remove in production if needed)
if (empty($data)) {
    echo json_encode(["status" => "error", "message" => "No data received. Ensure you are sending JSON or Form Data."]);
    exit();
}

// 4. Process Data
$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;
$payment_status = isset($data['payment_status']) ? strtolower(trim($data['payment_status'])) : ''; // paid / unpaid

// Validation
if ($order_id == 0) {
    echo json_encode(["status" => "error", "message" => "Missing order_id"]);
    exit();
}

if (!in_array($payment_status, ['paid', 'unpaid'])) {
    echo json_encode(["status" => "error", "message" => "Invalid payment_status. Allowed: 'paid', 'unpaid'"]);
    exit();
}

// 5. Update Database
$stmt = $conn->prepare("UPDATE bookings SET payment_status = ? WHERE id = ?");
$stmt->bind_param("si", $payment_status, $order_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Payment status updated to " . ucfirst($payment_status)]);
} else {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $conn->error]);
}

$conn->close();
?>