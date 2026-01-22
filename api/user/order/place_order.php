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

// Validation
if (empty($data['items']) || empty($data['address_id']) || empty($data['schedule_date'])) {
    echo json_encode(["status" => "error", "message" => "Missing required fields (items, address, date)"]);
    exit();
}

// --- Data Extraction & Escaping ---
$address_id = (int)$data['address_id'];
$full_address = isset($data['full_address']) ? $conn->real_escape_string($data['full_address']) : ''; // Backup Address

$schedule_date = $conn->real_escape_string($data['schedule_date']);
$schedule_time = $conn->real_escape_string($data['schedule_time']);

$payment_method = isset($data['payment_method']) ? $conn->real_escape_string($data['payment_method']) : 'cod';
$payment_status = isset($data['payment_status']) ? $conn->real_escape_string($data['payment_status']) : 'unpaid';
$transaction_id = isset($data['transaction_id']) ? $conn->real_escape_string($data['transaction_id']) : '';

$coupon_code = isset($data['coupon_code']) ? $conn->real_escape_string($data['coupon_code']) : null;
$order_note = isset($data['order_note']) ? $conn->real_escape_string($data['order_note']) : '';
$platform = isset($data['platform']) ? $conn->real_escape_string($data['platform']) : 'android';

// Contact Info (Use input if provided, else fallback to profile)
$contact_name = !empty($data['contact_name']) ? $conn->real_escape_string($data['contact_name']) : $user_data['name'];
$contact_phone = !empty($data['contact_phone']) ? $conn->real_escape_string($data['contact_phone']) : $user_data['phone'];

// 6. Calculate Totals (Server Side Validation)
$sub_total = 0;
$valid_items = [];

foreach ($data['items'] as $item) {
    $s_id = (int)$item['service_id'];
    $qty = (int)$item['quantity'];
    if ($qty < 1) continue;

    // Fetch Price form Database (Never trust client side price)
    $s_query = $conn->query("SELECT name, price, discount_price FROM services WHERE id = $s_id AND status = 'active'");
    if ($s_query->num_rows > 0) {
        $service = $s_query->fetch_assoc();
        
        // Price Logic
        $unit_price = ($service['discount_price'] > 0) ? $service['discount_price'] : $service['price'];
        
        $total = $unit_price * $qty;
        $sub_total += $total;
        
        $valid_items[] = [
            'service_id' => $s_id, 
            'name' => $service['name'], 
            'quantity' => $qty, 
            'unit_price' => $unit_price, 
            'total_price' => $total
        ];
    }
}

if (empty($valid_items)) {
    echo json_encode(["status" => "error", "message" => "Invalid or unavailable services selected"]);
    exit();
}

// Discount Calculation (Server Side)
$discount_amount = 0;
if ($coupon_code) {
    // এখানে ভবিষ্যতে কুপন চেক করার লজিক বসাতে পারেন
    // $discount_amount = checkCoupon($coupon_code, $sub_total);
}

$final_total = $sub_total - $discount_amount;

// 7. Save to Database (Transaction)
$conn->begin_transaction();

try {
    // A. Insert into `bookings` table
    // আপনার ডাটাবেসে এই কলামগুলো থাকতে হবে: 
    // full_address, payment_status, transaction_id, order_note, platform, created_at
    
    $sql = "INSERT INTO bookings (
                user_id, address_id, full_address, 
                contact_name, contact_phone, 
                sub_total, discount_amount, final_total, 
                schedule_date, schedule_time, 
                payment_method, payment_status, transaction_id, 
                status, coupon_code, order_note, platform, created_at
            ) VALUES (
                $user_id, $address_id, '$full_address',
                '$contact_name', '$contact_phone',
                '$sub_total', '$discount_amount', '$final_total',
                '$schedule_date', '$schedule_time',
                '$payment_method', '$payment_status', '$transaction_id',
                'pending', '$coupon_code', '$order_note', '$platform', NOW()
            )";
    
    if (!$conn->query($sql)) {
        throw new Exception("Order insertion failed: " . $conn->error);
    }
    
    $booking_id = $conn->insert_id;

    // B. Insert into `booking_items` table
    $stmt = $conn->prepare("INSERT INTO booking_items (booking_id, service_id, service_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($valid_items as $item) {
        $stmt->bind_param("iisidd", $booking_id, $item['service_id'], $item['name'], $item['quantity'], $item['unit_price'], $item['total_price']);
        
        if (!$stmt->execute()) {
            throw new Exception("Item insertion failed: " . $stmt->error);
        }
    }

    // C. Commit Transaction
    $conn->commit();

    echo json_encode([
        "status" => "success", 
        "message" => "Booking Successful!", 
        "booking_id" => $booking_id,
        "total_amount" => $final_total
    ]);

} catch (Exception $e) {
    // D. Rollback on Error
    $conn->rollback();
    echo json_encode([
        "status" => "error", 
        "message" => "System Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>