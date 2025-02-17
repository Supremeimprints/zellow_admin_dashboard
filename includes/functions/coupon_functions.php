<?php
function calculateCouponSavings($db, $startDate = null, $endDate = null) {
    try {
        $params = [];
        $dateCondition = "";
        
        if ($startDate && $endDate) {
            $dateCondition = "AND o.order_date BETWEEN ? AND ?";
            $params = [$startDate, $endDate];
        }

        $query = "
            SELECT 
                c.coupon_id,
                c.code,
                c.discount_type,
                c.discount_percentage,
                c.discount_value,
                COUNT(DISTINCT o.order_id) as usage_count,
                SUM(o.total_amount + o.shipping_fee) as total_order_amount,
                SUM(o.discount_amount) as total_savings
            FROM coupons c
            INNER JOIN orders o ON c.coupon_id = o.coupon_id
            INNER JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.payment_status = 'Paid'
            AND o.status != 'Cancelled'
            AND oi.status = 'purchased'
            $dateCondition
            GROUP BY 
                c.coupon_id, 
                c.code, 
                c.discount_type, 
                c.discount_percentage,
                c.discount_value
            HAVING usage_count > 0
            ORDER BY total_savings DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Coupon savings calculation: " . print_r($results, true));
        
        // Track expenses and normalize data
        $normalizedResults = array_map(function($result) {
            return array_merge($result, [
                'total_uses' => $result['usage_count'] ?? 0,
                'total_order_amount' => $result['total_order_amount'] ?? 0,
                'total_savings' => $result['total_savings'] ?? 0
            ]);
        }, $results);
        
        // Track expenses for valid savings
        foreach ($normalizedResults as $result) {
            if ($result['total_savings'] > 0) {
                trackCouponExpense($db, $result);
            }
        }
        
        return $normalizedResults;

    } catch (Exception $e) {
        error_log("Error calculating coupon savings: " . $e->getMessage());
        return false;
    }
}

function trackCouponExpense($db, $couponData) {
    try {
        // Ensure required keys exist
        $requiredKeys = ['code', 'total_uses', 'total_order_amount', 'total_savings'];
        foreach ($requiredKeys as $key) {
            if (!isset($couponData[$key])) {
                error_log("Missing required key in coupon data: $key");
                return false;
            }
        }

        $monthYear = date('F Y');
        
        // Check for existing expense
        $checkStmt = $db->prepare("
            SELECT expense_id 
            FROM expenses 
            WHERE category = 'Coupon Discount'
            AND description LIKE :description
            AND MONTH(expense_date) = MONTH(CURRENT_DATE())
            AND YEAR(expense_date) = YEAR(CURRENT_DATE())
        ");
        
        $description = "Coupon {$couponData['code']} - $monthYear";
        $checkStmt->execute([':description' => "%$description%"]);
        
        if (!$checkStmt->fetch()) {
            $insertStmt = $db->prepare("
                INSERT INTO expenses (
                    category,
                    amount,
                    expense_date,
                    description
                ) VALUES (
                    'Coupon Discount',
                    :amount,
                    CURRENT_DATE(),
                    :description
                )
            ");

            $fullDescription = sprintf(
                "Coupon %s - %s savings - Used %d times - Total orders value: Ksh.%s",
                $couponData['code'],
                $monthYear,
                $couponData['total_uses'],
                number_format($couponData['total_order_amount'], 2)
            );

            return $insertStmt->execute([
                ':amount' => $couponData['total_savings'],
                ':description' => $fullDescription
            ]);
        }
        
        return true;

    } catch (Exception $e) {
        error_log("Error tracking coupon expense: " . $e->getMessage());
        return false;
    }
}

function updateCouponMetrics($db) {
    try {
        $db->beginTransaction();

        // Calculate savings for current month's orders
        $query = "
            SELECT 
                c.coupon_id,
                c.code,
                c.discount_type,
                c.discount_percentage,
                COUNT(DISTINCT o.order_id) as usage_count,
                SUM(o.total_amount) as total_order_amount,
                SUM(o.discount_amount) as total_savings
            FROM orders o
            INNER JOIN coupons c ON o.coupon_id = c.coupon_id
            INNER JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.payment_status = 'Paid'
            AND o.status != 'Cancelled'
            AND oi.status = 'purchased'
            AND MONTH(o.order_date) = MONTH(CURRENT_DATE())
            AND YEAR(o.order_date) = YEAR(CURRENT_DATE())
            GROUP BY 
                c.coupon_id,
                c.code,
                c.discount_type,
                c.discount_percentage
            HAVING total_savings > 0";

        $stmt = $db->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $result) {
            trackCouponExpense($db, $result);
        }

        $db->commit();
        return true;

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error updating coupon metrics: " . $e->getMessage());
        return false;
    }
}
