<?php
try {
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($input['id'])) {
        send_error('Category ID is required', 400);
    }

    // Check for subcategories
    $stmt = $db->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
    $stmt->execute([$input['id']]);
    if ($stmt->fetchColumn() > 0) {
        send_error('Cannot delete category with subcategories', 400);
    }

    // Check for products
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $stmt->execute([$input['id']]);
    if ($stmt->fetchColumn() > 0) {
        send_error('Cannot delete category with associated products', 400);
    }

    // Perform soft delete
    $stmt = $db->prepare("UPDATE categories SET is_active = 0, deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$input['id']]);

    if ($stmt->rowCount() === 0) {
        send_error('Category not found', 404);
    }

    send_success('Category deleted successfully');

} catch (Exception $e) {
    send_error('Error deleting category: ' . $e->getMessage(), 500);
}
