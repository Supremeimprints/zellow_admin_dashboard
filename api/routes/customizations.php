<?php
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../admin/controllers/GiftCustomizationController.php';

$database = new Database();
$db = $database->getConnection();
$giftController = new GiftCustomizationController($db);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $type = $_GET['type'] ?? 'all';
        
        switch ($type) {
            case 'occasions':
                ApiResponse::success($giftController->getOccasions());
                break;
                
            case 'customizations':
                ApiResponse::success($giftController->getAvailableCustomizations());
                break;
                
            default:
                ApiResponse::success([
                    'occasions' => $giftController->getOccasions(),
                    'customizations' => $giftController->getAvailableCustomizations()
                ]);
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            switch ($data['type']) {
                case 'occasion':
                    $result = $giftController->createOccasion($data);
                    ApiResponse::success(['message' => 'Occasion created successfully']);
                    break;
                    
                case 'customization':
                    $result = $giftController->createCustomizationOption($data);
                    ApiResponse::success(['message' => 'Customization option created successfully']);
                    break;
                    
                default:
                    throw new Exception("Invalid customization type");
            }
        } catch (Exception $e) {
            ApiResponse::error($e->getMessage());
        }
        break;
}
