<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login_portal.php?sign_in=true");
    exit;
}

include '../../backend/database/connect.php';
$conn = getDBConnection();

// Get all orders with customer and shop information
$orders_sql = "
    SELECT 
        o.order_id,
        o.order_date,
        o.total_amount,
        o.order_status,
        u.full_name as customer_name,
        u.email as customer_email,
        s.shop_name,
        s.shop_category,
        p.payment_method,
        p.status as payment_status
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    JOIN order_item oi ON o.order_id = oi.order_id
    JOIN product p ON oi.product_id = p.product_id
    JOIN shops s ON p.shop_id = s.shop_id
    JOIN payment p ON o.payment_id = p.payment_id
    ORDER BY o.order_date DESC
";
$orders_stmt = oci_parse($conn, $orders_sql);
oci_execute($orders_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .orders-card {
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
        .payment-status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .payment-pending { background-color: #ffeeba; color: #856404; }
        .payment-completed { background-color: #c3e6cb; color: #155724; }
        .payment-failed { background-color: #f5c6cb; color: #721c24; }
        .payment-refunded { background-color: #d1ecf1; color: #0c5460; }
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
                        <h2 class="h3 mb-0">All Orders</h2>
                    </div>

                    <div class="card orders-card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Shop</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                            <th>Order Status</th>
                                            <th>Payment Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($order = oci_fetch_assoc($orders_stmt)): ?>
                                            <tr>
                                                <td>#<?= $order['ORDER_ID'] ?></td>
                                                <td>
                                                    <div><?= htmlspecialchars($order['CUSTOMER_NAME']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($order['CUSTOMER_EMAIL']) ?></small>
                                                </td>
                                                <td>
                                                    <div><?= htmlspecialchars($order['SHOP_NAME']) ?></div>
                                                    <small class="text-muted"><?= ucfirst($order['SHOP_CATEGORY']) ?></small>
                                                </td>
                                                <td>$<?= number_format($order['TOTAL_AMOUNT'], 2) ?></td>
                                                <td><?= date('Y-m-d H:i', strtotime($order['ORDER_DATE'])) ?></td>
                                                <td>
                                                    <span class="order-status status-<?= strtolower($order['ORDER_STATUS']) ?>">
                                                        <?= ucfirst($order['ORDER_STATUS']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="payment-status payment-<?= strtolower($order['PAYMENT_STATUS']) ?>">
                                                        <?= ucfirst($order['PAYMENT_STATUS']) ?>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
