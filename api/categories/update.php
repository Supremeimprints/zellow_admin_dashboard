<?php
try {
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($input['id'])) {
        send_error('Category ID is required', 400);
    }

    $updateFields = [];
    $params = [];

    // Build dynamic update query
    if (isset($input['name'])) {
        $updateFields[] = 'name = ?';
        $params[] = $input['name'];
        
        // Update slug if name changes
        $updateFields[] = 'slug = ?';
        $params[] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $input['name'])));
    }

    if (isset($input['description'])) {
        $updateFields[] = 'description = ?';
        $params[] = $input['description'];
    }

    if (isset($input['parent_id'])) {
        $updateFields[] = 'parent_id = ?';
        $params[] = $input['parent_id'];
    }

    if (isset($input['image_url'])) {
        $updateFields[] = 'image_url = ?';
        $params[] = $input['image_url'];
    }

    if (isset($input['is_active'])) {
        $updateFields[] = 'is_active = ?';
        $params[] = $input['is_active'];
    }

    $updateFields[] = 'updated_at = NOW()';
    $params[] = $input['id']; // Add ID for WHERE clause

    $query = "UPDATE categories SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        send_error('Category not found', 404);
    }

    send_success('Category updated successfully');

} catch (Exception $e) {
    send_error('Error updating category: ' . $e->getMessage(), 500);
}
