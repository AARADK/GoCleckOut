<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login_portal.php?sign_in=true");
    exit;
}

include '../../backend/database/connect.php';
$conn = getDBConnection();

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get total sales for the period
$sales_sql = "
    SELECT 
        COUNT(DISTINCT o.order_id) as total_orders,
        SUM(o.total_amount) as total_sales,
        COUNT(DISTINCT o.user_id) as total_customers,
        COUNT(DISTINCT s.shop_id) as total_shops
    FROM orders o
    JOIN order_item oi ON o.order_id = oi.order_id
    JOIN product p ON oi.product_id = p.product_id
    JOIN shops s ON p.shop_id = s.shop_id
    WHERE o.order_date BETWEEN TO_DATE(:start_date, 'YYYY-MM-DD') AND TO_DATE(:end_date, 'YYYY-MM-DD')
    AND o.order_status = 'completed'
";

$sales_stmt = oci_parse($conn, $sales_sql);
oci_bind_by_name($sales_stmt, ':start_date', $start_date);
oci_bind_by_name($sales_stmt, ':end_date', $end_date);
oci_execute($sales_stmt);
$sales_data = oci_fetch_assoc($sales_stmt);

// Get daily sales data
$daily_sales_sql = "
    SELECT 
        TRUNC(o.order_date) as sale_date,
        COUNT(DISTINCT o.order_id) as order_count,
        SUM(o.total_amount) as total_amount,
        COUNT(DISTINCT o.user_id) as customer_count
    FROM orders o
    WHERE o.order_date BETWEEN TO_DATE(:start_date, 'YYYY-MM-DD') AND TO_DATE(:end_date, 'YYYY-MM-DD')
    AND o.order_status = 'completed'
    GROUP BY TRUNC(o.order_date)
    ORDER BY TRUNC(o.order_date)
";

$daily_sales_stmt = oci_parse($conn, $daily_sales_sql);
oci_bind_by_name($daily_sales_stmt, ':start_date', $start_date);
oci_bind_by_name($daily_sales_stmt, ':end_date', $end_date);
oci_execute($daily_sales_stmt);

// Get top selling products
$top_products_sql = "
    SELECT * FROM (
        SELECT 
            p.product_name,
            s.shop_name,
            COUNT(oi.order_product_id) as times_ordered,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.quantity * oi.unit_price) as total_revenue
        FROM order_item oi
        JOIN product p ON oi.product_id = p.product_id
        JOIN shops s ON p.shop_id = s.shop_id
        JOIN orders o ON oi.order_id = o.order_id
        WHERE o.order_date BETWEEN TO_DATE(:start_date, 'YYYY-MM-DD') AND TO_DATE(:end_date, 'YYYY-MM-DD')
        AND o.order_status = 'completed'
        GROUP BY p.product_name, s.shop_name
        ORDER BY total_revenue DESC
    ) WHERE ROWNUM <= 5
";

$top_products_stmt = oci_parse($conn, $top_products_sql);
oci_bind_by_name($top_products_stmt, ':start_date', $start_date);
oci_bind_by_name($top_products_stmt, ':end_date', $end_date);
oci_execute($top_products_stmt);

// Get top performing shops
$top_shops_sql = "
    SELECT * FROM (
        SELECT 
            s.shop_name,
            COUNT(DISTINCT o.order_id) as total_orders,
            SUM(o.total_amount) as total_revenue
        FROM orders o
        JOIN order_item oi ON o.order_id = oi.order_id
        JOIN product p ON oi.product_id = p.product_id
        JOIN shops s ON p.shop_id = s.shop_id
        WHERE o.order_date BETWEEN TO_DATE(:start_date, 'YYYY-MM-DD') AND TO_DATE(:end_date, 'YYYY-MM-DD')
        AND o.order_status = 'completed'
        GROUP BY s.shop_name
        ORDER BY total_revenue DESC
    ) WHERE ROWNUM <= 5
";

