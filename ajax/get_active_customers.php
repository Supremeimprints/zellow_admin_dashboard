<?php
session_start();
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT 
            COUNT(DISTINCT c.customer_id) as current_customers,
            (
                SELECT COUNT(DISTINCT c2.customer_id)
                FROM customers c2
                WHERE c2.last_activity BETWEEN 
                    DATE_SUB(CURRENT_DATE, INTERVAL 2 MONTH)
                    AND DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)
                AND c2.status = 'active'
            ) as previous_customers
        FROM customers c
        WHERE c.status = 'active'
        AND (
            c.last_activity >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)
            OR EXISTS (
                SELECT 1 FROM orders o 
                WHERE o.customer_id = c.customer_id
                AND o.order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)
            )
            OR EXISTS (
                SELECT 1 FROM customer_activity ca 
                WHERE ca.customer_id = c.customer_id
                AND ca.activity_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)
            )
        )";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'current' => (int)$result['current_customers'],
        'previous' => (int)$result['previous_customers']
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    error_log("Error in get_active_customers.php: " . $e->getMessage());
}
