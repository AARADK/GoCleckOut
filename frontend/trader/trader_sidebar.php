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

// Check if the status is 'pending'
$show = ($row && $row['STATUS'] == 'pending') ? 'disabled' : '';
?>

<style>
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 250px;
    background: #1a1a1a;
    padding: 1.5rem;
    transition: all 0.3s ease;
    z-index: 1000;
}

.sidebar-header {
    padding-bottom: 1.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-header h3 {
    color: #fff;
    font-size: 1.5rem;
    margin: 0;
}

.sidebar-header p {
    color: rgba(255,255,255,0.6);
    font-size: 0.9rem;
    margin: 0.5rem 0 0;
}

.nav-item {
    margin-bottom: 0.5rem;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    color: rgba(255,255,255,0.7);
    border-radius: 8px;
    transition: all 0.3s ease;
}

.nav-link:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}

.nav-link.active {
    background: #ff6b6b;
    color: #fff;
}

.nav-link i {
    width: 20px;
    margin-right: 10px;
    font-size: 1.1rem;
}

.nav-link.disabled {
    opacity: 0.5;
    pointer-events: none;
}

.main-content {
    margin-left: 250px;
    padding: 2rem;
}

@media (max-width: 768px) {
    .sidebar {
        width: 70px;
        padding: 1rem;
    }
    
    .sidebar-header h3,
    .sidebar-header p,
    .nav-link span {
        display: none;
    }
    
    .nav-link {
        justify-content: center;
        padding: 0.75rem;
    }
    
    .nav-link i {
        margin: 0;
        font-size: 1.3rem;
    }
    
    .main-content {
        margin-left: 70px;
    }
}
</style>

<div class="sidebar">
    <div class="sidebar-header">
        <h3>Trader Panel</h3>
        <p>Manage your business</p>
    </div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo ($_GET['page'] ?? '') === 'dashboard' ? 'active' : ''; ?>" href="index.php?page=dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($_GET['page'] ?? '') === 'shops' ? 'active' : ''; ?> <?= $show ?>" href="index.php?page=shops">
                <i class="fas fa-store"></i>
                <span>Shops</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($_GET['page'] ?? '') === 'products' ? 'active' : ''; ?> <?= $show ?>" href="index.php?page=products">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($_GET['page'] ?? '') === 'orders' ? 'active' : ''; ?> <?= $show ?>" href="index.php?page=orders">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($_GET['page'] ?? '') === 'customers' ? 'active' : ''; ?> <?= $show ?>" href="index.php?page=customers">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($_GET['page'] ?? '') === 'profile' ? 'active' : ''; ?>" href="index.php?page=profile">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </li>
        <li class="nav-item mt-auto">
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

<!-- Add Bootstrap JS, Popper.js, and jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
