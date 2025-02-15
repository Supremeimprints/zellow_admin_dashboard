<?php

/**
 * Gets an existing tracking number or returns false
 */
function getExistingTrackingNumber($db, $orderId) {
    $stmt = $db->prepare("SELECT tracking_number FROM orders WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['tracking_number'] : false;
}

/**
 * Gets tracking number - either existing or generates new one
 */
function getOrderTrackingNumber($db, $orderId) {
    $tracking = getExistingTrackingNumber($db, $orderId);
    if (!$tracking) {
        $tracking = generateTrackingNumber();
        // Save the new tracking number
        $stmt = $db->prepare("UPDATE orders SET tracking_number = ? WHERE order_id = ?");
        $stmt->execute([$tracking, $orderId]);
    }
    return $tracking;
}
