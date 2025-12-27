<?php
// Security Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 1. Database Connection (Universal Fix)
$current_dir = __DIR__;
$api_dir = dirname(dirname($current_dir)); // api folder
$path_in_api = $api_dir . '/db.php';
$path_in_root = dirname($api_dir) . '/db.php';

if (file_exists($path_in_api)) include $path_in_api;
elseif (file_exists($path_in_root)) include $path_in_root;
else die(json_encode(["status" => "error", "message" => "Database connection failed!"]));

// 2. Authentication Function (Strict Check)
function checkAuth($conn) {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    // Clean 'Bearer ' prefix
    if (stripos($token, 'Bearer ') === 0) {
        $token = substr($token, 7);
    }

    if (empty($token)) {
        echo json_encode(["status" => "error", "message" => "Authorization Token Missing!"]);
        exit();
    }

    $token = $conn->real_escape_string($token);
    // Check if user exists and is admin
    $sql = "SELECT id FROM users WHERE auth_token = '$token' AND role = 'admin' AND status = 'active'";
    $result = $conn->query($sql);

    if ($result->num_rows == 0) {
        echo json_encode(["status" => "error", "message" => "Unauthorized Access! Invalid Token."]);
        exit();
    }
}

// 3. Run Auth Check
checkAuth($conn);

// 4. Fetch Data
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => "error", "message" => "Only GET method allowed"]);
    exit();
}

$sql = "SELECT * FROM categories ORDER BY id DESC";
$result = $conn->query($sql);

$categories = [];
while ($row = $result->fetch_assoc()) {
    // Full Image URL creation
    if (!empty($row['image'])) {
        // Adjust this base URL according to your live server or localhost
        $row['image_url'] = "http://" . $_SERVER['HTTP_HOST'] . "/WillkoServiceApi/api/uploads/" . $row['image'];
    } else {
        $row['image_url'] = null;
    }
    $categories[] = $row;
}

echo json_encode(["status" => "success", "data" => $categories]);
$conn->close();
?>