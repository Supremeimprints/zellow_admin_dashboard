<?php
session_start();
require_once '../config/database.php';
require_once '../includes/classes/TransactionHistory.php';

// Authentication check
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => true, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['error' => true, 'message' => 'Transaction ID is required']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$transactionHistory = new TransactionHistory($db);

$transaction = $transactionHistory->getTransactionDetails($_GET['id']);

if (!$transaction) {
    echo json_encode(['error' => true, 'message' => 'Transaction not found']);
    exit();
}

// Build HTML for transaction details
$html = <<<HTML
<div class="transaction-details">
    <div class="row">
        <div class="col-md-6">
            <h6>Transaction Information</h6>
            <table class="table table-sm">
                <tr>
                    <th>Transaction ID:</th>
                    <td>{$transaction['transaction_reference']}</td>
                </tr>
                <tr>
                    <th>Date:</th>
                    <td>{$transaction['transaction_date']}</td>
                </tr>
                <tr>
                    <th>Amount:</th>
                    <td>Ksh {$transaction['amount_paid']}</td>
                </tr>
                <tr>
                    <th>Status:</th>
                    <td><span class="badge bg-{$transaction['transaction_status']}">{$transaction['transaction_status']}</span></td>
                </tr>
            </table>
        </div>
        <div class="col-md-6">
            <h6>Customer Information</h6>
            <table class="table table-sm">
                <tr>
                    <th>Name:</th>
                    <td>{$transaction['customer_name']}</td>
                </tr>
                <tr>
                    <th>Email:</th>
                    <td>{$transaction['customer_email']}</td>
                </tr>
            </table>
        </div>
    </div>
</div>
HTML;

echo json_encode(['error' => false, 'html' => $html]);
