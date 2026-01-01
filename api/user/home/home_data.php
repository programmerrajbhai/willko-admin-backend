<?php
// File: api/user/home/home_data.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// Database Connection
$current_dir = __DIR__;
$possible_paths = [
    __DIR__ . '/../../../db.php', 
    __DIR__ . '/../../db.php',
];

foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        include $path;
        break;
    }
}

// 1. Fetch Banners
$banners = [];
$bannerRes = $conn->query("SELECT id, image FROM banners ORDER BY id DESC");
while ($row = $bannerRes->fetch_assoc()) {
    // ইমেজ পাথ ঠিক করা (তোমার সার্ভার URL অনুযায়ী)
    $row['image_url'] = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/" . $row['image'];
    $banners[] = $row;
}

// 2. Fetch Categories
$categories = [];
$catRes = $conn->query("SELECT id, name, image FROM categories ORDER BY id ASC");
while ($row = $catRes->fetch_assoc()) {
    $row['image_url'] = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/" . $row['image'];
    $categories[] = $row;
}

// 3. Fetch Popular Services (Rating > 4.5 or Random)
$popular = [];
$popRes = $conn->query("SELECT id, name, price, discount_price, image, rating FROM services WHERE status = 'active' ORDER BY rating DESC LIMIT 10");
while ($row = $popRes->fetch_assoc()) {
    $row['image_url'] = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/" . $row['image'];
    $row['price'] = (float)$row['price'];
    $row['discount_price'] = (float)$row['discount_price'];
    $row['rating'] = (float)$row['rating'];
    $popular[] = $row;
}

// Final Response
echo json_encode([
    "status" => "success",
    "data" => [
        "banners" => $banners,
        "categories" => $categories,
        "popular_services" => $popular
    ]
]);

$conn->close();
?>