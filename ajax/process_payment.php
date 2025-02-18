<?php
require_once '../config/database.php';
require_once '../includes/functions/transaction_functions.php';

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    if (empty($_POST['order_id'])) {
        throw new Exception('Missing required fields');
    }

    $order_id = filter_var($_POST['order_id'], FILTER_SANITIZE_STRING);

    $db->beginTransaction();

    // First, get order details
    $orderQuery = "SELECT total_amount, payment_method FROM orders WHERE order_id = :order_id";
    $orderStmt = $db->prepare($orderQuery);
    $orderStmt->execute([':order_id' => $order_id]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found');
    }

    // Create transaction record first
    $transactionRef = createOrderTransaction(
        $db, 
        $order_id, 
        $order['total_amount'],
        $order['payment_method'],
        $_SESSION['id']
    );

    // Update order payment status
    $updateQuery = "UPDATE orders SET 
                   payment_status = 'Paid',
                   transaction_id = :transaction_ref
                   WHERE order_id = :order_id";
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([
        ':transaction_ref' => $transactionRef,
        ':order_id' => $order_id
    ]);

    // Insert payment record
    $paymentQuery = "INSERT INTO payments (
        order_id,
        amount,
        payment_method,
        status,
        transaction_id,
        payment_date
    ) VALUES (
        :order_id,
        :amount,
        :payment_method,
        'completed',
        :transaction_id,
        CURRENT_TIMESTAMP
    )";

    $paymentStmt = $db->prepare($paymentQuery);
    $paymentStmt->execute([
        ':order_id' => $order_id,
        ':amount' => $order['total_amount'],
        ':payment_method' => $order['payment_method'],
        ':transaction_id' => $transactionRef
    ]);

    $db->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
