<?php

function get_all_settings($db, $group = null) {
    $sql = "SELECT setting_key, setting_value FROM system_settings";
    if ($group) {
        $sql .= " WHERE setting_group = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$group]);
    } else {
        $stmt = $db->query($sql);
    }
    
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function get_payment_gateways($db) {
    $stmt = $db->query("SELECT * FROM payment_gateways");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function update_payment_gateway($db, $code, $data) {
    $stmt = $db->prepare("UPDATE payment_gateways SET 
        is_enabled = ?,
        config = ?,
        sandbox_mode = ?,
        updated_at = CURRENT_TIMESTAMP
        WHERE gateway_code = ?");
    
    return $stmt->execute([
        $data['is_enabled'] ?? 0,
        json_encode($data['config']),
        $data['sandbox_mode'] ?? 1,
        $code
    ]);
}

function get_shipping_zones($db) {
    $stmt = $db->query("SELECT z.*, COUNT(r.id) as rate_count 
                        FROM shipping_zones z 
                        LEFT JOIN shipping_rates r ON z.zone_id = r.zone_id 
                        GROUP BY z.zone_id");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_zone_rates($db, $zone_id) {
    $stmt = $db->prepare("SELECT * FROM shipping_rates WHERE zone_id = ?");
    $stmt->execute([$zone_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function update_system_setting($db, $key, $value, $group = 'general') {
    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_group) 
                         VALUES (?, ?, ?) 
                         ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $group, $value]);
}

function delete_shipping_zone($db, $zone_id) {
    try {
        // First delete associated rates
        $stmt = $db->prepare("DELETE FROM shipping_rates WHERE zone_id = ?");
        $stmt->execute([$zone_id]);
        
        // Then delete the zone
        $stmt = $db->prepare("DELETE FROM shipping_zones WHERE zone_id = ?");
        return $stmt->execute([$zone_id]);
    } catch (Exception $e) {
        return false;
    }
}
