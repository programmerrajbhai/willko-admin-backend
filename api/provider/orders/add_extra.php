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

if (!isset($data->booking_id) || !isset($data->item_name) || !isset($data->price)) {
    echo json_encode(["status" => "error", "message" => "Missing details (booking_id, item_name, price)"]);
    exit();
}

$booking_id = (int)$data->booking_id;
$item_name = htmlspecialchars(trim($data->item_name)); // XSS প্রোটেকশন
$price = (float)$data->price;

// ৬. লজিক: বর্তমান ডাটা আনা এবং প্রোভাইডার ভেরিফাই করা
$sql = "SELECT extra_charges, amount FROM bookings WHERE id = ? AND provider_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $booking_id, $provider_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    $current_extra = $row['extra_charges'];
    $current_total = $row['amount'];

    // নতুন টেক্সট ফরম্যাট: "আগের ডাটা, নতুন আইটেম: ১০০ টাকা"
    $new_extra_entry = $item_name . ": " . $price . " Tk";
    
    if (!empty($current_extra)) {
        $new_extra = $current_extra . ", " . $new_extra_entry;
    } else {
        $new_extra = $new_extra_entry;
    }
    
    $new_total = $current_total + $price;

    // ৭. ডাটাবেস আপডেট
    $update_sql = "UPDATE bookings SET extra_charges = ?, amount = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sdi", $new_extra, $new_total, $booking_id);
    
    if ($update_stmt->execute()) {
        echo json_encode([
            "status" => "success", 
            "message" => "Extra charge added",
            "new_total" => $new_total,
            "added_item" => $new_extra_entry
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Update failed: " . $conn->error]);
    }
} else {
    // যদি বুকিং আইডি ভুল হয় বা অন্য প্রোভাইডারের হয়
    echo json_encode(["status" => "error", "message" => "Booking not found or access denied"]);
}

$conn->close();
?>