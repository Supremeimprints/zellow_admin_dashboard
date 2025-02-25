<?php

/**
 * Get badge class based on status and type
 * @param string $status Status value
 * @param string $type Type of status (order|service|payment)
 * @return string Bootstrap badge class
 */
function getStatusBadgeClass($status, $type = 'order') {
    $status = strtolower($status);
    
    return match($type) {
        'order' => match($status) {
            'pending' => 'bg-warning text-dark',
            'processing' => 'bg-info text-white',
            'shipped' => 'bg-primary text-white',
            'delivered' => 'bg-success text-white',
            'cancelled' => 'bg-danger text-white',
            default => 'bg-secondary text-white'
        },
        'service' => match($status) {
            'pending' => 'bg-warning text-dark',
            'in_progress' => 'bg-info text-white',
            'completed' => 'bg-success text-white',
            'cancelled' => 'bg-danger text-white',
            default => 'bg-secondary text-white'
        },
        'payment' => match($status) {
            'pending' => 'bg-warning text-dark',
            'paid' => 'bg-success text-white',
            'refunded' => 'bg-info text-white',
            'failed' => 'bg-danger text-white',
            default => 'bg-secondary text-white'
        },
        default => 'bg-secondary text-white'
    };
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
