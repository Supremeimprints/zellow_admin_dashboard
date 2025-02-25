<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function setupMailer() {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = SMTP_DEBUG;
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Additional SMTP settings for Gmail
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        return $mail;
    } catch (Exception $e) {
        error_log("Mailer setup failed: " . $e->getMessage());
        throw new Exception("Email configuration error: " . $e->getMessage());
    }
}

function sendPurchaseOrderEmail($db, $supplier_id, $purchase_order_id, $invoice_number, $total_amount, $orderProducts) {
    try {
        $mail = setupMailer();
        
        // Get supplier details
        $supplierStmt = $db->prepare("
            SELECT company_name, email, contact_person 
            FROM suppliers 
            WHERE supplier_id = ?
        ");
        $supplierStmt->execute([$supplier_id]);
        $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);

        if (!$supplier || empty($supplier['email'])) {
            throw new Exception("Invalid supplier email");
        }

        // Email setup
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($supplier['email'], $supplier['company_name']);
        
        // Add CC to admin if available
        if (isset($_SESSION['email'])) {
            $mail->addCC($_SESSION['email']);
        }

        $mail->isHTML(true);
        $mail->Subject = "New Purchase Order #$purchase_order_id - $invoice_number";
        
        // Generate email body
        $mail->Body = generatePurchaseOrderEmailBody(
            $purchase_order_id, 
            $invoice_number, 
            $orderProducts, 
            $total_amount
        );

        // Plain text version
        $mail->AltBody = generatePurchaseOrderPlainText(
            $purchase_order_id, 
            $invoice_number, 
            $total_amount
        );

        $sent = $mail->send();
        if (!$sent) {
            throw new Exception($mail->ErrorInfo);
        }

        error_log("Purchase order email sent successfully to: " . $supplier['email']);
        return true;

    } catch (Exception $e) {
        error_log("Purchase order email sending failed: " . $e->getMessage());
        throw new Exception("Failed to send email: " . $e->getMessage());
    }
}

function generatePurchaseOrderEmailBody($purchase_order_id, $invoice_number, $orderProducts, $total_amount) {
    $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #333; border-bottom: 2px solid #ddd; padding-bottom: 10px;'>Purchase Order Details</h2>
            
            <div style='background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 5px;'>
                <h3 style='color: #444; margin-top: 0;'>Order Information</h3>
                <p><strong>Purchase Order:</strong> #$purchase_order_id</p>
                <p><strong>Invoice Number:</strong> $invoice_number</p>
                <p><strong>Order Date:</strong> " . date('Y-m-d') . "</p>
                <p><strong>Due Date:</strong> " . date('Y-m-d', strtotime('+30 days')) . "</p>
            </div>

            <div style='margin: 20px 0;'>
                <h3 style='color: #444;'>Order Items</h3>
                <table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>
                    <thead>
                        <tr style='background: #eee;'>
                            <th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Product</th>
                            <th style='padding: 10px; text-align: right; border: 1px solid #ddd;'>Quantity</th>
                            <th style='padding: 10px; text-align: right; border: 1px solid #ddd;'>Unit Price</th>
                            <th style='padding: 10px; text-align: right; border: 1px solid #ddd;'>Total</th>
                        </tr>
                    </thead>
                    <tbody>";

    foreach ($orderProducts as $product) {
        $itemTotal = $product['quantity'] * $product['unit_price'];
        $body .= "
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($product['product_name']) . "</td>
                <td style='padding: 10px; text-align: right; border: 1px solid #ddd;'>" . number_format($product['quantity']) . "</td>
                <td style='padding: 10px; text-align: right; border: 1px solid #ddd;'>Ksh. " . number_format($product['unit_price'], 2) . "</td>
                <td style='padding: 10px; text-align: right; border: 1px solid #ddd;'>Ksh. " . number_format($itemTotal, 2) . "</td>
            </tr>";
    }

    $body .= "
                    <tr style='background: #f5f5f5;'>
                        <td colspan='3' style='padding: 10px; text-align: right; border: 1px solid #ddd;'><strong>Total Amount:</strong></td>
                        <td style='padding: 10px; text-align: right; border: 1px solid #ddd;'><strong>Ksh. " . number_format($total_amount, 2) . "</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div style='margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px;'>
            <p style='margin: 0;'><strong>Please Note:</strong></p>
            <ul style='margin: 10px 0;'>
                <li>Payment is due within 30 days</li>
                <li>Please reference the invoice number in all communications</li>
                <li>For any queries, please contact our purchasing department</li>
            </ul>
        </div>

        <div style='margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;'>
            <p>This is an automated message from Zellow Enterprises. Please do not reply directly to this email.</p>
        </div>
    </div>";

    return $body;
}

function generatePurchaseOrderPlainText($purchase_order_id, $invoice_number, $total_amount) {
    return "Purchase Order #$purchase_order_id\n"
         . "Invoice Number: $invoice_number\n"
         . "Total Amount: Ksh. " . number_format($total_amount, 2) . "\n"
         . "Due Date: " . date('Y-m-d', strtotime('+30 days')) . "\n\n"
         . "Please log in to your supplier portal for more details.";
}
