<?php
require_once __DIR__ . '/shipping_functions.php';

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

function update_system_setting($db, $key, $value, $group = 'general') {
    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_group) 
                         VALUES (?, ?, ?) 
                         ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $group, $value]);
}
