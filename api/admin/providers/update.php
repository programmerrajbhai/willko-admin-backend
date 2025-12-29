<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// ১. ডাটাবেস কানেকশন
$current_dir = __DIR__;
$api_dir = dirname(dirname($current_dir));
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// ২. অথেন্টিকেশন চেক
$headers = getallheaders();
$token = isset($headers['Authorization']) ? substr($headers['Authorization'], 7) : '';

if (empty($token) || $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// ৩. ইনপুট হ্যান্ডলিং
$input_data = json_decode(file_get_contents("php://input"), true);

// ID বের করা
$id = 0;
if (isset($_POST['id'])) $id = (int)$_POST['id'];
elseif (isset($input_data['id'])) $id = (int)$input_data['id'];

if ($id == 0) {
    echo json_encode(["status" => "error", "message" => "Provider ID required"]);
    exit();
}

// ৪. বর্তমান ডাটা আনা
$existing = $conn->query("SELECT * FROM users WHERE id = $id")->fetch_assoc();
if (!$existing) {
    echo json_encode(["status" => "error", "message" => "Provider not found"]);
    exit();
}

// ৫. ডাটা প্রিপারেশন
$name = isset($_POST['name']) ? $_POST['name'] : (isset($input_data['name']) ? $input_data['name'] : $existing['name']);
$phone = isset($_POST['phone']) ? $_POST['phone'] : (isset($input_data['phone']) ? $input_data['phone'] : $existing['phone']);
$status = isset($_POST['status']) ? $_POST['status'] : (isset($input_data['status']) ? $input_data['status'] : $existing['status']);

// --- নতুন ফিচার: পাসওয়ার্ড পরিবর্তন ---
$passwordSql = "";
$new_pass = isset($_POST['password']) ? $_POST['password'] : (isset($input_data['password']) ? $input_data['password'] : '');

if (!empty($new_pass)) {
    $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);
    $passwordSql = ", password = '$hashed_password'";
}
// -----------------------------------

// ৬. ইমেজ আপডেট লজিক
$imageSql = "";
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $upload_path = $api_dir . '/uploads/';
    if (!is_dir($upload_path)) mkdir($upload_path, 0777, true);

    $newImage = time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;
    if(move_uploaded_file($_FILES["image"]["tmp_name"], $upload_path . $newImage)) {
        $imageSql = ", image = '$newImage'";
    }
}

// ৭. আপডেট কুয়েরি (পাসওয়ার্ডসহ)
$sql = "UPDATE users SET name='$name', phone='$phone', status='$status' $imageSql $passwordSql WHERE id=$id";

if ($conn->query($sql) === TRUE) {
    echo json_encode([
        "status" => "success", 
        "message" => !empty($passwordSql) ? "Data & Password Updated" : "Provider Updated Successfully"
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Update Failed: " . $conn->error]);
}

$conn->close();
?>