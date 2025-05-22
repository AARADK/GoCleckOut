<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once "../../backend/database/connect.php";

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

$sql = "SELECT status FROM users WHERE user_id = :user_id";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":user_id", $user_id);
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);

if ($row) {
    if ($row['STATUS'] == 'pending') {
        echo 'User pending, Wait for Approval...';
        exit;
    } else if ($row['STATUS'] == 'inactive') {
        echo 'You have been rejected.';
        exit;
    }
}

$sales_sql = "SELECT 
    TRUNC(payment_date) as sale_date,
    NVL(SUM(amount), 0) as total_sales
FROM payment p
JOIN orders o ON p.payment_id = o.payment_id
JOIN order_item op ON o.order_id = op.order_id
JOIN product pr ON op.product_id = pr.product_id
WHERE pr.user_id = :user_id
AND payment_date >= SYSDATE - 7
GROUP BY TRUNC(payment_date)
ORDER BY sale_date";

$stmt = oci_parse($conn, $sales_sql);
if (!$stmt) {
    $e = oci_error($conn);
    echo "Error parsing sales query: " . $e['message'];
    exit;
}

oci_bind_by_name($stmt, ":user_id", $user_id);
if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    echo "Error executing sales query: " . $e['message'];
    exit;
}

$sales_data = [];
$sales_labels = [];
while ($row = oci_fetch_assoc($stmt)) {
    $sales_data[] = $row['TOTAL_SALES'];
    $sales_labels[] = date('M d', strtotime($row['SALE_DATE']));
}

$top_products_sql = "SELECT * FROM (
    SELECT 
        p.product_name,
        NVL(SUM(op.quantity), 0) as total_quantity
    FROM order_item op
    JOIN product p ON op.product_id = p.product_id
    WHERE p.user_id = :user_id
    GROUP BY p.product_name
    ORDER BY total_quantity DESC
) WHERE ROWNUM <= 5";

$stmt = oci_parse($conn, $top_products_sql);
if (!$stmt) {
    $e = oci_error($conn);
    echo "Error parsing top products query: " . $e['message'];
    exit;
}

oci_bind_by_name($stmt, ":user_id", $user_id);
if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    echo "Error executing top products query: " . $e['message'];
    exit;
}

$top_products = [];
$top_quantities = [];
while ($row = oci_fetch_assoc($stmt)) {
    $top_products[] = $row['PRODUCT_NAME'];
    $top_quantities[] = $row['TOTAL_QUANTITY'];
}

$shop_revenue_sql = "SELECT 
    s.shop_name,
    NVL(SUM(op.quantity * op.unit_price), 0) as total_revenue
FROM order_item op
JOIN product p ON op.product_id = p.product_id
JOIN shops s ON p.shop_id = s.shop_id
WHERE p.user_id = :user_id
GROUP BY s.shop_name";

$stmt = oci_parse($conn, $shop_revenue_sql);
if (!$stmt) {
    $e = oci_error($conn);
    echo "Error parsing shop revenue query: " . $e['message'];
    exit;
}

oci_bind_by_name($stmt, ":user_id", $user_id);
if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    echo "Error executing shop revenue query: " . $e['message'];
    exit;
}

$shop_names = [];
$shop_revenues = [];
while ($row = oci_fetch_assoc($stmt)) {
    $shop_names[] = $row['SHOP_NAME'];
    $shop_revenues[] = $row['TOTAL_REVENUE'];
}

$stock_sql = "SELECT * FROM (
    SELECT 
        product_name,
        stock
    FROM product
    WHERE user_id = :user_id
    ORDER BY stock DESC
) WHERE ROWNUM <= 5";

$stmt = oci_parse($conn, $stock_sql);
if (!$stmt) {
    $e = oci_error($conn);
    echo "Error parsing stock levels query: " . $e['message'];
    exit;
}

oci_bind_by_name($stmt, ":user_id", $user_id);
if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    echo "Error executing stock levels query: " . $e['message'];
    exit;
}

$stock_products = [];
$stock_levels = [];
while ($row = oci_fetch_assoc($stmt)) {
    $stock_products[] = $row['PRODUCT_NAME'];
    $stock_levels[] = $row['STOCK'];
}

oci_free_statement($stmt);

// Get all shops for the trader
$shops_sql = "SELECT shop_id, shop_name FROM shops WHERE user_id = :user_id";
$stmt = oci_parse($conn, $shops_sql);
oci_bind_by_name($stmt, ":user_id", $user_id);
oci_execute($stmt);

$shops = [];
while ($row = oci_fetch_assoc($stmt)) {
    $shops[] = [
        'id' => $row['SHOP_ID'],
        'name' => $row['SHOP_NAME']
    ];
}

