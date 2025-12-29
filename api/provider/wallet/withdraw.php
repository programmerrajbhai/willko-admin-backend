<?php
// 1. Error Reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

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
    echo json_encode(["status" => "error", "message" => "Unauthorized: Token missing"]); 
    exit(); 
}

// 4. Verify Provider & Check Balance (SECURE)
$stmt = $conn->prepare("SELECT id, balance FROM users WHERE auth_token = ? AND role = 'provider'");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) { 
    http_response_code(401); 
    echo json_encode(["status" => "error", "message" => "Unauthorized: Invalid Token"]); 
    exit(); 
}

$provider = $res->fetch_assoc();
$provider_id = $provider['id'];
$current_balance = (float)$provider['balance'];

// 5. Input Processing
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->amount) || !isset($data->method) || !isset($data->account_details)) {
    echo json_encode(["status" => "error", "message" => "Missing Info (amount, method, account_details)"]);
    exit();
}

$amount = (float)$data->amount;
$method = htmlspecialchars(trim($data->method)); // Bkash, Nagad, etc.
$account = htmlspecialchars(trim($data->account_details));

// Validation
if ($amount <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid amount"]);
    exit();
}

if ($amount > $current_balance) {
    echo json_encode(["status" => "error", "message" => "Insufficient Balance! Your balance is $current_balance"]);
    exit();
}

// 6. Process Withdrawal (Transaction)
$conn->begin_transaction();

try {
    // ক) উইথড্র রিকোয়েস্ট তৈরি করা
    $insStmt = $conn->prepare("INSERT INTO withdrawals (provider_id, amount, method, account_details, status) VALUES (?, ?, ?, ?, 'pending')");
    $insStmt->bind_param("idss", $provider_id, $amount, $method, $account);
    
    if (!$insStmt->execute()) {
        throw new Exception("Failed to create request");
    }

    // খ) ব্যালেন্স থেকে টাকা কেটে নেওয়া
    $updStmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
    $updStmt->bind_param("di", $amount, $provider_id);
    
    if (!$updStmt->execute()) {
        throw new Exception("Failed to update balance");
    }

    $conn->commit();
    echo json_encode([
        "status" => "success", 
        "message" => "Withdrawal request sent successfully!", 
        "new_balance" => ($current_balance - $amount)
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => "Transaction Failed: " . $e->getMessage()]);
}

$conn->close();
?>