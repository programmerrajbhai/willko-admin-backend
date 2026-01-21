<?php
// File: api/user/order/extra_charge_action.php

// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

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
$userCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'user'");

if ($userCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Token!"]);
    exit();
}
$user_id = $userCheck->fetch_assoc()['id'];

// 4. Input Handling
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['order_id']) || empty($data['action'])) {
    echo json_encode(["status" => "error", "message" => "Order ID and Action (accept/reject) required"]);
    exit();
}

$order_id = (int)$data['order_id'];
$action = strtolower(trim($data['action'])); // accept or reject

// 5. Check Order & Extra Charge Status
$sql = "SELECT id, final_total, extra_charge, extra_charge_status 
        FROM bookings 
        WHERE id = $order_id AND user_id = $user_id";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Order not found or access denied"]);
    exit();
}

$order = $result->fetch_assoc();

// চেক করা কোনো পেন্ডিং চার্জ আছে কিনা
if ($order['extra_charge_status'] !== 'pending') {
    echo json_encode(["status" => "error", "message" => "No pending extra charge found for this order."]);
    exit();
}

$extra_amount = (float)$order['extra_charge'];

// 6. Perform Action
if ($action == 'accept') {
    // অ্যাক্সেপ্ট করলে মেইন টোটালের সাথে যোগ হবে
    $new_total = (float)$order['final_total'] + $extra_amount;
    
    $updateSql = "UPDATE bookings 
                  SET extra_charge_status = 'accepted', 
                      final_total = '$new_total' 
                  WHERE id = $order_id";
    
    $msg = "Extra charge accepted. New Total: " . $new_total;

} elseif ($action == 'reject') {
    // রিজেক্ট করলে শুধু স্ট্যাটাস বদলাবে, টাকা যোগ হবে না
    $updateSql = "UPDATE bookings 
                  SET extra_charge_status = 'rejected' 
                  WHERE id = $order_id";
    
    $msg = "Extra charge rejected.";

} else {
    echo json_encode(["status" => "error", "message" => "Invalid action! Use 'accept' or 'reject'."]);
    exit();
}

if ($conn->query($updateSql)) {
    echo json_encode(["status" => "success", "message" => $msg]);
} else {
    echo json_encode(["status" => "error", "message" => "Database Error"]);
}

$conn->close();
?>