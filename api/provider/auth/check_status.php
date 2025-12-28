<?php
// File: api/provider/auth/check_status.php

include '../../../db.php';
// 1. Error Reporting ON (সমস্যা দেখার জন্য)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");


// 2. Database কানেকশন ফাইল খোঁজা (Smart Path Finding)
$current_dir = __DIR__; 
$possible_paths = [
    __DIR__ . '/../../db.php',       
    __DIR__ . '/../../../db.php',    
];


// Check if connection works
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit();
}

// 3. Helper Function to get Authorization Header
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
    http_response_code(401); // Unauthorized
    echo json_encode(["status" => "error", "message" => "Token missing"]);
    exit();
}

// 4. Safe Query (Only selecting fields confirmed to exist)
// আগের কোডের ফিল্ডগুলোই রাখলাম যাতে এরর না দেয়
$sql = "SELECT status, name, category FROM users WHERE auth_token = ? AND role = 'provider' LIMIT 1";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Success Response
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "account_status" => $row['status'],
            "data" => $row
        ]);
    } else {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid Token or User not found"]);
    }
    $stmt->close();
} else {
    // Query Error Details
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Query Failed", 
        "debug" => $conn->error // এরর মেসেজ দেখাবে
    ]);
}

$conn->close();
?>