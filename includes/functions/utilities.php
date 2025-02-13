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

/**
 * Count active marketing campaigns
 */
function count_active_campaigns() {
    global $conn;
    if (!$conn) return 0;
    
    $sql = "SELECT COUNT(*) as count FROM marketing_campaigns WHERE end_date >= CURDATE()";
    $result = $conn->query($sql);
    if (!$result) return 0;
    
    $row = $result->fetch_assoc();
    return $row['count'] ?? 0;
}

/**
 * Get total coupon usage
 */
function get_total_coupon_usage() {
    global $conn;
    if (!$conn) return 0;
    
    $sql = "SELECT COUNT(*) as count FROM orders WHERE coupon_id IS NOT NULL";
    $result = $conn->query($sql);
    if (!$result) return 0;
    
    $row = $result->fetch_assoc();
    return $row['count'] ?? 0;
}

/**
 * Generate Google Ads tracking code
 */
function get_google_ads_code($campaign_id) {
    $tracking_id = get_setting('google_ads_id');
    if (empty($tracking_id)) return '';
    
    return "<!-- Google Ads Tracking -->
    <script async src='https://www.googletagmanager.com/gtag/js?id={$tracking_id}'></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{$tracking_id}');
        gtag('event', 'page_view', {
            'campaign_id': '{$campaign_id}'
        });
    </script>";
}

/**
 * Get a setting value from the database
 */
function get_setting($key, $default = '') {
    global $conn;
    $key = clean_input($key);
    $sql = "SELECT $key FROM settings WHERE id = 1";
    $result = $conn->query($sql);
    
    if ($row = $result->fetch_assoc()) {
        return $row[$key] ?? $default;
    }
    return $default;
}

/**
 * Update a setting
 */
function update_setting($key, $value) {
    global $conn;
    $key = clean_input($key);
    $value = clean_input($value);
    
    $sql = "UPDATE settings SET $key = ? WHERE id = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $value);
    return $stmt->execute();
}