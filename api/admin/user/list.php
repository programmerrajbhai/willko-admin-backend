<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// DB & Auth
$current_dir = __DIR__;
$api_dir = dirname(dirname(dirname($current_dir)));
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// Auth Check
$headers = getallheaders();
$token = isset($headers['Authorization']) ? substr($headers['Authorization'], 7) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// Input
$data = json_decode(file_get_contents("php://input"), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;
$status = isset($data['status']) ? $conn->real_escape_string($data['status']) : '';

// Validation
$allowed_status = ['active', 'blocked']; 
if ($id == 0 || !in_array($status, $allowed_status)) {
    echo json_encode(["status" => "error", "message" => "Invalid ID or Status (use 'active' or 'blocked')"]);
    exit();
}

// Update Query (গুরুত্বপূর্ণ: role = 'user' দেওয়া আছে যাতে ভুলে এডমিন ব্লক না হয়)
$sql = "UPDATE users SET status = '$status' WHERE id = $id AND role = 'user'";

if ($conn->query($sql)) {
    if ($conn->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "User status updated to $status"]);
    } else {
        // যদি ইউজার না পাওয়া যায় বা স্ট্যাটাস আগে থেকেই একই থাকে
        echo json_encode(["status" => "error", "message" => "User not found, already $status, or you are trying to block an Admin"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "DB Error"]);
}
$conn->close();
?>