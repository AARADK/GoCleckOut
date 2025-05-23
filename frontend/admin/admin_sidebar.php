<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once "../../backend/database/connect.php"; 

$user_id = $_SESSION['user_id'];
?>

<div class="bg-danger bg-opacity-75 d-flex flex-column min-vh-100 py-3">
    <ul class="nav flex-column flex-grow-1">
        <li class="nav-item px-3">
            <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) === 'admin_dashboard.php' ? 'active' : '' ?>" href="admin_dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) === 'admin_verify.php' ? 'active' : '' ?>" href="admin_verify.php">
                <i class="fas fa-user-check me-2"></i>
                <span>Verify Traders</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) === 'admin_categories.php' ? 'active' : '' ?>" href="admin_categories.php">
                <i class="fas fa-tags me-2"></i>
                <span>Categories</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) === 'admin_orders.php' ? 'active' : '' ?>" href="admin_orders.php">
                <i class="fas fa-shopping-cart me-2"></i>
                <span>Orders</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) === 'admin_trader_shops.php' ? 'active' : '' ?>" href="admin_trader_shops.php">
                <i class="fas fa-store me-2"></i>
                <span>Shops</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) === 'admin_trader_products.php' ? 'active' : '' ?>" href="admin_trader_products.php">
                <i class="fas fa-box me-2"></i>
                <span>Products</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) === 'admin_users.php' ? 'active' : '' ?>" href="admin_users.php">
                <i class="fas fa-users me-2"></i>
                <span>Users</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) === 'admin_reports.php' ? 'active' : '' ?>" href="admin_reports.php">
                <i class="fas fa-chart-bar me-2"></i>
                <span>Reports</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) === 'admin_profile.php' ? 'active' : '' ?>" href="admin_profile.php">
                <i class="fas fa-user me-2"></i>
                <span>Profile</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white" href="../login/login_portal.php?sign_in=true">
                <i class="fas fa-sign-out-alt me-2"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

<!-- Add Bootstrap JS, Popper.js, and jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>