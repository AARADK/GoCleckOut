<?php
require_once '../../backend/database/db_connection.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'trader') {
    header('Location: ../login/login_portal.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

if (!$conn) {
    die("Database connection failed");
}

// Get trader's shops
$shops_sql = "SELECT shop_id, shop_name FROM shops WHERE user_id = :user_id";
$shops_stmt = oci_parse($conn, $shops_sql);
oci_bind_by_name($shops_stmt, ":user_id", $user_id);
oci_execute($shops_stmt);

$shops = [];
while ($shop = oci_fetch_array($shops_stmt, OCI_ASSOC)) {
    $shops[] = $shop;
}
oci_free_statement($shops_stmt);

// Get sales data for the last 30 days
$sales_sql = "SELECT 
    TRUNC(o.order_date) as sale_date,
    NVL(SUM(oi.quantity), 0) as total_quantity,
    NVL(SUM(oi.quantity * oi.unit_price), 0) as total_revenue,
    COUNT(DISTINCT o.order_id) as total_orders
FROM orders o
INNER JOIN order_item oi ON o.order_id = oi.order_id
INNER JOIN product p ON oi.product_id = p.product_id
WHERE p.user_id = :user_id
AND o.order_date >= TRUNC(SYSDATE) - 30
GROUP BY TRUNC(o.order_date)
ORDER BY sale_date";

$sales_stmt = oci_parse($conn, $sales_sql);
oci_bind_by_name($sales_stmt, ":user_id", $user_id);
oci_execute($sales_stmt);

$sales_data = [];
while ($row = oci_fetch_array($sales_stmt, OCI_ASSOC)) {
    $sales_data[] = $row;
}
oci_free_statement($sales_stmt);

// Get top selling products
$top_products_sql = "SELECT * FROM (
    SELECT 
        p.product_name,
        NVL(SUM(oi.quantity), 0) as total_quantity,
        NVL(SUM(oi.quantity * oi.unit_price), 0) as total_revenue
    FROM product p
    INNER JOIN order_item oi ON p.product_id = oi.product_id
    INNER JOIN orders o ON oi.order_id = o.order_id
    WHERE p.user_id = :user_id
    AND o.order_date >= TRUNC(SYSDATE) - 30
    GROUP BY p.product_name
    ORDER BY total_quantity DESC
) WHERE ROWNUM <= 5";

$top_products_stmt = oci_parse($conn, $top_products_sql);
oci_bind_by_name($top_products_stmt, ":user_id", $user_id);
oci_execute($top_products_stmt);

$top_products = [];
while ($row = oci_fetch_array($top_products_stmt, OCI_ASSOC)) {
    $top_products[] = $row;
}
oci_free_statement($top_products_stmt);

