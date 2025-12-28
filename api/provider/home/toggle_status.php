<?php
// File: api/provider/home/toggle_status.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include '../../../db.php';

// ১. ইনপুট নেওয়া
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->provider_id) || !isset($data->status)) {
    echo json_encode(["status" => "error", "message" => "Incomplete data"]);
    exit();
}

$provider_id = $data->provider_id;
$new_status = $data->status; // 1 for Online, 0 for Offline

// ২. স্ট্যাটাস আপডেট করা
$sql = "UPDATE providers SET is_online = '$new_status' WHERE id = '$provider_id'";

if ($conn->query($sql) === TRUE) {
    // সফল হলে রেসপন্স
    $status_text = ($new_status == 1) ? "Online" : "Offline";
    echo json_encode([
        "status" => "success",
        "message" => "Status changed to " . $status_text,
        "is_online" => $new_status
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Update failed: " . $conn->error]);
}

$conn->close();
?>