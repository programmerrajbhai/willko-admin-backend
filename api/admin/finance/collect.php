<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once "../../config/database.php"; // আপনার ডাটাবেস কানেকশন পাথ অনুযায়ী

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->provider_id) && !empty($data->amount) && !empty($data->admin_id)) {
    
    $provider_id = $data->provider_id;
    $amount = $data->amount; // কত টাকা কালেক্ট করা হলো
    $admin_id = $data->admin_id;

    try {
        $db->beginTransaction();

        // 1. Update Provider Balance (টাকা দিলে Due কমবে, তাই ব্যালেন্স বাড়বে অথবা Due ফিল্ড কমবে)
        // ধরুন: positive balance মানে প্রোভাইডার পাবে, negative মানে সে ঋণী।
        // তাই টাকা দিলে ব্যালেন্স বাড়বে (Example: -500 + 500 = 0)
        
        $query = "UPDATE providers SET balance = balance + :amount WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':id', $provider_id);
        
        if(!$stmt->execute()){
            throw new Exception("Provider balance update failed.");
        }

        // 2. Insert Transaction Record (রেকর্ড রাখা জরুরি)
        $txn_query = "INSERT INTO transactions (user_id, type, amount, description, created_at, created_by) 
                      VALUES (:uid, 'cash_collection', :amt, 'Cash collected by Admin', NOW(), :admin)";
        $txn_stmt = $db->prepare($txn_query);
        $txn_stmt->bindParam(':uid', $provider_id);
        $txn_stmt->bindParam(':amt', $amount);
        $txn_stmt->bindParam(':admin', $admin_id);

        if(!$txn_stmt->execute()){
             throw new Exception("Transaction log failed.");
        }

        $db->commit();
        
        echo json_encode(["status" => true, "message" => "Cash collected successfully!"]);

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => false, "message" => "Incomplete data."]);
}
?>