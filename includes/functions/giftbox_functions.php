<?php

function handleGiftBoxImageUpload($file) {
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload failed'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size exceeds 5MB limit'];
    }

    $type = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$type])) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG and PNG allowed'];
    }

    // Generate unique filename
    $ext = $allowed[$type];
    $filename = uniqid('giftbox_', true) . '.' . $ext;
    $uploadDir = 'uploads/gift_boxes/';
    $destination = $uploadDir . $filename;

    // Ensure upload directory exists
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Move file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }

    return [
        'success' => true,
        'path' => $destination,
        'filename' => $filename
    ];
}

function createGiftBox($db, $data) {
    try {
        $query = "INSERT INTO gift_boxes (name, description, base_price, image_path) 
                  VALUES (:name, :description, :base_price, :image_path)";
        
        $stmt = $db->prepare($query);
        return $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'],
            ':base_price' => $data['base_price'],
            ':image_path' => $data['image_path']
        ]);
    } catch (PDOException $e) {
        error_log("Error creating gift box: " . $e->getMessage());
        return false;
    }
}

function getGiftBoxes($db) {
    try {
        $query = "SELECT * FROM gift_boxes WHERE is_active = TRUE ORDER BY created_at DESC";
        $stmt = $db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching gift boxes: " . $e->getMessage());
        return [];
    }
}

function getAllProducts($db) {
    try {
        // Add debug output
        error_log("Starting getAllProducts() query");
        
        $query = "SELECT 
                    product_id,
                    product_name,
                    price,
                    stock_quantity
                 FROM products 
                 WHERE active = 1 
                 AND is_gift = 0
                 ORDER BY product_name ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Found " . count($products) . " products"); // Debug log
        
        return $products;
    } catch (PDOException $e) {
        error_log("Error in getAllProducts(): " . $e->getMessage());
        return [];
    }
}

function getGiftBoxById($db, $id) {
    try {
        $query = "SELECT * FROM gift_boxes WHERE gift_box_id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching gift box: " . $e->getMessage());
        return null;
    }
}

function getGiftBoxItems($db, $giftBoxId) {
    try {
        $query = "SELECT 
                    gbi.*,
                    p.product_name,
                    p.price as product_price 
                 FROM gift_box_items gbi 
                 JOIN products p ON gbi.product_id = p.product_id 
                 WHERE gbi.gift_box_id = :gift_box_id";
        
        error_log("Executing getGiftBoxItems for box ID: " . $giftBoxId); // Debug log
        $stmt = $db->prepare($query);
        $stmt->execute([':gift_box_id' => $giftBoxId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching gift box items: " . $e->getMessage());
        return [];
    }
}

function addGiftBoxItem($db, $data) {
    try {
        $query = "INSERT INTO gift_box_items (gift_box_id, product_id, quantity, price_override) 
                 VALUES (:gift_box_id, :product_id, :quantity, :price_override)";
        $stmt = $db->prepare($query);
        return $stmt->execute([
            ':gift_box_id' => $data['gift_box_id'],
            ':product_id' => $data['product_id'],
            ':quantity' => $data['quantity'],
            ':price_override' => $data['price_override'] ?: null
        ]);
    } catch (PDOException $e) {
        error_log("Error adding gift box item: " . $e->getMessage());
        return false;
    }
}

function removeGiftBoxItem($db, $itemId) {
    try {
        $query = "DELETE FROM gift_box_items WHERE id = :id";
        $stmt = $db->prepare($query);
        return $stmt->execute([':id' => $itemId]);
    } catch (PDOException $e) {
        error_log("Error removing gift box item: " . $e->getMessage());
        return false;
    }
}

function getGiftBoxAttributes($db, $giftBoxId) {
    try {
        $query = "SELECT * FROM gift_box_attributes WHERE gift_box_id = :gift_box_id ORDER BY id ASC";
        $stmt = $db->prepare($query);
        $stmt->execute([':gift_box_id' => $giftBoxId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching gift box attributes: " . $e->getMessage());
        return [];
    }
}

function addGiftBoxAttribute($db, $data) {
    try {
        $db->beginTransaction();
        
        $query = "INSERT INTO gift_box_attributes 
                 (gift_box_id, name, input_type, is_required) 
                 VALUES (:gift_box_id, :name, :input_type, :is_required)";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':gift_box_id' => $data['gift_box_id'],
            ':name' => $data['name'],
            ':input_type' => $data['input_type'],
            ':is_required' => isset($data['is_required']) ? 1 : 0
        ]);
        
        $attributeId = $db->lastInsertId();
        
        // Handle dropdown options if present
        if ($data['input_type'] === 'dropdown' && !empty($data['options'])) {
            foreach ($data['options'] as $option) {
                $query = "INSERT INTO attribute_options (attribute_id, value, additional_price) 
                         VALUES (:attribute_id, :value, :additional_price)";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':attribute_id' => $attributeId,
                    ':value' => $option['value'],
                    ':additional_price' => $option['price'] ?? 0
                ]);
            }
        }
        
        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Error adding gift box attribute: " . $e->getMessage());
        return false;
    }
}

