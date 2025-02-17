<?php

// ...existing code...

/**
 * Check if shipping method is valid for a region
 * @param PDO $db Database connection
 * @param int $methodId Shipping method ID
 * @param int $regionId Region ID
 * @return bool
 */
function isValidShippingMethod($db, $methodId, $regionId) {
    try {
        $query = "SELECT COUNT(*) FROM region_shipping_rates 
                  WHERE shipping_method_id = :method_id 
                  AND region_id = :region_id 
                  AND is_active = 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':method_id' => $methodId,
            ':region_id' => $regionId
        ]);
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Error validating shipping method: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate shipping fee based on method, region, and order details
 * @param PDO $db Database connection
 * @param int $methodId Shipping method ID
 * @param int $regionId Region ID
 * @param int $itemCount Number of items
 * @param float $orderTotal Total order amount
 * @return float|null Calculated shipping fee or null if error
 */
function calculateShippingFee($db, $methodId, $regionId, $itemCount = 1, $orderTotal = 0) {
    try {
        // Get shipping rate for method and region
        $stmt = $db->prepare("
            SELECT 
                r.base_rate,
                r.per_item_fee,
                m.free_shipping_threshold
            FROM region_shipping_rates r
            JOIN shipping_methods m ON r.shipping_method_id = m.id
            WHERE r.shipping_method_id = ?
            AND r.region_id = ?
            AND r.is_active = 1
        ");
        
        $stmt->execute([$methodId, $regionId]);
        $rate = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rate) {
            error_log("No shipping rate found for method $methodId and region $regionId");
            return null;
        }

        // Check for free shipping
        if ($rate['free_shipping_threshold'] && $orderTotal >= $rate['free_shipping_threshold']) {
            return 0;
        }

        // Calculate fee: base rate + (per item fee × additional items)
        $additionalItems = max(0, $itemCount - 1);
        return $rate['base_rate'] + ($rate['per_item_fee'] * $additionalItems);

    } catch (PDOException $e) {
        error_log("Error calculating shipping fee: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate shipping cost with all fees and thresholds
 * @param PDO $db Database connection
 * @param int $methodId Shipping method ID
 * @param int $regionId Region ID
 * @param int $itemCount Number of items
 * @param float $orderTotal Total order amount for free shipping check
 * @return float|null Calculated shipping cost or null if error
 */
function calculateShippingCost($db, $methodId, $regionId, $itemCount = 1, $orderTotal = 0) {
    try {
        // Get shipping rate details
        $rate = getShippingRate($db, $methodId, $regionId);
        
        if (!$rate) {
            error_log("No shipping rate found for method $methodId and region $regionId");
            return null;
        }

        // Check for free shipping threshold
        if ($rate['free_shipping_threshold'] && $orderTotal >= $rate['free_shipping_threshold']) {
            return 0;
        }

        // Calculate base cost plus per-item fee for additional items
        $additionalItems = max(0, $itemCount - 1);
        $cost = $rate['base_rate'] + ($rate['per_item_fee'] * $additionalItems);
        
        return $cost;

    } catch (PDOException $e) {
        error_log("Error calculating shipping cost: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all shipping regions
 */
function getRegions($db, $activeOnly = true) {
    $sql = "SELECT * FROM shipping_regions";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all shipping methods
 */
function getShippingMethods($db, $activeOnly = true) {
    $sql = "SELECT * FROM shipping_methods";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY display_name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get shipping rates for a region
 */
function getRegionRates($db, $regionId) {
    $stmt = $db->prepare("
        SELECT r.*, sm.name as method_name, sm.display_name 
        FROM region_shipping_rates r
        JOIN shipping_methods sm ON r.shipping_method_id = sm.id
        WHERE r.region_id = ? AND r.is_active = 1
    ");
    $stmt->execute([$regionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all shipping zones
 */
function get_shipping_zones($db) {
    $stmt = $db->prepare("
        SELECT * FROM shipping_zones 
        WHERE is_active = 1 
        ORDER BY zone_name
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get zones associated with a region
 * @param PDO $db Database connection
 * @param int $regionId Region ID
 * @return array Array of zones that include this region
 */
function getRegionZones($db, $regionId) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM shipping_zones 
            WHERE FIND_IN_SET(?, zone_regions) > 0
            AND is_active = 1
            ORDER BY zone_name
        ");
        
        $stmt->execute([$regionId]);
        $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the zone_regions for display
        foreach ($zones as &$zone) {
            $zone['zone_regions'] = $zone['zone_regions'] ? 
                implode(', ', array_map('trim', explode(',', $zone['zone_regions']))) : '';
        }
        
        return $zones;
    } catch (PDOException $e) {
        error_log("Error getting region zones: " . $e->getMessage());
        return [];
    }
}

/**
 * Toggle region status
 */
function toggleRegionStatus($db, $regionId) {
    $stmt = $db->prepare("
        UPDATE shipping_regions 
        SET is_active = NOT is_active,
        updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    return $stmt->execute([$regionId]);
}

/**
 * Get shipping methods and rates for a specific region
 * @param PDO $db Database connection
 * @param int $regionId Region ID
 * @return array Array of shipping methods with rates for the region
 */
function getRegionShippingMethods($db, $regionId) {
    $stmt = $db->prepare("
        SELECT 
            sm.id,
            sm.name,
            sm.display_name,
            sm.estimated_days,
            sm.free_shipping_threshold,
            rsr.base_rate,
            rsr.per_item_fee
        FROM shipping_methods sm
        JOIN region_shipping_rates rsr ON sm.id = rsr.shipping_method_id
        WHERE rsr.region_id = ?
        AND rsr.is_active = 1
        AND sm.is_active = 1
        ORDER BY sm.display_name
    ");
    
    $stmt->execute([$regionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Render HTML for region dropdown
 * @param PDO $db Database connection
 * @return string HTML select element with regions
 */
function renderRegionDropdown($db) {
    try {
        $regions = getRegions($db, true); // Get only active regions
        
        $html = '<select name="region_id" id="region_id" class="form-select" required>';
        $html .= '<option value="">Select Delivery Region</option>';
        
        foreach ($regions as $region) {
            $html .= sprintf(
                '<option value="%d">%s</option>',
                $region['id'],
                htmlspecialchars($region['name'])
            );
        }
        
        $html .= '</select>';
        return $html;
        
    } catch (PDOException $e) {
        error_log("Error rendering region dropdown: " . $e->getMessage());
        return '<select class="form-select" disabled><option>Error loading regions</option></select>';
    }
}

/**
 * Get shipping rate for a specific method and region
 * @param PDO $db Database connection
 * @param int $methodId Shipping method ID
 * @param int $regionId Region ID
 * @return array|null Shipping rate details or null if not found
 */
function getShippingRate($db, $methodId, $regionId) {
    try {
        $stmt = $db->prepare("
            SELECT 
                rsr.*,
                sm.name,
                sm.display_name,
                sm.free_shipping_threshold,
                sm.estimated_days
            FROM region_shipping_rates rsr
            JOIN shipping_methods sm ON sm.id = rsr.shipping_method_id
            WHERE rsr.shipping_method_id = ?
            AND rsr.region_id = ?
            AND rsr.is_active = 1
            AND sm.is_active = 1
            LIMIT 1
        ");
        
        $stmt->execute([$methodId, $regionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting shipping rate: " . $e->getMessage());
        return null;
    }
}

// ...rest of existing code...
