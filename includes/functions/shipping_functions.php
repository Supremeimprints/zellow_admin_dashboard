<?php

/**
 * Get all shipping rates from database
 */
function getShippingRates($db) {
    $stmt = $db->prepare("SELECT * FROM shipping_rates ORDER BY base_rate ASC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update shipping rate in database
 */
function updateShippingRate($db, $method, $rate) {
    $stmt = $db->prepare("UPDATE shipping_rates SET base_rate = ? WHERE shipping_method = ?");
    return $stmt->execute([$rate, $method]);
}

/**
 * Calculate shipping fee based on method and subtotal
 */
function calculateShippingFee($db, $shippingMethod, $subtotal = 0, $uniqueItemCount = 1) {
    $stmt = $db->prepare("SELECT base_rate FROM shipping_rates WHERE shipping_method = ?");
    $stmt->execute([$shippingMethod]);
    $rate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rate) {
        return 0;
    }

    $fee = $rate['base_rate'];
    
    // Add fee for additional unique items (not quantities)
    if ($uniqueItemCount > 1) {
        // Base fee for first item
        $totalFee = $fee;
        
        
        
        $fee = $totalFee;
    }
    
    // Free shipping threshold
    if ($subtotal >= 10000) {
        return 0;
    }

    return $fee;
}

/**
 * Get shipping fee for display
 */
function getShippingFeeFormatted($db, $method, $subtotal = 0) {
    $fee = calculateShippingFee($db, $method, $subtotal);
    return 'Ksh. ' . number_format($fee, 2);
}
