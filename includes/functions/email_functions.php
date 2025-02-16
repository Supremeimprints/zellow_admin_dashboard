<?php
function verifySupplierEmailSettings($db, $supplier_id) {
    $query = "SELECT s.*, 
                     COUNT(p.product_id) as has_products,
                     GROUP_CONCAT(p.product_name) as product_names
              FROM suppliers s
              LEFT JOIN products p ON s.supplier_id = p.supplier_id
              WHERE s.supplier_id = ?
              GROUP BY s.supplier_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$supplier_id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    $errors = [];

    if (!$supplier) {
        $errors[] = "Supplier not found";
    } else {
        if (empty($supplier['email'])) {
            $errors[] = "Supplier email is missing";
        } elseif (!filter_var($supplier['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid supplier email format";
        }
        
        if (empty($supplier['company_name'])) {
            $errors[] = "Company name is missing";
        }
        
        if ($supplier['has_products'] == 0) {
            $errors[] = "No products found for this supplier";
        }
    }

    return [
        'isValid' => empty($errors),
        'errors' => $errors,
        'supplier' => $supplier
    ];
}
