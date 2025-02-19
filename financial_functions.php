<?php

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

function getFinancialMetrics($db, $startDate, $endDate) {
    try {
        validateDateRange($startDate, $endDate);

        // Get revenue and expenses for current period
        $query = "SELECT 
            SUM(CASE WHEN t.transaction_type = 'Sale' THEN t.total_amount ELSE 0 END) as revenue,
            SUM(CASE WHEN t.transaction_type = 'Expense' THEN t.total_amount ELSE 0 END) as expenses,
            SUM(CASE WHEN t.transaction_type = 'Refund' THEN t.total_amount ELSE 0 END) as refunds,
            COUNT(DISTINCT CASE WHEN t.transaction_type = 'Sale' THEN t.order_id END) as total_orders
        FROM transactions t 
        WHERE t.transaction_date BETWEEN :start_date AND :end_date
        AND t.payment_status = 'Completed'";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        // Add error checking for database results
        if ($stmt === false) {
            throw new Exception('Failed to execute query');
        }

        // Get previous period data for comparison
        $prevStartDate = date('Y-m-d', strtotime($startDate . ' -' . dateDiffInDays($startDate, $endDate) . ' days'));
        $prevEndDate = date('Y-m-d', strtotime($startDate . ' -1 day'));
        
        $stmt->execute([
            ':start_date' => $prevStartDate,
            ':end_date' => $prevEndDate
        ]);
        $previous = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate metrics
        $revenue = $current['revenue'] ?? 0;
        $expenses = $current['expenses'] ?? 0;
        $refunds = $current['refunds'] ?? 0;
        $netProfit = $revenue - $expenses - $refunds;
        $profitMargin = $revenue > 0 ? ($netProfit / $revenue) * 100 : 0;
        
        $prevRevenue = $previous['revenue'] ?? 0;
        $revenueGrowth = $prevRevenue > 0 ? (($revenue - $prevRevenue) / $prevRevenue) * 100 : 0;

        $totalOrders = $current['total_orders'] ?? 0;
        $avgOrderValue = $totalOrders > 0 ? $revenue / $totalOrders : 0;

        // Add data validation for calculations
        if (!is_numeric($revenue) || !is_numeric($expenses) || !is_numeric($refunds)) {
            throw new Exception('Invalid numeric data retrieved from database');
        }

        return [
            'revenue' => $revenue,
            'expenses' => $expenses,
            'refunds' => $refunds,
            'net_profit' => $netProfit,
            'profit_margin' => $profitMargin,
            'revenue_growth' => $revenueGrowth,
            'total_orders' => $totalOrders,
            'avg_order_value' => $avgOrderValue
        ];

    } catch (Exception $e) {
        error_log('Financial metrics calculation error: ' . $e->getMessage());
        return [
            'error' => true,
            'message' => 'Failed to calculate financial metrics',
            'details' => $e->getMessage()
        ];
    }
}

/**
 * Calculate daily revenue trends
 */
function getDailyRevenueTrend($db, $startDate, $endDate) {
    try {
        validateDateRange($startDate, $endDate);
        
        $query = "SELECT DATE(transaction_date) as date, 
                         SUM(total_amount) as daily_revenue
                  FROM transactions 
                  WHERE transaction_type = 'Sale' 
                  AND transaction_date BETWEEN :start_date AND :end_date
                  AND payment_status = 'Completed'
                  GROUP BY DATE(transaction_date)
                  ORDER BY date";

        $stmt = $db->prepare($query);
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log('Daily revenue trend calculation error: ' . $e->getMessage());
        return ['error' => true, 'message' => $e->getMessage()];
    }
}

function getCustomerMetrics($db, $startDate, $endDate) {
    try {
        validateDateRange($startDate, $endDate);
        
        $query = "SELECT 
            COUNT(DISTINCT customer_id) as active_customers,
            COUNT(DISTINCT CASE WHEN transaction_type = 'Sale' THEN order_id END) as total_orders
            FROM transactions 
            WHERE transaction_date BETWEEN :start_date AND :end_date
            AND payment_status = 'Completed'";

        $stmt = $db->prepare($query);
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get previous period data
        $prevStartDate = date('Y-m-d', strtotime($startDate . ' -' . dateDiffInDays($startDate, $endDate) . ' days'));
        $prevEndDate = date('Y-m-d', strtotime($startDate . ' -1 day'));
        
        $stmt->execute([':start_date' => $prevStartDate, ':end_date' => $prevEndDate]);
        $previous = $stmt->fetch(PDO::FETCH_ASSOC);

        $customerGrowth = $previous['active_customers'] > 0 ? 
            (($current['active_customers'] - $previous['active_customers']) / $previous['active_customers']) * 100 : 0;

        return [
            'active_customers' => $current['active_customers'],
            'customer_growth' => $customerGrowth,
            'total_orders' => $current['total_orders']
        ];
    } catch (Exception $e) {
        error_log('Customer metrics calculation error: ' . $e->getMessage());
        return ['error' => true, 'message' => $e->getMessage()];
    }
}

function getTransactionHistory($db, $startDate, $endDate, $limit = 10) {
    try {
        validateDateRange($startDate, $endDate);
        
        $query = "SELECT 
            transaction_date,
            transaction_type as type,
            reference_number as reference,
            total_amount as amount,
            payment_status as status
            FROM transactions 
            WHERE transaction_date BETWEEN :start_date AND :end_date
            ORDER BY transaction_date DESC 
            LIMIT :limit";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':start_date', $startDate);
        $stmt->bindValue(':end_date', $endDate);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Transaction history error: ' . $e->getMessage());
        return ['error' => true, 'message' => $e->getMessage()];
    }
}

function getTopProducts($db, $startDate, $endDate, $limit = 5) {
    try {
        validateDateRange($startDate, $endDate);
        
        $query = "SELECT 
            p.product_name,
            COUNT(t.order_id) as order_count,
            SUM(t.total_amount) as total_sales
            FROM transactions t
            JOIN products p ON t.product_id = p.id
            WHERE t.transaction_date BETWEEN :start_date AND :end_date
            AND t.transaction_type = 'Sale'
            GROUP BY p.id
            ORDER BY total_sales DESC
            LIMIT :limit";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':start_date', $startDate);
        $stmt->bindValue(':end_date', $endDate);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Top products calculation error: ' . $e->getMessage());
        return ['error' => true, 'message' => $e->getMessage()];
    }
}

function getCategoryPerformance($db, $startDate, $endDate) {
    try {
        validateDateRange($startDate, $endDate);
        
        $query = "SELECT 
            c.category_name as category,
            COUNT(t.order_id) as order_count,
            SUM(t.total_amount) as total_sales
            FROM transactions t
            JOIN products p ON t.product_id = p.id
            JOIN categories c ON p.category_id = c.id
            WHERE t.transaction_date BETWEEN :start_date AND :end_date
            AND t.transaction_type = 'Sale'
            GROUP BY c.id
            ORDER BY total_sales DESC";

        $stmt = $db->prepare($query);
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Category performance calculation error: ' . $e->getMessage());
        return ['error' => true, 'message' => $e->getMessage()];
    }
}