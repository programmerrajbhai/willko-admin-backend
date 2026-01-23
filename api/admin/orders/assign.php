<?php
// File: api/admin/orders/assign.php

// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

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
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// 3. Auth Helper (Admin Only)
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

$authStmt = $conn->prepare("SELECT id FROM users WHERE auth_token = ? AND role = 'admin' LIMIT 1");
$authStmt->bind_param("s", $token);
$authStmt->execute();
if ($authStmt->get_result()->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Permission Denied"]);
    exit();
}

// 4. Input Validation
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['order_id']) || empty($data['provider_id'])) {
    echo json_encode(["status" => "error", "message" => "Order ID and Provider ID are required"]);
    exit();
}

$order_id = (int)$data['order_id'];
$provider_id = (int)$data['provider_id'];

// ==============================================
// 5. ADVANCED CHECKS (Business Logic)
// ==============================================

// A. প্রভাইডার ভ্যালিড এবং Active আছে কিনা চেক করা
$provCheck = $conn->query("SELECT name FROM providers WHERE id = $provider_id AND status = 'active'");
if ($provCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Provider is inactive or does not exist."]);
    exit();
}
$provider_name = $provCheck->fetch_assoc()['name'];

// B. অর্ডারের বর্তমান অবস্থা চেক করা
$orderCheck = $conn->query("SELECT status, booking_id_str FROM bookings WHERE id = $order_id");
if ($orderCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Order not found."]);
    exit();
}
$orderRow = $orderCheck->fetch_assoc();
$current_status = strtolower($orderRow['status']);
$booking_str = $orderRow['booking_id_str'] ?? "#ORD-$order_id";

// C. লজিক: অর্ডার কি অলরেডি কমপ্লিট বা ক্যানসেল?
if (in_array($current_status, ['completed', 'cancelled'])) {
    echo json_encode(["status" => "error", "message" => "Cannot assign provider to a $current_status order."]);
    exit();
}

// ==============================================
// 6. ASSIGNMENT LOGIC (Transaction)
// ==============================================
$conn->begin_transaction();

try {
    // ১. OTP জেনারেট করা (Secure Random)
    $otp = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

    // ২. বুকিং আপডেট করা (INSERT না, UPDATE হবে)
    $updateSql = "UPDATE bookings SET 
                    provider_id = ?, 
                    status = 'assigned', 
                    otp = ? 
                  WHERE id = ?";
    
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("isi", $provider_id, $otp, $order_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to assign provider.");
    }

    // ৩. প্রভাইডারকে নোটিফিকেশন পাঠানো (Database Entry)
    // প্রভাইডার অ্যাপ এই টেবিল চেক করে এলার্ট পাবে
    $notif_title = "New Job Assigned! 🛠️";
    $notif_msg = "You have been assigned to order $booking_str. Please check your dashboard.";
    
    $notifSql = "INSERT INTO notifications (provider_id, title, message, type) VALUES (?, ?, ?, 'order_assigned')";
    $notifStmt = $conn->prepare($notifSql);
    $notifStmt->bind_param("iss", $provider_id, $notif_title, $notif_msg);
    $notifStmt->execute();

    // ৪. সাকসেস কমিট
    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Provider ($provider_name) assigned successfully!",
        "data" => [
            "order_id" => $order_id,
            "provider" => $provider_name,
            "otp" => $otp, // Admin panel e OTP dekhar jonno (Debug)
            "new_status" => "Assigned"
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => "System Error: " . $e->getMessage()]);
}

$conn->close();
?>