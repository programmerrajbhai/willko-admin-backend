<?php
// ১. এরর রিপোর্টিং এবং হেডার
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// ২. ডাটাবেস কানেকশন (Smart Path)
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

// ৩. টোকেন চেক (শক্তিশালী ফাংশন - XAMPP ফিক্স সহ)
function getBearerToken() {
    $headers = null;
    
    // ১. সরাসরি সার্ভার ভেরিয়েবল চেক
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
    
    // ৩. বিয়ারার টোকেন বের করা
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

$token = getBearerToken();

if (!$token) { 
    http_response_code(401); 
    echo json_encode(["status" => "error", "message" => "Unauthorized: Token missing"]); 
    exit(); 
}

// ৪. প্রোভাইডার ভেরিফাই
$stmt = $conn->prepare("SELECT id FROM users WHERE auth_token = ? AND role = 'provider'");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) { 
    http_response_code(401); 
    echo json_encode(["status" => "error", "message" => "Invalid Token"]); 
    exit(); 
}
$provider_id = $res->fetch_assoc()['id'];

// ৫. রিভিউ ফেচ করা
$sql = "SELECT r.id, r.rating, r.comment, r.created_at, u.name as customer_name, u.profile_image as customer_image
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.provider_id = ?
        ORDER BY r.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$result = $stmt->get_result();

$reviews = [];
$total_rating = 0;
$count = 0;

while ($row = $result->fetch_assoc()) {
    // ইমেজ হ্যান্ডলিং (যদি না থাকে NULL সেট হবে)
    $cust_img = !empty($row['customer_image']) ? $row['customer_image'] : null;

    $reviews[] = [
        "id" => $row['id'],
        "customer_name" => $row['customer_name'],
        "customer_image" => $cust_img,
        "rating" => (int)$row['rating'],
        "comment" => $row['comment'],
        "date" => date("d M, Y", strtotime($row['created_at']))
    ];
    $total_rating += $row['rating'];
    $count++;
}

// গড় রেটিং বের করা (Zero Division Error ফিক্স)
$avg_rating = ($count > 0) ? round($total_rating / $count, 1) : 0;

echo json_encode([
    "status" => "success",
    "summary" => [
        "average_rating" => $avg_rating,
        "total_reviews" => $count
    ],
    "reviews" => $reviews
]);

$conn->close();
?>