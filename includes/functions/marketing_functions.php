<?php

/**
 * Get status color for campaign badges
 */
function getStatusColor($status) {
    return match ($status) {
        'active' => 'success',
        'paused' => 'warning',
        'completed' => 'secondary',
        default => 'primary'
    };
}

/**
 * Format campaign budget
 */
function formatBudget($amount) {
    return 'Ksh. ' . number_format($amount, 2);
}

/**
 * Check if campaign is active
 */
function isCampaignActive($startDate, $endDate) {
    $now = time();
    $start = strtotime($startDate);
    $end = strtotime($endDate);
    
    return ($now >= $start && $now <= $end);
}

/**
 * Generate Google Ads tracking code
 */
function generateTrackingCode() {
    // Creates unique Google Ads compatible tracking code
    return 'GA-' . uniqid() . '-' . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4);
}

/**
 * Validate campaign dates
 */
function validateCampaignDates($startDate, $endDate) {
    $start = strtotime($startDate);
    $end = strtotime($endDate);
    
    if ($start === false || $end === false) {
        return false;
    }
    
    return $end > $start;
}

/**
 * Generate unique coupon code
 */
function generateCouponCode($prefix = 'ZELLOW') {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = $prefix . '-';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * Check if coupon is valid
 */
function isCouponValid($couponCode, $db) {
    $stmt = $db->prepare("SELECT * FROM coupons WHERE code = ? AND expiration_date >= CURDATE()");
    $stmt->execute([$couponCode]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

/**
 * Calculate discount amount
 */
function calculateDiscount($amount, $discountPercentage) {
    return ($amount * $discountPercentage) / 100;
}

/**
 * Get remaining campaign duration in days
 */
function getRemainingDays($endDate) {
    $end = strtotime($endDate);
    $now = time();
    $diffDays = ceil(($end - $now) / (60 * 60 * 24));
    return max(0, $diffDays);
}

/**
 * Format campaign duration for display
 */
function formatCampaignDuration($startDate, $endDate) {
    $start = date('M d', strtotime($startDate));
    $end = date('M d, Y', strtotime($endDate));
    return "$start - $end";
}

/**
 * Calculate ROI
 */
function calculateROI($spend, $revenue) {
    if (!$spend) return 0;
    return (($revenue - $spend) / $spend) * 100;
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return 'KSH ' . number_format($amount, 2);
}

/**
 * Get date range metrics
 */
function getDateRangeMetrics($db, $campaign_id, $start_date, $end_date) {
    $query = "SELECT 
        SUM(impressions) as total_impressions,
        SUM(clicks) as total_clicks,
        SUM(conversions) as total_conversions,
        SUM(spend) as total_spend
        FROM campaign_metrics 
        WHERE campaign_id = ? 
        AND created_at BETWEEN ? AND ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$campaign_id, $start_date, $end_date]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
