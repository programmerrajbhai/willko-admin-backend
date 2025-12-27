<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// DB & Auth
$current_dir = __DIR__;
$api_dir = dirname(dirname($current_dir));
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// Auth Check
$headers = getallheaders();
$token = isset($headers['Authorization']) ? substr($headers['Authorization'], 7) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;

if ($id == 0) {
    echo json_encode(["status" => "error", "message" => "ID required"]);
    exit();
}

// Delete logic (শুধু প্রোভাইডার ডিলিট হবে, ইউজার বা এডমিন নয়)
if ($conn->query("DELETE FROM users WHERE id = $id AND role = 'provider'")) {
    if ($conn->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Provider Deleted"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Provider Not Found or ID belongs to Admin/User"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "DB Error"]);
}
$conn->close();
?>