<?php
// File: api/user/profile/update_profile.php

// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 2. Database Connection
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
    echo json_encode(["status" => "error", "message" => "Database connection failed!"]);
    exit();
}

// 3. Auth Helper (Check User)
function getBearerToken() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) $headers = trim($_SERVER["Authorization"]);
    elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    elseif (function_exists('apache_request_headers')) {
        $req = apache_request_headers();
        if (isset($req['Authorization'])) $headers = trim($req['Authorization']);
    }
    if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) return $matches[1];
    return null;
}

$token = getBearerToken();
if (!$token) {
    echo json_encode(["status" => "error", "message" => "Unauthorized!"]);
    exit();
}

$token = $conn->real_escape_string($token);
$userCheck = $conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'user'");

if ($userCheck->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Token!"]);
    exit();
}
$user_id = $userCheck->fetch_assoc()['id'];

// 4. Input Handling (POST & FILES)
// যেহেতু ইমেজ আপলোড আছে, তাই আমরা JSON বডি ব্যবহার করব না। সরাসরি $_POST এবং $_FILES ব্যবহার করব।

$name = isset($_POST['name']) ? trim($_POST['name']) : null;
$password = isset($_POST['password']) ? trim($_POST['password']) : null;

// আপডেটের জন্য কুয়েরি পার্টগুলো তৈরি করা
$updates = [];

// A. Name Update
if (!empty($name)) {
    $safe_name = $conn->real_escape_string($name);
    $updates[] = "name = '$safe_name'";
}

// B. Password Update
if (!empty($password)) {
    $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
    $updates[] = "password = '$hashed_pass'";
}

// C. Image Update
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $target_dir = "../../uploads/profile/";
    
    // ফোল্ডার না থাকলে তৈরি করে নিবে
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_extension = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
    $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // ভ্যালিডেশন (Optional: check file type)
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array(strtolower($file_extension), $allowed_types)) {
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $updates[] = "image = '$new_filename'";
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to upload image"]);
            exit();
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Only JPG, JPEG, PNG allowed"]);
        exit();
    }
}

// 5. Execute Update
if (count($updates) > 0) {
    $sql_part = implode(", ", $updates);
    $sql = "UPDATE users SET $sql_part WHERE id = $user_id";

    if ($conn->query($sql)) {
        echo json_encode(["status" => "success", "message" => "Profile updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database Error: " . $conn->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Nothing to update! Provide name, password or image."]);
}

$conn->close();
?>