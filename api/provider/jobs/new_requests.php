<?php
// File: api/provider/jobs/new_requests.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include '../../../db.php';

if (!isset($_GET['provider_id'])) {
    echo json_encode(["status" => "error", "message" => "Provider ID missing"]);
    exit();
}

$provider_id = (int)$_GET['provider_id']; // ID কে ইন্টিজারে কনভার্ট করা হলো (Security)

// শুধুমাত্র 'pending' কাজগুলো দেখাবে
$sql = "SELECT id, service_name, location, details, amount, booking_date 
        FROM bookings 
        WHERE provider_id = ? AND status = 'pending'
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$result = $stmt->get_result();

$jobs = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $jobs]);
} else {
    echo json_encode(["status" => "success", "message" => "No new job requests", "data" => []]);
}

$stmt->close();
$conn->close();
?>