<?php
// File: api/user/home/category_services.php

// 1. Error Reporting (Debugging)
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
    echo json_encode(["status" => "error", "message" => "Database connection failed! db.php not found."]);
    exit();
}

// 3. Input Validation
if (!isset($_GET['category_id'])) {
    echo json_encode(["status" => "error", "message" => "Category ID required (Use ?category_id=1)"]);
    exit();
}

$cat_id = (int)$_GET['category_id'];

// 4. Fetch Services (Robust Query)
// rating কলাম না থাকলে বা SQL ভুল হলে এখন আর 500 Error আসবে না
$sql = "SELECT id, name, price, discount_price, image, description, rating 
        FROM services 
        WHERE category_id = $cat_id AND status = 'active'";

$result = $conn->query($sql);

if (!$result) {
    // এখানে আসল সমস্যা ধরা পড়বে
    echo json_encode(["status" => "error", "message" => "Query Failed: " . $conn->error]);
    exit();
}

$services = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['image_url'] = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/" . $row['image'];
        $row['price'] = (float)$row['price'];
        $row['discount_price'] = (float)$row['discount_price'];
        $row['rating'] = (float)$row['rating'];
        $services[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $services]);
} else {
    echo json_encode(["status" => "success", "message" => "No services found in this category", "data" => []]);
}

$conn->close();
?>