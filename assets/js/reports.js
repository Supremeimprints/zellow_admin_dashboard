document.addEventListener("DOMContentLoaded", function () {
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
    // Update Sales Trend Chart
    updateSalesChart(data.sales_trends);
    
    // Update Products Chart
    updateProductsChart(data.top_products);
    
    // Update Income vs Expenditure Chart
    updateIncomeExpenditureChart(data);
    
    // Update Category Revenue Chart
    updateCategoryChart(data.top_categories);
    
    // Update Customer Distribution Chart
    updateCustomerChart(data.customer_distribution);
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