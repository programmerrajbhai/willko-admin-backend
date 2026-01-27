<?php
// File: api/admin/providers/history.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 1. Database Connection
$possible_paths = [__DIR__ . '/../../../db.php', __DIR__ . '/../../db.php'];
$db_loaded = false;
foreach ($possible_paths as $path) { if (file_exists($path)) { include_once $path; $db_loaded = true; break; } }
if (!$db_loaded) { echo json_encode(["status" => "error", "message" => "Database connection failed"]); exit(); }

// 2. Auth Check
$headers = getallheaders();
$token = isset($headers['Authorization']) ? trim(str_replace("Bearer ", "", $headers['Authorization'])) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]); exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id == 0) { echo json_encode(["status" => "error", "message" => "ID required"]); exit(); }

// 3. Get Profile Data
$profile = $conn->query("SELECT name, email, phone, address, image, rating, status FROM users WHERE id = $id")->fetch_assoc();

if (!$profile) { echo json_encode(["status" => "error", "message" => "Provider not found"]); exit(); }

// Image URL Generation (Key: image_url)
$profile['image_url'] = "";
if (!empty($profile['image'])) {
    $profile['image_url'] = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/" . $profile['image'];
}

// 4. Get Statistics (Earnings & Jobs)
// COALESCE ensures we get 0 instead of NULL
$statsSql = "SELECT 
    COUNT(*) as total_jobs,
    COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as completed_jobs,
    COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_jobs,
    COALESCE(SUM(CASE WHEN status = 'completed' THEN final_total ELSE 0 END), 0) as total_earnings
    FROM bookings WHERE provider_id = $id";

$stats = $conn->query($statsSql)->fetch_assoc();

// 5. Get Job History (Last 20 Jobs)
$history = [];
$historySql = "SELECT b.id, b.booking_id_str, b.schedule_date, b.final_total, b.status, u.name as customer_name
               FROM bookings b
               LEFT JOIN users u ON b.user_id = u.id
               WHERE b.provider_id = $id 
               ORDER BY b.created_at DESC LIMIT 20";
$hResult = $conn->query($historySql);

while ($row = $hResult->fetch_assoc()) {
    $history[] = [
        "booking_id" => $row['booking_id_str'] ?? "#ORD-" . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
        "customer" => $row['customer_name'] ?? "Unknown",
        "date" => date("d M, Y", strtotime($row['schedule_date'])),
        "amount" => "SAR " . number_format($row['final_total'], 0),
        "status" => ucfirst($row['status'])
    ];
}

// 6. JSON Response
echo json_encode([
    "status" => "success",
    "data" => [
        "profile" => $profile,
        "stats" => [
            "total_earnings" => "SAR " . number_format($stats['total_earnings'], 0),
            "total_jobs" => (string)$stats['total_jobs'],
            "completed_jobs" => (string)$stats['completed_jobs'],
            "cancelled_jobs" => (string)$stats['cancelled_jobs']
        ],
        "history" => $history
    ]
]);

$conn->close();
?>