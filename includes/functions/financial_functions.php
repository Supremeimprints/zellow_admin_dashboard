<?php

// Input validation functions
/**
 * Calculate date difference in days between two dates
 */
function dateDiffInDays($date1, $date2) {
    $diff = strtotime($date2) - strtotime($date1);
    return abs(round($diff / 86400));
}

/**
 * Validates date parameters for financial calculations
 */
function validateDateRange($startDate, $endDate) {
    if (!strtotime($startDate) || !strtotime($endDate)) {
        throw new InvalidArgumentException('Invalid date format');
    }
    if (strtotime($endDate) < strtotime($startDate)) {
        throw new InvalidArgumentException('End date must be after start date');
    }
}

// Financial calculation functions
/**
 * Get financial metrics for the specified date range
 */
function getFinancialMetrics($db, $startDate, $endDate) {
    try {
        validateDateRange($startDate, $endDate);
        
        $startDateTime = $startDate . ' 00:00:00';
        $endDateTime = $endDate . ' 23:59:59';
        
        // Updated revenue query to include refunds
        $revenueQuery = "SELECT 
            COUNT(DISTINCT CASE WHEN o.payment_status = 'Paid' THEN o.order_id END) as total_orders,
            COALESCE(SUM(CASE 
                WHEN o.payment_status = 'Paid' THEN o.total_amount
                ELSE 0
            END), 0) as revenue,
            COALESCE(SUM(CASE 
                WHEN o.payment_status = 'Refunded' THEN o.total_amount
                ELSE 0
            END), 0) as refunded_amount,
            COALESCE(SUM(CASE 
                WHEN o.payment_status = 'Paid' AND o.coupon_id IS NOT NULL 
                THEN o.discount_amount 
                ELSE 0
            END), 0) as total_discounts,
            COALESCE(SUM(CASE 
                WHEN o.payment_status = 'Refunded' AND o.coupon_id IS NOT NULL 
                THEN o.discount_amount 
                ELSE 0
            END), 0) as refunded_discounts
            FROM orders o
            WHERE o.order_date BETWEEN :start_date AND :end_date";
        
        $stmt = $db->prepare($revenueQuery);
        $stmt->execute([
            ':start_date' => $startDateTime,
            ':end_date' => $endDateTime
        ]);
        $revenueData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate metrics with coupon adjustments
        $revenue = floatval($revenueData['revenue']);
        $refunds = floatval($revenueData['refunded_amount']);
        $totalDiscounts = floatval($revenueData['total_discounts']);
        $refundedDiscounts = floatval($revenueData['refunded_discounts']);
        $netRevenue = $revenue - $totalDiscounts;
        $totalOrders = intval($revenueData['total_orders']);
        
        // Adjusted net profit calculation
        $netProfit = $netRevenue - $refunds + $refundedDiscounts;
        $profitMargin = $revenue > 0 ? ($netProfit / $revenue) * 100 : 0;
        $avgOrderValue = $totalOrders > 0 ? $revenue / $totalOrders : 0;

        // Get previous period data for comparison
        $prevStartDate = date('Y-m-d H:i:s', strtotime($startDate . ' -' . dateDiffInDays($startDate, $endDate) . ' days'));
        $prevEndDate = date('Y-m-d H:i:s', strtotime($startDate . ' -1 second'));
        
        $stmt = $db->prepare($revenueQuery);
        $stmt->execute([
            ':start_date' => $prevStartDate,
            ':end_date' => $prevEndDate
        ]);
        $previousRevenue = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['revenue' => 0];
        
        $revenueGrowth = floatval($previousRevenue['revenue']) > 0 ? 
            (($revenue - floatval($previousRevenue['revenue'])) / floatval($previousRevenue['revenue'])) * 100 : 0;

        return [
            'revenue' => $revenue,
            'net_revenue' => $netRevenue,
            'discounts' => $totalDiscounts,
            'refunds' => $refunds,
            'net_profit' => $netProfit,
            'profit_margin' => $profitMargin,
            'revenue_growth' => $revenueGrowth,
            'total_orders' => $totalOrders,
            'avg_order_value' => $avgOrderValue,
            'error' => false
        ];

    } catch (Exception $e) {
        error_log('Financial metrics calculation error: ' . $e->getMessage());
        // Return default values instead of false
        return [
            'revenue' => 0,
            'net_revenue' => 0,
            'discounts' => 0,
            'refunds' => 0,
            'net_profit' => 0,
            'profit_margin' => 0,
            'revenue_growth' => 0,
            'total_orders' => 0,
            'avg_order_value' => 0,
            'error' => true,
            'message' => $e->getMessage()
        ];
    }
}

