<?php
// File: api/admin/orders/view.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// ✅ ROBUST DATABASE CONNECTION
$possible_paths = [__DIR__ . '/../../../db.php', __DIR__ . '/../../db.php'];
$db_loaded = false;
foreach ($possible_paths as $path) { if (file_exists($path)) { include_once $path; $db_loaded = true; break; } }
if (!$db_loaded) { echo json_encode(["status" => "error", "message" => "Database connection failed"]); exit(); }

// Auth Logic (Shortened)
$headers = null;
if (isset($_SERVER['Authorization'])) $headers = trim($_SERVER["Authorization"]);
elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
elseif (function_exists('apache_request_headers')) { $req = apache_request_headers(); if (isset($req['Authorization'])) $headers = trim($req['Authorization']); }
$token = (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) ? $matches[1] : null;

if (!$token || $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]); exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(["status" => "error", "message" => "Order ID required"]);
    exit();
}

$order_id = (int)$_GET['id'];

// Fetch Full Details
$sql = "SELECT b.*, 
               u.name as customer_name, u.phone as customer_phone, u.email as customer_email,
               p.name as provider_name, p.phone as provider_phone, p.rating as provider_rating, p.image as provider_image,
               ua.label as address_label, ua.latitude, ua.longitude
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN providers p ON b.provider_id = p.id
        LEFT JOIN user_addresses ua ON b.address_id = ua.id
        WHERE b.id = $order_id";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $order = $result->fetch_assoc();

    $items_res = $conn->query("SELECT service_name, quantity, unit_price, total_price FROM booking_items WHERE booking_id = $order_id");
    $items = [];
    while ($item = $items_res->fetch_assoc()) {
        $items[] = [
            "name" => $item['service_name'],
            "qty" => $item['quantity'],
            "total" => "SAR " . number_format($item['total_price'], 0)
        ];
    }

    $provider_info = null;
    if (!empty($order['provider_name'])) {
        $p_image = !empty($order['provider_image']) ? "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/providers/" . $order['provider_image'] : null;
        $provider_info = [
            "id" => $order['provider_id'],
            "name" => $order['provider_name'],
            "phone" => $order['provider_phone'],
            "rating" => $order['provider_rating'],
            "image" => $p_image
        ];
    }

    $response = [
        "info" => [
            "order_id" => $order['id'],
            "booking_id_str" => $order['booking_id_str'] ?? "#ORD-" . $order['id'],
            "status" => ucfirst($order['status']),
            "raw_status" => $order['status'],
            "created_at" => date("d M, Y h:i A", strtotime($order['created_at'])),
            "schedule" => date("d M, Y", strtotime($order['schedule_date'])) . " | " . $order['schedule_time'],
        ],
        "customer" => [
            "name" => $order['customer_name'],
            "phone" => $order['customer_phone'],
            "email" => $order['customer_email'],
            "address" => $order['full_address'] ?? "Address unavailable"
        ],
        "provider" => $provider_info,
        "items" => $items,
        "payment" => [
            "sub_total" => "SAR " . number_format($order['sub_total'], 0),
            "discount" => "SAR " . number_format($order['discount_amount'], 0),
            "final_total" => "SAR " . number_format($order['final_total'], 0),
            "method" => strtoupper($order['payment_method']),
            "status" => ucfirst($order['payment_status'])
        ],
        "cancellation" => ($order['status'] == 'cancelled') ? [
            "reason" => $order['cancel_reason']
        ] : null
    ];

    echo json_encode(["status" => "success", "data" => $response]);

} else {
    echo json_encode(["status" => "error", "message" => "Order not found"]);
}
$conn->close();
?>