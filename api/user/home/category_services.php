<?php
// 1. Error Reporting (সমস্যা ধরার জন্য)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// 2. স্মার্ট ডাটাবেস কানেকশন (পাথ অটো ডিটেক্ট করবে)
$current_dir = __DIR__;
$possible_paths = [
    $current_dir . '/../../../db.php',  // যদি Root ফোল্ডারে থাকে
    $current_dir . '/../../db.php',     // যদি API ফোল্ডারে থাকে
    $current_dir . '/../db.php'         // যদি User ফোল্ডারে থাকে
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
    echo json_encode([
        "status" => "error", 
        "message" => "Database file not found. Checked paths: " . implode(", ", $possible_paths)
    ]);
    exit();
}

// কানেকশন ভেরিফিকেশন
if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection variable (\$conn) is missing in db.php"]);
    exit();
}

// 3. ইনপুট ভ্যালিডেশন
if (!isset($_GET['category_id'])) {
    echo json_encode(["status" => "error", "message" => "Category ID required"]);
    exit();
}

$cat_id = (int)$_GET['category_id'];
$base_url = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/";

// ---------------------------------------------------
// 4. মেইন ক্যাটাগরি ইনফো আনা
// ---------------------------------------------------
$cat_sql = "SELECT * FROM categories WHERE id = $cat_id";
$cat_res = $conn->query($cat_sql);

if (!$cat_res || $cat_res->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Category not found"]);
    exit();
}

$category = $cat_res->fetch_assoc();

// ---------------------------------------------------
// 5. সাব-ক্যাটাগরি (Tabs) আনা
// ---------------------------------------------------
$sub_sql = "SELECT id, name as title FROM sub_categories WHERE category_id = $cat_id AND status = 'active'";
$sub_res = $conn->query($sub_sql);

$items = [];       
$sub_cat_map = []; 
$index = 0;

if ($sub_res && $sub_res->num_rows > 0) {
    while ($sub = $sub_res->fetch_assoc()) {
        $items[] = ["title" => $sub['title']]; 
        $sub_cat_map[$sub['id']] = $index;     
        $index++;
    }
} else {
    // সাব-ক্যাটাগরি না থাকলে ডিফল্ট ট্যাব
    $items[] = ["title" => "All Services"];
    $sub_cat_map['default'] = 0;
}

// ---------------------------------------------------
// 6. সার্ভিসগুলো আনা এবং ট্যাবে সাজানো
// ---------------------------------------------------
// Flutter Map<int, List> আশা করে, তাই আমরা PHP Object {} ব্যবহার করব
$packagesByItem = (object)[]; 

// প্রতিটি ট্যাবের জন্য খালি লিস্ট ইনিশিয়ালাইজ করা
for($i=0; $i<count($items); $i++) {
    $packagesByItem->{$i} = [];
}

$serv_sql = "SELECT * FROM services WHERE category_id = $cat_id AND status = 'active'";
$serv_res = $conn->query($serv_sql);

if ($serv_res && $serv_res->num_rows > 0) {
    while ($serv = $serv_res->fetch_assoc()) {
        $subId = $serv['sub_category_id'];
        
        // কোন ট্যাবে যাবে তা নির্ধারণ করা
        $tabIndex = 0;
        if (isset($sub_cat_map[$subId])) {
            $tabIndex = $sub_cat_map[$subId];
        } 
        
        // শর্ট ডিটেইলস প্রসেসিং (কমা দিয়ে আলাদা করা থাকলে)
        $shortDetails = [];
        if (!empty($serv['short_details'])) {
            $tempDetails = explode(',', $serv['short_details']);
            foreach($tempDetails as $detail) {
                $shortDetails[] = trim($detail);
            }
        }

        // সার্ভিস ডাটা সাজানো
        $serviceItem = [
            "id" => $serv['id'],
            "title" => $serv['name'],
            "rating" => (float)($serv['rating'] ?? 0.0),
            "reviews" => ($serv['reviews_count'] ?? 0) . " Reviews",
            "priceStr" => "SAR " . number_format($serv['price'], 0),
            "priceInt" => (int)$serv['price'],
            "description" => $serv['description'] ?? "",
            "shortDetails" => $shortDetails,
            "tag" => ((float)$serv['discount_price'] > 0) ? "Discount" : "",
            "image_url" => $base_url . ($serv['image'] ?? 'default.png')
        ];

        // সঠিক ট্যাবের লিস্টে পুশ করা
        array_push($packagesByItem->{$tabIndex}, $serviceItem);
    }
}

// ---------------------------------------------------
// 7. ফাইনাল রেসপন্স পাঠানো
// ---------------------------------------------------
$response_data = [
    "id" => $category['id'],
    "label" => $category['name'],
    "rating" => 4.8,
    "bookings" => "1k+",
    "slug" => $category['slug'],
    "items" => $items,               
    "packagesByItem" => $packagesByItem 
];

echo json_encode([
    "status" => "success",
    "data" => $response_data
], JSON_PRETTY_PRINT);

$conn->close();
?>