$top_shops_stmt = oci_parse($conn, $top_shops_sql);
oci_bind_by_name($top_shops_stmt, ':start_date', $start_date);
oci_bind_by_name($top_shops_stmt, ':end_date', $end_date);
oci_execute($top_shops_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .reports-card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
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
    </style>
</head>
<body>
    <?php include 'admin_header.php' ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'admin_sidebar.php' ?>
            </div>
            <div class="col-md-9 col-lg-10">
                <div class="container-fluid py-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h3 mb-0">Sales Reports</h2>
                        <form method="GET" class="d-flex gap-2">
                            <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                            <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                            <button type="submit" class="btn btn-primary">Apply</button>
                        </form>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card bg-primary text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-shopping-cart stat-icon"></i>
                                    <h5 class="card-title">Total Orders</h5>
                                    <h2 class="mb-0"><?= number_format($sales_data['TOTAL_ORDERS']) ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-dollar-sign stat-icon"></i>
                                    <h5 class="card-title">Total Sales</h5>
                                    <h2 class="mb-0">$<?= number_format($sales_data['TOTAL_SALES'], 2) ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-info text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-users stat-icon"></i>
                                    <h5 class="card-title">Total Customers</h5>
                                    <h2 class="mb-0"><?= number_format($sales_data['TOTAL_CUSTOMERS']) ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-warning text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-store stat-icon"></i>
                                    <h5 class="card-title">Active Shops</h5>
                                    <h2 class="mb-0"><?= number_format($sales_data['TOTAL_SHOPS']) ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Chart -->
                    <div class="card reports-card mb-4">
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

                    <div class="row">
                        <!-- Top Products -->
                        <div class="col-md-6 mb-4">
                            <div class="card reports-card">
                                <div class="card-body">
                                    <h5 class="card-title">Top Selling Products</h5>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Shop</th>
                                                    <th>Revenue</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($product = oci_fetch_assoc($top_products_stmt)): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($product['PRODUCT_NAME']) ?></td>
                                                        <td><?= htmlspecialchars($product['SHOP_NAME']) ?></td>
                                                        <td>$<?= number_format($product['TOTAL_REVENUE'], 2) ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Top Shops -->
                        <div class="col-md-6 mb-4">
                            <div class="card reports-card">
                                <div class="card-body">
                                    <h5 class="card-title">Top Performing Shops</h5>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Shop Name</th>
                                                    <th>Total Orders</th>
                                                    <th>Total Revenue</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($shop = oci_fetch_assoc($top_shops_stmt)): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($shop['SHOP_NAME']) ?></td>
                                                        <td><?= number_format($shop['TOTAL_ORDERS']) ?></td>
                                                        <td>$<?= number_format($shop['TOTAL_REVENUE'], 2) ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
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
        const salesData = [];
        <?php
        oci_execute($daily_sales_stmt);
        while ($row = oci_fetch_assoc($daily_sales_stmt)) {
            echo "salesData.push(" . json_encode($row) . ");";
        }
        ?>

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
                        total_amount: 0,
                        order_count: 0,
                        customer_count: 0
                    };
                }
                
                weeklyData[weekKey].total_amount += parseFloat(item.TOTAL_AMOUNT);
                weeklyData[weekKey].order_count += parseInt(item.ORDER_COUNT);
                weeklyData[weekKey].customer_count += parseInt(item.CUSTOMER_COUNT);
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
                        total_amount: 0,
                        order_count: 0,
                        customer_count: 0
                    };
                }
                
                monthlyData[monthKey].total_amount += parseFloat(item.TOTAL_AMOUNT);
                monthlyData[monthKey].order_count += parseInt(item.ORDER_COUNT);
                monthlyData[monthKey].customer_count += parseInt(item.CUSTOMER_COUNT);
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
                                    label += '$' + context.parsed.y.toFixed(2);
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
                        data: salesData.map(item => item.TOTAL_AMOUNT),
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Orders',
                        data: salesData.map(item => item.ORDER_COUNT),
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
                            text: 'Revenue ($)'
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
                        data: weeklyData.map(item => item.total_amount),
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Orders',
                        data: weeklyData.map(item => item.order_count),
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
                            text: 'Revenue ($)'
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
                        data: monthlyData.map(item => item.total_amount),
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Orders',
                        data: monthlyData.map(item => item.order_count),
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
                            text: 'Revenue ($)'
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