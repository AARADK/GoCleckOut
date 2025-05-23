<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

include '../../backend/database/connect.php';
include 'timeslot_date.php';

$conn = getDBConnection();

// Function to get current cart total
function getCartTotal($conn, $user_id) {
    $total_sql = "
        SELECT SUM(cp.quantity) as total_quantity
        FROM cart_product cp
        JOIN cart c ON cp.cart_id = c.cart_id
        WHERE c.user_id = :user_id
    ";
    $stmt = oci_parse($conn, $total_sql);
    oci_bind_by_name($stmt, ":user_id", $user_id);
    oci_execute($stmt);
    $total_row = oci_fetch_array($stmt, OCI_ASSOC);
    $total = $total_row['TOTAL_QUANTITY'] ?? 0;
    oci_free_statement($stmt);
    return $total;
}

$cart_items = [];
$total_price = 0;
$user_id = $_SESSION['user_id'] ?? null;
$cart_total = 0;

if ($user_id) {
    $cart_total = getCartTotal($conn, $user_id);
    // Get the cart ID for this user
    $cart_sql = "SELECT cart_id FROM cart WHERE user_id = :user_id";
    $cart_stmt = oci_parse($conn, $cart_sql);
    oci_bind_by_name($cart_stmt, ":user_id", $user_id);
    oci_execute($cart_stmt);
    $cart_row = oci_fetch_array($cart_stmt, OCI_ASSOC);
    oci_free_statement($cart_stmt);

    if ($cart_row) {
        $cart_id = $cart_row['CART_ID'];

        // Get cart products with product info
        $item_sql = "
            SELECT cp.quantity, p.product_name, p.price, p.product_image, p.product_id
            FROM cart_product cp
            JOIN product p ON cp.product_id = p.product_id
            WHERE cp.cart_id = :cart_id
        ";
        $stmt = oci_parse($conn, $item_sql);
        oci_bind_by_name($stmt, ":cart_id", $cart_id);
        oci_execute($stmt);

        while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
            $cart_items[] = $row;
            $total_price += $row['PRICE'] * $row['QUANTITY'];
        }

        oci_free_statement($stmt);
    }
}

// Handle coupon application
$discount_amount = 0;
$applied_coupon = null;
$coupon_error = null;
$coupon_id = null;

