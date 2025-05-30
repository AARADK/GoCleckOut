<?php
session_start();
require_once '../../backend/database/connect.php';
include 'timeslot_date.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

$user_id = $_SESSION['user_id'];

// Get cart items and total
$cart_items = [];
$total_price = 0;

$cart_sql = "SELECT cart_id FROM cart WHERE user_id = :user_id";
$cart_stmt = oci_parse($conn, $cart_sql);
oci_bind_by_name($cart_stmt, ":user_id", $user_id);
oci_execute($cart_stmt);
$cart_row = oci_fetch_array($cart_stmt, OCI_ASSOC);
oci_free_statement($cart_stmt);

if ($cart_row) {
    $cart_id = $cart_row['CART_ID'];
    
    // Get cart items with product details
    $items_sql = "
        SELECT cp.quantity, p.product_name, p.price, p.product_id
        FROM cart_product cp
        JOIN product p ON cp.product_id = p.product_id
        WHERE cp.cart_id = :cart_id
    ";
    $stmt = oci_parse($conn, $items_sql);
    oci_bind_by_name($stmt, ":cart_id", $cart_id);
    oci_execute($stmt);
    
    while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
        $cart_items[] = $row;
        $total_price += $row['PRICE'] * $row['QUANTITY'];
    }
    oci_free_statement($stmt);
}

// Handle coupon if applied
$discount_amount = 0;
if (isset($_SESSION['applied_coupon'])) {
    $applied_coupon = $_SESSION['applied_coupon'];
    $discount_amount = ($total_price * $applied_coupon['COUPON_DISCOUNT_PERCENT']) / 100;
}

$final_total = $total_price - $discount_amount;

// PayPal configuration
$paypalURL = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
$paypalID = 'sb-2gbxy41155443@business.example.com'; // Replace with your PayPal business ID

// Get available timeslots with capacity
$timeslots_sql = "
    SELECT t.timeslot_id, t.slot_datetime, t.max_items_remaining, t.status,
           COUNT(tc.cart_id) as reserved_items
    FROM timeslot t
    LEFT JOIN timeslot_cart tc ON t.timeslot_id = tc.timeslot_id 
        AND tc.status = 'reserved'
    WHERE t.status != 'closed'
    AND t.slot_datetime > SYSDATE
    GROUP BY t.timeslot_id, t.slot_datetime, t.max_items_remaining, t.status
    HAVING t.max_items_remaining > 0
    ORDER BY t.slot_datetime
";

$timeslots_stmt = oci_parse($conn, $timeslots_sql);
oci_execute($timeslots_stmt);

