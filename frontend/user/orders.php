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

$user_id = $_SESSION['user_id'];

// Get user's payment history with timeslot and cart details
$orders_sql = "
    SELECT 
        p.payment_id as PAYMENT_ID,
        p.amount as AMOUNT,
        TO_CHAR(p.payment_date, 'YYYY-MM-DD HH24:MI:SS') as PAYMENT_DATE,
        p.payment_method as PAYMENT_METHOD,
        p.status as PAYMENT_STATUS,
        p.transaction_id as TRANSACTION_ID,
        TO_CHAR(t.slot_datetime, 'YYYY-MM-DD HH24:MI:SS') as SLOT_DATETIME,
        tc.items_count as ITEMS_COUNT,
        tc.cart_id as CART_ID,
        ROW_NUMBER() OVER (ORDER BY p.payment_date DESC) as ORDER_NUMBER,
        NVL(
            (
                SELECT LISTAGG(
                    NVL(op.product_name, 'Unknown Product') || ' (x' || NVL(op.quantity, 0) || ')', 
                    ', '
                ) WITHIN GROUP (ORDER BY op.product_name)
                FROM order_products op
                WHERE op.payment_id = p.payment_id
            ),
            'No products available'
        ) as PRODUCTS
    FROM payment p
    LEFT JOIN order_products op ON op.payment_id = p.payment_id
    LEFT JOIN cart_product cp ON cp.product_id = op.product_id
    LEFT JOIN cart c ON c.cart_id = cp.cart_id
    LEFT JOIN timeslot_cart tc ON tc.cart_id = c.cart_id
    LEFT JOIN timeslot t ON t.timeslot_id = tc.timeslot_id
    WHERE p.user_id = :user_id
    GROUP BY 
        p.payment_id,
        p.amount,
        p.payment_date,
        p.payment_method,
        p.status,
        p.transaction_id,
        t.slot_datetime,
        tc.items_count,
        tc.cart_id
    ORDER BY p.payment_date DESC
";

$stmt = oci_parse($conn, $orders_sql);
oci_bind_by_name($stmt, ":user_id", $user_id);

if (!oci_execute($stmt)) {
    $e = oci_error($stmt);
    die("Error executing query: " . $e['message']);
}

// Check if there are any orders
$has_orders = false;
$orders = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
    $has_orders = true;
    $orders[] = $row;
}
oci_free_statement($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - GoCleckOut</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .orders-container {
            max-width: 1000px;
            margin: 2rem auto;
        }
        .order-list {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .order-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        .order-item:last-child {
            border-bottom: none;
        }
        .order-item:hover {
            background-color: #f8f9fa;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
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
    </style>
</head>
<body>
    <?php include "../header.php" ?>

    <div class="orders-container">
        <h2 class="mb-4">My Orders</h2>
        
        <?php if ($has_orders): ?>
            <div class="order-list">
                <?php foreach ($orders as $order): ?>
                    <a href="order_details.php?tx=<?= urlencode($order['TRANSACTION_ID']) ?>" 
                       class="text-decoration-none text-dark">
                        <div class="order-item">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <h6 class="mb-1">Order #<?= $order['ORDER_NUMBER'] ?></h6>
                                    <small class="text-muted">
                                        <?= date('F j, Y, g:i a', strtotime($order['PAYMENT_DATE'])) ?>
                                    </small>
                                </div>
                                <div class="col-md-3">
                                    <span class="status-badge status-<?= strtolower($order['PAYMENT_STATUS']) ?>">
                                        <?= ucfirst($order['PAYMENT_STATUS']) ?>
                                    </span>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">
                                        <?= $order['ITEMS_COUNT'] ?? 0 ?> items
                                    </small>
                                </div>
                                <div class="col-md-3 text-end">
                                    <h6 class="mb-0 text-primary">
                                        Rs. <?= number_format($order['AMOUNT'], 2) ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($order['TRANSACTION_ID'] ?? 'N/A') ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                You haven't placed any orders yet.
                <a href="product_page.php" class="alert-link ms-2">Start shopping</a>
            </div>
        <?php endif; ?>
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