// Check for previously applied coupon first
if (isset($_SESSION['applied_coupon'])) {
    $applied_coupon = $_SESSION['applied_coupon'];
    $discount_amount = ($total_price * $applied_coupon['COUPON_DISCOUNT_PERCENT']) / 100;
    $coupon_id = $applied_coupon['COUPON_ID'];
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    if (isset($_POST['apply_coupon'])) {
        $coupon_code = trim($_POST['coupon_code']);
        
        // Validate coupon
        $coupon_sql = "
            SELECT * FROM coupon 
            WHERE coupon_code = :coupon_code 
            AND coupon_start_date <= SYSDATE 
            AND coupon_end_date >= SYSDATE
        ";
        $stmt = oci_parse($conn, $coupon_sql);
        oci_bind_by_name($stmt, ":coupon_code", $coupon_code);
        oci_execute($stmt);
        $coupon = oci_fetch_array($stmt, OCI_ASSOC);
        oci_free_statement($stmt);

        if ($coupon) {
            $coupon_id = $coupon['COUPON_ID'];
            $_SESSION['applied_coupon'] = $coupon;
            $_SESSION['toast_message'] = "Coupon applied successfully!";
            $_SESSION['toast_type'] = "success";
        } else {
            $coupon_id = null;
            $_SESSION['toast_message'] = "Invalid or expired coupon code";
            $_SESSION['toast_type'] = "error";
        }
        
        // Redirect to GET request
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    if (isset($_POST['remove_coupon'])) {
        unset($_SESSION['applied_coupon']);
        $coupon_id = null;
        $_SESSION['toast_message'] = "Coupon removed";
        $_SESSION['toast_type'] = "success";
        
        // Redirect to GET request
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Display toast message if set
if (isset($_SESSION['toast_message'])) {
    $toast_message = $_SESSION['toast_message'];
    $toast_type = $_SESSION['toast_type'];
    unset($_SESSION['toast_message'], $_SESSION['toast_type']);
}

$slots_sql = "
    SELECT 
        collection_slot_id,
        TO_CHAR(slot_date, 'YYYY-MM-DD') as slot_date,
        slot_day,
        TO_CHAR(slot_time, 'HH24:MI') as slot_time,
        slot_duration,
        max_order
    FROM collection_slot 
    WHERE max_order > 0 
    AND slot_time > SYSTIMESTAMP
    ORDER BY slot_date ASC, slot_time ASC
";

$slots_stmt = oci_parse($conn, $slots_sql);
if (!$slots_stmt) {
    $error = oci_error($conn);
    error_log("SQL Parse Error: " . $error['message']);
}

$execute_result = oci_execute($slots_stmt);
if (!$execute_result) {
    $error = oci_error($slots_stmt);
    error_log("SQL Execute Error: " . $error['message']);
}

$timeslots = [];
while ($slot = oci_fetch_array($slots_stmt, OCI_ASSOC)) {
    // Calculate end time by adding duration (in hours)
    $start_time = $slot['SLOT_TIME'];
    $duration_hours = $slot['SLOT_DURATION'] / 60; // Convert minutes to hours
    $end_time = date('H:i', strtotime($start_time . ' + ' . $duration_hours . ' hours'));
    
    $timeslots[] = [
        'id' => $slot['COLLECTION_SLOT_ID'],
        'label' => $slot['SLOT_DATE'] . " - " . $slot['SLOT_DAY'] . " - " . $slot['SLOT_TIME'] . " to " . $end_time,
        'timestamp' => $slot['SLOT_DATE'] . ' ' . $slot['SLOT_TIME'],
        'end_timestamp' => $slot['SLOT_DATE'] . ' ' . $end_time,
        'max_order' => $slot['MAX_ORDER']
    ];
}

error_log("Total slots fetched: " . count($timeslots));
error_log("Final timeslots array: " . print_r($timeslots, true));

oci_free_statement($slots_stmt);

$user_id = $_SESSION['user_id'] ?? null;

if (isset($_POST['clear_cart']) && $user_id) {
    $delete_sql = "
        DELETE FROM cart_product
        WHERE cart_id IN (
            SELECT cart_id FROM cart WHERE user_id = :user_id
        )
    ";

    $stmt = oci_parse($conn, $delete_sql);
    oci_bind_by_name($stmt, ":user_id", $user_id);
    $result = oci_execute($stmt);
    oci_free_statement($stmt);

    if ($result) {
        $_SESSION['toast_message'] = "Cart cleared successfully.";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Failed to clear cart.";
        $_SESSION['toast_type'] = "error";
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

if (isset($_POST['add_to_cart']) && $user_id) {
    $product_id = $_POST['product_id'] ?? null;
    $quantity = $_POST['quantity'] ?? 1;
    
    if ($cart_total + $quantity > 20) {
        $_SESSION['toast_message'] = "Cannot add items: Cart would exceed 20 items limit";
        $_SESSION['toast_type'] = "error";
    } else {
        $cart_sql = "SELECT cart_id FROM cart WHERE user_id = :user_id";
        $cart_stmt = oci_parse($conn, $cart_sql);
        oci_bind_by_name($cart_stmt, ":user_id", $user_id);
        oci_execute($cart_stmt);
        $cart_row = oci_fetch_array($cart_stmt, OCI_ASSOC);
        oci_free_statement($cart_stmt);

        if (!$cart_row) {
            $create_cart_sql = "INSERT INTO cart (user_id, add_date) VALUES (:user_id, SYSDATE) RETURNING cart_id INTO :cart_id";
            $create_stmt = oci_parse($conn, $create_cart_sql);
            oci_bind_by_name($create_stmt, ":user_id", $user_id);
            oci_bind_by_name($create_stmt, ":cart_id", $cart_id, -1, SQLT_INT);
            oci_execute($create_stmt);
            oci_free_statement($create_stmt);
        } else {
            $cart_id = $cart_row['CART_ID'];
        }

        $check_sql = "SELECT quantity FROM cart_product WHERE cart_id = :cart_id AND product_id = :product_id";
        $check_stmt = oci_parse($conn, $check_sql);
        oci_bind_by_name($check_stmt, ":cart_id", $cart_id);
        oci_bind_by_name($check_stmt, ":product_id", $product_id);
        oci_execute($check_stmt);
        $existing_item = oci_fetch_array($check_stmt, OCI_ASSOC);
        oci_free_statement($check_stmt);

        if ($existing_item) {
            // Update quantity if product exists in cart
            $new_quantity = $existing_item['QUANTITY'] + $quantity;
            if ($new_quantity > 20) {
                $_SESSION['toast_message'] = "Cannot add items: Cart would exceed 20 items limit";
                $_SESSION['toast_type'] = "error";
            } else {
                $update_sql = "UPDATE cart_product SET quantity = :quantity WHERE cart_id = :cart_id AND product_id = :product_id";
                $update_stmt = oci_parse($conn, $update_sql);
                oci_bind_by_name($update_stmt, ":quantity", $new_quantity);
                oci_bind_by_name($update_stmt, ":cart_id", $cart_id);
                oci_bind_by_name($update_stmt, ":product_id", $product_id);
                if (oci_execute($update_stmt)) {
                    $_SESSION['toast_message'] = "Cart updated successfully";
                    $_SESSION['toast_type'] = "success";
                } else {
                    $_SESSION['toast_message'] = "Failed to update cart";
                    $_SESSION['toast_type'] = "error";
                }
                oci_free_statement($update_stmt);
            }
        } else {
            // Insert new product into cart
            $insert_sql = "INSERT INTO cart_product (cart_id, product_id, quantity) VALUES (:cart_id, :product_id, :quantity)";
            $insert_stmt = oci_parse($conn, $insert_sql);
            oci_bind_by_name($insert_stmt, ":cart_id", $cart_id);
            oci_bind_by_name($insert_stmt, ":product_id", $product_id);
            oci_bind_by_name($insert_stmt, ":quantity", $quantity);
            if (oci_execute($insert_stmt)) {
                $_SESSION['toast_message'] = "Product added to cart successfully";
                $_SESSION['toast_type'] = "success";
            } else {
                $_SESSION['toast_message'] = "Failed to add product to cart";
                $_SESSION['toast_type'] = "error";
            }
            oci_free_statement($insert_stmt);
        }
    }
    
    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Add this after session_start()
$paypalURL = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
$paypalID = 'sb-2gbxy41155443@business.example.com';

// Handle form submission for checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed_to_checkout'])) {
    $selected_timeslot_id = $_POST['timeslot_id'] ?? null;
    
    if (!$selected_timeslot_id) {
        $_SESSION['toast_message'] = "Please select a delivery timeslot";
        $_SESSION['toast_type'] = "error";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Check if timeslot is still available
    $check_slot_sql = "SELECT max_order FROM collection_slot WHERE collection_slot_id = :slot_id AND max_order > 0";
    $check_stmt = oci_parse($conn, $check_slot_sql);
    oci_bind_by_name($check_stmt, ":slot_id", $selected_timeslot_id);
    oci_execute($check_stmt);
    $slot_available = oci_fetch_array($check_stmt, OCI_ASSOC);
    oci_free_statement($check_stmt);

    if (!$slot_available) {
        $_SESSION['toast_message'] = "Selected timeslot is no longer available";
        $_SESSION['toast_type'] = "error";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Calculate final total with any applied discount
    $final_total = $total_price;
    if (isset($_SESSION['applied_coupon'])) {
        $discount_percent = $_SESSION['applied_coupon']['COUPON_DISCOUNT_PERCENT'];
        $final_total = $total_price - ($total_price * $discount_percent / 100);
    }

    // Store necessary data in session
    $_SESSION['checkout_data'] = [
        'cart_id' => $cart_id,
        'timeslot_id' => $selected_timeslot_id,
        'amount' => $final_total,
        'coupon_id' => isset($_SESSION['applied_coupon']) ? $_SESSION['applied_coupon']['COUPON_ID'] : null
    ];

    // Create PayPal form
    echo '<form action="' . $paypalURL . '" method="post">';
    echo '<input type="hidden" name="business" value="' . $paypalID . '">';
    echo '<input type="hidden" name="cmd" value="_xclick">';
    echo '<input type="hidden" name="item_name" value="GoCleckOut Order">';
    echo '<input type="hidden" name="item_number" value="' . $selected_timeslot_id . '-' . $cart_id . '">';
    echo '<input type="hidden" name="amount" value="' . number_format($final_total, 2, '.', '') . '">';
    echo '<input type="hidden" name="currency_code" value="GBP">';
    echo '<input type="hidden" name="quantity" value="1">';
    
    echo '<input type="hidden" name="return" value="http://localhost/GCO/frontend/user/payment_success.php?amount=' . $final_total . '&cart_id=' . $cart_id . '&timeslot_id=' . $selected_timeslot_id . '&coupon_id=' . $coupon_id . '&user_id=' . $_SESSION['user_id'] . '&logged_in=true">';
    echo '<input type="hidden" name="cancel_return" value="http://localhost/GCO/frontend/user/payment_cancel.php?user_id=' . $_SESSION['user_id'] . '&logged_in=true">';
    echo '<input type="image" style="display: none;" name="submit" border="0" src="https://www.paypalobjects.com/en_US/i/btn/btn_buynow_LG.gif" alt="PayPal - The safer, easier way to pay online">';
    echo '<img alt="" border="0" width="1" height="1" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif">';
    echo '</form>';
    echo '<script>document.forms[0].submit();</script>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Cart - GoCleckOut</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }

        .header {
            background-color: #ffffff;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .header .form-control {
            border-radius: 20px;
            border: 1px solid #ccc;
        }

        .header h4 {
            color: #f44357;
            font-weight: bold;
        }

        .breadcrumb {
            background: none;
            font-size: 14px;
            font-weight: 500;
        }

        .breadcrumb a {
            text-decoration: none;
            color: #f44357;
        }

        .breadcrumb-item.active {
            color: #6c757d;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: 0.3s;
        }

        .btn-primary {
            background-color: #f44357;
            border-color: #f44357;
        }

        .btn-primary:hover {
            background-color: #d7374a;
            border-color: #d7374a;
        }

        .btn-outline-primary {
            color: #f44357;
            border-color: #f44357;
        }

        .btn-outline-primary:hover {
            background-color: #f44357;
            color: white;
        }

        .cart-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }

        .cart-item img {
            max-width: 80px;
            border-radius: 5px;
        }

        .cart-item .item-name {
            font-weight: 600;
            font-size: 16px;
        }

        .cart-item .item-price {
            color: #f44357;
            font-weight: 600;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .quantity-control button {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quantity-control button:not(:disabled):hover {
            background-color: #f44357;
            color: white;
            border-color: #f44357;
        }

        .quantity-control button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .quantity-display {
            font-size: 1.1rem;
            font-weight: bold;
            min-width: 2rem;
            text-align: center;
        }

        .remove-btn {
            color: #6c757d;
            border: none;
            background: none;
            font-size: 20px;
        }

        .remove-btn:hover {
            color: #f44357;
        }

        .cart-summary {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .summary-total {
            font-weight: bold;
            font-size: 18px;
            border-top: 1px solid #eee;
            padding-top: 10px;
            margin-top: 10px;
        }

        .promo-code {
            border: 1px dashed #ddd;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }

        .popular-products {
            margin-top: 40px;
        }

        .popular-products .card {
            padding: 10px;
            text-align: center;
        }

        .popular-products .card img {
            max-width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 5px;
        }

        .popular-products .product-name {
            font-size: 14px;
            font-weight: 600;
            margin: 10px 0 5px;
        }

        .popular-products .product-price {
            color: #f44357;
            font-weight: 600;
        }

        .footer {
            background-color: #fff;
            padding: 40px 0;
            border-top: 1px solid #ddd;
            margin-top: 40px;
        }

        .footer h6 {
            font-weight: bold;
        }

        .footer p,
        .footer a {
            color: #6c757d;
            text-decoration: none;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .toast {
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            padding: 15px 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-width: 250px;
            animation: slideIn 0.3s ease-out;
        }

        .toast.success {
            border-left: 4px solid #28a745;
        }

        .toast.error {
            border-left: 4px solid #dc3545;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>

<body data-cart-total="<?= $cart_total ?>">
    <div class="toast-container">
        <?php if (isset($toast_message)): ?>
            <div class="toast <?= $toast_type ?>" style="display: block;">
                <div><?= htmlspecialchars($toast_message) ?></div>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Header -->
    <?php include '../header.php' ?>

    <div class="container my-4">
        <?php
        $total_items = 0;

        if ($user_id) {
            $sql = "
                SELECT SUM(quantity) AS total
                FROM cart_product
                WHERE cart_id = (
                    SELECT cart_id FROM cart WHERE user_id = :user_id
                )
            ";

            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ":user_id", $user_id);
            oci_execute($stmt);

            $row = oci_fetch_array($stmt, OCI_ASSOC);
            $total_items = $row['total'] ?? 0;

            oci_free_statement($stmt);
        }
        ?>

        <h4 class="mb-4">Shopping Cart <span class="badge bg-secondary"><?= $total_items ?> Items</span></h4>


        <div class="row">
            <!-- Cart Items -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <?php if (!empty($cart_items)): ?>
                            <?php foreach ($cart_items as $item): ?>
                                <div class="cart-item row align-items-center" data-product-id="<?= $item['PRODUCT_ID'] ?>">
                                    <div class="col-md-2 col-3">
                                        <?php
                                        $img_src = '';
                                        if ($item['PRODUCT_IMAGE'] instanceof OCILob) {
                                            $img_data = $item['PRODUCT_IMAGE']->load();
                                            $img_src = "data:image/jpeg;base64," . base64_encode($img_data);
                                        }
                                        ?>
                                        <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($item['PRODUCT_NAME']) ?>" class="img-fluid" />
                                    </div>
                                    <div class="col-md-4 col-9">
                                        <div class="item-name"><?= htmlspecialchars($item['PRODUCT_NAME']) ?></div>
                                    </div>
                                    <div class="col-md-2 col-4 mt-3 mt-md-0">
                                        <div class="item-price" data-price="<?= $item['PRICE'] ?>">Rs. <?= number_format($item['PRICE'], 2) ?></div>
                                        <div class="item-subtotal text-muted small">Rs. <?= number_format($item['PRICE'] * $item['QUANTITY'], 2) ?></div>
                                    </div>
                                    <div class="col-md-3 col-6 mt-3 mt-md-0">
                                        <div class="quantity-control">
                                            <button type="button" class="btn btn-sm btn-outline-secondary decrease-btn" 
                                                    onclick="updateQuantity(<?= $item['PRODUCT_ID'] ?>, 'decrease')"
                                                    <?= $item['QUANTITY'] <= 1 ? 'disabled' : '' ?>>
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <span class="quantity-display mx-2"><?= $item['QUANTITY'] ?></span>
                                            <button type="button" class="btn btn-sm btn-outline-secondary increase-btn"
                                                    onclick="updateQuantity(<?= $item['PRODUCT_ID'] ?>, 'increase')"
                                                    <?= $total_items >= 20 ? 'disabled' : '' ?>>
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-1 col-2 text-end mt-3 mt-md-0">
                                        <button type="button" class="remove-btn" onclick="removeItem(<?= $item['PRODUCT_ID'] ?>)">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Your cart is empty.</p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <button class="btn btn-outline-primary" onclick="location.href='/GCO/frontend/home.php?logged_in=<?= $_SESSION['logged_in'] ?>'"><i class="fa fa-arrow-left me-2"></i>Continue Shopping</button>
                        <button type="button" onclick="confirmClearCart()" class="btn btn-outline-danger"><i class="fa fa-trash me-2"></i>Clear Cart</button>
                    </div>
                </div>
            </div>

            <!-- Cart Summary -->
            <div class="col-lg-4">
                <div class="cart-summary">
                    <div class="summary-item">
                        <span>Subtotal</span>
                        <span id="cart-subtotal">Rs. <?= number_format($total_price, 2) ?></span>
                    </div>
                    
                    <!-- Coupon Section -->
                    <div class="promo-code mt-3">
                        <?php if ($applied_coupon): ?>
                            <div class="applied-coupon mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Applied Coupon:</strong>
                                        <span class="text-success"><?= htmlspecialchars($applied_coupon['COUPON_CODE']) ?></span>
                                        <br>
                                        <small class="text-muted"><?= $applied_coupon['COUPON_DISCOUNT_PERCENT'] ?>% off</small>
                                    </div>
                                    <form method="post" class="d-inline">
                                        <button type="submit" name="remove_coupon" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="post" class="coupon-form">
                                <div class="input-group">
                                    <input type="text" name="coupon_code" class="form-control" placeholder="Enter coupon code" required>
                                    <button type="submit" name="apply_coupon" class="btn btn-primary">Apply</button>
                                </div>
                                <?php if ($coupon_error): ?>
                                    <div class="text-danger mt-2 small"><?= htmlspecialchars($coupon_error) ?></div>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if ($discount_amount > 0): ?>
                    <div class="summary-item text-success">
                        <span>Discount</span>
                        <span>- Rs. <?= number_format($discount_amount, 2) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="summary-item summary-total">
                        <span>Total</span>
                        <span id="cart-total">Rs. <?= number_format($total_price - $discount_amount, 2) ?></span>
                    </div>

                    <?php if (!empty($cart_items)): ?>
                        <form method="post" class="mt-4">
                            <div class="mb-3">
                                <label for="timeslot_id" class="form-label">Select Delivery Timeslot:</label>
                                <select name="timeslot_id" id="timeslot_id" class="form-select" required>
                                    <option value="">Choose a timeslot...</option>
                                    <?php foreach ($timeslots as $slot): ?>
                                        <option value="<?= htmlspecialchars($slot['id']) ?>">
                                            <?= htmlspecialchars($slot['label']) ?> 
                                            (<?= $slot['max_order'] ?> slots remaining)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="proceed_to_checkout" class="btn btn-primary w-100">
                                <i class="fas fa-shopping-cart me-2"></i>Proceed to Checkout
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <?php include '../footer.php' ?>

    <script>
        const TOAST_DURATION = 3000; // 3 seconds

        function showToast(message, type = 'success') {
            document.querySelectorAll('.toast').forEach(toast => toast.remove());
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div>${message}</div>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            document.querySelector('.toast-container').appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, TOAST_DURATION);
        }

        function updateCartTotals() {
            let subtotal = 0;
            let totalItems = 0;
            
            document.querySelectorAll('.cart-item').forEach(item => {
                const price = parseFloat(item.querySelector('.item-price').dataset.price);
                const quantity = parseInt(item.querySelector('.quantity-display').textContent);
                const itemSubtotal = price * quantity;
                item.querySelector('.item-subtotal').textContent = `Rs. ${itemSubtotal.toFixed(2)}`;
                subtotal += itemSubtotal;
                totalItems += quantity;
            });

            // Update total items badge
            const badge = document.querySelector('.badge');
            if (badge) {
                badge.textContent = `${totalItems} Items`;
            }

            // Get discount percentage from the applied coupon display
            const appliedCoupon = document.querySelector('.applied-coupon');
            let discount = 0;
            if (appliedCoupon) {
                const discountPercent = parseInt(appliedCoupon.querySelector('.text-muted').textContent);
                if (!isNaN(discountPercent)) {
                    discount = (subtotal * discountPercent) / 100;
                }
            }

            // Calculate total as subtotal minus discount
            const total = Math.max(0, subtotal - discount);

            // Update subtotal and total displays
            const subtotalElement = document.getElementById('cart-subtotal');
            const totalElement = document.getElementById('cart-total');
            
            if (subtotalElement) {
                subtotalElement.textContent = `Rs. ${subtotal.toFixed(2)}`;
            }
            if (totalElement) {
                totalElement.textContent = `Rs. ${total.toFixed(2)}`;
            }

            // Update discount display if it exists
            const discountElement = document.querySelector('.summary-item.text-success span:last-child');
            if (discountElement) {
                discountElement.textContent = `- Rs. ${discount.toFixed(2)}`;
            }

            // Update increase buttons based on total items
            document.querySelectorAll('.increase-btn').forEach(btn => {
                btn.disabled = totalItems >= 20;
            });

            // Update the PayPal form amount if it exists
            const paypalAmountInput = document.querySelector('input[name="amount"]');
            if (paypalAmountInput) {
                paypalAmountInput.value = total.toFixed(2);
            }
        }

        function updateQuantity(productId, action) {
            const cartItem = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
            const quantityDisplay = cartItem.querySelector('.quantity-display');
            const decreaseBtn = cartItem.querySelector('.decrease-btn');
            const increaseBtn = cartItem.querySelector('.increase-btn');
            const removeBtn = cartItem.querySelector('.remove-btn');
            const itemPrice = parseFloat(cartItem.querySelector('.item-price').dataset.price);
            const itemSubtotal = cartItem.querySelector('.item-subtotal');

            // Disable buttons during update
            decreaseBtn.disabled = true;
            increaseBtn.disabled = true;
            if (removeBtn) removeBtn.disabled = true;

            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('action', action);

            fetch('update_cart_quantity.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.new_quantity === 0) {
                        // Remove item from cart
                        cartItem.remove();
                        if (document.querySelectorAll('.cart-item').length === 0) {
                            location.reload();
                        }
                    } else {
                        // Update quantity display and subtotal only after successful server response
                        quantityDisplay.textContent = data.new_quantity;
                        const newSubtotal = (itemPrice * data.new_quantity).toFixed(2);
                        itemSubtotal.textContent = `Rs. ${newSubtotal}`;
                        decreaseBtn.disabled = data.new_quantity <= 1;
                        increaseBtn.disabled = false;
                        if (removeBtn) removeBtn.disabled = false;
                    }

                    // Update cart totals
                    updateCartTotals();

                    showToast(data.message);
                } else {
                    showToast(data.message, 'error');
                    // Restore previous state on error
                    decreaseBtn.disabled = parseInt(quantityDisplay.textContent) <= 1;
                    increaseBtn.disabled = false;
                    if (removeBtn) removeBtn.disabled = false;
                }
            })
            .catch(error => {
                showToast('An error occurred while updating the cart', 'error');
                // Restore previous state on error
                decreaseBtn.disabled = parseInt(quantityDisplay.textContent) <= 1;
                increaseBtn.disabled = false;
                if (removeBtn) removeBtn.disabled = false;
            });
        }

        function removeItem(productId) {
            if (confirm('Are you sure you want to remove this item?')) {
                const cartItem = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
                const removeBtn = cartItem.querySelector('.remove-btn');
                
                // Disable button and show loading state
                removeBtn.disabled = true;
                const originalHTML = removeBtn.innerHTML;
                removeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('action', 'remove');

                fetch('update_cart_quantity.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove item from cart
                        cartItem.remove();
                        if (document.querySelectorAll('.cart-item').length === 0) {
                            location.reload();
                        }
                        
                        // Update cart totals
                        updateCartTotals();

                        showToast(data.message);
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(error => {
                    showToast('An error occurred while removing the item', 'error');
                })
                .finally(() => {
                    // Restore button state
                    removeBtn.disabled = false;
                    removeBtn.innerHTML = originalHTML;
                });
            }
        }

        function confirmClearCart() {
            if (confirm('Are you sure you want to clear your cart? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'clear_cart';
                input.value = '1';
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Call updateCartTotals when the page loads
        document.addEventListener('DOMContentLoaded', updateCartTotals);
    </script>
</body>

</html>