function deleteGiftBoxAttribute($db, $attributeId) {
    try {
        $query = "DELETE FROM gift_box_attributes WHERE id = :id";
        $stmt = $db->prepare($query);
        return $stmt->execute([':id' => $attributeId]);
    } catch (PDOException $e) {
        error_log("Error deleting gift box attribute: " . $e->getMessage());
        return false;
    }
}

function updateGiftBox($db, $data) {
    try {
        $fields = ['name', 'description', 'base_price'];
        $updateFields = [];
        $params = [':gift_box_id' => $data['gift_box_id']];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        // Add image path if provided
        if (isset($data['image_path'])) {
            $updateFields[] = "image_path = :image_path";
            $params[':image_path'] = $data['image_path'];
        }

        $query = "UPDATE gift_boxes SET " . implode(', ', $updateFields) . 
                " WHERE gift_box_id = :gift_box_id";

        $stmt = $db->prepare($query);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Error updating gift box: " . $e->getMessage());
        return false;
    }
}

function calculateGiftBoxTotalValue($db, $giftBoxId) {
    try {
        $query = "SELECT 
                    SUM(COALESCE(gbi.price_override, p.price) * gbi.quantity) as total_value
                 FROM gift_box_items gbi
                 JOIN products p ON gbi.product_id = p.product_id
                 WHERE gbi.gift_box_id = :gift_box_id";
        
        $stmt = $db->prepare($query);
        $stmt->execute([':gift_box_id' => $giftBoxId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['total_value'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error calculating gift box value: " . $e->getMessage());
        return 0;
    }
}

function updateGiftBoxBasePrice($db, $giftBoxId, $newBasePrice) {
    try {
        $query = "UPDATE gift_boxes 
                 SET base_price = :base_price,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE gift_box_id = :gift_box_id";
        
        $stmt = $db->prepare($query);
        return $stmt->execute([
            ':gift_box_id' => $giftBoxId,
            ':base_price' => $newBasePrice
        ]);
    } catch (PDOException $e) {
        error_log("Error updating gift box base price: " . $e->getMessage());
        return false;
    }
}

function getGiftBoxPricingDetails($db, $giftBoxId) {
    try {
        $itemsTotal = calculateGiftBoxTotalValue($db, $giftBoxId);
        $giftBox = getGiftBoxById($db, $giftBoxId);
        
        return [
            'items_total' => $itemsTotal,
            'base_price' => $giftBox['base_price'],
            'margin' => $giftBox['base_price'] - $itemsTotal,
            'margin_percentage' => $itemsTotal > 0 ? 
                (($giftBox['base_price'] - $itemsTotal) / $itemsTotal) * 100 : 0
        ];
    } catch (Exception $e) {
        error_log("Error getting gift box pricing details: " . $e->getMessage());
        return null;
    }
}
