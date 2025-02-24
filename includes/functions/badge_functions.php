<?php

function getStatusBadgeClass($status, $type = 'status') {
    switch ($type) {
        case 'status':
            switch (strtolower($status)) {
                case 'pending': return 'bg-warning text-dark';
                case 'processing': return 'bg-info text-white';
                case 'in_progress': return 'bg-info text-white';
                case 'shipped': return 'bg-primary text-white';
                case 'delivered': case 'completed': return 'bg-success text-white';
                case 'cancelled': return 'bg-danger text-white';
                default: return 'bg-secondary text-white';
            }

        case 'payment':
            switch (strtolower($status)) {
                case 'paid': return 'bg-success';
                case 'pending': return 'bg-warning text-dark';
                case 'failed': return 'bg-danger';
                case 'refunded': return 'bg-info';
                default: return 'bg-secondary';
            }

        case 'service':
            switch (strtolower($status)) {
                case 'pending': return 'bg-warning text-dark';
                case 'in_progress': return 'bg-info';
                case 'completed': return 'bg-success';
                case 'cancelled': return 'bg-danger';
                default: return 'bg-secondary';
            }

        default:
            return 'bg-secondary';
    }
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

function renderStatusBadge($status, $type = 'order') {
    if (empty($status)) {
        return '<span class="badge bg-warning text-dark">Pending</span>';
    }
    
    $badgeClass = getStatusBadgeClass($status, $type);
    
    return sprintf(
        '<span class="badge %s">%s</span>',
        $badgeClass,
        htmlspecialchars(ucfirst(strtolower($status)))
    );
}

?>
