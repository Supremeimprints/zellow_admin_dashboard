<?php
/**
 * Utility functions for Zellow Admin
 * Contains commonly used helper functions
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(__FILE__))));
}

/**
 * Sanitize input data
 */
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate a unique tracking number
 */
function generateTrackingNumber() {
    $prefix = 'TRK';
    $date = date('Ymd');
    $random = strtoupper(substr(uniqid(), -4));
    return "{$prefix}-{$date}-{$random}";
}

/**
 * Format currency amount
 */
function format_currency($amount, $currency = 'Ksh') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Calculate time elapsed string
 */
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );

    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

/**
 * Validate email address
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate random string
 */
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $random_string;
}

/**
 * Check if string contains only alphanumeric characters
 */
function is_alphanumeric($string) {
    return ctype_alnum($string);
}

/**
 * Validate phone number format
 */
function is_valid_phone($phone) {
    return preg_match('/^[0-9]{10}$/', $phone);
}

/**
 * Sanitize filename
 */
function sanitize_filename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '', $filename);
    return $filename;
}

/**
 * Get file extension
 */
function get_file_extension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Format date for display
 */
function format_date($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

/**
 * Format date and time for display
 */
function format_datetime($datetime, $format = 'M j, Y H:i') {
    return date($format, strtotime($datetime));
}

/**
 * Validate date format
 */
function is_valid_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}