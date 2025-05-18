<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once "../../backend/database/connect.php"; // Use connect.php for Oracle (OCI)

$user_id = $_SESSION['user_id'];

$conn = getDBConnection(); // Get the Oracle connection

// Prepare the SQL query
$sql = "SELECT status FROM USERS WHERE user_id = :user_id";
$stmt = oci_parse($conn, $sql);

// Bind the parameter
oci_bind_by_name($stmt, ":user_id", $user_id);

// Execute the statement
oci_execute($stmt);

// Fetch the result
$row = oci_fetch_assoc($stmt);

// Check the status
if ($row) {
    if ($row['STATUS'] == 'pending') {
        echo 'User pending, Wait for Approval...';
    } else if ($row['STATUS'] == 'inactive') {
        echo 'You have been rejected.';
    } else {
        echo 'Add Your Shops to See Analytics.';
    }
} else {
    echo 'User not found or database error.';
}
?>
