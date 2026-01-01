<?php
// File: api/user/home/service_details.php

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
    echo json_encode(["status" => "error", "message" => "Database connection failed! db.php not found."]);
    exit();
}

// 3. Input Validation
if (!isset($_GET['service_id'])) {
    echo json_encode(["status" => "error", "message" => "Service ID required (Example: ?service_id=1)"]);
    exit();
}

$service_id = (int)$_GET['service_id'];

// 4. Fetch Service Details with Category Name
$sql = "SELECT s.*, c.name as category_name 
        FROM services s 
        LEFT JOIN categories c ON s.category_id = c.id 
        WHERE s.id = $service_id AND s.status = 'active'";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // Image URL Formatting
    $row['image_url'] = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/" . $row['image'];
    
    // Type Casting (Frontend এ ক্যালকুলেশনের সুবিধার জন্য)
    $row['price'] = (float)$row['price'];
    $row['discount_price'] = (float)$row['discount_price'];
    $row['rating'] = (float)$row['rating'];
    
    // Optional: Calculate Discount Percentage
    $row['discount_percentage'] = 0;
    if ($row['price'] > 0 && $row['discount_price'] > 0) {
        $saved = $row['price'] - $row['discount_price'];
        $row['discount_percentage'] = round(($saved / $row['price']) * 100);
    }

    echo json_encode(["status" => "success", "data" => $row]);

} else {
    echo json_encode(["status" => "error", "message" => "Service not found or inactive"]);
}

$conn->close();
?>