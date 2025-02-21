<?php
require_once __DIR__ . '/../../config/mail_config.php';
require_once __DIR__ . '/../../includes/templates/email/gift_notification.php';
use PHPMailer\PHPMailer\PHPMailer;

class GiftOrderController {
    private $db;
    private $mailer;
    private $emailTemplates;
    
    public function __construct($db) {
        $this->db = $db;
        $this->initializeMailer();
        $this->loadEmailTemplates();
    }

    private function initializeMailer() {
        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        
        // Load mail settings from config
        $config = MAIL_CONFIG;
        foreach ($config as $key => $value) {
            $property = strtolower($key);
            $this->mailer->$property = $value;
        }
    }

    private function loadEmailTemplates() {
        if (!isset($this->emailTemplates)) {
            $this->emailTemplates = [
                'giftee' => function($orderId, $giftData) {
                    ob_start();
                    include __DIR__ . '/../../includes/templates/email/giftee_notification.php';
                    return ob_get_clean();
                },
                'gifter' => function($orderId, $giftData) {
                    ob_start();
                    include __DIR__ . '/../../includes/templates/email/gifter_notification.php';
                    return ob_get_clean();
                }
            ];
        }
    }

    public function createGiftOrder($orderData, $giftData) {
        try {
            $this->db->beginTransaction();
            
            // Add gift details to order
            $stmt = $this->db->prepare("
                INSERT INTO orders (
                    id, email, recipient_email, shipping_address,
                    is_gift, occasion_id, gift_message, is_gift_wrapped,
                    gift_wrap_cost, notify_recipient, tracking_code,
                    status, payment_status, total_amount
                ) VALUES (
                    :id, :email, :recipient_email, :shipping_address,
                    1, :occasion_id, :gift_message, :is_gift_wrapped,
                    :gift_wrap_cost, :notify_recipient, :tracking_code,
                    'Pending', 'Pending', :total_amount
                )
            ");

            $trackingCode = $this->generateTrackingCode();
            
            $stmt->execute([
                ':id' => $orderData['id'],
                ':email' => $orderData['email'],
                ':recipient_email' => $giftData['recipient_email'],
                ':shipping_address' => $giftData['shipping_address'],
                ':occasion_id' => $giftData['occasion_id'],
                ':gift_message' => $giftData['gift_message'],
                ':is_gift_wrapped' => $giftData['is_gift_wrapped'],
                ':gift_wrap_cost' => $giftData['is_gift_wrapped'] ? 5.00 : 0.00,
                ':notify_recipient' => $giftData['notify_recipient'],
                ':tracking_code' => $trackingCode,
                ':total_amount' => $orderData['total_amount']
            ]);

            $orderId = $this->db->lastInsertId();

            // Send emails
            if ($giftData['notify_recipient']) {
                $this->sendGiftNotifications($orderId, $orderData, $giftData);
            }

            $this->db->commit();
            return ['order_id' => $orderId, 'tracking_code' => $trackingCode];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function generateTrackingCode() {
        return 'GIFT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    private function sendGiftNotifications($orderId, $orderData, $giftData) {
        try {
            // Send to gift recipient
            if ($giftData['notify_recipient']) {
                $this->mailer->clearAddresses();
                $this->mailer->addAddress($giftData['recipient_email']);
                $this->mailer->Subject = "You've Received a Gift!";
                $this->mailer->Body = ($this->emailTemplates['giftee'])($orderId, $giftData);
                $this->mailer->send();
            }

            // Send confirmation to purchaser
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($orderData['email']);
            $this->mailer->Subject = "Your Gift Order Confirmation";
            $this->mailer->Body = ($this->emailTemplates['gifter'])($orderId, $giftData);
            $this->mailer->send();

        } catch (Exception $e) {
            error_log("Failed to send gift notifications: " . $e->getMessage());
        }
    }

    public function updateGiftOrderStatus($orderId, $status) {
        // Update status and notify both parties
        $stmt = $this->db->prepare("
            UPDATE orders 
            SET status = :status, updated_at = NOW() 
            WHERE order_id = :order_id AND is_gift = 1
        ");
        
        $stmt->execute([':status' => $status, ':order_id' => $orderId]);
        
        // Fetch order details and send notifications
        $this->sendStatusUpdateNotifications($orderId, $status);
    }

    private function sendStatusUpdateNotifications($orderId, $status) {
        try {
            // Get order details
            $stmt = $this->db->prepare("
                SELECT o.*, u.email as purchaser_email
                FROM orders o
                JOIN users u ON o.id = u.id
                WHERE o.order_id = ? AND o.is_gift = 1
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new Exception("Gift order not found");
            }

            $statusMessage = $this->getStatusUpdateMessage($status);
            
            // Notify recipient if enabled
            if ($order['notify_recipient'] && $order['recipient_email']) {
                $this->mailer->clearAddresses();
                $this->mailer->addAddress($order['recipient_email']);
                $this->mailer->Subject = "Gift Order Status Update";
                $this->mailer->Body = $this->getStatusEmailTemplate($order, $status, false);
                $this->mailer->send();
            }

            // Notify purchaser
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($order['purchaser_email']);
            $this->mailer->Subject = "Your Gift Order Status Update";
            $this->mailer->Body = $this->getStatusEmailTemplate($order, $status, true);
            $this->mailer->send();

        } catch (Exception $e) {
            error_log("Failed to send status update notifications: " . $e->getMessage());
            // Continue processing even if email fails
        }
    }

    private function getStatusUpdateMessage($status) {
        switch ($status) {
            case 'Processing':
                return 'Your gift is being prepared';
            case 'Shipped':
                return 'Your gift is on its way';
            case 'Delivered':
                return 'Your gift has been delivered';
            case 'Cancelled':
                return 'The gift order has been cancelled';
            default:
                return 'Your gift order status has been updated';
        }
    }

    private function getStatusEmailTemplate($order, $status, $isPurchaser) {
        $template = file_get_contents(__DIR__ . '/../../includes/templates/email/order_status_update.php');
        
        return str_replace(
            ['{{STATUS}}', '{{ORDER_ID}}', '{{MESSAGE}}', '{{TRACKING_CODE}}'],
            [
                $status,
                $order['order_id'],
                $this->getStatusUpdateMessage($status),
                $order['tracking_code']
            ],
            $template
        );
    }
}
