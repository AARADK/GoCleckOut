<?php
session_start();
require_once '../../backend/database/connection.php';

if (isset($_SESSION['selected_timeslot_id']) && isset($_SESSION['selected_cart_id'])) {
    try {
        $conn = getConnection();
        
        $release_sql = "
            DELETE FROM timeslot_cart 
            WHERE timeslot_id = :timeslot_id 
            AND cart_id = :cart_id 
            AND status = 'reserved'
        ";
        $release_stmt = oci_parse($conn, $release_sql);
        oci_bind_by_name($release_stmt, ":timeslot_id", $_SESSION['selected_timeslot_id']);
        oci_bind_by_name($release_stmt, ":cart_id", $_SESSION['selected_cart_id']);
        oci_execute($release_stmt);
        oci_free_statement($release_stmt);
    } catch (Exception $e) {
        error_log("Error releasing timeslot reservation: " . $e->getMessage());
    }
}

unset($_SESSION['selected_timeslot_id']);
unset($_SESSION['selected_cart_id']);

$_SESSION['toast_message'] = "Payment was cancelled. Your timeslot reservation has been released.";
$_SESSION['toast_type'] = "error";
header("Location: cart.php");
exit();
?> 