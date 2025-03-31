<?php
function validate_password($password) {
    // At least 8 characters long
    if (strlen($password) < 8) return false;
    
    // At least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) return false;
    
    // At least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) return false;
    
    // At least one number
    if (!preg_match('/[0-9]/', $password)) return false;
    
    return true;
}

function sanitize_phone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Handle Kenyan phone numbers
    if (strlen($phone) === 9 && substr($phone, 0, 1) === '7') {
        $phone = '254' . $phone;
    } elseif (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    }
    
    return $phone;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
