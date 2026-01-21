<?php
// File: api/user/settings/app_settings.php

// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

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

// 3. Fetch Settings
// আমরা ধরে নিচ্ছি ১ নম্বর রো-তেই সব সেটিংস আছে
$sql = "SELECT support_phone, whatsapp_link, privacy_policy, terms_condition, app_version, force_update, maintenance_mode 
        FROM app_settings 
        ORDER BY id ASC LIMIT 1";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $settings = $result->fetch_assoc();
    
    // Type Casting (Frontend এর সুবিধার জন্য)
    $settings['force_update'] = (bool)$settings['force_update'];
    $settings['maintenance_mode'] = (bool)$settings['maintenance_mode'];

    echo json_encode(["status" => "success", "data" => $settings]);
} else {
    // ডাটাবেসে ডাটা না থাকলে ডিফল্ট ডাটা রিটার্ন করবে
    echo json_encode([
        "status" => "success", 
        "data" => [
            "support_phone" => "+8801700000000",
            "whatsapp_link" => "",
            "app_version" => "1.0.0",
            "force_update" => false,
            "maintenance_mode" => false
        ]
    ]);
}

$conn->close();
?>