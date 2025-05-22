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
        o.order_id,
        TO_CHAR(o.order_date, 'YYYY-MM-DD HH24:MI:SS') as order_date,
        o.total_amount,
        o.order_status,
        p.payment_method,
        p.status as payment_status,
        TO_CHAR(cs.slot_date, 'YYYY-MM-DD') as slot_date,
        TO_CHAR(cs.slot_time, 'HH24:MI:SS') as slot_time,
        c.coupon_code,
        c.coupon_discount_percent,
        ROW_NUMBER() OVER (ORDER BY o.order_date DESC) as order_number
    FROM orders o
    JOIN payment p ON o.payment_id = p.payment_id
    LEFT JOIN collection_slot cs ON o.collection_slot_id = cs.collection_slot_id
    LEFT JOIN coupon c ON o.coupon_id = c.coupon_id
    WHERE o.user_id = :user_id
    ORDER BY o.order_date DESC
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
            max-width: 800px;
            margin: 2rem auto;
        }
        .order-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
            padding: 1.5rem;
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
        .collection-time {
            background: #e9ecef;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            display: inline-block;
            margin: 0.5rem 0;
        }
        .discount-badge {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <?php include "../header.php" ?>

    <div class="orders-container">
        <h2 class="mb-4">My Orders</h2>
        
        <?php if ($has_orders): ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <h5 class="mb-1">Order #<?= $order['ORDER_NUMBER'] ?></h5>
                            <p class="text-muted mb-0">
                                <?= date('F j, Y, g:i a', strtotime($order['ORDER_DATE'])) ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <div class="collection-time">
                                <i class="fas fa-clock me-2"></i>
                                <?= date('F j, Y', strtotime($order['SLOT_DATE'])) ?> 
                                at <?= date('g:i A', strtotime($order['SLOT_TIME'])) ?>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="status-badge status-<?= strtolower($order['ORDER_STATUS']) ?>">
                                <?= ucfirst($order['ORDER_STATUS']) ?>
                            </span>
                            <?php if (isset($order['COUPON_CODE']) && $order['COUPON_DISCOUNT_PERCENT']): ?>
                                <div class="discount-badge mt-2">
                                    <?= $order['COUPON_CODE'] ?> (<?= $order['COUPON_DISCOUNT_PERCENT'] ?>% off)
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">
                                        Payment: <?= ucfirst($order['PAYMENT_METHOD']) ?> 
                                        (<?= ucfirst($order['PAYMENT_STATUS']) ?>)
                                    </small>
                                </div>
                                <div class="text-end">
                                    <h5 class="mb-0">Rs. <?= number_format($order['TOTAL_AMOUNT'], 2) ?></h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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