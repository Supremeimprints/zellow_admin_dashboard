<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function setupMailer() {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings with fallback values
        $mail->SMTPDebug = defined('SMTP_DEBUG') ? SMTP_DEBUG : 0;
        $mail->isSMTP();
        $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
        $mail->SMTPAuth = defined('SMTP_AUTH') ? SMTP_AUTH : true;
        $mail->Username = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
        $mail->Password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
        $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
        
        // SSL/TLS Settings for development
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => defined('SMTP_VERIFY_PEER') ? SMTP_VERIFY_PEER : false,
                'verify_peer_name' => defined('SMTP_VERIFY_PEER') ? SMTP_VERIFY_PEER : false,
                'allow_self_signed' => defined('SMTP_VERIFY_PEER') ? !SMTP_VERIFY_PEER : true
            )
        );

        // Default sender with fallback values
        $from_email = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : SMTP_USERNAME;
        $from_name = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Zellow Admin';
        $mail->setFrom($from_email, $from_name);
        
        return $mail;
    } catch (Exception $e) {
        throw new Exception("Mailer setup failed: " . $e->getMessage());
    }
}

function sendPurchaseOrderEmail(
    PDO $db,
    int $supplier_id,
    int $purchase_order_id,
    float $total_amount,
    array $orderProducts,
    string $invoice_number  // Changed parameter name from transaction_id
): bool {
    if (empty($orderProducts)) {
        throw new Exception('Order products array cannot be empty');
    }
    
    try {
        // Get supplier details
        $stmt = $db->prepare("SELECT email, company_name FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$supplier_id]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$supplier) {
            throw new Exception("Supplier not found");
        }

        $mail = setupMailer();
        
        $mail->addAddress($supplier['email'], $supplier['company_name']);
        $mail->isHTML(true);
        $mail->Subject = "Purchase Order #$purchase_order_id - $invoice_number";
        
        // Use the styled email template instead of basic HTML
        $mail->Body = generatePurchaseOrderEmailBody($purchase_order_id, $invoice_number, $orderProducts, $total_amount);
        $mail->AltBody = generatePurchaseOrderPlainText($purchase_order_id, $invoice_number, $total_amount);
        
        if (!$mail->send()) {
            throw new Exception("Email could not be sent: " . $mail->ErrorInfo);
        }
        
        return true;
    } catch (Exception $e) {
        throw new Exception("Failed to send email: " . $e->getMessage());
    }
}

