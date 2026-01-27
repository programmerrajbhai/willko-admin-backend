<?php
// File: api/admin/orders/list.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// ✅ ROBUST DATABASE CONNECTION
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
    echo json_encode(["status" => "error", "message" => "Database connection failed! db.php not found."]);
    exit();
}

// 1. Auth Check (Admin Only)
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

$token = $conn->real_escape_string($token);
$authCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'");
if ($authCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Permission Denied"]);
    exit();
}

// 2. Fetch Orders Logic
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// 🔴 FIX: Removed 'b.booking_id_str' from SELECT query
$sql = "SELECT b.id, b.final_total, b.schedule_date, b.schedule_time, 
               b.status, b.payment_status, b.created_at,
               u.name as customer_name, u.phone as customer_phone,
               p.name as provider_name
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN providers p ON b.provider_id = p.id";

if ($status != 'all') {
    $status = $conn->real_escape_string($status);
    $sql .= " WHERE b.status = '$status'";
}

$sql .= " ORDER BY b.created_at DESC";

$result = $conn->query($sql);
$orders = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // ✅ FIX: Generating ID manually in PHP
        $generated_booking_id = "#ORD-" . str_pad($row['id'], 4, '0', STR_PAD_LEFT);

        $orders[] = [
            "id" => $row['id'],
            "booking_id" => $generated_booking_id, // e.g. #ORD-0005
            "customer_name" => $row['customer_name'] ?? "Unknown",
            "customer_phone" => $row['customer_phone'] ?? "-",
            "provider_name" => $row['provider_name'] ?? "Not Assigned",
            "schedule_date" => date("d M, Y", strtotime($row['schedule_date'])),
            "schedule_time" => $row['schedule_time'],
            "price" => "SAR " . number_format($row['final_total'], 0),
            "status" => ucfirst($row['status']), 
            "raw_status" => strtolower($row['status']), 
            "payment_status" => ucfirst($row['payment_status']),
            "created_at" => $row['created_at']
        ];
    }
}

echo json_encode([
    "status" => "success",
    "count" => count($orders),
    "data" => $orders
]);

$conn->close();
?>