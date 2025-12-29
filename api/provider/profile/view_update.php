<?php
// ১. এরর রিপোর্টিং এবং হেডার
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");
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

// ৩. টোকেন চেক (শক্তিশালী ফাংশন)
function getBearerToken() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } 
    else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } 
    elseif (function_exists('apache_request_headers')) {
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

$token = getBearerToken();

if (!$token) { 
    http_response_code(401); 
    echo json_encode(["status" => "error", "message" => "Unauthorized: Token not found"]); 
    exit(); 
}

// ৪. প্রোভাইডার ভেরিফাই
$stmt = $conn->prepare("SELECT * FROM users WHERE auth_token = ? AND role = 'provider'");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    http_response_code(401); 
    echo json_encode(["status" => "error", "message" => "Invalid Token"]); 
    exit(); 
}

$provider = $result->fetch_assoc();
$provider_id = $provider['id'];

// ৫. মেথড চেক (GET না POST?)
$method = $_SERVER['REQUEST_METHOD'];

// ============ GET: প্রোফাইল দেখা ============
if ($method == 'GET') {
    // রেটিং ক্যালকুলেট করা
    $ratingSql = "SELECT AVG(rating) as avg_rating, COUNT(id) as total_reviews FROM reviews WHERE provider_id = $provider_id";
    $ratingRes = $conn->query($ratingSql);
    
    $avg_rating = 0;
    $total_reviews = 0;

    if ($ratingRes && $ratingRes->num_rows > 0) {
        $row = $ratingRes->fetch_assoc();
        // ✅ ফিক্স: মান NULL হলে round() কল হবে না, ০ সেট হবে
        $avg_rating = $row['avg_rating'] ? round((float)$row['avg_rating'], 1) : 0;
        $total_reviews = $row['total_reviews'];
    }
    
    // ✅ ফিক্স: profile_image কলাম না থাকলেও যেন এরর না দেয়
    $profile_img = isset($provider['profile_image']) ? $provider['profile_image'] : null;

    $profile_data = [
        "name" => $provider['name'],
        "phone" => $provider['phone'],
        "email" => $provider['email'],
        "category" => $provider['category'],
        "profile_image" => $profile_img, 
        "nid_number" => $provider['nid_number'],
        "rating" => $avg_rating,
        "total_reviews" => $total_reviews,
        "joined_at" => $provider['created_at']
    ];

    echo json_encode(["status" => "success", "data" => $profile_data]);
}

// ============ POST: প্রোফাইল আপডেট ============
elseif ($method == 'POST') {
    $name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name'])) : $provider['name'];
    $email = isset($_POST['email']) ? htmlspecialchars(trim($_POST['email'])) : $provider['email'];
    $category = isset($_POST['category']) ? htmlspecialchars(trim($_POST['category'])) : $provider['category'];
    
    // ✅ ফিক্স: কলাম না থাকলেও ডিফল্ট হ্যান্ডেল করা
    $profile_image = isset($provider['profile_image']) ? $provider['profile_image'] : null;

    // ছবি আপলোড লজিক
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $upload_dir = dirname(dirname(dirname(__DIR__))) . '/uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $fileName = "profile_" . $provider_id . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $fileName)) {
                $profile_image = "uploads/profiles/" . $fileName; 
            }
        }
    }

    // আপডেট কুয়েরি
    $updateStmt = $conn->prepare("UPDATE users SET name = ?, email = ?, category = ?, profile_image = ? WHERE id = ?");
    $updateStmt->bind_param("ssssi", $name, $email, $category, $profile_image, $provider_id);

    if ($updateStmt->execute()) {
        echo json_encode([
            "status" => "success", 
            "message" => "Profile updated successfully",
            "updated_data" => [
                "name" => $name,
                "email" => $email,
                "profile_image" => $profile_image
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Update failed: " . $conn->error]);
    }
}

$conn->close();
?>