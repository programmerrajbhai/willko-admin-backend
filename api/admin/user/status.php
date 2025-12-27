<?php
// 1. Error Reporting ON (যাতে 500 Error এর আসল কারণ দেখা যায়)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 2. getallheaders() Polyfill (এটি সার্ভার ক্র্যাশ আটকাবে)
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// 3. Database Connection
$current_dir = __DIR__;
$root_path = $_SERVER['DOCUMENT_ROOT'] . '/WillkoServiceApi'; 

// পাথ খোঁজার লজিক
if (file_exists($root_path . '/db.php')) {
    include $root_path . '/db.php';
} elseif (file_exists(dirname(dirname(dirname($current_dir))) . '/db.php')) {
    include dirname(dirname(dirname($current_dir))) . '/db.php';
} else {
    // ম্যানুয়াল পাথ (যদি উপরেরগুলো কাজ না করে)
    include '../../../../db.php'; 
}

if (!isset($conn)) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// 4. Auth Check
$headers = getallheaders();
$token = isset($headers['Authorization']) ? substr($headers['Authorization'], 7) : '';

if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// 5. Input Processing
$data = json_decode(file_get_contents("php://input"), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;
$status = isset($data['status']) ? $conn->real_escape_string($data['status']) : '';

// Validation
$allowed_status = ['active', 'blocked']; 
if ($id == 0 || !in_array($status, $allowed_status)) {
    echo json_encode(["status" => "error", "message" => "Invalid ID or Status"]);
    exit();
}

// 6. Update Query
$sql = "UPDATE users SET status = '$status' WHERE id = $id AND role = 'user'";

if ($conn->query($sql)) {
    if ($conn->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "User status updated to $status"]);
    } else {
        echo json_encode(["status" => "error", "message" => "User not found, already $status, or trying to block Admin"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "DB Error: " . $conn->error]);
}
$conn->close();
?>