$available_timeslots = [];
while ($row = oci_fetch_array($timeslots_stmt, OCI_ASSOC)) {
    $available_timeslots[] = $row;
}
oci_free_statement($timeslots_stmt);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (!isset($_POST['timeslot_id']) || empty($_POST['timeslot_id'])) {
        $_SESSION['toast_message'] = "Please select a delivery timeslot";
        $_SESSION['toast_type'] = "error";
    } else {
        $timeslot_id = $_POST['timeslot_id'];
        
        // Check if timeslot is still available
        $check_sql = "
            SELECT t.max_items_remaining, COUNT(tc.cart_id) as reserved_items
            FROM timeslot t
            LEFT JOIN timeslot_cart tc ON t.timeslot_id = tc.timeslot_id 
                AND tc.status = 'reserved'
            WHERE t.timeslot_id = :timeslot_id
            AND t.status != 'closed'
            GROUP BY t.max_items_remaining
        ";
        $check_stmt = oci_parse($conn, $check_sql);
        oci_bind_by_name($check_stmt, ":timeslot_id", $timeslot_id);
        oci_execute($check_stmt);
        $timeslot_status = oci_fetch_array($check_stmt, OCI_ASSOC);
        oci_free_statement($check_stmt);

        if (!$timeslot_status || $timeslot_status['MAX_ITEMS_REMAINING'] <= 0) {
            $_SESSION['toast_message'] = "Selected timeslot is no longer available";
            $_SESSION['toast_type'] = "error";
        } else {
            // Reserve the timeslot
            $reserve_sql = "
                INSERT INTO timeslot_cart (timeslot_id, cart_id, items_count, status)
                VALUES (:timeslot_id, :cart_id, :items_count, 'reserved')
            ";
            $reserve_stmt = oci_parse($conn, $reserve_sql);
            oci_bind_by_name($reserve_stmt, ":timeslot_id", $timeslot_id);
            oci_bind_by_name($reserve_stmt, ":cart_id", $cart_id);
            oci_bind_by_name($reserve_stmt, ":items_count", $cart_total);

            if (oci_execute($reserve_stmt)) {
                $_SESSION['selected_timeslot_id'] = $timeslot_id;
                $_SESSION['selected_cart_id'] = $cart_id;
            } else {
                $_SESSION['toast_message'] = "Failed to reserve timeslot";
                $_SESSION['toast_type'] = "error";
            }
            oci_free_statement($reserve_stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - GoCleckOut</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .checkout-container {
            max-width: 1200px;
            margin: 2rem auto;
        }
        .order-summary {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 1.5rem;
        }
        .timeslot-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .cart-item {
            border-bottom: 1px solid #eee;
            padding: 1rem 0;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .paypal-button {
            width: 100%;
            max-width: 300px;
            margin: 0 auto;
            display: block;
        }
    </style>
</head>
<body>
    <?php include "../header.php" ?>

    <div class="checkout-container">
        <div class="row">
            <div class="col-md-8">
                <!-- Timeslot Selection -->
                <div class="timeslot-section">
                    <h4 class="mb-4">Select Delivery Timeslot</h4>
                    <form method="POST" id="checkoutForm">
                        <div class="mb-3">
                            <select name="timeslot_id" class="form-select" required>
                                <option value="">Select a timeslot</option>
                                <?php foreach ($available_timeslots as $ts): 
                                    $datetime = new DateTime($ts['SLOT_DATETIME']);
                                    $label = $datetime->format('l, F j, Y - g:i A');
                                    $available = $ts['MAX_ITEMS_REMAINING'] - $ts['RESERVED_ITEMS'];
                                ?>
                                    <option value="<?= $ts['TIMESLOT_ID'] ?>" 
                                            <?= isset($_SESSION['selected_timeslot_id']) && $_SESSION['selected_timeslot_id'] == $ts['TIMESLOT_ID'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?> 
                                        (<?= $available ?> slots available)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="checkout" class="btn btn-primary">Proceed to Payment</button>
                    </form>
                </div>

                <!-- Order Items -->
                <div class="order-summary">
                    <h4 class="mb-4">Order Summary</h4>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h6><?= htmlspecialchars($item['PRODUCT_NAME']) ?></h6>
                                    <small class="text-muted">Quantity: <?= $item['QUANTITY'] ?></small>
                                </div>
                                <div class="col-md-6 text-end">
                                    <span class="text-primary">Rs. <?= number_format($item['PRICE'] * $item['QUANTITY'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Payment Summary -->
                <div class="order-summary">
                    <h4 class="mb-4">Payment Summary</h4>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span>Rs. <?= number_format($total_price, 2) ?></span>
                    </div>
                    <?php if ($discount_amount > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>Discount:</span>
                            <span>- Rs. <?= number_format($discount_amount, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <hr>
                    <div class="d-flex justify-content-between mb-4">
                        <strong>Total:</strong>
                        <strong class="text-primary">Rs. <?= number_format($final_total, 2) ?></strong>
                    </div>

                    <?php if (isset($_SESSION['selected_timeslot_id']) && isset($_SESSION['selected_cart_id'])): ?>
                        <!-- PayPal Form -->
                        <form action="<?php echo $paypalURL; ?>" method="post">
                            <input type="hidden" name="business" value="<?php echo $paypalID; ?>">
                            <input type="hidden" name="cmd" value="_xclick">
                            <input type="hidden" name="item_name" value="GoCleckOut Order">
                            <input type="hidden" name="item_number" value="<?= $_SESSION['selected_timeslot_id'] . '-' . $_SESSION['selected_cart_id'] ?>">
                            <input type="hidden" name="amount" value="<?= $final_total ?>">
                            <input type="hidden" name="currency_code" value="GBP">
                            <input type="hidden" name="quantity" value="1">
                            <input type="hidden" name="return" value="http://localhost/GCO/frontend/user/payment_success.php">
                            <input type="hidden" name="cancel_return" value="http://localhost/GCO/frontend/user/payment_cancel.php">
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/PP_logo_h_100x26.png" 
                                     alt="PayPal" class="paypal-button">
                            </button>
                        </form>
                    <?php endif; ?>
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