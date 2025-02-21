<?php
class CustomizationManager {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function addCustomizationOption($data) {
        $stmt = $this->db->prepare("
            INSERT INTO customization_options (
                name, type, description, additional_cost, 
                max_length, allowed_values, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['name'],
            $data['type'],
            $data['description'],
            $data['additional_cost'] ?? 0.00,
            $data['max_length'] ?? null,
            json_encode($data['allowed_values'] ?? null),
            $data['is_active'] ?? true
        ]);
    }

    public function assignCustomizationToProduct($productId, $optionId, $isRequired = false) {
        $stmt = $this->db->prepare("
            INSERT INTO product_customization_options (
                product_id, option_id, is_required
            ) VALUES (?, ?, ?)
        ");
        
        return $stmt->execute([$productId, $optionId, $isRequired]);
    }

    public function getProductCustomizations($productId) {
        $stmt = $this->db->prepare("
            SELECT co.*, pco.is_required
            FROM customization_options co
            JOIN product_customization_options pco ON co.id = pco.option_id
            WHERE pco.product_id = ? AND co.is_active = 1
            ORDER BY pco.sort_order
        ");
        
        $stmt->execute([$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveOrderCustomizations($orderItemId, $customizations) {
        $stmt = $this->db->prepare("
            INSERT INTO order_customizations (
                order_item_id, option_id, value, additional_cost
            ) VALUES (?, ?, ?, ?)
        ");

        foreach ($customizations as $customization) {
            $stmt->execute([
                $orderItemId,
                $customization['option_id'],
                $customization['value'],
                $customization['additional_cost'] ?? 0.00
            ]);
        }
    }
}
