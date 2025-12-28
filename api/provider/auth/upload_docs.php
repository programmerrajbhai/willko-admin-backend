<?php
// 1. Error Reporting অন করা
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 2. Database কানেকশন ফাইল খোঁজা (Smart Path Finding)
$current_dir = __DIR__; 
$possible_paths = [
    __DIR__ . '/../../db.php',       
    __DIR__ . '/../../../db.php',    
];

$db_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        include $path;
        $db_loaded = true;
        break;
    }
}

if (!$db_loaded) {
    die(json_encode(["status" => "error", "message" => "Database file (db.php) not found!"]));
}

// 3. getallheaders() সাপোর্ট না করলে তার বিকল্প (Polyfill)
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// 4. টোকেন ভেরিফিকেশন
$headers = getallheaders();
$token = isset($headers['Authorization']) ? $headers['Authorization'] : '';

// Bearer প্রিফিক্স থাকলে রিমুভ করা
if (stripos($token, 'Bearer ') === 0) {
    $token = substr($token, 7);
}

if (empty($token)) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access! Token missing."]);
    exit();
}

// 5. ফাইল আপলোড লজিক
if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {
        
        // uploads ফোল্ডারের ডাইনামিক পাথ (API ফোল্ডারের বাইরে uploads ফোল্ডার)
        // ধরছি uploads ফোল্ডারটি 'api' ফোল্ডারের প্যারালাল বা ভেতরে আছে
        // এখানে আমরা সেফটির জন্য __DIR__ ব্যবহার করছি
        $upload_dir = dirname(dirname(dirname(__DIR__))) . '/uploads/'; 
        // যদি আপনার uploads ফোল্ডার 'api/uploads' এ থাকে, তবে: dirname(dirname(__DIR__)) . '/uploads/';
        
        // ফোল্ডার না থাকলে তৈরি করা
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $fileName = "doc_" . time() . "_" . bin2hex(random_bytes(5)) . "." . $ext;
        $target_file = $upload_dir . $fileName;
        
        if (move_uploaded_file($_FILES["document"]["tmp_name"], $target_file)) {
            
            // ডাটাবেস আপডেট (Prepared Statement ব্যবহার করে)
            $stmt = $conn->prepare("UPDATE users SET document_image = ? WHERE auth_token = ? AND role = 'provider'");
            $stmt->bind_param("ss", $fileName, $token);

            if ($stmt->execute()) {
                // চেক করা আপডেট হয়েছে কিনা
                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        "status" => "success", 
                        "message" => "Document uploaded successfully", 
                        "url" => $fileName
                    ]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Invalid Token or Provider Not Found"]);
                }
            } else {
                echo json_encode(["status" => "error", "message" => "Database Update Failed: " . $stmt->error]);
            }
            $stmt->close();

        } else {
            echo json_encode(["status" => "error", "message" => "File upload failed! Check folder permission."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid file format. Only JPG, PNG, PDF allowed"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "No file selected"]);
}
$conn->close();
?>