<?php
// File: api/user/order/place_order.php

// 1. Setup & Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Database Connection
$current_dir = __DIR__;
$possible_paths = [__DIR__ . '/../../../db.php', __DIR__ . '/../../db.php'];
$db_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) { include $path; $db_loaded = true; break; }
}
if (!$db_loaded) { echo json_encode(["status" => "error", "message" => "Database connection failed"]); exit(); }

// 3. Auth Helper (Token Validation)
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
    http_response_code(401);
    echo json_encode(["status" => "unauthorized", "message" => "Unauthorized! Please login."]);
    exit();
}

// 4. Verify User
$token = $conn->real_escape_string($token);
$userCheck = $conn->query("SELECT id, name, phone FROM users WHERE auth_token = '$token' AND status = 'active' LIMIT 1");

if ($userCheck->num_rows == 0) {
    http_response_code(401);
    echo json_encode(["status" => "unauthorized", "message" => "Session expired! Please login again."]);
    exit();
}

$user_data = $userCheck->fetch_assoc();
$user_id = $user_data['id'];

// 5. Input Processing
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['items']) || empty($data['address_id']) || empty($data['schedule_date'])) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit();
}

$address_id = (int)$data['address_id'];
$schedule_date = $conn->real_escape_string($data['schedule_date']);
$schedule_time = $conn->real_escape_string($data['schedule_time']);
$payment_method = isset($data['payment_method']) ? $conn->real_escape_string($data['payment_method']) : 'cod';
$coupon_code = isset($data['coupon_code']) ? $conn->real_escape_string($data['coupon_code']) : null;

// অ্যাপ থেকে নাম/ফোন আসলে সেটা নেব, নাহলে প্রোফাইল থেকে ডিফল্ট
$contact_name = !empty($data['contact_name']) ? $conn->real_escape_string($data['contact_name']) : $user_data['name'];
$contact_phone = !empty($data['contact_phone']) ? $conn->real_escape_string($data['contact_phone']) : $user_data['phone'];

// 6. Calculate Totals
$sub_total = 0;
$valid_items = [];

foreach ($data['items'] as $item) {
    $s_id = (int)$item['service_id'];
    $qty = (int)$item['quantity'];
    if ($qty < 1) continue;

    $s_query = $conn->query("SELECT name, price, discount_price FROM services WHERE id = $s_id AND status = 'active'");
    if ($s_query->num_rows > 0) {
        $service = $s_query->fetch_assoc();
        $unit_price = ($service['discount_price'] > 0) ? $service['discount_price'] : $service['price'];
        $total = $unit_price * $qty;
        $sub_total += $total;
        
        $valid_items[] = [
            'service_id' => $s_id, 'name' => $service['name'], 
            'quantity' => $qty, 'unit_price' => $unit_price, 'total_price' => $total
        ];
    }
}

if (empty($valid_items)) {
    echo json_encode(["status" => "error", "message" => "Invalid services selected"]);
    exit();
}

// Discount Logic (Simple Example)
$discount_amount = 0; 
$final_total = $sub_total - $discount_amount;

// 7. Save to Database
$conn->begin_transaction();
try {
    // Insert Booking
    $sql = "INSERT INTO bookings (user_id, address_id, contact_name, contact_phone, sub_total, discount_amount, final_total, schedule_date, schedule_time, payment_method, status, coupon_code) 
            VALUES ($user_id, $address_id, '$contact_name', '$contact_phone', '$sub_total', '$discount_amount', '$final_total', '$schedule_date', '$schedule_time', '$payment_method', 'pending', '$coupon_code')";
    
    if (!$conn->query($sql)) throw new Exception($conn->error);
    $booking_id = $conn->insert_id;

    // Insert Items
    $stmt = $conn->prepare("INSERT INTO booking_items (booking_id, service_id, service_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($valid_items as $item) {
        $stmt->bind_param("iisidd", $booking_id, $item['service_id'], $item['name'], $item['quantity'], $item['unit_price'], $item['total_price']);
        $stmt->execute();
    }

    $conn->commit();
    echo json_encode([
        "status" => "success", 
        "message" => "Booking Successful!", 
        "booking_id" => $booking_id,
        "user_info" => ["name" => $contact_name, "phone" => $contact_phone]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
$conn->close();
?>