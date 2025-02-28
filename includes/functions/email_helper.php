<?php
require_once __DIR__ . '/../../config/mail.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function getMailConfig($key = null) {
    static $config = null;
    
    if ($config === null) {
        $config = require __DIR__ . '/../../config/mail.php';
    }
    
    return $key ? ($config[$key] ?? null) : $config;
}

function sendEmail($to, $subject, $body, $attachments = []) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        
        // Server settings
        $mail->Host = getMailConfig('smtp_host');
        $mail->SMTPAuth = true;
        $mail->Username = getMailConfig('smtp_user');
        $mail->Password = getMailConfig('smtp_pass');
        $mail->SMTPSecure = getMailConfig('smtp_encryption');
        $mail->Port = getMailConfig('smtp_port');
        
        // Sender
        $mail->setFrom(
            getMailConfig('from_address'),
            getMailConfig('from_name')
        );
        
        // Recipients
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}
