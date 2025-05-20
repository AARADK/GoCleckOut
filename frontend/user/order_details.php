<?php
session_start();
require_once __DIR__ . '/../../backend/database/connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

$transaction_id = $_GET['tx'] ?? '';
$user_id = $_SESSION['user_id'];

if (empty($transaction_id)) {
    $_SESSION['toast_message'] = "No transaction ID provided";
    $_SESSION['toast_type'] = "error";
    header('Location: orders.php');
    exit;
}

// Get detailed order information
$order_sql = "
    WITH order_details AS (
        SELECT 
            p.payment_id,
            p.amount,
            TO_CHAR(p.payment_date, 'YYYY-MM-DD HH24:MI:SS') as payment_date,
            p.payment_method,
            p.status as payment_status,
            p.transaction_id,
            TO_CHAR(t.slot_datetime, 'YYYY-MM-DD HH24:MI:SS') as slot_datetime,
            tc.items_count,
            tc.cart_id,
            p2.product_id,
            p2.product_name,
            p2.price as unit_price,
            cp.quantity,
            (p2.price * cp.quantity) as item_total,
            NVL(s.shop_name, 'Unknown Shop') as shop_name,
            s.shop_id,
            ROW_NUMBER() OVER (ORDER BY TO_DATE(p.payment_date, 'YYYY-MM-DD HH24:MI:SS') DESC) as order_number
        FROM payment p
        LEFT JOIN timeslot_cart tc ON tc.cart_id IN (
            SELECT cart_id 
            FROM cart 
            WHERE user_id = :user_id
        )
        LEFT JOIN timeslot t ON tc.timeslot_id = t.timeslot_id
        LEFT JOIN cart_product cp ON tc.cart_id = cp.cart_id
        LEFT JOIN product p2 ON cp.product_id = p2.product_id
        LEFT JOIN shops s ON p2.shop_id = s.shop_id
        WHERE p.user_id = :user_id
        AND p.transaction_id = :transaction_id
    )
    SELECT 
        payment_id,
        amount as total_amount,
        payment_date,
        payment_method,
        payment_status,
        transaction_id,
        slot_datetime,
        items_count,
        cart_id,
        shop_id,
        shop_name,
        order_number,
        CASE 
            WHEN COUNT(product_name) > 0 
            THEN LISTAGG(
                NVL(product_name, 'Unknown Product') || ' (x' || NVL(quantity, 0) || ') - Rs. ' || NVL(item_total, 0),
                ', '
            ) WITHIN GROUP (ORDER BY product_name)
            ELSE 'No products available'
        END as products,
        NVL(SUM(item_total), 0) as subtotal
    FROM order_details
    GROUP BY 
        payment_id,
        amount,
        payment_date,
        payment_method,
        payment_status,
        transaction_id,
        slot_datetime,
        items_count,
        cart_id,
        shop_id,
        shop_name,
        order_number
";

$stmt = oci_parse($conn, $order_sql);
oci_bind_by_name($stmt, ":user_id", $user_id);
oci_bind_by_name($stmt, ":transaction_id", $transaction_id);
oci_execute($stmt);
$order = oci_fetch_array($stmt, OCI_ASSOC);
oci_free_statement($stmt);

if (!$order) {
    $_SESSION['toast_message'] = "Order not found";
    $_SESSION['toast_type'] = "error";
    header('Location: orders.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - GoCleckOut</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .order-details-container {
            max-width: 1000px;
            margin: 2rem auto;
        }
        .order-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .order-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        .order-body {
            padding: 1.5rem;
        }
        .order-footer {
            background: #f8f9fa;
            padding: 1.5rem;
            border-top: 1px solid #eee;
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        .product-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .product-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        .product-list li:last-child {
            border-bottom: none;
        }
        .shop-badge {
            background: #e9ecef;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.9rem;
            color: #495057;
        }
    </style>
</head>
<body>
    <?php include "../header.php" ?>

    <div class="order-details-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Order Details</h2>
            <a href="orders.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Orders
            </a>
        </div>

        <div class="order-card">
            <div class="order-header">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-1">Order #<?= $order['ORDER_NUMBER'] ?></h5>
                        <p class="mb-0 text-muted">
                            <small>
                                <i class="far fa-calendar-alt me-1"></i>
                                <?= date('F j, Y, g:i a', strtotime($order['PAYMENT_DATE'])) ?>
                            </small>
                        </p>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="status-badge status-<?= strtolower($order['PAYMENT_STATUS']) ?>">
                            <?= ucfirst($order['PAYMENT_STATUS']) ?>
                        </span>
                        <?php if ($order['SHOP_NAME']): ?>
                            <span class="shop-badge ms-2">
                                <i class="fas fa-store me-1"></i><?= htmlspecialchars($order['SHOP_NAME']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="order-body">
                <div class="row">
                    <div class="col-md-8">
                        <h6 class="mb-3">Products</h6>
                        <ul class="product-list">
                            <?php foreach (explode(', ', $order['PRODUCTS']) as $product): ?>
                                <li><?= htmlspecialchars($product) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">Delivery Details</h6>
                                <p class="card-text mb-1">
                                    <strong>Timeslot:</strong><br>
                                    <?= date('F j, Y, g:i a', strtotime($order['SLOT_DATETIME'])) ?>
                                </p>
                                <p class="card-text mb-1">
                                    <strong>Items:</strong> <?= $order['ITEMS_COUNT'] ?>
                                </p>
                                <p class="card-text mb-0">
                                    <strong>Transaction ID:</strong><br>
                                    <small class="text-muted"><?= $order['TRANSACTION_ID'] ?></small>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="order-footer">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <small class="text-muted">
                            Payment Method: <?= ucfirst($order['PAYMENT_METHOD']) ?>
                        </small>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="mb-1">
                            <small class="text-muted">Subtotal:</small>
                            <span class="float-end">Rs. <?= number_format($order['SUBTOTAL'], 2) ?></span>
                        </div>
                        <h5 class="mb-0 text-primary">
                            Total: Rs. <?= number_format($order['TOTAL_AMOUNT'], 2) ?>
                        </h5>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include "../footer.php" ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show toast messages if any
        <?php if (isset($_SESSION['toast_message'])): ?>
            alert("<?= $_SESSION['toast_message'] ?>");
            <?php unset($_SESSION['toast_message'], $_SESSION['toast_type']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
<?php
oci_close($conn);
?> 