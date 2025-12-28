<?php
// Headers to force download CSV
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=orders_report.csv");

// DB Connection only (No Auth needed for download usually, or pass token in URL)
$current_dir = __DIR__;
$api_dir = dirname(dirname(dirname($current_dir))); 
if (file_exists($api_dir . '/db.php')) include $api_dir . '/db.php';
else include dirname($api_dir) . '/db.php';

// Output stream খুলছি
$output = fopen('php://output', 'w');

// ১. কলাম হেডার (Column Headers)
fputcsv($output, array('Order ID', 'Customer Name', 'Service', 'Provider', 'Total Price', 'Status', 'Date'));

// ২. ডাটা আনা (Fetch Data)
$sql = "SELECT o.id, u.name as customer, s.name as service, p.name as provider, o.total_price, o.status, o.created_at
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN users p ON o.provider_id = p.id
        LEFT JOIN services s ON o.service_id = s.id
        ORDER BY o.id DESC";

$result = $conn->query($sql);

// ৩. লুপ চালিয়ে CSV তে লেখা
while ($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
$conn->close();
?>