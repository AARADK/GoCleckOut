<?php
session_start();
print_r($_SERVER);
print_r($_SESSION);

// session_start();
// require_once '../../backend/database/connect.php';



// if (!isset($_SESSION['user_id'])) {
//     // header('Location: login.php');
//     // exit;
// }

// $conn = getDBConnection();
// if (!$conn) {
//     die("Database connection failed");
// }

// // Get PayPal transaction details
// $payment_id = $_GET['tx'] ?? null; // Transaction ID
// $payment_status = $_GET['st'] ?? null; // Payment status
// $amount = $_GET['amt'] ?? null; // Amount paid
// $custom = $_GET['cm'] ?? null; // Custom data (cart_id-timeslot_id)

// if ($payment_status === 'Completed' && $payment_id && $amount && $custom) {
//     // Start transaction
//     oci_execute($conn, "BEGIN");
    
//     try {
//         // Split custom data to get cart_id and timeslot_id
//         list($cart_id, $timeslot_id) = explode('-', $custom);
        
//         // Insert payment record
//         $payment_sql = "
//             INSERT INTO payment (
//                 cart_id, user_id, amount, payment_date, status, 
//                 payment_method, transaction_id
//             ) VALUES (
//                 :cart_id, :user_id, :amount, SYSTIMESTAMP, 'completed',
//                 'PayPal', :transaction_id
//             ) RETURNING payment_id INTO :payment_id
//         ";
        
//         $payment_stmt = oci_parse($conn, $payment_sql);
//         oci_bind_by_name($payment_stmt, ":cart_id", $cart_id);
//         oci_bind_by_name($payment_stmt, ":user_id", $_SESSION['user_id']);
//         oci_bind_by_name($payment_stmt, ":amount", $amount);
//         oci_bind_by_name($payment_stmt, ":transaction_id", $payment_id);
//         oci_bind_by_name($payment_stmt, ":payment_id", $new_payment_id, -1, SQLT_INT);
        
//         if (!oci_execute($payment_stmt)) {
//             throw new Exception("Failed to insert payment record");
//         }
        
//         // Get cart items for order creation
//         $items_sql = "
//             SELECT cp.quantity, p.product_name, p.price, p.product_id
//             FROM cart_product cp
//             JOIN product p ON cp.product_id = p.product_id
//             WHERE cp.cart_id = :cart_id
//         ";
        
//         $items_stmt = oci_parse($conn, $items_sql);
//         oci_bind_by_name($items_stmt, ":cart_id", $cart_id);
//         oci_execute($items_stmt);
        
//         $total_items = 0;
//         $total_amount = 0;
        
//         while ($item = oci_fetch_array($items_stmt, OCI_ASSOC)) {
//             $total_items += $item['QUANTITY'];
//             $total_amount += $item['PRICE'] * $item['QUANTITY'];
//         }
        
//         // Create order
//         $order_sql = "
//             INSERT INTO orders (
//                 order_date, total_item_count, total_amount, 
//                 order_status, collection_slot_id, user_id, cart_id
//             ) VALUES (
//                 SYSTIMESTAMP, :total_items, :total_amount,
//                 'pending', :timeslot_id, :user_id, :cart_id
//             )
//         ";
        
//         $order_stmt = oci_parse($conn, $order_sql);
//         oci_bind_by_name($order_stmt, ":total_items", $total_items);
//         oci_bind_by_name($order_stmt, ":total_amount", $total_amount);
//         oci_bind_by_name($order_stmt, ":timeslot_id", $timeslot_id);
//         oci_bind_by_name($order_stmt, ":user_id", $_SESSION['user_id']);
//         oci_bind_by_name($order_stmt, ":cart_id", $cart_id);
        
//         if (!oci_execute($order_stmt)) {
//             throw new Exception("Failed to create order");
//         }
        
//         // Update collection slot availability
//         $update_slot_sql = "
//             UPDATE collection_slot 
//             SET max_order = max_order - 1
//             WHERE collection_slot_id = :timeslot_id
//         ";
        
//         $update_slot_stmt = oci_parse($conn, $update_slot_sql);
//         oci_bind_by_name($update_slot_stmt, ":timeslot_id", $timeslot_id);
        
//         if (!oci_execute($update_slot_stmt)) {
//             throw new Exception("Failed to update collection slot");
//         }
        
//         // Clear the cart
//         $clear_cart_sql = "DELETE FROM cart_product WHERE cart_id = :cart_id";
//         $clear_cart_stmt = oci_parse($conn, $clear_cart_sql);
//         oci_bind_by_name($clear_cart_stmt, ":cart_id", $cart_id);
        
//         if (!oci_execute($clear_cart_stmt)) {
//             throw new Exception("Failed to clear cart");
//         }
        
//         // Commit transaction
//         oci_execute($conn, "COMMIT");
        
//         // Clear session variables
//         unset($_SESSION['selected_timeslot_id']);
//         unset($_SESSION['selected_cart_id']);
//         unset($_SESSION['applied_coupon']);
        
//         // Set success message
//         $_SESSION['toast_message'] = "Payment successful! Your order has been placed.";
//         $_SESSION['toast_type'] = "success";
        
//         // Redirect to orders page
//         header('Location: orders.php');
//         exit;
        
//     } catch (Exception $e) {
//         // Rollback transaction on error
//         oci_execute($conn, "ROLLBACK");
//         error_log("Payment processing error: " . $e->getMessage());
        
//         $_SESSION['toast_message'] = "An error occurred while processing your payment. Please try again.";
//         $_SESSION['toast_type'] = "error";
        
//         header('Location: cart.php');
//         exit;
//     }
// } else {
//     // Invalid payment data
//     $_SESSION['toast_message'] = "Invalid payment data received.";
//     $_SESSION['toast_type'] = "error";
    
//     header('Location: cart.php');
//     exit;
// }
?> 