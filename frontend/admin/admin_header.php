<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login_portal.php?sign_in=true");
    exit;
}
?>

<style>
.navbar {
    box-shadow: 0 2px 4px rgba(0,0,0,.1);
    padding: 1rem;
}
.navbar-brand {
    font-size: 1.5rem;
    font-weight: 600;
}
</style>

<nav class="navbar navbar-expand-lg navbar-light bg-light container-fluid justify-content-center">
    <img src="../assets/logo.png" alt="GoCleckOut Logo" class="img-fluid" style="width: 90px; height: 40px;">
    <a class="navbar-brand text-center" href="admin_dashboard.php">GoCleckOut - Admin Interface</a>
</nav>
