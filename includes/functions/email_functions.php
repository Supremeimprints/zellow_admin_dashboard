<?php

function verifySupplierEmailSettings($db, $supplier_id) {
    $result = [
        'isValid' => true,
        'errors' => []
    ];

    try {
        // Get supplier details
        $stmt = $db->prepare("SELECT email, company_name, status FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$supplier_id]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$supplier) {
            $result['isValid'] = false;
            $result['errors'][] = "Supplier not found";
            return $result;
        }

        // Check if supplier is active
        if ($supplier['status'] !== 'Active') {
            $result['isValid'] = false;
            $result['errors'][] = "Supplier is not active";
        }

        // Validate email
        if (!filter_var($supplier['email'], FILTER_VALIDATE_EMAIL)) {
            $result['isValid'] = false;
            $result['errors'][] = "Invalid supplier email address";
        }

        // Check if email is empty
        if (empty($supplier['email'])) {
            $result['isValid'] = false;
            $result['errors'][] = "Supplier email address is missing";
        }

        // Check if company name is set
        if (empty($supplier['company_name'])) {
            $result['isValid'] = false;
            $result['errors'][] = "Supplier company name is missing";
        }

        return $result;

    } catch (Exception $e) {
        $result['isValid'] = false;
        $result['errors'][] = "Database error: " . $e->getMessage();
        return $result;
    }
}
