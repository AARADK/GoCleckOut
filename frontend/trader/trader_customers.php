<?php
require_once '../../backend/database/db_connection.php';
session_start();

// Check if user is logged in and is a trader
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trader') {
    header('Location: ../login/login_portal.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get all customers who have bought from this trader's shops, sorted by purchase frequency
$sql = "SELECT 
            u.user_id,
            u.full_name,
            u.email,
            u.phone_no,
            COUNT(DISTINCT o.order_id) as total_orders,
            SUM(oi.quantity) as total_items,
            SUM(oi.quantity * oi.unit_price) as total_spent,
            MAX(o.order_date) as last_order_date
        FROM users u
        JOIN orders o ON u.user_id = o.user_id
        JOIN order_item oi ON o.order_id = oi.order_id
        JOIN product p ON oi.product_id = p.product_id
        JOIN shops s ON p.shop_id = s.shop_id
        WHERE s.user_id = :user_id
        GROUP BY u.user_id, u.full_name, u.email, u.phone_no
        ORDER BY total_orders DESC, total_spent DESC";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":user_id", $user_id);
oci_execute($stmt);

$customers = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
    $customers[] = $row;
}
oci_free_statement($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Customers | GoCleckOut</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .customer-card {
            transition: transform 0.2s;
        }
        .customer-card:hover {
            transform: translateY(-2px);
        }
        .stat-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #0d6efd;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
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
                <div class="container my-5">
                    <h1 class="h3 mb-4">My Customers</h1>

                    <?php if (empty($customers)): ?>
                        <div class="text-center py-5">
                            <i class="material-icons" style="font-size: 48px; color: #ccc;">people</i>
                            <h3 class="mt-3 text-muted">No Customers Found</h3>
                            <p class="text-muted">You haven't had any customers yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Customer</th>
                                        <th>Contact</th>
                                        <th class="text-center">Orders</th>
                                        <th class="text-center">Items Bought</th>
                                        <th class="text-end">Total Spent</th>
                                        <th class="text-end">Last Order</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customers as $customer): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($customer['FULL_NAME']) ?></strong>
                                            </td>
                                            <td>
                                                <div class="small text-muted">
                                                    <div><i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($customer['EMAIL']) ?></div>
                                                    <div><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($customer['PHONE_NO']) ?></div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary"><?= $customer['TOTAL_ORDERS'] ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?= number_format($customer['TOTAL_ITEMS']) ?>
                                            </td>
                                            <td class="text-end">
                                                £<?= number_format($customer['TOTAL_SPENT'], 2) ?>
                                            </td>
                                            <td class="text-end text-muted small">
                                                <?= date('F j, Y', strtotime($customer['LAST_ORDER_DATE'])) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

