<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Clear checkout data from session
unset($_SESSION['checkout_data']);

$_SESSION['toast_message'] = "Payment was cancelled. You can try again when you're ready.";
$_SESSION['toast_type'] = "info";
header("Location: cart.php");
exit;
?> 