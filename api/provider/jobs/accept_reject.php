<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. DB Connection (Dynamic Path Check)
$current_dir = __DIR__;
if (file_exists(dirname(dirname(dirname(__DIR__))) . '/db.php')) {
    include dirname(dirname(dirname(__DIR__))) . '/db.php';
} else {
    include '../../../db.php';
}

// 2. ইনপুট ডাটা নেওয়া
$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['booking_id']) || empty($data['provider_id']) || empty($data['action'])) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit();
}

$booking_id = (int)$data['booking_id']; // Security check
$provider_id = (int)$data['provider_id']; // Security check
$action = strtolower(trim($data['action']));

// 3. স্ট্যাটাস লজিক
if ($action === 'accept') {
    $new_status = 'accepted'; // অথবা 'on_going' যদি আপনার ফ্লোতে থাকে
    $msg = "Job Accepted Successfully";
} elseif ($action === 'reject') {
    $new_status = 'cancelled'; // রিজেক্ট করলে ক্যানসেল অথবা রিজেক্টেড
    $msg = "Job Rejected";
} else {
    echo json_encode(["status" => "error", "message" => "Invalid action. Use 'accept' or 'reject'."]);
    exit();
}

// 4. Secure Update Query (Prepared Statement)
// চেক করা হচ্ছে যেন এই প্রোভাইডারই এই জবটি এক্সেপ্ট/রিজেক্ট করতে পারে
$sql = "UPDATE bookings SET status = ? WHERE id = ? AND provider_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $new_status, $booking_id, $provider_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // সফল হলে
        echo json_encode(["status" => "success", "message" => $msg]);
        
        // (Optional) আপনি চাইলে এখানে অ্যাডমিনের কাছে নোটিফিকেশন পাঠানোর লজিক যোগ করতে পারেন
    } else {
        echo json_encode(["status" => "error", "message" => "Job not found or already updated"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>