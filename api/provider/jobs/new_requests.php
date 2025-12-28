<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include '../../../db.php';

if (!isset($_GET['provider_id'])) {
    echo json_encode(["status" => "error", "message" => "Provider ID missing"]);
    exit();
}

$provider_id = $_GET['provider_id'];

// শুধুমাত্র 'pending' রিকোয়েস্টগুলো দেখাবে
$sql = "SELECT id, service_name, location, details, amount, booking_date 
        FROM bookings 
        WHERE provider_id = '$provider_id' AND status = 'pending' 
        ORDER BY id DESC";

$result = $conn->query($sql);

$jobs = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $jobs]);
} else {
    echo json_encode(["status" => "success", "message" => "No new job requests", "data" => []]);
}

$conn->close();
?>