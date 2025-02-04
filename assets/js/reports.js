document.addEventListener("DOMContentLoaded", function () {
    // Chart color definitions
    const chartColors = {
        revenue: '#10B981',  // Green
        expenses: '#EF4444', // Red
        refunds: '#F59E0B',  // Orange
        profit: '#4F46E5'    // Indigo
    };

    // Initialize date range picker with a single input
    $('#dateRange').daterangepicker({
        startDate: moment().startOf('month'),
        endDate: moment().endOf('month'),
        opens: 'left',
        locale: {
            format: 'YYYY-MM-DD'
        }
    });

    // Initialize DataTables
    initializeDataTables();

    // Handle filter button click
    $('#filterBtn').click(function() {
        const startDate = $('input[name="daterangepicker_start"]').val();
        const endDate = $('input[name="daterangepicker_end"]').val();
        
        if (startDate && endDate) {
            updateAllMetrics({
                start: startDate,
                end: endDate
            });
        } else {
            alert('Please select both start and end dates');
        }
    });

    // Initial load of metrics and charts
    updateAllMetrics();

    // Set up auto-refresh every 30 seconds
    setInterval(() => updateAllMetrics(), 30000);
});

// Get date range based on filter selection
function getDateRangeFromFilter(filter) {
    const today = moment();
    
    switch(filter) {
        case 'week':
            return {
                start: today.clone().startOf('week').format('YYYY-MM-DD'),
                end: today.clone().endOf('week').format('YYYY-MM-DD')
            };
        case 'month':
            return {
                start: today.clone().startOf('month').format('YYYY-MM-DD'),
                end: today.clone().endOf('month').format('YYYY-MM-DD')
            };
        case 'year':
            return {
                start: today.clone().startOf('year').format('YYYY-MM-DD'),
                end: today.clone().endOf('year').format('YYYY-MM-DD')
            };
        default:
            return null;
    }
}

// Update all metrics and charts
function updateAllMetrics(dateRange = null) {
    let url = 'fetch_metrics.php';
    if (dateRange) {
        url += `?start=${dateRange.start}&end=${dateRange.end}`;
    }

    $.ajax({
        url: url,
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            updateMetricCards(data);
            updateCharts(data);
            updateTables(data);
        },
        error: function(xhr, status, error) {
            console.error('Error fetching metrics:', error);
            alert('Failed to update metrics. Please try again later.');
        }
    });
}

// Update metric cards at the top
function updateMetricCards(data) {
    $('#totalCustomers').text(data.total_customers.toLocaleString());
    $('#monthlySales').text('Ksh.' + data.monthly_sales.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }));
    $('#lowStockItems').text(data.low_stock_items);
    $('#avgOrdersCustomer').text(data.avg_orders_customer.toFixed(1));
    $('#totalRevenue').text('Ksh.' + data.total_revenue.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }));
    $('#netProfit').text('Ksh.' + data.net_profit.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }));
    $('#revenueGrowth').text(data.revenue_growth.toFixed(2) + '%');
}

// Update all charts
function updateCharts(data) {
    if (window.dashboardCharts) {
        window.dashboardCharts.revenue.data = data.revenueData;
        window.dashboardCharts.revenue.update();

        window.dashboardCharts.category.data.labels = data.categoriesData.map(d => d.category);
        window.dashboardCharts.category.data.datasets[0].data = data.categoriesData.map(d => d.revenue);
        window.dashboardCharts.category.update();
    }
}

// Individual chart update functions
function updateSalesChart(salesData) {
    const ctx = document.getElementById('salesChart').getContext('2d');
    if (window.salesChart) {
        window.salesChart.destroy();
    }
    window.salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: salesData.map(item => item.month),
            datasets: [{
                label: 'Monthly Sales',
                data: salesData.map(item => item.total_sales),
                borderColor: '#4e73df',
                tension: 0.3,
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function updateProductsChart(productsData) {
    const ctx = document.getElementById('productsChart').getContext('2d');
    if (window.productsChart) {
        window.productsChart.destroy();
    }
    window.productsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: productsData.map(item => item.product_name),
            datasets: [{
                label: 'Revenue',
                data: productsData.map(item => item.total_revenue),
                backgroundColor: '#1cc88a'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

// Initialize DataTables with export buttons
function initializeDataTables() {
    const tableConfig = {
        dom: 'Bfrtip',
        buttons: [
            'csv', 'excel', 'pdf'
        ],
        pageLength: 10,
        responsive: true
    };

    $('#lowStockTable').DataTable(tableConfig);
    $('#recentTransactionsTable').DataTable(tableConfig);
    $('#topCustomersTable').DataTable(tableConfig);
}

function initializeCharts(data) {
    // Revenue vs Expenses Chart
    const revenueChart = new Chart(
        document.getElementById('revenueChart'),
        {
            type: 'line',
            data: {
                labels: data.revenueData.map(d => d.month),
                datasets: [
                    {
                        label: 'Gross Revenue',
                        data: data.revenueData.map(d => parseFloat(d.revenue)),
                        borderColor: chartColors.revenue,
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.1,
                        order: 1
                    },
                    {
                        label: 'Total Expenses',
                        data: data.revenueData.map(d => parseFloat(d.expenses) + parseFloat(d.refunds)),
                        borderColor: chartColors.expenses,
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: true,
                        tension: 0.1,
                        order: 2
                    },
                    {
                        label: 'Net Profit',
                        data: data.revenueData.map(d => parseFloat(d.net_profit)),
                        borderColor: chartColors.profit,
                        borderDash: [5, 5],
                        fill: false,
                        tension: 0.1,
                        order: 0
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: Ksh.${context.parsed.y.toLocaleString('en-KE', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                })}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => `Ksh.${value.toLocaleString('en-KE')}`
                        }
                    }
                }
            }
        }
    );

    // Sales by Category Chart
    const categoryChart = new Chart(
        document.getElementById('categoryChart'),
        {
            type: 'bar',
            data: {
                labels: data.categoriesData.map(d => d.category),
                datasets: [
                    {
                        label: 'Revenue',
                        data: data.categoriesData.map(d => parseFloat(d.revenue)),
                        backgroundColor: chartColors.profit
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        }
    );

    // Orders per Customer Pie Chart
    const ordersPerCustomerChart = new Chart(
        document.getElementById('ordersPerCustomerChart'),
        {
            type: 'doughnut',
            data: {
                labels: data.ordersData.map(d => d.order_group),
                datasets: [{
                    data: data.ordersData.map(d => d.customer_count),
                    backgroundColor: [
                        chartColors.profit, // Indigo
                        chartColors.revenue, // Green
                        chartColors.refunds, // Yellow
                        chartColors.expenses, // Red
                        '#8B5CF6'  // Purple
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                family: "'Montserrat', sans-serif",
                                size: 11
                            },
                            padding: 10
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value * 100) / total).toFixed(1);
                                const avgItems = data.ordersData[context.dataIndex].avg_items;
                                return [
                                    `${label}: ${value} orders`,
                                    `${percentage}% of total`,
                                    `Avg items: ${avgItems}`
                                ];
                            }
                        }
                    }
                }
            }
        }
    );

    // Store charts in window object for later access
    window.dashboardCharts = {
        revenue: revenueChart,
        category: categoryChart,
        ordersPerCustomer: ordersPerCustomerChart
    };
}