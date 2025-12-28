<?php
// ডিবাগ করার জন্য এই ৩ লাইন যোগ করো
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// ডাটাবেস কানেকশন
include '../../../db.php'; 

// বাকি কোড আগের মতোই...
if (!isset($_GET['provider_id'])) {
    echo json_encode(["status" => "error", "message" => "Provider ID missing"]);
    exit();
}

$provider_id = $_GET['provider_id'];

// কুয়েরি রান করা
$sql = "SELECT id, name, is_online, rating, balance FROM providers WHERE id = '$provider_id'";
$result = $conn->query($sql);

if (!$result) {
    // যদি কুয়েরিতে ভুল থাকে, তাহলে এখানে এরর দেখাবে
    die(json_encode(["status" => "error", "message" => "Query Failed: " . $conn->error]));
}

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    $dashboard_data = [
        "status" => "success",
        "provider_name" => $row['name'],
        "is_online" => $row['is_online'],
        "rating" => $row['rating'],
        "current_balance" => $row['balance'],
        "today_income" => 500, 
        "total_jobs" => 12
    ];

    echo json_encode($dashboard_data);
} else {
    echo json_encode(["status" => "error", "message" => "Provider not found"]);
}

$conn->close();
?>