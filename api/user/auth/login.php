<?php
// File: api/user/auth/login.php

// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// 3. Database Connection
$current_dir = __DIR__;
$possible_paths = [
    __DIR__ . '/../../../db.php', 
    __DIR__ . '/../../db.php',
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
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed!"]);
    exit();
}

// 4. Input Handling
$data = json_decode(file_get_contents("php://input"), true);

// আমরা 'login_id' নাম দিয়ে রিসিভ করব (যা ইমেইল বা ফোন দুটোই হতে পারে)
// তবে আগের 'phone' বা 'email' ফিল্ড আসলেও কাজ করবে।
$login_id = "";

if (!empty($data['login_id'])) {
    $login_id = trim($data['login_id']);
} elseif (!empty($data['phone'])) {
    $login_id = trim($data['phone']);
} elseif (!empty($data['email'])) {
    $login_id = trim($data['email']);
}

if (empty($login_id) || empty($data['password'])) {
    echo json_encode(["status" => "error", "message" => "Phone/Email and Password are required!"]);
    exit();
}

$login_id = $conn->real_escape_string($login_id);
$password = trim($data['password']);

// 5. User Find (Smart Query: Check Phone OR Email)
// Role অবশ্যই 'user' হতে হবে
$sql = "SELECT id, name, email, phone, password, status, auth_token 
        FROM users 
        WHERE (phone = '$login_id' OR email = '$login_id') 
        AND role = 'user' 
        LIMIT 1";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    // 6. Password Verify
    if (password_verify($password, $user['password'])) {
        
        // Status Check
        if ($user['status'] == 'blocked' || $user['status'] == 'banned') {
            echo json_encode(["status" => "error", "message" => "Your account has been blocked! Contact Admin."]);
            exit();
        }

        // 7. Generate Token
        $token = bin2hex(random_bytes(32)); 
        
        // Update token
        $updateSql = "UPDATE users SET auth_token = '$token', is_online = 1 WHERE id = " . $user['id'];
        
        if ($conn->query($updateSql)) {
            echo json_encode([
                "status" => "success",
                "message" => "Login Successful",
                "data" => [
                    "user_id" => $user['id'],
                    "name" => $user['name'],
                    "phone" => $user['phone'],
                    "email" => $user['email'],
                    "token" => $token
                ]
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Token generation failed!"]);
        }

    } else {
        echo json_encode(["status" => "error", "message" => "Invalid Password!"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "User not found with this Email or Phone."]);
}

$conn->close();
?>