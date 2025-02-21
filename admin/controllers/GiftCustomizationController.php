<?php
require_once __DIR__ . '/../../includes/classes/CustomizationManager.php';

class GiftCustomizationController {
    private $db;
    private $customizationManager;

    public function __construct($db) {
        $this->db = $db;
        $this->customizationManager = new CustomizationManager($db);
    }

    public function createOccasion($data) {
        $stmt = $this->db->prepare("
            INSERT INTO occasions (
                name, description, icon_path, is_active
            ) VALUES (?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['icon_path'] ?? null,
            $data['is_active'] ?? true
        ]);
    }

    public function addProductToOccasion($productId, $occasionId) {
        $stmt = $this->db->prepare("
            INSERT INTO product_occasions (product_id, occasion_id)
            VALUES (?, ?)
        ");
        return $stmt->execute([$productId, $occasionId]);
    }

    public function getAvailableCustomizations() {
        $stmt = $this->db->prepare("
            SELECT * FROM customization_options 
            WHERE is_active = 1 
            ORDER BY name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOccasions() {
        $stmt = $this->db->prepare("
            SELECT * FROM occasions 
            WHERE is_active = 1 
            ORDER BY name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Add public method to handle customization creation
    public function createCustomizationOption($data) {
        return $this->customizationManager->addCustomizationOption($data);
    }
}
