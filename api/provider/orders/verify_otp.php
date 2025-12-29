<?php
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

// ৩. শক্তিশালী টোকেন ফাংশন
function getBearerToken() {
    $headers = null;
    
    // ১. সরাসরি সার্ভার ভেরিয়েবল চেক
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } 
    else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } 
    // ২. অ্যাপাচি হেডার ফাংশন চেক
    elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    
    // ৩. বিয়ারার শব্দ থাকলে বের করা
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
        return $headers; // যদি শুধু টোকেন থাকে
    }
    return null;
}

$token = getBearerToken();

// ডিবাগিং (যদি দরকার হয় এই লাইনটি আনকমেন্ট করে রেসপন্স চেক করুন)
// die(json_encode(["debug_token" => $token, "headers" => apache_request_headers()]));

if (!$token) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized: Token missing or Header stripped"]);
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

if (!isset($data->booking_id) || !isset($data->otp)) {
    echo json_encode(["status" => "error", "message" => "Booking ID and OTP required"]);
    exit();
}

$booking_id = $data->booking_id;
$input_otp = $data->otp;

// ৬. OTP চেক এবং জব স্টার্ট
$checkSql = "SELECT id FROM bookings WHERE id = ? AND provider_id = ? AND otp = ?";
$stmt = $conn->prepare($checkSql);
$stmt->bind_param("iis", $booking_id, $provider_id, $input_otp);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // ম্যাচ করেছে -> স্ট্যাটাস আপডেট
    $updateStmt = $conn->prepare("UPDATE bookings SET status = 'in_progress' WHERE id = ?");
    $updateStmt->bind_param("i", $booking_id);
    
    if ($updateStmt->execute()) {
        echo json_encode(["status" => "success", "message" => "OTP Verified. Job Started!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Update failed"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid OTP or Wrong Order"]);
}

$conn->close();
?>