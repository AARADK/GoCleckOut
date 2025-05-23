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

// Get all orders for this trader's products
$sql = "SELECT o.order_id, o.order_date, o.total_amount, o.order_status, 
               oi.quantity, oi.unit_price, oi.product_name,
               p.product_id, p.product_image,
               s.shop_name,
               u.full_name as customer_name,
               cs.slot_date, cs.slot_time
        FROM orders o
        JOIN order_item oi ON o.order_id = oi.order_id
        JOIN product p ON oi.product_id = p.product_id
        JOIN shops s ON p.shop_id = s.shop_id
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN collection_slot cs ON o.collection_slot_id = cs.collection_slot_id
        WHERE s.user_id = :user_id
        ORDER BY o.order_date DESC";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":user_id", $user_id);
oci_execute($stmt);

$orders = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
    // Handle LOB fields
    foreach ($row as $key => $value) {
        if (is_object($value) && get_class($value) === 'OCI-Lob') {
            if ($key === 'PRODUCT_IMAGE') {
                $image_data = $value->load();
                $row[$key] = "data:image/jpeg;base64," . base64_encode($image_data);
            } else {
                $row[$key] = $value->load();
            }
        }
    }
    $orders[] = $row;
}
oci_free_statement($stmt);

// Group orders by order_id
$grouped_orders = [];
foreach ($orders as $order) {
    $order_id = $order['ORDER_ID'];
    if (!isset($grouped_orders[$order_id])) {
        $grouped_orders[$order_id] = [
            'order_id' => $order_id,
            'order_date' => $order['ORDER_DATE'],
            'total_amount' => $order['TOTAL_AMOUNT'],
            'order_status' => $order['ORDER_STATUS'],
            'customer_name' => $order['CUSTOMER_NAME'],
            'slot_date' => $order['SLOT_DATE'],
            'slot_time' => $order['SLOT_TIME'],
            'items' => []
        ];
    }
    $grouped_orders[$order_id]['items'][] = [
        'product_name' => $order['PRODUCT_NAME'],
        'quantity' => $order['QUANTITY'],
        'unit_price' => $order['UNIT_PRICE'],
        'product_image' => $order['PRODUCT_IMAGE'],
        'shop_name' => $order['SHOP_NAME']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | GoCleckOut</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .order-card {
            transition: transform 0.2s;
        }
        .order-card:hover {
            transform: translateY(-2px);
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.5em 1em;
        }
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
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
                    <h1 class="h3 mb-4">My Orders</h1>

                    <?php if (empty($grouped_orders)): ?>
                        <div class="text-center py-5">
                            <i class="material-icons" style="font-size: 48px; color: #ccc;">receipt_long</i>
                            <h3 class="mt-3 text-muted">No Orders Found</h3>
                            <p class="text-muted">You haven't received any orders yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($grouped_orders as $order): ?>
                            <div class="card mb-4 order-card shadow-sm">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-0">Order #<?= $order['order_id'] ?></h5>
                                        <small class="text-muted">
                                            <?= date('F j, Y, g:i a', strtotime($order['order_date'])) ?>
                                        </small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="badge <?= $order['order_status'] === 'completed' ? 'bg-success' : 
                                            ($order['order_status'] === 'pending' ? 'bg-warning' : 'bg-danger') ?> status-badge">
                                            <?= ucfirst($order['order_status']) ?>
                                        </span>
                                        <?php if ($order['order_status'] !== 'completed'): ?>
                                            <button class="btn btn-link text-success ms-2 p-0 complete-order-btn" 
                                                    data-order-id="<?= $order['order_id'] ?>"
                                                    title="Mark as completed">
                                                <i class="material-icons">check_circle</i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                                            <?php if ($order['slot_date'] && $order['slot_time']): ?>
                                                <p class="mb-1">
                                                    <strong>Collection Slot:</strong><br>
                                                    <?= date('F j, Y', strtotime($order['slot_date'])) ?><br>
                                                    <?= date('g:i a', strtotime($order['slot_time'])) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6 text-md-end">
                                            <p class="mb-1"><strong>Total Amount:</strong> £<?= number_format($order['total_amount'], 2) ?></p>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Shop</th>
                                                    <th>Quantity</th>
                                                    <th>Unit Price</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($order['items'] as $item): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <?php if (!empty($item['product_image'])): ?>
                                                                    <img src="<?= htmlspecialchars($item['product_image']) ?>" 
                                                                         alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                                                         class="product-image me-2">
                                                                <?php endif; ?>
                                                                <?= htmlspecialchars($item['product_name']) ?>
                                                            </div>
                                                        </td>
                                                        <td><?= htmlspecialchars($item['shop_name']) ?></td>
                                                        <td><?= $item['quantity'] ?></td>
                                                        <td>£<?= number_format($item['unit_price'], 2) ?></td>
                                                        <td>£<?= number_format($item['quantity'] * $item['unit_price'], 2) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle order completion
        document.querySelectorAll('.complete-order-btn').forEach(button => {
            button.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                const orderCard = this.closest('.order-card');
                
                if (confirm('Are you sure you want to mark this order as completed?')) {
                    fetch('update_order_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'order_id=' + orderId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the UI
                            const statusBadge = orderCard.querySelector('.status-badge');
                            statusBadge.className = 'badge bg-success status-badge';
                            statusBadge.textContent = 'Completed';
                            
                            // Remove the complete button
                            this.remove();
                            
                            // Show success message
                            const toast = document.createElement('div');
                            toast.className = 'toast bg-success text-white position-fixed top-0 end-0 m-3';
                            toast.innerHTML = `
                                <div class="toast-body">
                                    Order marked as completed successfully!
                                </div>
                            `;
                            document.body.appendChild(toast);
                            const bsToast = new bootstrap.Toast(toast);
                            bsToast.show();
                            
                            // Remove toast after it's hidden
                            toast.addEventListener('hidden.bs.toast', () => {
                                toast.remove();
                            });
                        } else {
                            throw new Error(data.error || 'Failed to update order status');
                        }
                    })
                    .catch(error => {
                        // Show error message
                        const toast = document.createElement('div');
                        toast.className = 'toast bg-danger text-white position-fixed top-0 end-0 m-3';
                        toast.innerHTML = `
                            <div class="toast-body">
                                ${error.message}
                            </div>
                        `;
                        document.body.appendChild(toast);
                        const bsToast = new bootstrap.Toast(toast);
                        bsToast.show();
                        
                        // Remove toast after it's hidden
                        toast.addEventListener('hidden.bs.toast', () => {
                            toast.remove();
                        });
                    });
                }
            });
        });
    </script>
</body>
</html>
