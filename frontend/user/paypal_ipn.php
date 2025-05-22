<?php
session_start();
require_once '../../backend/database/connect.php';

// PayPal sandbox configuration
$paypal_email = "sb-2gbxy41155443@business.example.com";
$paypal_sandbox = true;

// Get POST data
$raw_post_data = file_get_contents('php://input');
$raw_post_array = explode('&', $raw_post_data);
$myPost = array();
foreach ($raw_post_array as $keyval) {
    $keyval = explode('=', $keyval);
    if (count($keyval) == 2) {
        $myPost[$keyval[0]] = urldecode($keyval[1]);
    }
}

// Read POST data
$req = 'cmd=_notify-validate';
foreach ($myPost as $key => $value) {
    $value = urlencode($value);
    $req .= "&$key=$value";
}

// Post back to PayPal system for validation
$paypal_url = $paypal_sandbox ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' : 'https://ipnpb.paypal.com/cgi-bin/webscr';

$ch = curl_init($paypal_url);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
curl_setopt($ch, CURLOPT_SSLVERSION, 6);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

$res = curl_exec($ch);

if (!$res) {
    $errno = curl_errno($ch);
    $errstr = curl_error($ch);
    curl_close($ch);
    error_log("PayPal IPN Error: [$errno] $errstr");
    exit;
}

$info = curl_getinfo($ch);
curl_close($ch);

// Check if PayPal verified the transaction
if ($res == "VERIFIED") {
    // Verify the payment status
    $payment_status = $_POST['payment_status'];
    $txn_id = $_POST['txn_id'];
    $receiver_email = $_POST['receiver_email'];
    $payment_amount = $_POST['mc_gross'];
    $payment_currency = $_POST['mc_currency'];
    $custom = $_POST['custom'];

    // Verify the receiver email
    if ($receiver_email != $paypal_email) {
        error_log("PayPal IPN Error: Invalid receiver email");
        exit;
    }

    // Process the payment based on status
    if ($payment_status == "Completed") {
        // Split custom data to get cart_id and timeslot_id
        list($cart_id, $timeslot_id) = explode('-', $custom);

        $conn = getDBConnection();
        if (!$conn) {
            error_log("Database connection failed");
            exit;
        }

        try {
            // Begin transaction
            oci_execute($conn, "BEGIN");

            // Insert payment record
            $payment_sql = "INSERT INTO payment (
                cart_id, user_id, amount, payment_date, status, 
                payment_method, transaction_id
            ) VALUES (
                :cart_id, 
                (SELECT user_id FROM cart WHERE cart_id = :cart_id),
                :amount,
                SYSTIMESTAMP,
                'completed',
                'PayPal',
                :transaction_id
            ) RETURNING payment_id INTO :payment_id";

            $stmt = oci_parse($conn, $payment_sql);
            oci_bind_by_name($stmt, ':cart_id', $cart_id);
            oci_bind_by_name($stmt, ':amount', $payment_amount);
            oci_bind_by_name($stmt, ':transaction_id', $txn_id);
            oci_bind_by_name($stmt, ':payment_id', $payment_id, -1, SQLT_INT);

            if (!oci_execute($stmt)) {
                throw new Exception("Failed to insert payment record");
            }

            // Create order
            $order_sql = "INSERT INTO orders (
                order_date, total_item_count, total_amount, 
                order_status, collection_slot_id, user_id, cart_id
            ) VALUES (
                SYSTIMESTAMP,
                (SELECT SUM(quantity) FROM cart_product WHERE cart_id = :cart_id),
                :amount,
                'pending',
                :timeslot_id,
                (SELECT user_id FROM cart WHERE cart_id = :cart_id),
                :cart_id
            ) RETURNING order_id INTO :order_id";

            $stmt = oci_parse($conn, $order_sql);
            oci_bind_by_name($stmt, ':cart_id', $cart_id);
            oci_bind_by_name($stmt, ':amount', $payment_amount);
            oci_bind_by_name($stmt, ':timeslot_id', $timeslot_id);
            oci_bind_by_name($stmt, ':order_id', $order_id, -1, SQLT_INT);

            if (!oci_execute($stmt)) {
                throw new Exception("Failed to create order");
            }

            // Update collection slot max_order
            $update_slot_sql = "UPDATE collection_slot 
                              SET max_order = max_order - 1 
                              WHERE collection_slot_id = :timeslot_id";
            $stmt = oci_parse($conn, $update_slot_sql);
            oci_bind_by_name($stmt, ':timeslot_id', $timeslot_id);
            if (!oci_execute($stmt)) {
                throw new Exception("Failed to update collection slot");
            }

            // Commit transaction
            oci_execute($conn, "COMMIT");
            error_log("PayPal IPN: Payment processed successfully for transaction $txn_id");

        } catch (Exception $e) {
            // Rollback transaction on error
            oci_execute($conn, "ROLLBACK");
            error_log("PayPal IPN Error: " . $e->getMessage());
        }

        oci_close($conn);
    }
} else if ($res == "INVALID") {
    error_log("PayPal IPN Error: Invalid transaction");
}
?> 