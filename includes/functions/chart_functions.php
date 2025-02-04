<?php

function formatChartData($data) {
    return [
        'labels' => array_map(function($item) {
            return date('M Y', strtotime($item['month'] . '-01'));
        }, $data),
        'datasets' => [
            [
                'label' => 'Gross Revenue',
                'data' => array_map(function($item) {
                    return floatval($item['revenue']);
                }, $data),
                'borderColor' => '#10B981',
                'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                'fill' => true,
                'tension' => 0.1
            ],
            [
                'label' => 'Expenses & Refunds',
                'data' => array_map(function($item) {
                    return floatval($item['expenses']) + floatval($item['refunds']);
                }, $data),
                'borderColor' => '#EF4444',
                'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                'fill' => true,
                'tension' => 0.1
            ],
            [
                'label' => 'Net Profit',
                'data' => array_map(function($item) {
                    return floatval($item['net_profit']);
                }, $data),
                'borderColor' => '#4F46E5',
                'borderDash' => [5, 5],
                'fill' => false,
                'tension' => 0.1
            ]
        ]
    ];
}
