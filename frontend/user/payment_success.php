<?php
session_start();
require_once __DIR__ . '/../../backend/database/connect.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['selected_timeslot_id']) || !isset($_SESSION['selected_cart_id'])) {
    header('Location: cart.php');
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

try {
    oci_execute(oci_parse($conn, "ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'"));
    
    // Start transaction
    oci_execute(oci_parse($conn, "BEGIN"));
    
    $user_id = $_SESSION['user_id'];
    $timeslot_id = $_SESSION['selected_timeslot_id'];
    $cart_id = $_SESSION['selected_cart_id'];
    
    // Get cart total
    $cart_sql = "
        SELECT SUM(cp.quantity * p.price) as total_amount
        FROM cart_product cp
        JOIN product p ON cp.product_id = p.product_id
        WHERE cp.cart_id = :cart_id
    ";
    $cart_stmt = oci_parse($conn, $cart_sql);
    oci_bind_by_name($cart_stmt, ":cart_id", $cart_id);
    oci_execute($cart_stmt);
    $cart_total = oci_fetch_array($cart_stmt, OCI_ASSOC)['TOTAL_AMOUNT'];
    oci_free_statement($cart_stmt);
    
    // Create payment record
    $payment_sql = "
        INSERT INTO payment (
            payment_id, user_id, amount, payment_date, 
            payment_method, status, transaction_id
        ) VALUES (
            payment_seq.NEXTVAL, :user_id, :amount, SYSDATE,
            'paypal', 'completed', :transaction_id
        ) RETURNING payment_id INTO :payment_id
    ";
    $payment_stmt = oci_parse($conn, $payment_sql);
    $transaction_id = $_GET['tx'] ?? 'PAYPAL-' . uniqid();
    oci_bind_by_name($payment_stmt, ":user_id", $user_id);
    oci_bind_by_name($payment_stmt, ":amount", $cart_total);
    oci_bind_by_name($payment_stmt, ":transaction_id", $transaction_id);
    oci_bind_by_name($payment_stmt, ":payment_id", $payment_id, 32);
    oci_execute($payment_stmt);
    oci_free_statement($payment_stmt);
    
    // Update timeslot_cart status to confirmed
    $update_sql = "
        UPDATE timeslot_cart 
        SET status = 'confirmed', payment_id = :payment_id
        WHERE timeslot_id = :timeslot_id 
        AND cart_id = :cart_id 
        AND status = 'reserved'
    ";
    $update_stmt = oci_parse($conn, $update_sql);
    oci_bind_by_name($update_stmt, ":payment_id", $payment_id);
    oci_bind_by_name($update_stmt, ":timeslot_id", $timeslot_id);
    oci_bind_by_name($update_stmt, ":cart_id", $cart_id);
    oci_execute($update_stmt);
    oci_free_statement($update_stmt);
    
    // Update product stock
    $stock_sql = "
        UPDATE product p
        SET stock = stock - (
            SELECT quantity 
            FROM cart_product cp 
            WHERE cp.product_id = p.product_id 
            AND cp.cart_id = :cart_id
        )
        WHERE product_id IN (
            SELECT product_id 
            FROM cart_product 
            WHERE cart_id = :cart_id
        )
    ";
    $stock_stmt = oci_parse($conn, $stock_sql);
    oci_bind_by_name($stock_stmt, ":cart_id", $cart_id);
    oci_execute($stock_stmt);
    oci_free_statement($stock_stmt);
    
    // Clear the cart
    $clear_sql = "DELETE FROM cart_product WHERE cart_id = :cart_id";
    $clear_stmt = oci_parse($conn, $clear_sql);
    oci_bind_by_name($clear_stmt, ":cart_id", $cart_id);
    oci_execute($clear_stmt);
    oci_free_statement($clear_stmt);
    
    // Commit transaction
    oci_execute(oci_parse($conn, "COMMIT"));
    
    // Clear session variables
    unset($_SESSION['selected_timeslot_id']);
    unset($_SESSION['selected_cart_id']);
    
    $_SESSION['toast_message'] = "Payment successful! Your order has been confirmed.";
    $_SESSION['toast_type'] = "success";
    header("Location: orders.php");
    exit();
    
} catch (Exception $e) {
    // Rollback transaction
    oci_execute(oci_parse($conn, "ROLLBACK"));
    
    $_SESSION['toast_message'] = "Error processing payment: " . $e->getMessage();
    $_SESSION['toast_type'] = "error";
    header("Location: cart.php");
    exit();
}
?> 