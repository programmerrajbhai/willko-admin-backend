<?php
// File: api/user/home/search.php

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

// 3. Input Handling
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

// ভ্যালিডেশন: খুব ছোট কিওয়ার্ড হলে রেজাল্ট ফাঁকা দিব (অপশনাল)
if (strlen($query) < 2) {
    echo json_encode([
        "status" => "success", 
        "message" => "Type at least 2 characters", 
        "data" => []
    ]);
    exit();
}

$search_term = $conn->real_escape_string($query);

// 4. Search Query (Name or Description)
// আমরা সার্ভিসের নাম এবং বিবরণ দুটোতেই খুঁজব
$sql = "SELECT id, name, price, discount_price, image, rating 
        FROM services 
        WHERE (name LIKE '%$search_term%' OR description LIKE '%$search_term%') 
        AND status = 'active' 
        LIMIT 20";

$result = $conn->query($sql);
$services = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Image URL Fix
        $row['image_url'] = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/" . $row['image'];
        
        // Type Casting
        $row['price'] = (float)$row['price'];
        $row['discount_price'] = (float)$row['discount_price'];
        $row['rating'] = (float)$row['rating'];
        
        $services[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $services]);
} else {
    echo json_encode(["status" => "success", "message" => "No services found matching '$query'", "data" => []]);
}

$conn->close();
?>