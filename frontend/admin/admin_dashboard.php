<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login_portal.php?sign_in=true");
    exit;
}

include '../../backend/database/connect.php';
$conn = getDBConnection();

// Get total traders count
$traders_sql = "SELECT COUNT(*) as total FROM users WHERE role = 'trader'";
$traders_stmt = oci_parse($conn, $traders_sql);
oci_execute($traders_stmt);
$traders_count = oci_fetch_assoc($traders_stmt)['TOTAL'];

// Get total shops count
$shops_sql = "SELECT COUNT(*) as total FROM shops";
$shops_stmt = oci_parse($conn, $shops_sql);
oci_execute($shops_stmt);
$shops_count = oci_fetch_assoc($shops_stmt)['TOTAL'];

// Get total products count
$products_sql = "SELECT COUNT(*) as total FROM product";
$products_stmt = oci_parse($conn, $products_sql);
oci_execute($products_stmt);
$products_count = oci_fetch_assoc($products_stmt)['TOTAL'];

// Get total orders count
$orders_sql = "SELECT COUNT(*) as total FROM orders";
$orders_stmt = oci_parse($conn, $orders_sql);
oci_execute($orders_stmt);
$orders_count = oci_fetch_assoc($orders_stmt)['TOTAL'];

// Get recent orders
$recent_orders_sql = "
    SELECT o.*, u.full_name as customer_name, s.shop_name
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    JOIN order_item oi ON o.order_id = oi.order_id
    JOIN product p ON oi.product_id = p.product_id
    JOIN shops s ON p.shop_id = s.shop_id
    WHERE ROWNUM <= 5
    ORDER BY o.order_date DESC
";
$recent_orders_stmt = oci_parse($conn, $recent_orders_sql);
oci_execute($recent_orders_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
        .recent-orders {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .order-status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .status-pending { background-color: #ffeeba; color: #856404; }
        .status-processing { background-color: #b8daff; color: #004085; }
        .status-completed { background-color: #c3e6cb; color: #155724; }
        .status-cancelled { background-color: #f5c6cb; color: #721c24; }
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
                    <h2 class="mb-4">Dashboard Overview</h2>
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card bg-primary text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-store stat-icon"></i>
                                    <h5 class="card-title">Total Traders</h5>
                                    <h2 class="mb-0"><?= $traders_count ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-shopping-bag stat-icon"></i>
                                    <h5 class="card-title">Total Shops</h5>
                                    <h2 class="mb-0"><?= $shops_count ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-info text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-box stat-icon"></i>
                                    <h5 class="card-title">Total Products</h5>
                                    <h2 class="mb-0"><?= $products_count ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-warning text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-shopping-cart stat-icon"></i>
                                    <h5 class="card-title">Total Orders</h5>
                                    <h2 class="mb-0"><?= $orders_count ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
    
                    <!-- Recent Orders -->
                    <div class="card recent-orders">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Recent Orders</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Shop</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($order = oci_fetch_assoc($recent_orders_stmt)): ?>
                                            <tr>
                                                <td>#<?= $order['ORDER_ID'] ?></td>
                                                <td><?= htmlspecialchars($order['CUSTOMER_NAME']) ?></td>
                                                <td><?= htmlspecialchars($order['SHOP_NAME']) ?></td>
                                                <td>$<?= number_format($order['TOTAL_AMOUNT'], 2) ?></td>
                                                <td><?= date('Y-m-d', strtotime($order['ORDER_DATE'])) ?></td>
                                                <td>
                                                    <span class="order-status status-<?= strtolower($order['ORDER_STATUS']) ?>">
                                                        <?= ucfirst($order['ORDER_STATUS']) ?>
                                                    </span>
                                                </td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>