<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once '../../backend/database/connect.php';

// Restore session data from URL parameters
$_SESSION['user_id'] = $_GET['user_id'] ?? '';
$_SESSION['logged_in'] = isset($_SESSION['user_id']) ? 'true' : 'false';

// Get parameters from URL
$cart_id = $_GET['cart_id'] ?? null;
if (!$cart_id) {
    error_log("No cart_id provided in URL");
    die("Invalid request: No cart ID provided");
}

$timeslot_id = $_GET['timeslot_id'] ?? '';
$coupon_id = $_GET['coupon_id'] ?? '';
$user_id = $_GET['user_id'] ?? '';
$amount = $_GET['amount'] ?? '';

require_once '../../backend/database/connect.php';

$conn = getDBConnection();

// Verify cart exists and belongs to user
$verify_cart_sql = "SELECT cart_id FROM cart WHERE cart_id = :cart_id AND user_id = :user_id";
$verify_cart_stmt = oci_parse($conn, $verify_cart_sql);
if (!$verify_cart_stmt) {
    $error = oci_error($conn);
    error_log("SQL Parse Error (verify cart): " . $error['message']);
    die("Database error occurred");
}

oci_bind_by_name($verify_cart_stmt, ":cart_id", $cart_id);
oci_bind_by_name($verify_cart_stmt, ":user_id", $user_id);

if (!oci_execute($verify_cart_stmt)) {
    $error = oci_error($verify_cart_stmt);
    error_log("SQL Execute Error (verify cart): " . $error['message']);
    die("Database error occurred");
}

$cart_exists = oci_fetch_array($verify_cart_stmt, OCI_ASSOC);
oci_free_statement($verify_cart_stmt);

if (!$cart_exists) {
    error_log("Cart ID $cart_id not found or does not belong to user $user_id");
    die("Invalid cart ID or unauthorized access");
}

// Get cart items count
$cart_sql = "SELECT COUNT(*) as total_items FROM cart_product WHERE cart_id = :cart_id";
$cart_stmt = oci_parse($conn, $cart_sql);
if (!$cart_stmt) {
    $error = oci_error($conn);
    error_log("SQL Parse Error (cart count): " . $error['message']);
    die("Database error occurred");
}

oci_bind_by_name($cart_stmt, ":cart_id", $cart_id);

if (!oci_execute($cart_stmt)) {
    $error = oci_error($cart_stmt);
    error_log("SQL Execute Error (cart count): " . $error['message']);
    die("Database error occurred");
}

$cart_row = oci_fetch_array($cart_stmt, OCI_ASSOC);
$total_items = $cart_row['TOTAL_ITEMS'];
oci_free_statement($cart_stmt);

if ($total_items == 0) {
    error_log("Cart $cart_id is empty");
    header("Location: orders.php");
}

// Insert payment record
$payment_sql = "INSERT INTO payment (user_id, amount, payment_date, status, payment_method) 
                VALUES (:user_id, :amount, SYSDATE, 'completed', 'PayPal')";
$payment_stmt = oci_parse($conn, $payment_sql);
if (!$payment_stmt) {
    $error = oci_error($conn);
    error_log("SQL Parse Error (payment): " . $error['message']);
    die("Database error occurred");
}

oci_bind_by_name($payment_stmt, ":user_id", $user_id);
oci_bind_by_name($payment_stmt, ":amount", $amount);

if (!oci_execute($payment_stmt)) {
    $error = oci_error($payment_stmt);
    error_log("SQL Execute Error (payment): " . $error['message']);
    die("Database error occurred");
}

// Get the payment ID
$get_payment_id_sql = "SELECT payment_seq.CURRVAL as payment_id FROM dual";
$get_payment_id_stmt = oci_parse($conn, $get_payment_id_sql);
if (!$get_payment_id_stmt) {
    $error = oci_error($conn);
    error_log("SQL Parse Error (get payment id): " . $error['message']);
    die("Database error occurred");
}

