<?php
// File: api/user/home/service_details.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// ডাটাবেস কানেকশন
$current_dir = __DIR__;
$possible_paths = [__DIR__ . '/../../../db.php', __DIR__ . '/../../db.php'];
$db_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) { include $path; $db_loaded = true; break; }
}

if (!$db_loaded) {
    echo json_encode(["status" => "error", "message" => "Database connection failed!"]);
    exit();
}

if (!isset($_GET['category_id'])) {
    echo json_encode(["status" => "error", "message" => "Category ID required"]);
    exit();
}

$cat_id = (int)$_GET['category_id'];
$base_url = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/";

// ১. মেইন ক্যাটাগরি ডাটা আনা
$cat_sql = "SELECT * FROM categories WHERE id = $cat_id AND status = 'active'";
$cat_res = $conn->query($cat_sql);

if ($cat_res->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Service not found"]);
    exit();
}

$category = $cat_res->fetch_assoc();

// ২. সাব-ক্যাটাগরি (Tabs) আনা
$sub_sql = "SELECT id, name as title FROM sub_categories WHERE category_id = $cat_id AND status = 'active'";
$sub_res = $conn->query($sub_sql);

$items = [];
$sub_cat_ids = []; // ID ম্যাপিংয়ের জন্য
$index = 0;
$sub_cat_map = []; // sub_cat_id => index

while ($sub = $sub_res->fetch_assoc()) {
    $items[] = ["title" => $sub['title']];
    $sub_cat_map[$sub['id']] = $index; // ID কে লিস্ট ইনডেক্সে কনভার্ট করা হচ্ছে
    $index++;
}

// ৩. সার্ভিস/প্যাকেজ আনা এবং ইনডেক্স অনুযায়ী সাজানো
$packagesByItem = [];

// সার্ভিসগুলো ফেচ করা
$serv_sql = "SELECT * FROM services WHERE category_id = $cat_id AND status = 'active'";
$serv_res = $conn->query($serv_sql);

while ($serv = $serv_res->fetch_assoc()) {
    $subId = $serv['sub_category_id'];
    
    // যদি সাব ক্যাটাগরি না থাকে, তবে প্রথম ট্যাবে (0) রাখবে
    $tabIndex = isset($sub_cat_map[$subId]) ? $sub_cat_map[$subId] : 0;

    // Short Details String কে List এ কনভার্ট করা (ex: "Warranty,Verified" -> ["Warranty", "Verified"])
    $shortDetails = !empty($serv['short_details']) ? explode(',', $serv['short_details']) : ["Verified Professional", "Safe & Secure"];

    $serviceItem = [
        "id" => $serv['id'],
        "title" => $serv['name'],
        "rating" => (float)$serv['rating'],
        "reviews" => $serv['reviews_count'] . " Reviews",
        "priceStr" => "SAR " . number_format($serv['price'], 0),
        "priceInt" => (int)$serv['price'],
        "description" => $serv['description'] ?? "Best quality service provided by Willko.",
        "shortDetails" => $shortDetails,
        "tag" => ($serv['discount_price'] > 0) ? "Discount" : "", // লজিক অনুযায়ী ট্যাগ
        "image_url" => $base_url . $serv['image']
    ];

    $packagesByItem[$tabIndex][] = $serviceItem;
}

// ৪. রেস্পন্স সাজানো (Flutter UI এর স্ট্রাকচার অনুযায়ী)
$response = [
    "label" => $category['name'],
    "rating" => 4.8, // অথবা ক্যাটাগরির গড় রেটিং
    "bookings" => "5k+", // স্ট্যাটিক বা ডাইনামিক করতে পারেন
    "slug" => $category['slug'],
    "items" => $items, // Tabs [ {title: "Installation"}, ... ]
    "packagesByItem" => $packagesByItem // { 0: [...], 1: [...] }
];

echo json_encode(["status" => "success", "data" => $response]);
$conn->close();
?>