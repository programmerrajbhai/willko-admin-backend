<?php
// File: api/admin/orders/view.php

// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

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

// 4. Admin Verification
$token = getBearerToken();
if (!$token) {
    echo json_encode(["status" => "error", "message" => "Unauthorized Access"]);
    exit();
}

$token = $conn->real_escape_string($token);
// চেক করছি ইউজার অ্যাডমিন কিনা (role = 'admin')
$adminCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin' LIMIT 1");

if ($adminCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Permission Denied: Admin Access Required"]);
    exit();
}

// 5. Input Validation
if (!isset($_GET['id'])) {
    echo json_encode(["status" => "error", "message" => "Order ID required"]);
    exit();
}

$order_id = (int)$_GET['id'];

// ==============================================
// 6. Fetch Full Order Details
// ==============================================
$sql = "SELECT b.*, 
               u.name as customer_name, u.phone as customer_phone, u.email as customer_email, u.image as customer_image,
               p.name as provider_name, p.phone as provider_phone, p.image as provider_image, p.rating as provider_rating,
               ua.label as address_label, ua.latitude, ua.longitude
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN providers p ON b.provider_id = p.id
        LEFT JOIN user_addresses ua ON b.address_id = ua.id
        WHERE b.id = $order_id";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $order = $result->fetch_assoc();

    // 7. Fetch Order Items (Services)
    $items_sql = "SELECT service_name, quantity, unit_price, total_price 
                  FROM booking_items 
                  WHERE booking_id = $order_id";
    $items_res = $conn->query($items_sql);
    
    $items = [];
    while ($item = $items_res->fetch_assoc()) {
        $items[] = [
            "name" => $item['service_name'],
            "qty" => $item['quantity'],
            "price" => "SAR " . number_format($item['unit_price'], 0),
            "total" => "SAR " . number_format($item['total_price'], 0)
        ];
    }

    // 8. Data Formatting
    // Address Logic: যদি bookings টেবিলে full_address থাকে সেটা নিবে (backup)
    $final_address = !empty($order['full_address']) ? $order['full_address'] : "Address details not available";

    // Provider Info Handling
    $provider_info = null;
    if (!empty($order['provider_name'])) {
        $provider_info = [
            "id" => $order['provider_id'],
            "name" => $order['provider_name'],
            "phone" => $order['provider_phone'],
            "rating" => $order['provider_rating'],
            "image" => !empty($order['provider_image']) ? "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/providers/" . $order['provider_image'] : null
        ];
    }

    // Response Structure
    $response = [
        "info" => [
            "order_id" => $order['id'],
            "booking_id_str" => "#ORD-" . str_pad($order['id'], 4, '0', STR_PAD_LEFT),
            "status" => ucfirst($order['status']),
            "raw_status" => $order['status'],
            "created_at" => date("d M, Y h:i A", strtotime($order['created_at'])),
            "schedule" => date("d M, Y", strtotime($order['schedule_date'])) . " | " . $order['schedule_time'],
        ],
        "customer" => [
            "name" => $order['customer_name'] ?? "Unknown User",
            "phone" => $order['customer_phone'] ?? "No Phone",
            "email" => $order['customer_email'] ?? "-",
            "address" => $final_address,
            "coordinates" => [
                "lat" => (float)$order['latitude'],
                "lng" => (float)$order['longitude']
            ]
        ],
        "provider" => $provider_info, // Assigned না থাকলে null যাবে
        "items" => $items,
        "payment" => [
            "sub_total" => "SAR " . number_format($order['sub_total'], 0),
            "discount" => "SAR " . number_format($order['discount_amount'], 0),
            "final_total" => "SAR " . number_format($order['final_total'], 0),
            "method" => strtoupper($order['payment_method']), // COD / CARD
            "status" => ucfirst($order['payment_status']) // Paid / Unpaid
        ],
        "cancellation" => ($order['status'] == 'cancelled') ? [
            "reason" => $order['cancel_reason'] ?? "No reason provided"
        ] : null
    ];

    echo json_encode(["status" => "success", "data" => $response]);

} else {
    echo json_encode(["status" => "error", "message" => "Order not found"]);
}

$conn->close();
?>