if (!oci_execute($get_payment_id_stmt)) {
    $error = oci_error($get_payment_id_stmt);
    error_log("SQL Execute Error (get payment id): " . $error['message']);
    die("Database error occurred");
}

$payment_row = oci_fetch_array($get_payment_id_stmt, OCI_ASSOC);
$payment_id = $payment_row['PAYMENT_ID'];
oci_free_statement($get_payment_id_stmt);

error_log("Payment ID: " . $payment_id);

// Insert order
$order_sql = "INSERT INTO orders (user_id, payment_id, cart_id, total_item_count, total_amount, order_date, order_status, collection_slot_id, coupon_id) 
              VALUES (:user_id, :payment_id, :cart_id, :total_items, :amount, SYSDATE, 'pending', :timeslot_id, :coupon_id)
              RETURNING order_id INTO :order_id";
$order_stmt = oci_parse($conn, $order_sql);
if (!$order_stmt) {
    $error = oci_error($conn);
    error_log("SQL Parse Error (order): " . $error['message']);
    die("Database error occurred");
}

oci_bind_by_name($order_stmt, ":user_id", $user_id);
oci_bind_by_name($order_stmt, ":payment_id", $payment_id);
oci_bind_by_name($order_stmt, ":cart_id", $cart_id);
oci_bind_by_name($order_stmt, ":total_items", $total_items);
oci_bind_by_name($order_stmt, ":amount", $amount);
oci_bind_by_name($order_stmt, ":timeslot_id", $timeslot_id);
oci_bind_by_name($order_stmt, ":coupon_id", $coupon_id);
oci_bind_by_name($order_stmt, ":order_id", $order_id, -1, SQLT_INT);

if (!oci_execute($order_stmt)) {
    $error = oci_error($order_stmt);
    error_log("SQL Execute Error (order): " . $error['message']);
    die("Database error occurred");
}

error_log("Order ID: " . $order_id);

// Get cart items for order
$cart_items_sql = "SELECT cp.product_id, p.product_name, cp.quantity, p.price 
                   FROM cart_product cp 
                   JOIN product p ON cp.product_id = p.product_id 
                   WHERE cp.cart_id = :cart_id";
$cart_items_stmt = oci_parse($conn, $cart_items_sql);
if (!$cart_items_stmt) {
    $error = oci_error($conn);
    error_log("SQL Parse Error (cart items): " . $error['message']);
    die("Database error occurred");
}

oci_bind_by_name($cart_items_stmt, ":cart_id", $cart_id);

if (!oci_execute($cart_items_stmt)) {
    $error = oci_error($cart_items_stmt);
    error_log("SQL Execute Error (cart items): " . $error['message']);
    die("Database error occurred");
}

// Insert order items
while ($item = oci_fetch_array($cart_items_stmt, OCI_ASSOC)) {
    $order_item_sql = "INSERT INTO order_item (order_id, product_id, product_name, quantity, unit_price) 
                      VALUES (:order_id, :product_id, :product_name, :quantity, :unit_price)";
    $order_item_stmt = oci_parse($conn, $order_item_sql);
    if (!$order_item_stmt) {
        $error = oci_error($conn);
        error_log("SQL Parse Error (order item): " . $error['message']);
        continue;
    }

    oci_bind_by_name($order_item_stmt, ":order_id", $order_id);
    oci_bind_by_name($order_item_stmt, ":product_id", $item['PRODUCT_ID']);
    oci_bind_by_name($order_item_stmt, ":product_name", $item['PRODUCT_NAME']);
    oci_bind_by_name($order_item_stmt, ":quantity", $item['QUANTITY']);
    oci_bind_by_name($order_item_stmt, ":unit_price", $item['PRICE']);

    if (!oci_execute($order_item_stmt)) {
        $error = oci_error($order_item_stmt);
        error_log("SQL Execute Error (order item): " . $error['message']);
    }
    oci_free_statement($order_item_stmt);
}

oci_free_statement($cart_items_stmt);

