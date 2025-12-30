<?php
// ১. এরর রিপোর্টিং
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ২. CORS এবং হেডার সেটআপ (সবচেয়ে গুরুত্বপূর্ণ অংশ)
// Flutter Web এর জন্য OPTIONS মেথড এবং Authorization হেডার অ্যালাউ করা বাধ্যতামূলক
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

// Access-Control headers received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    exit(0); // OPTIONS রিকোয়েস্ট এখানেই শেষ করা জরুরি
}

header("Content-Type: application/json; charset=UTF-8");

// ৩. ডাটাবেস কানেকশন (Smart Path Finding)
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

// ৪. Auth Helper Function
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
        return $headers;
    }
    return null;
}

$token = getBearerToken();

if (empty($token)) {
    http_response_code(401); 
    echo json_encode(["status" => "error", "message" => "Unauthorized: Token missing"]); 
    exit(); 
}

// ৫. ডাটাবেস চেক (Query)
$stmt = $conn->prepare("SELECT id, status, name, category, document_image FROM users WHERE auth_token = ? AND role = 'provider'");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    
    // লজিক: যদি document_image কলামে ডাটা থাকে, তার মানে ফাইল সাবমিট করা হয়েছে
    $is_submitted = !empty($row['document_image']);

    echo json_encode([
        "status" => "success",
        "account_status" => $row['status'], // active, pending, blocked
        "is_doc_submitted" => $is_submitted, // true / false
        "data" => [
            "name" => $row['name'],
            "category" => $row['category']
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid Token"]);
}
$conn->close();
?>