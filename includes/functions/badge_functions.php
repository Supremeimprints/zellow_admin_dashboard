<?php

function getStatusBadgeClass($status, $type = 'order') {
    if ($type === 'order') {
        return match (strtolower($status)) {
            'pending' => 'order-badge order-status-pending',
            'processing' => 'order-badge order-status-processing',
            'shipped' => 'order-badge order-status-shipped',
            'delivered' => 'order-badge order-status-delivered',
            'cancelled' => 'order-badge order-status-cancelled',
            default => 'order-badge order-status-pending'
        };
    }

    if ($type === 'payment') {
        return match (strtolower($status)) {
            'paid' => 'order-badge payment-status-paid',
            'pending' => 'order-badge payment-status-pending',
            'failed' => 'order-badge payment-status-failed',
            'refunded' => 'order-badge payment-status-refunded',
            default => 'order-badge payment-status-pending'
        };
    }

    return 'order-badge order-status-pending';
}

function getTransactionBadgeClass($type) {
    return match (strtolower($type)) {
        'sale' => 'success',
        'payment' => 'success',
        'refund' => 'warning',
        'expense' => 'danger',
        'withdrawal' => 'danger',
        'deposit' => 'info',
        default => 'secondary'
    };
}

function renderStatusBadge($status, $type = 'order', $size = 'md') {
    if (empty($status)) return '<span class="order-badge order-status-pending size-md">Pending</span>';
    
    $badgeClass = getStatusBadgeClass($status, $type);
    $sizeClass = "size-$size";
    
    return sprintf(
        '<span class="%s %s">%s</span>',
        $badgeClass,
        $sizeClass,
        htmlspecialchars(ucfirst(strtolower($status)))
    );
}
