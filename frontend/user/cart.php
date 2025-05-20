<?php
if (session_status() === PHP_SESSION_NONE) session_start();

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

$timeslots = getTimeslots(7);

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

$timeslots = getTimeslots(7);

$user_id = $_SESSION['user_id'] ?? null;

if (isset($_POST['clear_cart']) && $user_id) {
    // Delete products from cart_product where cart belongs to this user
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

    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

if (isset($_POST['remove_product_id']) && $user_id) {
    $product_id = $_POST['remove_product_id'];
    
    // Get cart ID and item quantity
    $cart_sql = "
        SELECT c.cart_id, cp.quantity 
        FROM cart c 
        JOIN cart_product cp ON c.cart_id = cp.cart_id 
        WHERE c.user_id = :user_id AND cp.product_id = :product_id";
    $cart_stmt = oci_parse($conn, $cart_sql);
    oci_bind_by_name($cart_stmt, ":user_id", $user_id);
    oci_bind_by_name($cart_stmt, ":product_id", $product_id);
    oci_execute($cart_stmt);
    $cart_row = oci_fetch_array($cart_stmt, OCI_ASSOC);
    oci_free_statement($cart_stmt);

    if ($cart_row) {
        $cart_id = $cart_row['CART_ID'];
        $quantity = $cart_row['QUANTITY'];
        
        // First update the product stock
        $update_stock_sql = "
            UPDATE product 
            SET stock = stock + :quantity 
            WHERE product_id = :product_id";
        $stmt = oci_parse($conn, $update_stock_sql);
        oci_bind_by_name($stmt, ":quantity", $quantity);
        oci_bind_by_name($stmt, ":product_id", $product_id);
        $update_result = oci_execute($stmt);
        oci_free_statement($stmt);

        if ($update_result) {
            // Then remove from cart
            $delete_sql = "DELETE FROM cart_product WHERE cart_id = :cart_id AND product_id = :product_id";
            $stmt = oci_parse($conn, $delete_sql);
            oci_bind_by_name($stmt, ":cart_id", $cart_id);
            oci_bind_by_name($stmt, ":product_id", $product_id);
            $delete_result = oci_execute($stmt);
            oci_free_statement($stmt);

            if ($delete_result) {
                $_SESSION['toast_message'] = "Item removed from cart and stock updated successfully.";
                $_SESSION['toast_type'] = "success";
            } else {
                // If delete fails, try to revert the stock update
                $revert_stock_sql = "
                    UPDATE product 
                    SET stock = stock - :quantity 
                    WHERE product_id = :product_id";
                $stmt = oci_parse($conn, $revert_stock_sql);
                oci_bind_by_name($stmt, ":quantity", $quantity);
                oci_bind_by_name($stmt, ":product_id", $product_id);
                oci_execute($stmt);
                oci_free_statement($stmt);

                $_SESSION['toast_message'] = "Failed to remove item from cart.";
                $_SESSION['toast_type'] = "error";
            }
        } else {
            $_SESSION['toast_message'] = "Failed to update product stock.";
            $_SESSION['toast_type'] = "error";
        }
    } else {
        $_SESSION['toast_message'] = "Item not found in cart.";
        $_SESSION['toast_type'] = "error";
    }

    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Add this near the top of the file, after session_start()
if (isset($_POST['add_to_cart']) && $user_id) {
    $product_id = $_POST['product_id'] ?? null;
    $quantity = $_POST['quantity'] ?? 1;
    
    // Check if adding this quantity would exceed the limit
    if ($cart_total + $quantity > 20) {
        $_SESSION['toast_message'] = "Cannot add items: Cart would exceed 20 items limit";
        $_SESSION['toast_type'] = "error";
    } else {
        // Rest of your add to cart logic here
        // ... existing add to cart code ...
    }
    
    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['REQUEST_URI']);
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
        }

        .quantity-control button {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 1px solid #ddd;
            background: white;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quantity-control button:hover {
            background-color: #f8f9fa;
        }

        .quantity-control button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .quantity-control span {
            margin: 0 10px;
            font-weight: bold;
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
    <!-- Toast Container -->
    <div class="toast-container"></div>

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
                        <!-- Cart Item 1 -->
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
                                            <button class="decrease-btn" <?= $item['QUANTITY'] <= 1 ? 'disabled' : '' ?>>-</button>
                                            <span class="quantity-display"><?= $item['QUANTITY'] ?></span>
                                            <button class="increase-btn" <?= $total_items >= 20 ? 'disabled' : '' ?>>+</button>
                                        </div>
                                    </div>
                                    <div class="col-md-1 col-2 text-end mt-3 mt-md-0">
                                        <button type="button" class="remove-btn" onclick="removeFromCart(<?= $item['PRODUCT_ID'] ?>)">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Your cart is empty.</p>
                        <?php endif; ?>
                    </div>
                    <select>
                        <?php foreach ($timeslots as $ts): ?>
                            <option value="<?= htmlspecialchars($ts['timestamp']) ?>">
                                <?= htmlspecialchars($ts['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="card-footer d-flex justify-content-between">
                        <button class="btn btn-outline-primary" onclick="location.href='/GCO/frontend/home.php?logged_in=<?= $_SESSION['logged_in'] ?>'"><i class="fa fa-arrow-left me-2"></i>Continue Shopping</button>
                        <form action="" method="post">
                            <button type="submit" name="clear_cart" class="btn btn-outline-danger"><i class="fa fa-trash me-2"></i>Clear Cart</input>
                        </form>
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
                    <div class="summary-item">
                        <span>Shipping</span>
                        <span>Rs. 5.00</span>
                    </div>
                    <div class="summary-item summary-total">
                        <span>Total</span>
                        <span id="cart-total">Rs. <?= number_format($total_price + 5.00, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <?php include '../footer.php' ?>

    <script>
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div>${message}</div>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            document.querySelector('.toast-container').appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function updateCartTotals() {
            let subtotal = 0;
            document.querySelectorAll('.cart-item').forEach(item => {
                const price = parseFloat(item.querySelector('.item-price').dataset.price);
                const quantity = parseInt(item.querySelector('.quantity-display').textContent);
                const itemSubtotal = price * quantity;
                item.querySelector('.item-subtotal').textContent = `Rs. ${itemSubtotal.toFixed(2)}`;
                subtotal += itemSubtotal;
            });

            const shipping = 5.00;
            const total = subtotal + shipping;

            document.getElementById('cart-subtotal').textContent = `Rs. ${subtotal.toFixed(2)}`;
            document.getElementById('cart-total').textContent = `Rs. ${total.toFixed(2)}`;
        }

        function updateQuantity(productId, action) {
            const cartItem = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
            const quantityDisplay = cartItem.querySelector('.quantity-display');
            const decreaseBtn = cartItem.querySelector('.decrease-btn');
            const increaseBtn = cartItem.querySelector('.increase-btn');
            
            // Disable buttons during update
            decreaseBtn.disabled = true;
            increaseBtn.disabled = true;

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
                        cartItem.remove();
                        if (document.querySelectorAll('.cart-item').length === 0) {
                            location.reload();
                        }
                    } else {
                        quantityDisplay.textContent = data.new_quantity;
                        decreaseBtn.disabled = data.new_quantity <= 1;
                    }

                    // Update total items badge
                    const badge = document.querySelector('.badge');
                    badge.textContent = `${data.new_total} Items`;

                    // Update all increase buttons based on new total
                    document.querySelectorAll('.increase-btn').forEach(btn => {
                        btn.disabled = data.new_total >= 20;
                    });

                    // Update cart totals
                    updateCartTotals();

                    showToast(data.message);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast('An error occurred while updating the cart', 'error');
            })
            .finally(() => {
                // Re-enable buttons
                decreaseBtn.disabled = false;
                increaseBtn.disabled = false;
            });
        }

        // Add click handlers to all quantity buttons
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.quantity-control').forEach(control => {
                const cartItem = control.closest('.cart-item');
                const productId = cartItem.dataset.productId;
                
                control.querySelector('.decrease-btn').addEventListener('click', () => {
                    updateQuantity(productId, 'decrease');
                });
                
                control.querySelector('.increase-btn').addEventListener('click', () => {
                    updateQuantity(productId, 'increase');
                });
            });
        });

        function removeFromCart(productId) {
            if (confirm('Are you sure you want to remove this item from your cart?')) {
                const cartItem = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
                const formData = new FormData();
                formData.append('remove_product_id', productId);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        cartItem.remove();
                        updateCartTotals();
                        
                        const badge = document.querySelector('.badge');
                        const currentTotal = parseInt(badge.textContent);
                        badge.textContent = `${currentTotal - 1} Items`;
                        
                        if (document.querySelectorAll('.cart-item').length === 0) {
                            location.reload();
                        }
                        
                        showToast('Item removed from cart successfully');
                    } else {
                        showToast('Failed to remove item from cart', 'error');
                    }
                })
                .catch(error => {
                    showToast('An error occurred while removing the item', 'error');
                });
            }
        }
    </script>
</body>

</html>