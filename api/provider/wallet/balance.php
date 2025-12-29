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
    echo json_encode(["status" => "error", "message" => "Unauthorized"]); 
    exit(); 
}

// 4. Get Provider Data
$stmt = $conn->prepare("SELECT id, balance, current_due FROM users WHERE auth_token = ? AND role = 'provider'");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $provider = $result->fetch_assoc();
    $provider_id = $provider['id'];

    // 5. Calculate Total Earnings & Withdrawals
    // ✅ ফিক্স: 'total_price' এর বদলে 'amount' ব্যবহার করা হয়েছে
    $earningsSql = "SELECT SUM(amount) as total FROM bookings WHERE provider_id = $provider_id AND status = 'completed'";
    $earningsResult = $conn->query($earningsSql);
    $total_earned = ($earningsResult && $earningsResult->num_rows > 0) ? ($earningsResult->fetch_assoc()['total'] ?? 0) : 0;

    // মোট উত্তোলন (Approved Withdrawals)
    $withdrawSql = "SELECT SUM(amount) as total FROM withdrawals WHERE provider_id = $provider_id AND status = 'approved'";
    $withdrawResult = $conn->query($withdrawSql);
    $total_withdrawn = ($withdrawResult && $withdrawResult->num_rows > 0) ? ($withdrawResult->fetch_assoc()['total'] ?? 0) : 0;

    echo json_encode([
        "status" => "success",
        "data" => [
            "current_balance" => (float)$provider['balance'], // বর্তমানে ওয়ালেটে কত আছে
            "current_due" => (float)$provider['current_due'], // অ্যাডমিন কত পাবে
            "total_earned" => (float)$total_earned, // লাইফটাইম ইনকাম
            "total_withdrawn" => (float)$total_withdrawn // মোট তোলা হয়েছে
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid Token"]);
}
$conn->close();
?>