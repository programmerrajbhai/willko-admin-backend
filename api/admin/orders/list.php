<?php
// File: api/admin/orders/list.php

// 1. Error Reporting (ডেভেলপমেন্টের জন্য)
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// ==============================================
// ✅ 2. ROBUST DATABASE CONNECTION
// ==============================================
$current_dir = __DIR__;
$possible_paths = [
    __DIR__ . '/../../../db.php', // রুট ফোল্ডারে থাকলে
    __DIR__ . '/../../db.php',    // api ফোল্ডারে থাকলে
];

$db_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        include_once $path;
        $db_loaded = true;
        break;
    }
}

if (!$db_loaded) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed: db.php not found"]);
    exit();
}

// ==============================================
// 3. Fetch Orders Logic
// ==============================================

$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// SQL Query Construction
// ইউজারের নাম, ফোন এবং প্রোভাইডারের নাম সহ অর্ডার লিস্ট আনা হচ্ছে
$sql = "SELECT b.id, 
               b.final_total, 
               b.schedule_date, 
               b.schedule_time, 
               b.status, 
               b.created_at,
               b.payment_status,
               u.name as customer_name, 
               u.phone as customer_phone,
               p.name as provider_name
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN providers p ON b.provider_id = p.id";

// Filter by Status (যদি নির্দিষ্ট স্ট্যাটাস চাওয়া হয়)
if ($status != 'all') {
    $status = $conn->real_escape_string($status);
    $sql .= " WHERE b.status = '$status'";
}

$sql .= " ORDER BY b.created_at DESC";

$result = $conn->query($sql);

$orders = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        
        // Data Formatting for UI
        // ১. বুকিং আইডি ফরম্যাট (#ORD-00XX)
        $booking_id_formatted = "#ORD-" . str_pad($row['id'], 4, '0', STR_PAD_LEFT);
        
        // ২. তারিখ ফরম্যাট (25 Jan, 2026)
        $date_formatted = date("d M, Y", strtotime($row['schedule_date']));
        
        // ৩. টাকার ফরম্যাট (SAR 1,200)
        $price_formatted = "SAR " . number_format($row['final_total'], 0);
        
        // ৪. স্ট্যাটাস সুন্দর করা (pending -> Pending)
        $status_formatted = ucfirst($row['status']);
        
        // ৫. প্রোভাইডার চেক
        $provider_name = !empty($row['provider_name']) ? $row['provider_name'] : "Not Assigned";

        $orders[] = [
            "id" => $row['id'],
            "booking_id" => $booking_id_formatted,
            "customer_name" => $row['customer_name'] ?? "Unknown",
            "customer_phone" => $row['customer_phone'] ?? "-",
            "provider_name" => $provider_name,
            "schedule_date" => $date_formatted,
            "schedule_time" => $row['schedule_time'],
            "price" => $price_formatted,
            "status" => $status_formatted,
            "raw_status" => $row['status'], // কালার কোডিংয়ের জন্য
            "payment_status" => ucfirst($row['payment_status']),
            "created_at" => $row['created_at']
        ];
    }
}

// JSON Response
echo json_encode([
    "status" => "success",
    "count" => count($orders),
    "data" => $orders
]);

$conn->close();
?>