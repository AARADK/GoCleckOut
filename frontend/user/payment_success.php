<?php
session_start();
require_once '../../backend/database/connect.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['selected_timeslot'])) {
    header('Location: cart.php');
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

// Start transaction
oci_execute(oci_parse($conn, "BEGIN"));

try {
    $user_id = $_SESSION['user_id'];
    $timeslot = $_SESSION['selected_timeslot'];
    $cart_id = null;
    $order_id = null;

    // Get cart ID
    $cart_sql = "SELECT cart_id FROM cart WHERE user_id = :user_id";
    $cart_stmt = oci_parse($conn, $cart_sql);
    oci_bind_by_name($cart_stmt, ":user_id", $user_id);
    oci_execute($cart_stmt);
    $cart_row = oci_fetch_array($cart_stmt, OCI_ASSOC);
    oci_free_statement($cart_stmt);

    if (!$cart_row) {
        throw new Exception("Cart not found");
    }
    $cart_id = $cart_row['CART_ID'];

    // Create order
    $order_sql = "
        INSERT INTO orders (user_id, order_date, delivery_timeslot, status, total_amount)
        VALUES (:user_id, SYSDATE, TO_TIMESTAMP(:timeslot, 'YYYY-MM-DD HH24:MI:SS'), 'pending', 
            (SELECT SUM(cp.quantity * p.price) 
             FROM cart_product cp 
             JOIN product p ON cp.product_id = p.product_id 
             WHERE cp.cart_id = :cart_id))
        RETURNING order_id INTO :order_id
    ";
    $order_stmt = oci_parse($conn, $order_sql);
    oci_bind_by_name($order_stmt, ":user_id", $user_id);
    oci_bind_by_name($order_stmt, ":timeslot", $timeslot);
    oci_bind_by_name($order_stmt, ":cart_id", $cart_id);
    oci_bind_by_name($order_stmt, ":order_id", $order_id, -1, SQLT_INT);
    oci_execute($order_stmt);
    oci_free_statement($order_stmt);

    // Move items from cart to order_items
    $move_items_sql = "
        INSERT INTO order_items (order_id, product_id, quantity, price)
        SELECT :order_id, cp.product_id, cp.quantity, p.price
        FROM cart_product cp
        JOIN product p ON cp.product_id = p.product_id
        WHERE cp.cart_id = :cart_id
    ";
    $move_stmt = oci_parse($conn, $move_items_sql);
    oci_bind_by_name($move_stmt, ":order_id", $order_id);
    oci_bind_by_name($move_stmt, ":cart_id", $cart_id);
    oci_execute($move_stmt);
    oci_free_statement($move_stmt);

    // Update product stock
    $update_stock_sql = "
        UPDATE product p
        SET stock = stock - (
            SELECT cp.quantity 
            FROM cart_product cp 
            WHERE cp.product_id = p.product_id 
            AND cp.cart_id = :cart_id
        )
        WHERE EXISTS (
            SELECT 1 
            FROM cart_product cp 
            WHERE cp.product_id = p.product_id 
            AND cp.cart_id = :cart_id
        )
    ";
    $stock_stmt = oci_parse($conn, $update_stock_sql);
    oci_bind_by_name($stock_stmt, ":cart_id", $cart_id);
    oci_execute($stock_stmt);
    oci_free_statement($stock_stmt);

    // Clear cart
    $clear_cart_sql = "DELETE FROM cart_product WHERE cart_id = :cart_id";
    $clear_stmt = oci_parse($conn, $clear_cart_sql);
    oci_bind_by_name($clear_stmt, ":cart_id", $cart_id);
    oci_execute($clear_stmt);
    oci_free_statement($clear_stmt);

    // Commit transaction
    oci_execute(oci_parse($conn, "COMMIT"));

    // Clear session variables
    unset($_SESSION['selected_timeslot']);
    if (isset($_SESSION['applied_coupon'])) {
        unset($_SESSION['applied_coupon']);
    }

    $_SESSION['toast_message'] = "Order placed successfully! Your order ID is: " . $order_id;
    $_SESSION['toast_type'] = "success";
    header('Location: orders.php');
    exit;

} catch (Exception $e) {
    // Rollback transaction on error
    oci_execute(oci_parse($conn, "ROLLBACK"));
    $_SESSION['toast_message'] = "Error processing order: " . $e->getMessage();
    $_SESSION['toast_type'] = "error";
    header('Location: cart.php');
    exit;
}
?> 