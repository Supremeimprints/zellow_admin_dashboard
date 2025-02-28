<?php

function createPurchaseOrder($db, $data) {
    try {
        $db->beginTransaction();

        // Basic validation
        if (empty($data['supplier_id']) || empty($data['total_amount'])) {
            throw new Exception("Missing required fields");
        }

        // Insert purchase order - match the existing table structure exactly
        $query = "INSERT INTO purchase_orders (
            supplier_id,
            total_amount,
            status,
            created_by,
            order_date,
            payment_status,
            is_fulfilled
        ) VALUES (
            :supplier_id,
            :total_amount,
            'pending',
            :created_by,
            NOW(),
            'unpaid',
            0
        )";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':supplier_id' => $data['supplier_id'],
            ':total_amount' => $data['total_amount'],
            ':created_by' => $_SESSION['id']
        ]);

        $purchaseOrderId = $db->lastInsertId();

        // Insert order items
        if (!empty($data['items'])) {
            $itemStmt = $db->prepare("
                INSERT INTO purchase_order_items (
                    purchase_order_id,
                    product_id,
                    quantity,
                    unit_price
                ) VALUES (?, ?, ?, ?)
            ");

            foreach ($data['items'] as $item) {
                $itemStmt->execute([
                    $purchaseOrderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price']
                ]);
            }
        }

        // Create payment record if payment is provided
        if (!empty($data['payment_amount'])) {
            $paymentStmt = $db->prepare("
                INSERT INTO purchase_payments (
                    purchase_order_id,
                    amount,
                    payment_method,
                    status,
                    payment_date
                ) VALUES (?, ?, ?, 'completed', NOW())
            ");

            $paymentStmt->execute([
                $purchaseOrderId,
                $data['payment_amount'],
                $data['payment_method'] ?? 'Mpesa'
            ]);

            // Update purchase order payment status
            updatePurchaseOrderPaymentStatus($db, $purchaseOrderId);
        }

        $db->commit();
        return $purchaseOrderId;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Error creating purchase order: ' . $e->getMessage());
        throw $e;
    }
}

function updatePurchaseOrderPaymentStatus($db, $purchaseOrderId) {
    try {
        // Get total amount and paid amount
        $query = "SELECT 
            po.total_amount,
            COALESCE(SUM(pp.amount), 0) as amount_paid
            FROM purchase_orders po
            LEFT JOIN purchase_payments pp ON po.purchase_order_id = pp.purchase_order_id
            WHERE po.purchase_order_id = :id
            GROUP BY po.purchase_order_id, po.total_amount";

        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $purchaseOrderId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $status = 'unpaid';
            if ($result['amount_paid'] >= $result['total_amount']) {
                $status = 'paid';
            } elseif ($result['amount_paid'] > 0) {
                $status = 'partial';
            }

            $updateStmt = $db->prepare("UPDATE purchase_orders SET payment_status = ? WHERE purchase_order_id = ?");
            $updateStmt->execute([$status, $purchaseOrderId]);
        }

    } catch (Exception $e) {
        error_log('Error updating purchase order payment status: ' . $e->getMessage());
        throw $e;
    }
}
