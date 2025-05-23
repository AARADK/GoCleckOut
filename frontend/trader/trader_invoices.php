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

// SQL query to get all items
$sql = "SELECT 
            oi.order_product_id,
            oi.product_name,
            oi.quantity,
            oi.unit_price,
            o.order_date,
            o.order_id,
            s.shop_name,
            u.full_name as customer_name
        FROM order_item oi
        JOIN orders o ON oi.order_id = o.order_id
        JOIN product p ON oi.product_id = p.product_id
        JOIN shops s ON p.shop_id = s.shop_id
        JOIN users u ON o.user_id = u.user_id
        WHERE s.user_id = :user_id
        ORDER BY o.order_date DESC";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":user_id", $user_id);
oci_execute($stmt);

// Fetch all items and restructure the data
$raw_items = [];
oci_fetch_all($stmt, $raw_items);

// Restructure the data into a more usable format
$items = [];
$num_rows = count($raw_items['ORDER_PRODUCT_ID']);
for ($i = 0; $i < $num_rows; $i++) {
    $items[] = [
        'ORDER_PRODUCT_ID' => $raw_items['ORDER_PRODUCT_ID'][$i],
        'PRODUCT_NAME' => $raw_items['PRODUCT_NAME'][$i],
        'QUANTITY' => $raw_items['QUANTITY'][$i],
        'UNIT_PRICE' => $raw_items['UNIT_PRICE'][$i],
        'ORDER_DATE' => $raw_items['ORDER_DATE'][$i],
        'ORDER_ID' => $raw_items['ORDER_ID'][$i],
        'SHOP_NAME' => $raw_items['SHOP_NAME'][$i],
        'CUSTOMER_NAME' => $raw_items['CUSTOMER_NAME'][$i]
    ];
}

// Clean up
oci_free_statement($stmt);
oci_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sold Items | GoCleckOut</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .item-card {
            transition: transform 0.2s;
        }
        .item-card:hover {
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
                <div class="container my-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Sold Items</h1>
                    </div>

                    <?php if (!empty($items)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Shop</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($item['PRODUCT_NAME']) ?></div>
                                                    <div class="small text-muted">ID: <?= $item['ORDER_PRODUCT_ID'] ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="" class="text-decoration-none">
                                                    #<?= str_pad($item['ORDER_ID'], 6, '0', STR_PAD_LEFT) ?>
                                                </a>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($item['ORDER_DATE'])) ?></td>
                                            <td><?= htmlspecialchars($item['CUSTOMER_NAME']) ?></td>
                                            <td><?= htmlspecialchars($item['SHOP_NAME']) ?></td>
                                            <td class="text-center"><?= $item['QUANTITY'] ?></td>
                                            <td class="text-end">£<?= number_format($item['UNIT_PRICE'], 2) ?></td>
                                            <td class="text-end fw-bold">
                                                £<?= number_format($item['QUANTITY'] * $item['UNIT_PRICE'], 2) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="material-icons" style="font-size: 48px; color: #ccc;">inventory_2</i>
                            <h3 class="mt-3 text-muted">No Items Found</h3>
                            <p class="text-muted">There are no items in your sales history.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>




