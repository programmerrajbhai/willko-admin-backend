<?php
// File: api/user/order/place_order.php

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
    echo json_encode(["status" => "error", "message" => "Unauthorized! Please login."]);
    exit();
}

// Token Verification
$token = $conn->real_escape_string($token);
$userCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'user'");
if ($userCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Token!"]);
    exit();
}
$user_id = $userCheck->fetch_assoc()['id'];

// 4. Input Handling
$data = json_decode(file_get_contents("php://input"), true);

// Required Fields Check
if (
    empty($data['items']) || 
    empty($data['address_id']) || 
    empty($data['schedule_date']) || 
    empty($data['schedule_time'])
) {
    echo json_encode(["status" => "error", "message" => "Missing required fields (items, address, date, time)!"]);
    exit();
}

$address_id = (int)$data['address_id'];
$schedule_date = $conn->real_escape_string($data['schedule_date']);
$schedule_time = $conn->real_escape_string($data['schedule_time']);
$coupon_code = isset($data['coupon_code']) ? $conn->real_escape_string($data['coupon_code']) : null;
$payment_method = isset($data['payment_method']) ? $conn->real_escape_string($data['payment_method']) : 'cod';

// 5. Verify Address (Must belong to user)
$addrCheck = $conn->query("SELECT id FROM user_addresses WHERE id = $address_id AND user_id = $user_id");
if ($addrCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Address ID"]);
    exit();
}

// 6. Calculate Totals (Server Side Calculation for Security)
$sub_total = 0;
$valid_items = [];

foreach ($data['items'] as $item) {
    $s_id = (int)$item['service_id'];
    $qty = (int)$item['quantity'];
    
    if ($qty < 1) continue;

    // ডাটাবেস থেকে আসল দাম আনা
    $s_query = $conn->query("SELECT name, price, discount_price FROM services WHERE id = $s_id AND status = 'active'");
    if ($s_query->num_rows > 0) {
        $service = $s_query->fetch_assoc();
        
        // যদি ডিসকাউন্ট প্রাইস থাকে, সেটা নেব, নাহলে রেগুলার প্রাইস
        $unit_price = ($service['discount_price'] > 0) ? (float)$service['discount_price'] : (float)$service['price'];
        $item_total = $unit_price * $qty;
        
        $sub_total += $item_total;
        
        $valid_items[] = [
            'service_id' => $s_id,
            'name' => $service['name'],
            'quantity' => $qty,
            'unit_price' => $unit_price,
            'total_price' => $item_total
        ];
    }
}

if (empty($valid_items)) {
    echo json_encode(["status" => "error", "message" => "No valid services found to book!"]);
    exit();
}

// 7. Apply Coupon (If any)
$discount_amount = 0;
if ($coupon_code) {
    $c_query = $conn->query("SELECT * FROM coupons WHERE code = '$coupon_code' AND status = 'active' AND valid_until >= CURDATE()");
    if ($c_query->num_rows > 0) {
        $coupon = $c_query->fetch_assoc();
        if ($coupon['discount_type'] == 'fixed') {
            $discount_amount = (float)$coupon['discount_amount'];
        } else {
            $discount_amount = ($sub_total * (float)$coupon['discount_amount']) / 100;
        }
    }
}

// ডিসকাউন্ট যেন সাব-টোটালের বেশি না হয়
if ($discount_amount > $sub_total) $discount_amount = $sub_total;

$final_total = $sub_total - $discount_amount;

// 8. Insert Order into Database
// Start Transaction (যাতে সব একসাথে সেভ হয়, মাঝপথে এরর হলে রোলব্যাক হয়)
$conn->begin_transaction();

try {
    // A. Insert into bookings
    $sql = "INSERT INTO bookings (user_id, address_id, coupon_code, sub_total, discount_amount, final_total, schedule_date, schedule_time, payment_method, status) 
            VALUES ($user_id, $address_id, '$coupon_code', '$sub_total', '$discount_amount', '$final_total', '$schedule_date', '$schedule_time', '$payment_method', 'pending')";
    
    if (!$conn->query($sql)) {
        throw new Exception("Booking Insert Failed: " . $conn->error);
    }
    
    $booking_id = $conn->insert_id;

    // B. Insert into booking_items
    $item_sql = "INSERT INTO booking_items (booking_id, service_id, service_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($item_sql);

    foreach ($valid_items as $item) {
        $stmt->bind_param("iisidd", $booking_id, $item['service_id'], $item['name'], $item['quantity'], $item['unit_price'], $item['total_price']);
        if (!$stmt->execute()) {
            throw new Exception("Item Insert Failed");
        }
    }

    // Commit Transaction
    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Booking Placed Successfully!",
        "data" => [
            "booking_id" => $booking_id,
            "final_amount" => $final_total,
            "status" => "pending"
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>