// Get stock comparison data
$stock_comparison_sql = "SELECT 
    CASE 
        WHEN p.user_id = :user_id THEN 'Your Products'
        ELSE 'Other Traders'
    END as category,
    SUM(p.stock) as total_stock
FROM product p
GROUP BY 
    CASE 
        WHEN p.user_id = :user_id THEN 'Your Products'
        ELSE 'Other Traders'
    END";

$stmt = oci_parse($conn, $stock_comparison_sql);
oci_bind_by_name($stmt, ":user_id", $user_id);
oci_execute($stmt);

$stock_comparison = [];
while ($row = oci_fetch_assoc($stmt)) {
    $stock_comparison[] = [
        'category' => $row['CATEGORY'],
        'total_stock' => $row['TOTAL_STOCK']
    ];
}

oci_free_statement($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trader Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: #f8f9fa;
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
                <div class="mt-5 p-4">
                    <div class="mb-4">
                        <h1 class="h3">Welcome back!</h1>
                        <p class="text-muted">Here's what's happening with your business today.</p>
                    </div>

                    <div class="row g-4 mb-4">
                        <!-- Total Sales -->
                        <div class="col-md-3">
                            <div class="card shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                                            <i class="fas fa-dollar-sign text-primary"></i>
                                        </div>
                                    </div>
                                    <h6 class="text-muted mb-2">Total Sales</h6>
                                    <h4 class="mb-0">$<?php echo number_format(array_sum($sales_data), 2); ?></h4>
                                </div>
                            </div>
                        </div>

                        <!-- Total Products -->
                        <div class="col-md-3">
                            <div class="card shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-success bg-opacity-10 p-3 rounded">
                                            <i class="fas fa-box text-success"></i>
                                        </div>
                                    </div>
                                    <h6 class="text-muted mb-2">Total Products</h6>
                                    <h4 class="mb-0"><?php echo count($stock_products); ?></h4>
                                </div>
                            </div>
                        </div>

                        <!-- Total Shops -->
                        <div class="col-md-3">
                            <div class="card shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                                            <i class="fas fa-store text-warning"></i>
                                        </div>
                                    </div>
                                    <h6 class="text-muted mb-2">Total Shops</h6>
                                    <h4 class="mb-0"><?php echo count($shops); ?></h4>
                                </div>
                            </div>
                        </div>

                        <!-- Total Orders -->
                        <div class="col-md-3">
                            <div class="card shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-danger bg-opacity-10 p-3 rounded">
                                            <i class="fas fa-shopping-cart text-danger"></i>
                                        </div>
                                    </div>
                                    <h6 class="text-muted mb-2">Total Orders</h6>
                                    <h4 class="mb-0"><?php echo array_sum($top_quantities); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Sales Chart -->
                        <div class="col-md-8">
                            <div class="card shadow-sm mb-4">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">Sales Overview</h5>
                                    <div style="height: 300px;">
                                        <canvas id="salesChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Top Products -->
                        <div class="col-md-4">
                            <div class="card shadow-sm mb-4">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">Top Products</h5>
                                    <div style="height: 300px;">
                                        <canvas id="productsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Stock Levels -->
                        <div class="col-md-6">
                            <div class="card shadow-sm mb-4">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">Stock Levels</h5>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Stock</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php for($i = 0; $i < count($stock_products); $i++): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($stock_products[$i]); ?></td>
                                                    <td><?php echo $stock_levels[$i]; ?></td>
                                                    <td>
                                                        <?php if($stock_levels[$i] > 20): ?>
                                                            <span class="badge bg-success">In Stock</span>
                                                        <?php elseif($stock_levels[$i] > 5): ?>
                                                            <span class="badge bg-warning">Low Stock</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Out of Stock</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endfor; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Shop Revenue -->
                        <div class="col-md-6">
                            <div class="card shadow-sm mb-4">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">Shop Revenue</h5>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Shop</th>
                                                    <th>Revenue</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php for($i = 0; $i < count($shop_names); $i++): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($shop_names[$i]); ?></td>
                                                    <td>$<?php echo number_format($shop_revenues[$i], 2); ?></td>
                                                </tr>
                                                <?php endfor; ?>
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

    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($sales_labels); ?>,
                datasets: [{
                    label: 'Sales',
                    data: <?php echo json_encode($sales_data); ?>,
                    borderColor: '#ff6b6b',
                    backgroundColor: 'rgba(255, 107, 107, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            display: true,
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Products Chart
        const productsCtx = document.getElementById('productsChart').getContext('2d');
        new Chart(productsCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($top_products); ?>,
                datasets: [{
                    data: <?php echo json_encode($top_quantities); ?>,
                    backgroundColor: [
                        '#ff6b6b',
                        '#4ecdc4',
                        '#45b7d1',
                        '#96ceb4',
                        '#ffeead'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
