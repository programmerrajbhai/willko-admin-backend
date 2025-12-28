<?php
include '../../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Only POST method allowed"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['email']) || empty($data['password'])) {
    echo json_encode(["status" => "error", "message" => "Email and Password required"]);
    exit();
}

$email = $conn->real_escape_string($data['email']);
$password = $data['password'];

$sql = "SELECT id, name, email, password, role, status FROM users WHERE email = '$email'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    // পাসওয়ার্ড চেক (সিম্পল)
    if ($password == $user['password']) {
        
        if($user['status'] == 'banned') {
            echo json_encode(["status" => "error", "message" => "Account is Banned"]);
            exit();
        }

        // === টোকেন জেনারেট এবং সেভ ===
        $token = bin2hex(random_bytes(32)); // নতুন র‍্যান্ডম টোকেন
        $user_id = $user['id'];
        
        $conn->query("UPDATE users SET auth_token = '$token' WHERE id = '$user_id'");

        echo json_encode([
            "status" => "success",
            "message" => "Login Successful",
            "data" => [
                "id" => $user['id'],
                "name" => $user['name'],
                "role" => $user['role'],
                "token" => $token // এই টোকেনটি অ্যাপে সেভ রাখতে হবে
            ]
        ]);

    } else {
        echo json_encode(["status" => "error", "message" => "Wrong password!"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "User not found!"]);
}
$conn->close();
?>