function getCustomerMetrics($db, $startDate, $endDate) {
    try {
        validateDateRange($startDate, $endDate);
        
        $startDateTime = $startDate . ' 00:00:00';
        $endDateTime = $endDate . ' 23:59:59';
        
        // Get active customers who have made orders
        $query = "SELECT 
            COUNT(DISTINCT o.email) as active_customers,
            COUNT(DISTINCT o.order_id) as total_orders
            FROM orders o
            WHERE o.order_date BETWEEN :start_date AND :end_date
            AND o.status != 'Cancelled'
            AND o.payment_status = 'Paid'";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':start_date' => $startDateTime,
            ':end_date' => $endDateTime
        ]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'active_customers' => 0,
            'total_orders' => 0
        ];

        // Get previous period data
        $prevStartDate = date('Y-m-d H:i:s', strtotime($startDate . ' -' . dateDiffInDays($startDate, $endDate) . ' days'));
        $prevEndDate = date('Y-m-d H:i:s', strtotime($startDate . ' -1 second'));
        
        $stmt->execute([
            ':start_date' => $prevStartDate,
            ':end_date' => $prevEndDate
        ]);
        $previous = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'active_customers' => 0,
            'total_orders' => 0
        ];

        // Calculate growth
        $currentCustomers = intval($current['active_customers']);
        $previousCustomers = intval($previous['active_customers']);
        $customerGrowth = $previousCustomers > 0 ? 
            (($currentCustomers - $previousCustomers) / $previousCustomers) * 100 : 0;

        return [
            'active_customers' => $currentCustomers,
            'customer_growth' => $customerGrowth,
            'total_orders' => intval($current['total_orders']),
            'error' => false
        ];

    } catch (Exception $e) {
        error_log('Customer metrics calculation error: ' . $e->getMessage());
        return [
            'active_customers' => 0,
            'customer_growth' => 0,
            'total_orders' => 0,
            'error' => true,
            'message' => $e->getMessage()
        ];
    }
}

function getRevenueData($db, $startDate, $endDate) {
    try {
        validateDateRange($startDate, $endDate);
        
        $startDateTime = $startDate . ' 00:00:00';
        $endDateTime = $endDate . ' 23:59:59';
        
        $query = "SELECT 
            DATE(o.order_date) as period,
            COALESCE(SUM(o.total_amount), 0) as revenue,
            COALESCE(SUM(e.amount), 0) as expenses
            FROM orders o
            LEFT JOIN expenses e ON DATE(e.expense_date) = DATE(o.order_date)
            WHERE o.order_date BETWEEN :start_date AND :end_date
            AND o.payment_status = 'Paid'
            GROUP BY DATE(o.order_date)
            ORDER BY period ASC";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':start_date' => $startDateTime,
            ':end_date' => $endDateTime
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log('Revenue data calculation error: ' . $e->getMessage());
        return [
            'error' => true,
            'message' => $e->getMessage()
        ];
    }
}

