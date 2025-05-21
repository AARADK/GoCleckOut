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
JOIN order_products op ON p.payment_id = op.payment_id
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
    FROM order_products op
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
    NVL(SUM(op.total_price), 0) as total_revenue
FROM order_products op
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            margin: auto;
            height: 400px;
            width: 100%;
            margin-bottom: 2rem;
        }
        .dashboard-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .nav-tabs .nav-link {
            color: #495057;
        }
        .nav-tabs .nav-link.active {
            color: #0d6efd;
            font-weight: bold;
        }
        .tab-content {
            padding: 1rem 0;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <h1 class="mb-4">Trader Dashboard</h1>
        
        <div class="row">
            <!-- Shop Stock Levels -->
            <div class="col-md-8">
                <div class="dashboard-card">
                    <h3>Product Stocks by Shop</h3>
                    <ul class="nav nav-tabs" id="shopTabs" role="tablist">
                        <?php foreach ($shops as $index => $shop): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $index === 0 ? 'active' : ''; ?>" 
                                    id="shop-<?php echo $shop['id']; ?>-tab" 
                                    data-bs-toggle="tab" 
                                    data-bs-target="#shop-<?php echo $shop['id']; ?>" 
                                    type="button" 
                                    role="tab">
                                <?php echo htmlspecialchars($shop['name']); ?>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="tab-content" id="shopTabsContent">
                        <?php foreach ($shops as $index => $shop): ?>
                        <div class="tab-pane fade <?php echo $index === 0 ? 'show active' : ''; ?>" 
                             id="shop-<?php echo $shop['id']; ?>" 
                             role="tabpanel">
                            <div class="chart-container">
                                <canvas id="shopStockChart-<?php echo $shop['id']; ?>"></canvas>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Stock Comparison -->
            <div class="col-md-4">
                <div class="dashboard-card">
                    <h3>Stock Comparison</h3>
                    <div class="chart-container">
                        <canvas id="stockComparisonChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to fetch shop stock data
        async function fetchShopStockData(shopId) {
            const response = await fetch(`get_shop_stocks.php?shop_id=${shopId}`);
            return await response.json();
        }

        // Initialize shop stock charts
        <?php foreach ($shops as $shop): ?>
        fetchShopStockData(<?php echo $shop['id']; ?>).then(data => {
            new Chart(document.getElementById('shopStockChart-<?php echo $shop['id']; ?>'), {
                type: 'bar',
                data: {
                    labels: data.products,
                    datasets: [{
                        label: 'Stock Level',
                        data: data.stocks,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgb(75, 192, 192)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Quantity in Stock'
                            }
                        }
                    }
                }
            });
        });
        <?php endforeach; ?>

        // Stock Comparison Chart
        new Chart(document.getElementById('stockComparisonChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($stock_comparison, 'category')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($stock_comparison, 'total_stock')); ?>,
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(255, 99, 132, 0.5)'
                    ],
                    borderColor: [
                        'rgb(75, 192, 192)',
                        'rgb(255, 99, 132)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    title: {
                        display: true,
                        text: 'Your Products vs Other Traders'
                    }
                }
            }
        });
    </script>
</body>
</html>
