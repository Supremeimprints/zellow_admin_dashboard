<?php

function getStatusBadgeClass($status, $type = 'status') {
    $classes = [
        'status' => [
            'Pending' => 'bg-warning',
            'Processing' => 'bg-info',
            'Shipped' => 'bg-primary',
            'Delivered' => 'bg-success',
            'Cancelled' => 'bg-danger'
        ],
        'payment' => [
            'Pending' => 'bg-warning',
            'Paid' => 'bg-success',
            'Failed' => 'bg-danger',
            'Refunded' => 'bg-info'
        ],
        'service' => [
            'pending' => 'bg-warning',
            'in_progress' => 'bg-info',
            'completed' => 'bg-success',
            'cancelled' => 'bg-danger'
        ]
    ];

    return $classes[$type][$status] ?? 'bg-secondary';
}

function getTransactionBadgeClass($method) {
    $classes = [
        'Credit Card' => 'bg-info',
        'Mpesa' => 'bg-success',
        'Cash' => 'bg-secondary',
        'Airtel Money' => 'bg-danger',
        'Bank Transfer' => 'bg-primary',
        'Other' => 'bg-warning'
    ];
    
    return $classes[$method] ?? 'bg-secondary';
}

/**
 * Returns the appropriate CSS class for transaction type badges
 * @param string $type The transaction type
 * @return string The CSS class for the badge
 */
function getTransactionTypeBadgeClass($type) {
    switch ($type) {
        case 'Customer Payment':
            return 'bg-success text-white';
        case 'Refund':
            return 'bg-warning text-dark';
        case 'Expense':
            return 'bg-danger text-white';
        default:
            return 'bg-secondary text-white';
    }
}

/**
 * Returns the appropriate Font Awesome icon for a transaction type
 * 
 * @param string $type The transaction type
 * @return string The Font Awesome icon class name
 */
function getTransactionTypeIcon($type) {
    switch ($type) {
        case 'Customer Payment':
            return 'shopping-cart';
        case 'Refund':
            return 'undo';
        case 'Expense':
            return 'file-invoice';
        default:
            return 'circle'; // Default icon
    }
}

/**
 * Returns the appropriate CSS class for transaction status badges
 * @param string $status The transaction status
 * @return string The CSS class for the badge
 */
function getTransactionStatusBadgeClass($status) {
    switch ($status) {
        case 'completed':
            return 'bg-success text-white';
        case 'pending':
            return 'bg-warning text-dark';
        case 'failed':
            return 'bg-danger text-white';
        default:
            return 'bg-secondary text-white';
    }
}

/**
 * Returns the appropriate CSS class for payment method badges
 * 
 * @param string $method The payment method
 * @return string The CSS class for the badge
 */
function getPaymentMethodBadgeClass($method) {
    switch ($method) {
        case 'Credit Card':
            return 'bg-primary text-white';
        case 'Mpesa':
            return 'bg-success text-white';
        case 'Cash':
            return 'bg-warning text-dark';
        case 'Airtel Money':
            return 'bg-danger text-white';
        case 'Bank Transfer':
            return 'bg-info text-dark';
        case 'Other':
        default:
            return 'bg-secondary text-white';
    }
}

function renderStatusBadge($status, $type = 'order', $size = 'md') {
    if (empty($status)) return '<span class="order-badge" data-status="pending">Pending</span>';
    
    $badgeClass = $type === 'payment' ? 'payment-badge' : 'order-badge';
    $sizeClass = "size-$size";
    $status = strtolower($status);
    
    return sprintf(
        '<span class="%s %s" data-status="%s">%s</span>',
        $badgeClass,
        $sizeClass,
        $status,
        ucfirst($status)
    );
}
