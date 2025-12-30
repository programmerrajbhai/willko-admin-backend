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

$provider_id = $_GET['provider_id'];

// ✅ FIX: 'pending' ছাড়া বাকি সব Active স্ট্যাটাস এখানে আসবে
$sql = "SELECT id, service_name, location, details, amount, booking_date, status, customer_name, customer_phone 
        FROM bookings 
        WHERE provider_id = '$provider_id' 
        AND status IN ('accepted', 'ongoing', 'working', 'arrived', 'in_progress') 
        ORDER BY booking_date DESC";

$result = $conn->query($sql);

$active_jobs = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $active_jobs[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $active_jobs]);
} else {
    echo json_encode(["status" => "success", "message" => "No active jobs found", "data" => []]);
}

$conn->close();
?>