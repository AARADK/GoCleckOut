<?php
session_start();

// Clear the selected timeslot from session
if (isset($_SESSION['selected_timeslot'])) {
    unset($_SESSION['selected_timeslot']);
}

$_SESSION['toast_message'] = "Payment was cancelled. Please try again.";
$_SESSION['toast_type'] = "error";
header('Location: cart.php');
exit;
?> 