<?php
$currentPage = '';

function setCurrentPage($page) {
    $GLOBALS['currentPage'] = $page;
}

function getCurrentPage() {
    return $GLOBALS['currentPage'];
}

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (isset($_SESSION['logged_in'])) {
    $_SESSION['logged_in'] = $_SESSION['logged_in'] == 'true' ? 'true' : 'false';
} else {
    $_SESSION['logged_in'] = 'false';
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
</style>

<header class="bg-white border-bottom">
    <div class="container py-3">
        <div class="row align-items-center">
            <div class="col-md-2">
                <a href="http://<?= $_SERVER['HTTP_HOST']?>/GCO/frontend/home.php?logged_in=<?= $_SESSION['logged_in'] ?>">
                    <img src="http://<?= $_SERVER['HTTP_HOST'] ?>/GCO/frontend/assets/Logo.png" alt="Logo" class="logo">
                </a>
            </div>

            <div class="col-md-5">
                <div class="input-group mb-2">
                    <input type="text" class="form-control" placeholder="Search products...">
                    <button class="btn btn-outline-secondary btn-sm" type="button" style="background-color: #ff6b6b; flex: 0.1">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
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
                                        <a class="dropdown-item" href="User_Profile.php">Profile</a>
                                        <a class="dropdown-item" href="home.php?logged_in=false">Logout</a>
                                    </div>
                                </div>
                            <?php else: ?>
                        <li class="nav-item me-4">
                            <a class="nav-link" href="http://<?= $_SERVER['HTTP_HOST'] ?>/GCO/frontend/login/login_portal.php?sign_in=true" class="me-4"><i class="mx-1 far fa-user"></i>Sign in</a>
                        </li>
                        <li class="nav-item me-4">
                            <a class="nav-link" href="http://<?= $_SERVER['HTTP_HOST'] ?>/GCO/frontend/login/login_portal.php?sign_in=false" class="btn btn-primary px-4"><i class="mx-1 far fa-user"></i>Sign up</a>
                        </li>
                        <?php endif; ?>
                        </li>
                        <li class="nav-item me-4">
                            <a class="nav-link" href="http://<?= $_SERVER['HTTP_HOST'] ?>/GCO/frontend/user/wishlist.php"><i class="mx-1 fas fa-heart"></i>Wishlist</a>
                        </li>
                        <li class="nav-item me-4">
                            <a class="nav-link" href="http://<?= $_SERVER['HTTP_HOST'] ?>/GCO/frontend/user/shopping_cart.php"><i class="mx-1 fa fa-shopping-cart"></i>Cart</a>
                        </li>
                        <li class="nav-item me-4">
                            <a class="nav-link" href="http://<?= $_SERVER['HTTP_HOST'] ?>/GCO/frontend/user/product_page.php"><i class="mx-1 fa fa-box-open"></i>Product</a>
                        </li>
                        <!-- <li class="nav-item me-4" onclick="setCurrentPage('Wishlist')">
                            <a class="nav-link"><i class="mx-1 fas fa-heart"></i>Wishlist</a>
                        </li>
                        <li class="nav-item me-4" onclick="setCurrentPage('Cart')">
                            <a class="nav-link"><i class="mx-1 fa fa-shopping-cart"></i>Cart</a>
                        </li>
                        <li class="nav-item me-4" onclick="setCurrentPage('Product')">
                            <a class="nav-link"><i class="mx-1 fa fa-box-open"></i>Product</a>
                        </li> -->
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>