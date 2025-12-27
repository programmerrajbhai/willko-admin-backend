<?php
// File: wilkoservices/db.php

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "wilkoservices";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

// CORS Headers (Flutter Web থেকে যাতে এরর না আসে)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
header("Content-Type: application/json; charset=UTF-8");
?>