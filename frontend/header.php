<?php
// if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (isset($_SESSION['logged_in'])) {
    $_SESSION['logged_in'] = $_SESSION['logged_in'] == 'true' ? 'true' : 'false';
} else {
    $_SESSION['logged_in'] = 'false';
}

// Get wishlist count if user is logged in
$wishlist_count = 0;
if ($_SESSION['logged_in'] === 'true' && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../backend/database/connect.php';
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    
    $wishlist_sql = "SELECT COUNT(*) as count FROM wishlist WHERE user_id = :user_id";
    $stmt = oci_parse($conn, $wishlist_sql);
    oci_bind_by_name($stmt, ":user_id", $user_id);
    oci_execute($stmt);
    $wishlist_row = oci_fetch_array($stmt, OCI_ASSOC);
    $wishlist_count = $wishlist_row['COUNT'] ?? 0;
    oci_free_statement($stmt);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['name'])) {
    $category = urlencode(trim($_POST['name']));
    header("Location: /GCO/frontend/user/product_page.php?name=$category");
    exit();
}
?>

<head>
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>

<style>
    .logo {
        width: 80px;
        height: 40px;
        margin-right: 5px;
    }

    header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 999;
        background-color: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    body {
        padding-top: 100px;
    }

    .wishlist-badge {
        position: relative;
        display: inline-block;
    }

    .wishlist-count {
        position: absolute;
        top: -8px;
        right: -8px;
        background-color: #ff6b6b;
        color: white;
        border-radius: 50%;
        padding: 2px 6px;
        font-size: 12px;
        min-width: 18px;
        text-align: center;
    }
</style>

<header class="bg-white border-bottom">
    <div class="container py-3">
        <div class="row align-items-center">
            <div class="col-md-2">
                <a href="/GCO/frontend/home.php?logged_in=<?= $_SESSION['logged_in'] ?>">
                    <img src="/GCO/frontend/assets/Logo.png" alt="Logo" class="logo">
                </a>
            </div>

            <div class="col-md-5">
                <form action="/GCO/frontend/user/product_page.php" method="get">
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" placeholder="Search products..." name="name" value="<?= htmlspecialchars($_GET['name'] ?? '') ?>">
                        <button class="btn btn-outline-secondary btn-sm" type="submit" style="background-color: #ff6b6b; flex: 0.1">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-md-5">
                <div class="d-flex justify-content-end align-items-center" id="navbarText">
                    <ul class="navbar-nav d-flex flex-row">
                        <li class="nav-item me-4">
                            <?php if ($_SESSION['logged_in'] == 'true') : ?>
                                <div class="dropdown">
                                    <button class="btn btn-secondary dropdown-toggle" style="background-color: #ff6b6b;" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-user"></i> User
                                    </button>
                                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                        <a class="dropdown-item" href="/GCO/frontend/User_Profile.php">Profile</a>
                                        <a class="dropdown-item" href="/GCO/frontend/home.php?logged_in=false">Logout</a>
                                    </div>
                                </div>
                            <?php else: ?>
                        <li class="nav-item me-4">
                            <a class="nav-link" href="/GCO/frontend/login/login_portal.php?sign_in=true"><i class="mx-1 far fa-user"></i>Sign in</a>
                        </li>
                        <li class="nav-item me-4">
                            <a class="nav-link" href="/GCO/frontend/login/login_portal.php?sign_in=false" class="btn btn-primary px-4"><i class="mx-1 far fa-user"></i>Sign up</a>
                        </li>
                    <?php endif; ?>
                    </li>
                    <li class="nav-item me-4">
                        <a class="nav-link wishlist-badge" href="/GCO/frontend/user/wishlist.php">
                            <i class="mx-1 fas fa-heart"></i>Wishlist
                        </a>
                    </li>
                    <li class="nav-item me-4">
                        <a class="nav-link" href="/GCO/frontend/user/cart.php"><i class="mx-1 fa fa-shopping-cart"></i>Cart</a>
                    </li>
                    <li class="nav-item me-4">
                        <a class="nav-link" href="/GCO/frontend/user/product_page.php"><i class="mx-1 fa fa-box-open"></i>Product</a>
                    </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>