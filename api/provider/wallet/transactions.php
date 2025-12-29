<?php
// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 2. Database Connection
$db_loaded = false;
$possible_paths = [
    __DIR__ . '/../../../db.php', 
    __DIR__ . '/../../db.php', 
    $_SERVER['DOCUMENT_ROOT'] . '/willko-admin-backend/db.php'
];

foreach ($possible_paths as $path) { 
    if (file_exists($path)) { 
        include $path; 
        $db_loaded = true; 
        break; 
    } 
}

if (!$db_loaded) { 
    http_response_code(500); 
    echo json_encode(["status" => "error", "message" => "Database connection failed"]); 
    exit(); 
}

// 3. Auth Helper
function getBearerToken() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) $headers = trim($_SERVER["Authorization"]);
    elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    elseif (function_exists('apache_request_headers')) {
        $req = apache_request_headers();
        $req = array_combine(array_map('ucwords', array_keys($req)), array_values($req));
        if (isset($req['Authorization'])) $headers = trim($req['Authorization']);
    }
    
    if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) return $matches[1];
    return null;
}

$token = getBearerToken();
if (!$token) { 
    http_response_code(401); 
    echo json_encode(["status" => "error", "message" => "Token missing"]); 
    exit(); 
}

// 4. Verify Provider (SECURE WAY)
$stmt = $conn->prepare("SELECT id FROM users WHERE auth_token = ? AND role = 'provider'");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) { 
    http_response_code(401); 
    echo json_encode(["status" => "error", "message" => "Unauthorized: Invalid Token"]); 
    exit(); 
}

$provider_id = $result->fetch_assoc()['id'];

// 5. Fetch Job Income History
$sql = "SELECT id as job_id, service_name, amount, payment_method, booking_date as date 
        FROM bookings 
        WHERE provider_id = ? AND status = 'completed' 
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $provider_id);
$stmt->execute();
$historyResult = $stmt->get_result();

$history = [];
while ($row = $historyResult->fetch_assoc()) {
    $history[] = [
        "type" => "income", 
        "title" => $row['service_name'],
        "amount" => (float)$row['amount'],
        "method" => $row['payment_method'], // Cash / Online
        "date" => date("d M, Y h:i A", strtotime($row['date'])),
        "status" => "success"
    ];
}

echo json_encode(["status" => "success", "data" => $history]);
$conn->close();
?>