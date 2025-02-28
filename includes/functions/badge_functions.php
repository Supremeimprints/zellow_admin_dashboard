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
