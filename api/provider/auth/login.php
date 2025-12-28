<?php
// 1. Error Reporting অন করা (যাতে 500 Error এর কারণ দেখা যায়)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 2. Database কানেকশন ফাইল খোঁজা (Smart Path Finding)
$current_dir = __DIR__; 
$possible_paths = [
    __DIR__ . '/../../db.php',       
    __DIR__ . '/../../../db.php',    
];

$db_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        include $path;
        $db_loaded = true;
        break;
    }
}

if (!$db_loaded) {
    die(json_encode(["status" => "error", "message" => "Database file (db.php) not found!"]));
}

// 3. ইনপুট নেওয়া
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['phone']) || empty($data['password'])) {
    echo json_encode(["status" => "error", "message" => "Phone and Password required"]);
    exit();
}

$phone = $conn->real_escape_string(trim($data['phone']));
$password = trim($data['password']);

// 4. প্রোভাইডার খোঁজা
$sql = "SELECT * FROM users WHERE phone = '$phone' AND role = 'provider' LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    // 5. পাসওয়ার্ড ভেরিফাই
    if (password_verify($password, $user['password'])) {
        
        if ($user['status'] == 'blocked') {
            echo json_encode(["status" => "error", "message" => "Your account is blocked!"]);
            exit();
        }

        // টোকেন জেনারেট এবং আপডেট
        $token = bin2hex(random_bytes(32));
        $updateToken = $conn->query("UPDATE users SET auth_token = '$token' WHERE id = " . $user['id']);

        if($updateToken) {
            echo json_encode([
                "status" => "success",
                "message" => "Login Successful",
                "token" => $token,
                "account_status" => $user['status'],
                "provider_id" => $user['id'],
                "name" => $user['name']
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Token update failed: " . $conn->error]);
        }

    } else {
        echo json_encode(["status" => "error", "message" => "Invalid Password"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Provider account not found!"]);
}
$conn->close();
?>