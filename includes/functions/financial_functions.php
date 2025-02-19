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
        
        // Add time component to dates for full day coverage
        $startDateTime = $startDate . ' 00:00:00';
        $endDateTime = $endDate . ' 23:59:59';
        
        // Get revenue from paid orders
        $revenueQuery = "SELECT 
            COUNT(DISTINCT o.order_id) as total_orders,
            SUM(o.total_amount) as revenue
            FROM orders o
            WHERE o.order_date BETWEEN :start_date AND :end_date
            AND o.payment_status = 'Paid'";
        
        $stmt = $db->prepare($revenueQuery);
        if (!$stmt->execute([
            ':start_date' => $startDateTime,
            ':end_date' => $endDateTime
        ])) {
            throw new Exception('Failed to execute revenue query');
        }
        
        $revenueData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_orders' => 0,
            'revenue' => 0
        ];

        // Get total expenses
        $expensesQuery = "SELECT COALESCE(SUM(amount), 0) as total_expenses 
                         FROM expenses 
                         WHERE expense_date BETWEEN :start_date AND :end_date";
        
        $stmt = $db->prepare($expensesQuery);
        $stmt->execute([
            ':start_date' => $startDateTime,
            ':end_date' => $endDateTime
        ]);
        $expensesData = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_expenses' => 0];

        // Get refunds
        $refundsQuery = "SELECT COALESCE(SUM(total_amount), 0) as total_refunds 
                        FROM orders 
                        WHERE order_date BETWEEN :start_date AND :end_date
                        AND status = 'Refunded'";
        
        $stmt = $db->prepare($refundsQuery);
        $stmt->execute([
            ':start_date' => $startDateTime,
            ':end_date' => $endDateTime
        ]);
        $refundsData = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_refunds' => 0];

        // Calculate metrics with safe defaults
        $revenue = floatval($revenueData['revenue']);
        $expenses = floatval($expensesData['total_expenses']);
        $refunds = floatval($refundsData['total_refunds']);
        $totalOrders = intval($revenueData['total_orders']);
        
        $netProfit = $revenue - $expenses - $refunds;
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
            'expenses' => $expenses,
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
            'expenses' => 0,
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
        
        // Get order-related transactions
        $orderQuery = "SELECT 
            o.order_date as transaction_date,
            CASE 
                WHEN o.payment_status = 'Refunded' THEN 'Refund'
                ELSE 'Payment'
            END as transaction_type,
            o.order_id,
            COALESCE(o.transaction_id, CONCAT('ORD-', o.order_id)) as reference_id,
            CASE 
                WHEN o.payment_status = 'Refunded' THEN -o.total_amount
                ELSE o.total_amount
            END as amount,
            o.payment_status,
            o.email as description,
            'order' as source
            FROM orders o
            WHERE o.order_date BETWEEN :start_date AND :end_date
            AND o.payment_status IN ('Paid', 'Refunded')";

        // Get expense transactions
        $expenseQuery = "SELECT 
            e.expense_date as transaction_date,
            'Expense' as transaction_type,
            NULL as order_id,
            CONCAT('EXP-', e.expense_id) as reference_id,
            -e.amount as amount,
            'completed' as payment_status,
            e.category as description,
            'expense' as source
            FROM expenses e
            WHERE e.expense_date BETWEEN :start_date AND :end_date";

        // Combine queries
        $query = "($orderQuery) UNION ($expenseQuery)
                 ORDER BY transaction_date DESC
                 LIMIT :limit";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':start_date', $startDate . ' 00:00:00');
        $stmt->bindValue(':end_date', $endDate . ' 23:59:59');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process results
        return array_map(function($transaction) {
            return [
                'transaction_date' => $transaction['transaction_date'],
                'transaction_type' => $transaction['transaction_type'],
                'reference_id' => $transaction['reference_id'],
                'order_id' => $transaction['order_id'], // Will be NULL for expenses
                'amount' => $transaction['amount'],
                'payment_status' => $transaction['payment_status'],
                'description' => $transaction['description'],
                'source' => $transaction['source']
            ];
        }, $results);

    } catch (Exception $e) {
        error_log('Transaction history error: ' . $e->getMessage());
        return [];
    }
}

function getCategoryPerformance($db, $startDate, $endDate) {
    try {
        validateDateRange($startDate, $endDate);
        
        $query = "SELECT 
            COALESCE(p.category, 'Uncategorized') as category,
            COUNT(DISTINCT o.order_id) as order_count,
            COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_sales
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN products p ON oi.product_id = p.product_id
            WHERE o.order_date BETWEEN :start_date AND :end_date
            AND o.payment_status = 'Paid'
            AND o.status != 'Refunded'
            GROUP BY p.category
            HAVING total_sales > 0
            ORDER BY total_sales DESC";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':start_date' => $startDate . ' 00:00:00',
            ':end_date' => $endDate . ' 23:59:59'
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($results)) {
            return [[
                'category' => 'No Data',
                'order_count' => 0,
                'total_sales' => 0
            ]];
        }

        // Calculate percentages
        $totalSales = array_sum(array_column($results, 'total_sales'));
        foreach ($results as &$row) {
            $row['percentage'] = $totalSales > 0 ? 
                round(($row['total_sales'] / $totalSales) * 100, 1) : 0;
        }

        return $results;

    } catch (Exception $e) {
        error_log('Category performance calculation error: ' . $e->getMessage());
        return [[
            'category' => 'Error Loading Data',
            'order_count' => 0,
            'total_sales' => 1, // Use 1 to avoid NaN in percentage calculations
            'percentage' => 100
        ]];
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
