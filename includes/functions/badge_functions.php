<?php

function getStatusBadgeClass($status, $type = 'order', $size = 'md') {
    // Get base class by status
    $baseClass = '';
    switch (strtolower($status)) {
        case 'pending':
            $baseClass = 'badge-warning';
            break;
        case 'processing':
            $baseClass = 'badge-info';
            break;
        case 'shipped':
        case 'delivered':
            $baseClass = 'badge-success';
            break;
        case 'cancelled':
        case 'failed':
            $baseClass = 'badge-danger';
            break;
        case 'paid':
            $baseClass = 'badge-success';
            break;
        case 'refunded':
            $baseClass = 'badge-info';
            break;
        default:
            $baseClass = 'badge-secondary';
    }

    // Get size class
    $sizeClass = '';
    switch ($size) {
        case 'sm':
            $sizeClass = 'badge-sm';
            break;
        case 'lg':
            $sizeClass = 'badge-lg';
            break;
        default:
            $sizeClass = '';
    }

    // Get type class
    $typeClass = '';
    switch ($type) {
        case 'order':
            $typeClass = 'order-badge';
            break;
        case 'payment':
            $typeClass = 'payment-badge';
            break;
        case 'shipping':
            $typeClass = 'shipping-badge';
            break;
        default:
            $typeClass = '';
    }

    return trim("badge $baseClass $sizeClass $typeClass");
}

function getTransactionBadgeClass($type) {
    switch (strtolower($type)) {
        case 'sale':
        case 'payment':
            return 'success';
        case 'refund':
            return 'warning';
        case 'expense':
        case 'withdrawal':
            return 'danger';
        case 'deposit':
            return 'info';
        default:
            return 'secondary';
    }
}

function renderStatusBadge($status, $type = 'order', $size = 'md') {
    if (empty($status)) return '<span class="order-badge order-status-pending size-md">Pending</span>';
    
    $badgeClass = getStatusBadgeClass($status, $type, $size);
    $sizeClass = "size-$size";
    
    return sprintf(
        '<span class="%s %s">%s</span>',
        $badgeClass,
        $sizeClass,
        htmlspecialchars(ucfirst(strtolower($status)))
    );
}