function generatePurchaseOrderEmailBody($purchase_order_id, $invoice_number, $orderProducts, $total_amount) {
    $body = "
        <div style='font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto; background: #f8f9fa; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);'>
            <!-- Header -->
            <div style='background: #2c3e50; color: white; text-align: center; padding: 25px; border-radius: 10px 10px 0 0;'>
                <h1 style='margin: 0; font-size: 28px; font-weight: 600;'>PURCHASE ORDER</h1>
                <p style='margin: 5px 0 0; font-size: 16px; opacity: 0.9;'>Zellow Enterprises</p>
            </div>

            <!-- Order Summary Box -->
            <div style='background: white; margin: 20px; border-radius: 8px; padding: 20px; border-left: 5px solid #3498db;'>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 8px 0;'><strong style='color: #2c3e50; font-size: 16px;'>Purchase Order:</strong></td>
                        <td style='padding: 8px 0; text-align: right;'><span style='font-size: 16px; color: #3498db; font-weight: 600;'>#$purchase_order_id</span></td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0;'><strong style='color: #2c3e50; font-size: 16px;'>Invoice Number:</strong></td>
                        <td style='padding: 8px 0; text-align: right;'><span style='font-size: 16px;'>$invoice_number</span></td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0;'><strong style='color: #2c3e50; font-size: 16px;'>Order Date:</strong></td>
                        <td style='padding: 8px 0; text-align: right;'><span style='font-size: 16px;'>" . date('F j, Y') . "</span></td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0;'><strong style='color: #2c3e50; font-size: 16px;'>Delivery Date:</strong></td>
                        <td style='padding: 8px 0; text-align: right;'><span style='font-size: 16px;'>" . date('F j, Y', strtotime('+30 days')) . "</span></td>
                    </tr>
                </table>
            </div>

            <!-- Order Items -->
            <div style='background: white; margin: 20px; border-radius: 8px; padding: 20px;'>
                <h2 style='color: #2c3e50; margin-top: 0; border-bottom: 2px solid #f1f1f1; padding-bottom: 10px;'>Order Items</h2>
                <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                    <thead>
                        <tr>
                            <th style='padding: 12px 15px; background: #3498db; color: white; text-align: left; border-radius: 5px 0 0 0;'>Product</th>
                            <th style='padding: 12px 15px; background: #3498db; color: white; text-align: center;'>Quantity</th>
                            <th style='padding: 12px 15px; background: #3498db; color: white; text-align: right;'>Unit Price</th>
                            <th style='padding: 12px 15px; background: #3498db; color: white; text-align: right; border-radius: 0 5px 0 0;'>Total</th>
                        </tr>
                    </thead>
                    <tbody>";

    $row_count = 0;
    foreach ($orderProducts as $product) {
        $row_style = $row_count % 2 == 0 ? 'background: #f8f9fa;' : 'background: #ffffff;';
        $row_count++;
        
        $itemTotal = $product['quantity'] * $product['unit_price'];
        $body .= "
                        <tr style='$row_style'>
                            <td style='padding: 12px 15px; border-bottom: 1px solid #e6e6e6;'>" . htmlspecialchars($product['product_name']) . "</td>
                            <td style='padding: 12px 15px; text-align: center; border-bottom: 1px solid #e6e6e6;'>" . number_format($product['quantity']) . "</td>
                            <td style='padding: 12px 15px; text-align: right; border-bottom: 1px solid #e6e6e6;'>Ksh. " . number_format($product['unit_price'], 2) . "</td>
                            <td style='padding: 12px 15px; text-align: right; border-bottom: 1px solid #e6e6e6;'>Ksh. " . number_format($itemTotal, 2) . "</td>
                        </tr>";
    }

    $body .= "
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan='3' style='padding: 12px 15px; text-align: right; font-weight: bold; color: #2c3e50;'>Total Amount:</td>
                            <td style='padding: 12px 15px; text-align: right; font-weight: bold; font-size: 18px; color: #3498db; background: #f0f7fc;'>Ksh. " . number_format($total_amount, 2) . "</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Notes Section -->
            <div style='background: white; margin: 20px; border-radius: 8px; padding: 20px; border-left: 5px solid #f39c12;'>
                <h3 style='color: #2c3e50; margin-top: 0;'>Important Information</h3>
                <ul style='margin: 15px 0; padding-left: 20px; color: #555;'>
                    <li style='margin-bottom: 8px;'>We will process payment according to the agreed payment terms with your company.</li>
                    <li style='margin-bottom: 8px;'>Please reference PO <strong>#$purchase_order_id</strong> on all shipping documents and invoices.</li>
                    <li style='margin-bottom: 8px;'>Please confirm receipt of this order and expected delivery date.</li>
                    <li style='margin-bottom: 8px;'>For any questions or concerns regarding this order, please contact our purchasing department at <a href='mailto:purchasing@zellow.com' style='color: #3498db;'>purchasing@zellow.com</a>.</li>
                </ul>
            </div>

            <!-- Thank You Message -->
            <div style='background: #e8f4fc; margin: 20px; border-radius: 8px; padding: 15px; text-align: center;'>
                <p style='margin: 0; color: #2c3e50; font-size: 16px;'>Thank you for your business partnership!</p>
            </div>

            <!-- Footer -->
            <div style='background: #34495e; color: white; text-align: center; padding: 15px; border-radius: 0 0 10px 10px; font-size: 12px;'>
                <p style='margin: 0 0 5px 0;'>This is an automated message from Zellow Enterprises.</p>
                <p style='margin: 0;'>© " . date('Y') . " Zellow Enterprises. All rights reserved.</p>
            </div>
        </div>
    ";

    return $body;
}

function generatePurchaseOrderPlainText($purchase_order_id, $invoice_number, $total_amount) {
    return 
        "PURCHASE ORDER #$purchase_order_id\n" .
        "======================================\n\n" .
        "ZELLOW ENTERPRISES\n\n" .
        "ORDER DETAILS:\n" .
        "--------------------------------------\n" .
        "Purchase Order: #$purchase_order_id\n" .
        "Invoice Number: $invoice_number\n" .
        "Order Date: " . date('F j, Y') . "\n" .
        "Delivery Date: " . date('F j, Y', strtotime('+30 days')) . "\n\n" .
        "TOTAL AMOUNT: KSH. " . number_format($total_amount, 2) . "\n\n" .
        "IMPORTANT INFORMATION:\n" .
        "--------------------------------------\n" .
        "* We will process payment according to the agreed payment terms with your company.\n" .
        "* Please reference this PO number on all shipping documents and invoices.\n" .
        "* Please confirm receipt of this order and expected delivery date.\n" .
        "* For any questions, please contact our purchasing department at purchasing@zellow.com\n\n" .
        "Thank you for your business partnership!\n\n" .
        "This is an automated message from Zellow Enterprises.\n" .
        "© " . date('Y') . " Zellow Enterprises. All rights reserved.";
}