function getTransactionHistory($db, $startDate, $endDate, $limit = 10) {
    try {
        validateDateRange($startDate, $endDate);
        
        $query = "SELECT 
            t.transaction_date,
            t.transaction_type,
            t.reference_id,
            t.order_id,
            t.total_amount as amount,
            t.payment_status,
            t.description
            FROM (
                -- Regular order payments
                SELECT 
                    order_date as transaction_date,
                    'Payment' as transaction_type,
                    COALESCE(transaction_id, CONCAT('ORD-', order_id)) as reference_id,
                    order_id,
                    total_amount,
                    payment_status,
                    email as description
                FROM orders 
                WHERE payment_status = 'Paid'
                AND order_date BETWEEN :start_date AND :end_date
                
                UNION ALL
                
                -- Refund transactions
                SELECT 
                    order_date as transaction_date,
                    'Refund' as transaction_type,
                    CONCAT('REF-', order_id) as reference_id,
                    order_id,
                    -total_amount as total_amount,
                    'Refunded' as payment_status,
                    email as description
                FROM orders
                WHERE payment_status = 'Refunded'
                AND order_date BETWEEN :start_date AND :end_date

                UNION ALL

                -- Coupon discounts
                SELECT 
                    o.order_date as transaction_date,
                    'Discount' as transaction_type,
                    CONCAT('CPN-', o.order_id) as reference_id,
                    o.order_id,
                    -o.discount_amount as total_amount,
                    'Applied' as payment_status,
                    CONCAT('Coupon: ', COALESCE(c.code, 'Unknown')) as description
                FROM orders o
                LEFT JOIN coupons c ON o.coupon_id = c.coupon_id
                WHERE o.discount_amount > 0 
                AND o.payment_status = 'Paid'
                AND o.order_date BETWEEN :start_date AND :end_date
            ) t
            ORDER BY t.transaction_date DESC
            LIMIT :limit";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':start_date', $startDate . ' 00:00:00');
        $stmt->bindValue(':end_date', $endDate . ' 23:59:59');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log('Transaction history error: ' . $e->getMessage());
        return [];
    }
}

function getCategoryPerformance($db, $startDate, $endDate) {
    try {
        validateDateRange($startDate, $endDate);
        
        $query = "SELECT 
            COALESCE(c.category_name, 'Uncategorized') as category,
            COUNT(DISTINCT oi.order_id) as order_count,
            SUM(oi.quantity * oi.unit_price) as total_sales
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN products p ON oi.product_id = p.product_id
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE o.order_date BETWEEN :start_date AND :end_date
            AND o.payment_status = 'Paid'
            AND o.status NOT IN ('Cancelled')
            GROUP BY c.category_id, c.category_name
            HAVING total_sales > 0
            ORDER BY total_sales DESC";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':start_date' => $startDate . ' 00:00:00',
            ':end_date' => $endDate . ' 23:59:59'
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Handle empty results
        if (empty($results)) {
            return [
                [
                    'category' => 'No Sales Data',
                    'order_count' => 0,
                    'total_sales' => 1, // Use 1 to avoid division by zero
                    'percentage' => 100
                ]
            ];
        }

        // Calculate total sales for percentage
        $totalSales = array_sum(array_column($results, 'total_sales'));
        
        // Add percentages and format data
        foreach ($results as &$row) {
            $row['percentage'] = round(($row['total_sales'] / $totalSales) * 100, 1);
            $row['total_sales'] = floatval($row['total_sales']);
            $row['order_count'] = intval($row['order_count']);
        }

        return $results;

    } catch (Exception $e) {
        error_log('Category performance calculation error: ' . $e->getMessage());
        return [
            [
                'category' => 'Error Loading Data',
                'order_count' => 0,
                'total_sales' => 1,
                'percentage' => 100
            ]
        ];
    }
}

function getTopProducts($db, $startDate, $endDate, $limit = 5) {
    try {
        validateDateRange($startDate, $endDate);
        
        $startDateTime = $startDate . ' 00:00:00';
        $endDateTime = $endDate . ' 23:59:59';
        
        $query = "SELECT 
            p.product_id,
            p.name as product_name,
            COUNT(DISTINCT o.order_id) as order_count,
            SUM(oi.subtotal) as total_sales,
            COUNT(oi.id) as units_sold
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN products p ON oi.product_id = p.product_id
            WHERE o.order_date BETWEEN :start_date AND :end_date
            AND o.payment_status = 'Paid'
            AND o.status != 'Cancelled'
            GROUP BY p.product_id, p.name
            ORDER BY total_sales DESC
            LIMIT :limit";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':start_date', $startDateTime);
        $stmt->bindValue(':end_date', $endDateTime);
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return !empty($results) ? $results : [
            [
                'product_name' => 'No Data',
                'order_count' => 0,
                'total_sales' => 0,
                'units_sold' => 0
            ]
        ];

    } catch (Exception $e) {
        error_log('Top products calculation error: ' . $e->getMessage());
        return [
            [
                'product_name' => 'Error',
                'order_count' => 0,
                'total_sales' => 0,
                'units_sold' => 0
            ]
        ];
    }
}