oci_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports | GoCleckOut</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 70vh;
            width: 100%;
        }
        .nav-tabs .nav-link {
            color: #6c757d;
        }
        .nav-tabs .nav-link.active {
            color: #0d6efd;
            font-weight: 500;
        }
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <?php include 'trader_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'trader_sidebar.php'; ?>
            </div>
            <div class="col-md-9 col-lg-10">
                <div class="container-fluid py-4">
                    <h1 class="h3 mb-4">Sales Reports</h1>

                    <!-- Stats Cards -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Total Revenue (30 Days)</h6>
                                    <h3 class="card-title mb-0">
                                        £<?= number_format(array_sum(array_column($sales_data, 'TOTAL_REVENUE')), 2) ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Total Orders (30 Days)</h6>
                                    <h3 class="card-title mb-0">
                                        <?= number_format(array_sum(array_column($sales_data, 'TOTAL_ORDERS'))) ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Total Items Sold (30 Days)</h6>
                                    <h3 class="card-title mb-0">
                                        <?= number_format(array_sum(array_column($sales_data, 'TOTAL_QUANTITY'))) ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Average Order Value</h6>
                                    <h3 class="card-title mb-0">
                                        £<?= number_format(array_sum(array_column($sales_data, 'TOTAL_REVENUE')) / max(1, array_sum(array_column($sales_data, 'TOTAL_ORDERS'))), 2) ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Chart -->
                    <div class="card">
                        <div class="card-body">
                            <ul class="nav nav-tabs" id="timeRangeTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="daily-tab" data-bs-toggle="tab" data-bs-target="#daily" type="button" role="tab">Daily</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="weekly-tab" data-bs-toggle="tab" data-bs-target="#weekly" type="button" role="tab">Weekly</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="monthly-tab" data-bs-toggle="tab" data-bs-target="#monthly" type="button" role="tab">Monthly</button>
                                </li>
                            </ul>
                            <div class="tab-content mt-3">
                                <div class="tab-pane fade show active" id="daily" role="tabpanel">
                                    <div class="chart-container">
                                        <canvas id="dailyChart"></canvas>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="weekly" role="tabpanel">
                                    <div class="chart-container">
                                        <canvas id="weeklyChart"></canvas>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="monthly" role="tabpanel">
                                    <div class="chart-container">
                                        <canvas id="monthlyChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Products -->
                    <div class="card mt-4">
                        <div class="card-body">
                            <h5 class="card-title">Top Selling Products (Last 30 Days)</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Quantity Sold</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_products as $product): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($product['PRODUCT_NAME']) ?></td>
                                                <td><?= number_format($product['TOTAL_QUANTITY']) ?></td>
                                                <td>£<?= number_format($product['TOTAL_REVENUE'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Prepare data for charts
        const salesData = <?= json_encode($sales_data) ?>;
        
        // Function to group data by week
        function groupByWeek(data) {
            const weeklyData = {};
            data.forEach(item => {
                const date = new Date(item.SALE_DATE);
                const weekStart = new Date(date);
                weekStart.setDate(date.getDate() - date.getDay());
                const weekKey = weekStart.toISOString().split('T')[0];
                
                if (!weeklyData[weekKey]) {
                    weeklyData[weekKey] = {
                        total_revenue: 0,
                        total_quantity: 0,
                        total_orders: 0
                    };
                }
                
                weeklyData[weekKey].total_revenue += parseFloat(item.TOTAL_REVENUE);
                weeklyData[weekKey].total_quantity += parseInt(item.TOTAL_QUANTITY);
                weeklyData[weekKey].total_orders += parseInt(item.TOTAL_ORDERS);
            });
            
            return Object.entries(weeklyData).map(([date, data]) => ({
                date,
                ...data
            }));
        }

        // Function to group data by month
        function groupByMonth(data) {
            const monthlyData = {};
            data.forEach(item => {
                const date = new Date(item.SALE_DATE);
                const monthKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
                
                if (!monthlyData[monthKey]) {
                    monthlyData[monthKey] = {
                        total_revenue: 0,
                        total_quantity: 0,
                        total_orders: 0
                    };
                }
                
                monthlyData[monthKey].total_revenue += parseFloat(item.TOTAL_REVENUE);
                monthlyData[monthKey].total_quantity += parseInt(item.TOTAL_QUANTITY);
                monthlyData[monthKey].total_orders += parseInt(item.TOTAL_ORDERS);
            });
            
            return Object.entries(monthlyData).map(([date, data]) => ({
                date,
                ...data
            }));
        }

        // Chart configuration
        const chartConfig = {
            type: 'line',
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Value'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.dataset.yAxisID === 'y') {
                                    label += '£' + context.parsed.y.toFixed(2);
                                } else {
                                    label += context.parsed.y;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        };

        // Create daily chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        new Chart(dailyCtx, {
            ...chartConfig,
            data: {
                labels: salesData.map(item => item.SALE_DATE),
                datasets: [
                    {
                        label: 'Revenue',
                        data: salesData.map(item => item.TOTAL_REVENUE),
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Orders',
                        data: salesData.map(item => item.TOTAL_ORDERS),
                        borderColor: 'rgb(255, 99, 132)',
                        tension: 0.1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                ...chartConfig.options,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (£)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Number of Orders'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Create weekly chart
        const weeklyData = groupByWeek(salesData);
        const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
        new Chart(weeklyCtx, {
            ...chartConfig,
            data: {
                labels: weeklyData.map(item => item.date),
                datasets: [
                    {
                        label: 'Revenue',
                        data: weeklyData.map(item => item.total_revenue),
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Orders',
                        data: weeklyData.map(item => item.total_orders),
                        borderColor: 'rgb(255, 99, 132)',
                        tension: 0.1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                ...chartConfig.options,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (£)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Number of Orders'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });

        // Create monthly chart
        const monthlyData = groupByMonth(salesData);
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            ...chartConfig,
            data: {
                labels: monthlyData.map(item => item.date),
                datasets: [
                    {
                        label: 'Revenue',
                        data: monthlyData.map(item => item.total_revenue),
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Orders',
                        data: monthlyData.map(item => item.total_orders),
                        borderColor: 'rgb(255, 99, 132)',
                        tension: 0.1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                ...chartConfig.options,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (£)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Number of Orders'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
