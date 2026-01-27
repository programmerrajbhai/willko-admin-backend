<?php
// File: api/admin/providers/history.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// DB Connect
$current_dir = __DIR__;
$possible_paths = [__DIR__ . '/../../../db.php', __DIR__ . '/../../db.php'];
foreach ($possible_paths as $path) { if (file_exists($path)) { include_once $path; break; } }

// Auth Check...
$headers = getallheaders();
$token = isset($headers['Authorization']) ? trim(str_replace("Bearer ", "", $headers['Authorization'])) : '';
if ($conn->query("SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin'")->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]); exit();
}

$provider_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ১. বেসিক প্রোফাইল তথ্য
$provider = $conn->query("SELECT name, email, phone, address, image, rating, created_at, status FROM users WHERE id = $provider_id")->fetch_assoc();

if (!$provider) { echo json_encode(["status" => "error", "message" => "Provider not found"]); exit(); }

// ২. ইনকাম এবং জব স্ট্যাটাস (Stats)
$statsSql = "SELECT 
    COUNT(*) as total_jobs,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_jobs,
    SUM(CASE WHEN status = 'completed' THEN final_total ELSE 0 END) as total_earnings
    FROM bookings WHERE provider_id = $provider_id";
$stats = $conn->query($statsSql)->fetch_assoc();

// ৩. জব হিস্ট্রি লিস্ট
$jobsSql = "SELECT b.id, b.booking_id_str, b.status, b.schedule_date, b.final_total, 
            u.name as customer_name
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.id
            WHERE b.provider_id = $provider_id 
            ORDER BY b.created_at DESC";
$jobsResult = $conn->query($jobsSql);

$jobs = [];
while ($row = $jobsResult->fetch_assoc()) {
    $jobs[] = [
        "booking_id" => $row['booking_id_str'] ?? "#ORD-".$row['id'],
        "customer" => $row['customer_name'],
        "date" => date("d M, Y", strtotime($row['schedule_date'])),
        "amount" => "SAR " . number_format($row['final_total'], 0),
        "status" => ucfirst($row['status'])
    ];
}

echo json_encode([
    "status" => "success",
    "profile" => $provider,
    "stats" => [
        "total_earnings" => "SAR " . number_format($stats['total_earnings'] ?? 0, 0),
        "total_jobs" => $stats['total_jobs'],
        "completed" => $stats['completed_jobs'],
        "cancelled" => $stats['cancelled_jobs']
    ],
    "history" => $jobs
]);

$conn->close();
?>