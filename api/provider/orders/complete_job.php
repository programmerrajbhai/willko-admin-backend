<?php
// ১. এরর রিপোর্টিং এবং হেডার
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// ২. ডাটাবেস কানেকশন (Smart Path Finding)
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
    
    if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        return $matches[1];
    }
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
$payment_method = htmlspecialchars(trim($data->payment_method)); // 'cash' or 'online'
$payment_status = 'paid';

// ৬. আপডেট লজিক: চেক করা হচ্ছে বুকিংটি এই প্রোভাইডারের কিনা
$sql = "UPDATE bookings 
        SET status = 'completed', 
            payment_method = ?, 
            payment_status = ? 
        WHERE id = ? AND provider_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssii", $payment_method, $payment_status, $booking_id, $provider_id);

if ($stmt->execute()) {
    // চেক করা আসলেই কোনো রো আপডেট হলো কিনা (আইডি ভুল হলে বা অন্য প্রোভাইডারের হলে 0 হবে)
    if ($stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Job Completed Successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Job already completed or Access Denied"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Error: " . $stmt->error]);
}

$conn->close();
?>