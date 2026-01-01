<?php
// File: api/provider/jobs/my_active_jobs.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include '../../../db.php';

if (!isset($_GET['provider_id'])) {
    echo json_encode(["status" => "error", "message" => "Provider ID missing"]);
    exit();
}

$provider_id = (int)$_GET['provider_id']; // Security check

// ✅ Active jobs list
$sql = "SELECT id, service_name, location, details, amount, booking_date, status, customer_name, customer_phone 
        FROM bookings 
        WHERE provider_id = ? 
        AND status IN ('accepted', 'ongoing', 'working', 'arrived', 'in_progress') 
        ORDER BY booking_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$result = $stmt->get_result();

$active_jobs = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $active_jobs[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $active_jobs]);
} else {
    echo json_encode(["status" => "success", "message" => "No active jobs found", "data" => []]);
}

$stmt->close();
$conn->close();
?>