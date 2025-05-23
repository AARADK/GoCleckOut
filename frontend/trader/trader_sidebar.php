<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once "../../backend/database/connect.php"; 

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

// Check if the status is 'pending'
$is_pending = ($row && $row['STATUS'] == 'pending');
?>

<div class="bg-danger bg-opacity-75 d-flex flex-column min-vh-100 py-3">
    <ul class="nav flex-column flex-grow-1">
        <li class="nav-item px-3">
            <a class="nav-link text-white" href="trader_dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white <?php echo $is_pending ? 'opacity-50' : ''; ?>" href="<?php echo $is_pending ? '#' : 'trader_reports.php'; ?>" <?php echo $is_pending ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                <i class="fas fa-file-alt me-2"></i>
                <span>Reports</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white <?php echo $is_pending ? 'opacity-50' : ''; ?>" href="<?php echo $is_pending ? '#' : 'trader_shops.php'; ?>" <?php echo $is_pending ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                <i class="fas fa-store me-2"></i>
                <span>Shops</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white <?php echo $is_pending ? 'opacity-50' : ''; ?>" href="<?php echo $is_pending ? '#' : 'trader_products.php'; ?>" <?php echo $is_pending ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                <i class="fas fa-box me-2"></i>
                <span>Products</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white <?php echo $is_pending ? 'opacity-50' : ''; ?>" href="<?php echo $is_pending ? '#' : 'trader_orders.php'; ?>" <?php echo $is_pending ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                <i class="fas fa-shopping-cart me-2"></i>
                <span>Orders</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white <?php echo $is_pending ? 'opacity-50' : ''; ?>" href="<?php echo $is_pending ? '#' : 'trader_invoices.php'; ?>" <?php echo $is_pending ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                <i class="fas fa-file-invoice-dollar me-2"></i>
                <span>Invoices</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white <?php echo $is_pending ? 'opacity-50' : ''; ?>" href="<?php echo $is_pending ? '#' : 'trader_customers.php'; ?>" <?php echo $is_pending ? 'tabindex="-1" aria-disabled="true"' : ''; ?>>
                <i class="fas fa-users me-2"></i>
                <span>Customers</span>
            </a>
        </li>
        <li class="nav-item px-3">
            <a class="nav-link text-white" href="trader_profile.php">
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
