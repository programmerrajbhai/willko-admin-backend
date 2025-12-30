<?php
// File: api/provider/jobs/accept_reject.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include '../../../db.php';

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->booking_id) || !isset($data->provider_id) || !isset($data->action)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit();
}

$booking_id = $data->booking_id;
$provider_id = $data->provider_id;
$action = strtolower($data->action);

// স্ট্যাটাস লজিক
if ($action == 'accept') {
    $new_status = 'accepted';
    $msg = "Job Accepted Successfully";
} elseif ($action == 'reject') {
    $new_status = 'rejected'; // অথবা provider_id = NULL করে দিতে পারেন যাতে অন্য কেউ পায়
    $msg = "Job Rejected";
} else {
    echo json_encode(["status" => "error", "message" => "Invalid action"]);
    exit();
}

// আপডেট কুয়েরি
$sql = "UPDATE bookings SET status = '$new_status' WHERE id = '$booking_id' AND provider_id = '$provider_id'";

if ($conn->query($sql) === TRUE) {
    echo json_encode(["status" => "success", "message" => $msg]);
} else {
    echo json_encode(["status" => "error", "message" => "Update failed: " . $conn->error]);
}

$conn->close();
?>