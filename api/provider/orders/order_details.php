<?php
// ১. ডিবাগিংয়ের জন্য এরর রিপোর্ট অন করা
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// ২. ডাটাবেস কানেকশন (স্মার্ট পাথ ফাইন্ডিং)
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
    echo json_encode(["status" => "error", "message" => "Database file (db.php) not found! Check path."]);
    exit();
}

// ৩. অথেন্টিকেশন ফাংশন (টোকেন বের করা)
function getBearerToken() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

// ৪. টোকেন চেক করা
$token = getBearerToken();
if (!$token) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized: Token missing"]);
    exit();
}

// ৫. প্রোভাইডার ভেরিফাই করা
$stmt = $conn->prepare("SELECT id FROM users WHERE auth_token = ? AND role = 'provider' LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid Token or Provider not found"]);
    exit();
}

$provider = $result->fetch_assoc();
$provider_id = $provider['id']; // লগইন করা প্রোভাইডারের আইডি

// ৬. মেইন লজিক (অর্ডার ডিটেইলস আনা)
if (!isset($_GET['booking_id'])) {
    echo json_encode(["status" => "error", "message" => "Booking ID parameter missing"]);
    exit();
}

$booking_id = $_GET['booking_id'];

// কুয়েরি: বুকিং আইডি মিলতে হবে এবং সেটি অবশ্যই এই প্রোভাইডারের হতে হবে
$sql = "SELECT b.id, b.service_name, b.amount, b.status, b.otp, b.booking_date, b.extra_charges, b.location, b.details,
               u.name as customer_name, u.phone as customer_phone
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        WHERE b.id = ? AND b.provider_id = ?";

$orderStmt = $conn->prepare($sql);
$orderStmt->bind_param("ii", $booking_id, $provider_id);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();

if ($orderResult->num_rows > 0) {
    $order = $orderResult->fetch_assoc();
    
    // ডামি লোকেশন (ভবিষ্যতে রিয়েল ডাটা হবে)
    $order['latitude'] = 23.8103; 
    $order['longitude'] = 90.4125;
    
    echo json_encode(["status" => "success", "data" => $order]);
} else {
    // যদি অর্ডার না পাওয়া যায় অথবা অন্য প্রোভাইডারের অর্ডার হয়
    echo json_encode(["status" => "error", "message" => "Order not found or access denied"]);
}

$conn->close();
?>