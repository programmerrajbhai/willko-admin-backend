<?php
// File: api/user/order/order_details.php

// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

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

// 3. Auth Check
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
$userCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND status = 'active'");

if ($userCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Token or Account Suspended!"]);
    exit();
}
$user_id = $userCheck->fetch_assoc()['id'];

// 4. Input Validation
if (!isset($_GET['order_id'])) {
    echo json_encode(["status" => "error", "message" => "Order ID required"]);
    exit();
}

$order_id = (int)$_GET['order_id'];

// 5. Fetch Order Details
// ✅ UPDATE: আমরা bookings টেবিলের 'full_address' কে অগ্রাধিকার দিচ্ছি
$sql = "SELECT b.*, 
               ua.label as address_label, 
               ua.latitude, ua.longitude,
               p.name as provider_name, p.phone as provider_phone, p.image as provider_image, p.rating as provider_rating
        FROM bookings b
        LEFT JOIN user_addresses ua ON b.address_id = ua.id
        LEFT JOIN providers p ON b.provider_id = p.id
        WHERE b.id = $order_id AND b.user_id = $user_id";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $order = $result->fetch_assoc();

    // 6. Fetch Order Items
    $items_sql = "SELECT service_name, quantity, unit_price, total_price 
                  FROM booking_items 
                  WHERE booking_id = $order_id";
    $items_res = $conn->query($items_sql);
    
    $items = [];
    while ($item = $items_res->fetch_assoc()) {
        $items[] = $item;
    }

    // 7. Provider Image URL Handling
    $provider_info = null;
    if (!empty($order['provider_name'])) {
        // ইমেজ থাকলে URL বানাবে, না থাকলে null
        $p_image = !empty($order['provider_image']) 
            ? "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/providers/" . $order['provider_image'] 
            : null;

        $provider_info = [
            "name" => $order['provider_name'],
            "phone" => $order['provider_phone'],
            "image" => $p_image,
            "rating" => $order['provider_rating']
        ];
    }

    // 8. Address Handling (Backup Logic)
    // যদি bookings টেবিলে full_address থাকে সেটা নিবে, না হলে user_addresses টেবিল থেকে নিবে
    $final_address = !empty($order['full_address']) ? $order['full_address'] : "Address details not available";

    echo json_encode([
        "status" => "success",
        "data" => [
            "order_info" => [
                "id" => $order['id'],
                "status" => ucfirst($order['status']), // Capitalize first letter
                "schedule_date" => date("d M, Y", strtotime($order['schedule_date'])), // Format Date
                "schedule_time" => $order['schedule_time'],
                "payment_method" => strtoupper($order['payment_method']),
                "created_at" => $order['created_at']
            ],
            "price_details" => [
                "sub_total" => (float)$order['sub_total'],
                "discount" => (float)$order['discount_amount'],
                "final_total" => (float)$order['final_total']
            ],
            "address" => [
                "label" => $order['address_label'] ?? "Home",
                "details" => $final_address, // ✅ Fixed Address Logic
                "lat" => (float)$order['latitude'],
                "lng" => (float)$order['longitude']
            ],
            "items" => $items,
            "provider" => $provider_info
        ]
    ]);

} else {
    echo json_encode(["status" => "error", "message" => "Order not found"]);
}

$conn->close();
?>