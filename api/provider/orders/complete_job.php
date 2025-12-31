<?php
// ফাইল: api/provider/orders/complete_job.php

// ১. এরর রিপোর্টিং এবং হেডার
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// ২. ডাটাবেস কানেকশন
$db_loaded = false;
$possible_paths = [
    __DIR__ . '/../../../db.php', 
    __DIR__ . '/../../db.php',
    $_SERVER['DOCUMENT_ROOT'] . '/willko-admin-backend/db.php'
];

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        include $path;
        $db_loaded = true;
        break;
    }
}

if (!$db_loaded) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// ৩. টোকেন চেক ফাংশন
function getBearerToken() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) $headers = trim($_SERVER["Authorization"]);
    elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    elseif (function_exists('apache_request_headers')) {
        $req = apache_request_headers();
        $req = array_combine(array_map('ucwords', array_keys($req)), array_values($req));
        if (isset($req['Authorization'])) $headers = trim($req['Authorization']);
    }
    if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) return $matches[1];
    return null;
}

$token = getBearerToken();
if (!$token) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized: Token missing"]);
    exit();
}

// ৪. প্রোভাইডার অথেন্টিকেশন
$authStmt = $conn->prepare("SELECT id FROM users WHERE auth_token = ? AND role = 'provider' LIMIT 1");
$authStmt->bind_param("s", $token);
$authStmt->execute();
$authRes = $authStmt->get_result();

if ($authRes->num_rows == 0) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid Token"]);
    exit();
}
$provider = $authRes->fetch_assoc();
$provider_id = $provider['id'];

// ৫. ইনপুট নেওয়া
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->booking_id) || !isset($data->payment_method)) {
    echo json_encode(["status" => "error", "message" => "Missing Info (booking_id, payment_method)"]);
    exit();
}

$booking_id = (int)$data->booking_id;
$payment_method = htmlspecialchars(trim($data->payment_method));
$payment_status = 'paid'; // সাধারণত কাজ শেষ হলে ক্যাশ পেমেন্ট রিসিভ হয়

// ৬. সিঙ্ক লজিক ও কমিশন ক্যালকুলেশন শুরু
$conn->begin_transaction();

try {
    // ধাপ ১: বুকিং ও পেমেন্ট স্ট্যাটাস চেক করা
    // একই সাথে টাকার পরিমাণ (amount) নেওয়া হচ্ছে কমিশন হিসাবের জন্য
    $findUser = $conn->prepare("SELECT user_id, status, payment_status, amount FROM bookings WHERE id = ? AND provider_id = ?");
    $findUser->bind_param("ii", $booking_id, $provider_id);
    $findUser->execute();
    $userRes = $findUser->get_result();

    if ($userRes->num_rows == 0) {
        throw new Exception("Booking not found or Access Denied");
    }
    
    $bookingData = $userRes->fetch_assoc();
    $user_id = $bookingData['user_id'];
    $order_amount = $bookingData['amount'];

    // চেক: যদি আগেই কমপ্লিট বা পেইড থাকে
    if ($bookingData['status'] === 'completed' || $bookingData['payment_status'] === 'paid') {
        $conn->rollback(); 
        echo json_encode(["status" => "error", "message" => "Already completed and paid"]);
        exit();
    }

    // ধাপ ২: bookings টেবিল আপডেট (Provider App)
    $updBooking = $conn->prepare("UPDATE bookings SET status = 'completed', payment_method = ?, payment_status = ? WHERE id = ?");
    $updBooking->bind_param("ssi", $payment_method, $payment_status, $booking_id);
    
    if (!$updBooking->execute()) {
        throw new Exception("Failed to update booking status");
    }

    // ধাপ ৩: orders টেবিল আপডেট (Admin Panel Sync)
    $updOrder = $conn->prepare("UPDATE orders SET status = 'completed', payment_status = ? WHERE user_id = ? AND provider_id = ? AND status = 'assigned'");
    $updOrder->bind_param("sii", $payment_status, $user_id, $provider_id);
    $updOrder->execute();


    // [NEW LOGIC] ধাপ ৪: কমিশন ক্যালকুলেশন এবং ডিউ আপডেট
    // প্রথমে প্রোভাইডারের কমিশন রেট বের করা (provider_details টেবিল থেকে)
    // ডিফল্ট কমিশন ২০% ধরা হলো যদি সেট করা না থাকে
    $commission_rate = 20; 
    
    $commQuery = $conn->prepare("SELECT commission_rate FROM provider_details WHERE user_id = ?");
    $commQuery->bind_param("i", $provider_id);
    $commQuery->execute();
    $commRes = $commQuery->get_result();
    
    if ($commRes->num_rows > 0) {
        $row = $commRes->fetch_assoc();
        if (!empty($row['commission_rate'])) {
            $commission_rate = $row['commission_rate'];
        }
    }

    // কমিশন হিসাব করা
    // সূত্র: (অর্ডার ভ্যালু * কমিশন রেট) / ১০০
    $admin_commission = ($order_amount * $commission_rate) / 100;

    // users টেবিলে current_due আপডেট করা
    $updDue = $conn->prepare("UPDATE users SET current_due = current_due + ? WHERE id = ?");
    $updDue->bind_param("di", $admin_commission, $provider_id);
    
    if (!$updDue->execute()) {
        throw new Exception("Failed to update commission/due amount");
    }

    // সব ঠিক থাকলে সেভ করা
    $conn->commit();
    echo json_encode([
        "status" => "success", 
        "message" => "Job Completed! Commission Added: " . $admin_commission,
        "commission_amount" => $admin_commission,
        "current_due_updated" => true
    ]);

} catch (Exception $e) {
    $conn->rollback(); // কোনো ভুল হলে আগের অবস্থায় ফিরে যাবে
    echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
}

$conn->close();
?>