// Clear the cart
$clear_cart_sql = "DELETE FROM cart_product WHERE cart_id = :cart_id";
$clear_cart_stmt = oci_parse($conn, $clear_cart_sql);
if (!$clear_cart_stmt) {
    $error = oci_error($conn);
    error_log("SQL Parse Error (clear cart): " . $error['message']);
    die("Database error occurred");
}

oci_bind_by_name($clear_cart_stmt, ":cart_id", $cart_id);

if (!oci_execute($clear_cart_stmt)) {
    $error = oci_error($clear_cart_stmt);
    error_log("SQL Execute Error (clear cart): " . $error['message']);
    die("Database error occurred");
}

oci_free_statement($clear_cart_stmt);

// Update collection slot max_order
$update_slot_sql = "UPDATE collection_slot SET max_order = max_order - 1 WHERE collection_slot_id = :timeslot_id";
$update_slot_stmt = oci_parse($conn, $update_slot_sql);
if (!$update_slot_stmt) {
    $error = oci_error($conn);
    error_log("SQL Parse Error (update slot): " . $error['message']);
    die("Database error occurred");
}

oci_bind_by_name($update_slot_stmt, ":timeslot_id", $timeslot_id);

if (!oci_execute($update_slot_stmt)) {
    $error = oci_error($update_slot_stmt);
    error_log("SQL Execute Error (update slot): " . $error['message']);
    die("Database error occurred");
}

oci_free_statement($update_slot_stmt);

// Clear session data
unset($_SESSION['applied_coupon']);
unset($_SESSION['checkout_data']);

// Get order details for invoice
$order_details_sql = "SELECT o.*, cs.slot_date, cs.slot_time, c.coupon_code, c.coupon_discount_percent 
                     FROM orders o 
                     LEFT JOIN collection_slot cs ON o.collection_slot_id = cs.collection_slot_id 
                     LEFT JOIN coupon c ON o.coupon_id = c.coupon_id 
                     WHERE o.order_id = :order_id";
$order_details_stmt = oci_parse($conn, $order_details_sql);
oci_bind_by_name($order_details_stmt, ":order_id", $order_id);
oci_execute($order_details_stmt);
$order_details = oci_fetch_array($order_details_stmt, OCI_ASSOC);

// Get order items
$order_items_sql = "SELECT oi.*, p.product_name, p.price as unit_price 
                    FROM order_item oi 
                    JOIN product p ON oi.product_id = p.product_id 
                    WHERE oi.order_id = :order_id";
$order_items_stmt = oci_parse($conn, $order_items_sql);
oci_bind_by_name($order_items_stmt, ":order_id", $order_id);
oci_execute($order_items_stmt);

// Calculate totals
$subtotal = 0;
$discount_amount = 0;
$items = [];

while ($item = oci_fetch_array($order_items_stmt, OCI_ASSOC)) {
    $item_total = $item['QUANTITY'] * $item['UNIT_PRICE'];
    $subtotal += $item_total;
    $items[] = $item;
}

// Calculate discount if coupon was applied
if (isset($order_details['COUPON_DISCOUNT_PERCENT']) && $order_details['COUPON_DISCOUNT_PERCENT'] > 0) {
    $discount_amount = $subtotal * ($order_details['COUPON_DISCOUNT_PERCENT'] / 100);
}

$final_total = $subtotal - $discount_amount;

// Get PayPal response data
$txn_id = $_GET['tx'] ?? '';
$payment_status = $_GET['st'] ?? '';
$amount = $_GET['amt'] ?? '';
$currency = $_GET['cc'] ?? '';
$payer_email = $_GET['payer_email'] ?? '';
$payer_id = $_GET['payer_id'] ?? '';
$payment_date = $_GET['payment_date'] ?? '';
$payment_type = $_GET['payment_type'] ?? '';
$receiver_email = $_GET['receiver_email'] ?? '';
$receiver_id = $_GET['receiver_id'] ?? '';
$item_name = $_GET['item_name'] ?? '';
$item_number = $_GET['item_number'] ?? '';

