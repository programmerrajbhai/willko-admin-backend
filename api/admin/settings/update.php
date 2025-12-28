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
    echo json_encode(["status" => "error", "message" => "Unauthorized"]); exit();
}

$data = json_decode(file_get_contents("php://input"), true);

$site_title = $data['site_title'] ?? 'Wilko Service';
$currency = $data['currency'] ?? 'BDT';
$vat = $data['vat'] ?? 0;
$phone = $data['support_phone'] ?? '';
$email = $data['support_email'] ?? '';

$sql = "UPDATE settings SET 
        site_title='$site_title', 
        currency='$currency', 
        vat='$vat', 
        support_phone='$phone', 
        support_email='$email' 
        WHERE id=1";

if ($conn->query($sql)) {
    echo json_encode(["status" => "success", "message" => "Settings Updated"]);
} else {
    echo json_encode(["status" => "error", "message" => "DB Error"]);
}
$conn->close();
?>