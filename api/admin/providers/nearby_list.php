<?php
// File: api/admin/providers/nearby_list.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// DB Connection
$current_dir = __DIR__;
$possible_paths = [__DIR__ . '/../../../db.php', __DIR__ . '/../../db.php'];
$db_loaded = false;
foreach ($possible_paths as $path) { if (file_exists($path)) { include_once $path; $db_loaded = true; break; } }
if (!$db_loaded) { echo json_encode(["status" => "error", "message" => "Database connection failed"]); exit(); }

// Auth Check (Admin Only)
$headers = getallheaders();
$token = isset($headers['Authorization']) ? trim(str_replace("Bearer ", "", $headers['Authorization'])) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]); exit();
}

// Input: Order Latitude & Longitude
$order_lat = isset($_GET['lat']) ? (float)$_GET['lat'] : 0;
$order_lng = isset($_GET['lng']) ? (float)$_GET['lng'] : 0;

if ($order_lat == 0 || $order_lng == 0) {
    // যদি লোকেশন না থাকে, সাধারণ লিস্ট দেখাবে
    $sql = "SELECT id, name, phone, image, rating, 'N/A' as distance 
            FROM users WHERE role = 'provider' AND status = 'active'";
} else {
    // Haversine Formula (দূরত্ব বের করার সূত্র - KM)
    $sql = "SELECT id, name, phone, image, rating, latitude, longitude,
            ( 6371 * acos( cos( radians($order_lat) ) * cos( radians( latitude ) ) 
            * cos( radians( longitude ) - radians($order_lng) ) + sin( radians($order_lat) ) 
            * sin( radians( latitude ) ) ) ) AS distance 
            FROM users 
            WHERE role = 'provider' AND status = 'active'
            HAVING distance < 50 
            ORDER BY distance ASC";
}

$result = $conn->query($sql);
$providers = [];

while ($row = $result->fetch_assoc()) {
    $row['distance'] = ($row['distance'] != 'N/A') ? round($row['distance'], 1) . " km away" : "Location Unknown";
    
    // Image Handling
    if (!empty($row['image'])) {
        $row['image'] = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/" . $row['image'];
    }
    
    // Check Active Jobs (Currently Assigned)
    $active_jobs = $conn->query("SELECT count(*) as total FROM bookings WHERE provider_id = {$row['id']} AND status IN ('assigned', 'on_way', 'started')")->fetch_assoc()['total'];
    $row['active_jobs'] = $active_jobs;

    $providers[] = $row;
}

echo json_encode(["status" => "success", "data" => $providers]);
$conn->close();
?>