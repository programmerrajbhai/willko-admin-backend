<?php
// File: api/admin/providers/view.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 1. Database Connection
$possible_paths = [__DIR__ . '/../../../db.php', __DIR__ . '/../../db.php'];
$db_loaded = false;
foreach ($possible_paths as $path) { 
    if (file_exists($path)) { include_once $path; $db_loaded = true; break; } 
}
if (!$db_loaded) { echo json_encode(["status" => "error", "message" => "Database connection failed"]); exit(); }

// 2. Auth Check
$headers = getallheaders();
$token = isset($headers['Authorization']) ? trim(str_replace("Bearer ", "", $headers['Authorization'])) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]); exit();
}

// 3. Validate ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id == 0) { echo json_encode(["status" => "error", "message" => "Provider ID required"]); exit(); }

// 4. Fetch Provider Info
$sqlInfo = "SELECT id, name, email, phone, address, image, rating, status, created_at, latitude, longitude 
            FROM users WHERE id = $id AND role = 'provider'";
$resInfo = $conn->query($sqlInfo);

if ($resInfo->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Provider not found"]);
    exit();
}

$info = $resInfo->fetch_assoc();

// Image URL Formatting
$info['image_url'] = !empty($info['image']) ? "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/" . $info['image'] : "";

// 5. Fetch Stats (Earnings & Job Counts)
$sqlStats = "SELECT 
    COUNT(*) as total_jobs,
    COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as completed,
    COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled,
    COALESCE(SUM(CASE WHEN status = 'assigned' OR status = 'on_way' OR status = 'started' THEN 1 ELSE 0 END), 0) as active,
    COALESCE(SUM(CASE WHEN status = 'completed' THEN final_total ELSE 0 END), 0) as total_earnings
    FROM bookings WHERE provider_id = $id";

$stats = $conn->query($sqlStats)->fetch_assoc();

// 6. Fetch Recent Jobs (Limit 10)
// 🔴 FIX: Removed 'b.booking_id_str' from SELECT query to prevent Fatal Error
$sqlJobs = "SELECT b.id, b.schedule_date, b.final_total, b.status, u.name as customer_name
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.id
            WHERE b.provider_id = $id 
            ORDER BY b.created_at DESC LIMIT 10";

$resJobs = $conn->query($sqlJobs);
$jobs = [];

if ($resJobs) {
    while ($row = $resJobs->fetch_assoc()) {
        // ✅ FIX: Generating Booking ID manually in PHP
        $generated_booking_id = "#ORD-" . str_pad($row['id'], 4, '0', STR_PAD_LEFT);

        $jobs[] = [
            "booking_id" => $generated_booking_id,
            "customer" => $row['customer_name'] ?? "Guest",
            "date" => date("d M, Y", strtotime($row['schedule_date'])),
            "amount" => "SAR " . number_format($row['final_total'], 0),
            "status" => ucfirst($row['status']),
            "color_code" => ($row['status'] == 'completed') ? 'green' : (($row['status'] == 'cancelled') ? 'red' : 'blue')
        ];
    }
}

// 7. Final Response
echo json_encode([
    "status" => "success",
    "data" => [
        "info" => $info,
        "stats" => [
            "earnings" => "SAR " . number_format($stats['total_earnings'], 0),
            "total" => (string)$stats['total_jobs'],
            "completed" => (string)$stats['completed'],
            "cancelled" => (string)$stats['cancelled'],
            "active" => (string)$stats['active']
        ],
        "recent_jobs" => $jobs
    ]
]);

$conn->close();
?>