// Debug information
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("GET Parameters: " . print_r($_GET, true));
error_log("Session Data: " . print_r($_SESSION, true));

// Display the payment details
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Success</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, .15);
            font-size: 16px;
            line-height: 24px;
            font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
            color: #555;
        }
        .invoice-box table {
            width: 100%;
            line-height: inherit;
            text-align: left;
        }
        .invoice-box table td {
            padding: 5px;
            vertical-align: top;
        }
        .invoice-box table tr.top table td {
            padding-bottom: 20px;
        }
        .invoice-box table tr.heading td {
            background: #eee;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
        }
        .invoice-box table tr.details td {
            padding-bottom: 20px;
        }
        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
        }
        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #eee;
            font-weight: bold;
        }
        .discount-row {
            color: #28a745;
        }
        .total-row {
            font-weight: bold;
            border-top: 2px solid #eee;
        }
    </style>
</head>
<body>
    <?php include '../header.php'; ?>
    
    <div class="container mt-5">
        <div class="invoice-box">
            <div class="text-center mb-4">
                <h2 class="text-success">Payment Successful!</h2>
                <p class="text-muted">Thank you for your order</p>
            </div>

            <table>
                <tr class="top">
                    <td colspan="4">
                        <table>
                            <tr>
                                <td class="title">
                                    <h3>GoCleckOut</h3>
                                </td>
                                <td>
                                    Order #: <?= $order_id ?><br>
                                    Date: <?= date('Y-m-d H:i:s') ?><br>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr class="heading">
                    <td colspan="4">Order Details</td>
                </tr>
                <tr class="details">
                    <td colspan="4">
                        Collection Time: <?= date('Y-m-d', strtotime($order_details['SLOT_DATE'])) ?> 
                        at <?= date('H:i', strtotime($order_details['SLOT_TIME'])) ?><br>
                        <?php if (isset($order_details['COUPON_CODE']) && $order_details['COUPON_CODE']): ?>
                            Coupon Applied: <?= $order_details['COUPON_CODE'] ?> 
                            (<?= $order_details['COUPON_DISCOUNT_PERCENT'] ?>% off)
                        <?php endif; ?>
                    </td>
                </tr>

                <tr class="heading">
                    <td>Item</td>
                    <td>Quantity</td>
                    <td>Unit Price</td>
                    <td>Total</td>
                </tr>

                <?php foreach ($items as $item): ?>
                <tr class="item">
                    <td><?= htmlspecialchars($item['PRODUCT_NAME']) ?></td>
                    <td><?= $item['QUANTITY'] ?></td>
                    <td>Rs. <?= number_format($item['UNIT_PRICE'], 2) ?></td>
                    <td>Rs. <?= number_format($item['QUANTITY'] * $item['UNIT_PRICE'], 2) ?></td>
                </tr>
                <?php endforeach; ?>

                <tr class="total">
                    <td colspan="3"></td>
                    <td>
                        <table class="w-100">
                            <tr>
                                <td>Subtotal:</td>
                                <td class="text-end">Rs. <?= number_format($subtotal, 2) ?></td>
                            </tr>
                            <?php if ($discount_amount > 0): ?>
                            <tr class="discount-row">
                                <td>Discount (<?= $order_details['COUPON_DISCOUNT_PERCENT'] ?>%):</td>
                                <td class="text-end">- Rs. <?= number_format($discount_amount, 2) ?></td>
                            </tr>
                        <?php endif; ?>
                            <tr class="total-row">
                                <td>Total:</td>
                                <td class="text-end">Rs. <?= number_format($final_total, 2) ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <div class="mt-4 text-center">
                <a href="orders.php" class="btn btn-primary">View All Orders</a>
                <a href="../home.php" class="btn btn-secondary">Continue Shopping</a>
            </div>
        </div>
    </div>

    <?php include '../footer.php'; ?>
</body>
</html>
<?php
oci_free_statement($order_details_stmt);
oci_free_statement($order_items_stmt);
oci_close($conn);
?> 