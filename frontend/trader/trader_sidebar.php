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
if ($row) {
    if ($row['STATUS'] == 'pending') {
        $show = 'disabled';
    } else {
        $show = '';
    }
} else {
    // Handle the case where no row was found
    $show = '';
}
?>


<style>
   .nav-item, .nav-link {
      color: #ff6b6b;
   }
   .nav-item:hover, .nav-link:hover {
      color:rgb(70, 70, 70);
   }
</style>

<link rel="stylesheet" href="../css/trader/sidebar.css">

<div class="sidebar">
   <ul class="nav flex-column">
      <li class="nav-item">
         <a class="nav-link" href="index.php?page=dashboard">
            <i class="fas fa-tachometer-alt"></i> Dashboard
         </a>
      </li>
      
      <!-- Dropdown for Products -->
      <!-- <li class="nav-item dropdown">
         <a class="nav-link dropdown-toggle <?= $show?>" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="fas fa-cogs"></i> Products
         </a>
         <div class="dropdown-menu" aria-labelledby="navbarDropdown">
            <a class="dropdown-item" href="index.php?page=shop1">Shop 1</a>
            <a class="dropdown-item" href="index.php?page=shop2">Shop 2</a>
         </div>
      </li> -->
      
      <li class="nav-item">
         <a class="nav-link <?= $show?>" href="index.php?page=products">
            <i class="fas fa-box"></i> Products
         </a>
      </li>
      <li class="nav-item">
         <a class="nav-link <?= $show?>" href="index.php?page=orders">
            <i class="fas fa-box"></i> Orders
         </a>
      </li>
      <li class="nav-item">
         <a class="nav-link <?= $show?>" href="index.php?page=shops">
            <i class="fas fa-box"></i> Shops
         </a>
      </li>
      <li class="nav-item">
         <a class="nav-link" href="index.php?page=profile">
            <i class="fas fa-user"></i> Profile
         </a>
      </li>
      <li class="nav-item">
         <a class="nav-link" href="logout.php">
            <i class="fas fa-user"></i> Log Out
         </a>
      </li>
   </ul>
</div>

<!-- Add Bootstrap JS, Popper.js, and jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.min.js"></script>
