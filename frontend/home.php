<?php
ob_start();
if (session_status() != PHP_SESSION_ACTIVE) session_start();

echo session_id();

if (isset($_GET["logged_in"])) {
    $logged_in = $_GET["logged_in"] == "true" ? "true" : "false";
    $_SESSION["logged_in"] = $_GET["logged_in"] == "true" ? "true" : "false";

    if ($logged_in == 'false') {
        if (session_status() == PHP_SESSION_ACTIVE) session_destroy();
    }
}

if (isset($_GET["email"])) {
    $email = $_GET["email"] ?? "";
    $_SESSION["email"] = $_GET["email"] ?? "";
}

include 'header.php';

if (!isset($_SESSION['logged_in'])):
    $_SESSION['logged_in'] = false;
endif;
?>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoCleckOut - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./css/home.css">
    <!-- <script src="cart.js"></script> -->
    <style>
        .category-item:hover {
            background-color: #2563EB;
            color: white;
            cursor: pointer;
        }

        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .cart-img {
            margin-left: 5px;
            margin-right: 8px;
            width: 20vw;
            height: 20vh;
        }

        .container-main {
            display: flex;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .categories {
            width: 220px;
            padding: 15px;
            background-color: #f9f9f9;
            border-right: 1px solid #ddd;
            height: auto;
        }

        .categories ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        .categories li {
            padding: 12px;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.2s ease-in-out;
        }

        .categories li:hover,
        .categories li.active {
            background-color: #3B82F6;
            color: white;
        }

        .products {
            flex: 1;
            padding: 20px;
            width: 100%;
            overflow: hidden;
        }

        .product-grid,
        .top-selling {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
        }

        .product-card {
            width: 100%;
            max-width: 200px;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .product-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
        }

        .cart-btn {
            display: inline-block;
            margin-top: 5px;
            padding: 3px 8px;
            /* Smaller size */
            background-color: transparent;
            /* No fill */
            color: #3B82F6;
            border: 1px solid #3B82F6;
            /* Thinner border */
            border-radius: 12px;
            /* More rounded corners */
            cursor: pointer;
            font-size: 12px;
            /* Smaller text */
            transition: 0.3s;
            font-weight: bold;
        }

        .cart-btn:hover {
            border-color: #2563EB;
            color: #2563EB;
        }

        .top-selling {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            overflow: hidden;
            white-space: nowrap;
            animation: slide 15s linear infinite;
        }

        @keyframes slide {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-100%);
            }
        }

        @media (max-width: 768px) {
            .container-main {
                flex-direction: column;
            }

            .categories {
                width: 100%;
                text-align: center;
                border-right: none;
                border-bottom: 1px solid #ddd;
            }

            .categories ul {
                display: flex;
                justify-content: center;
                flex-wrap: wrap;
                gap: 10px;
            }

            .categories li {
                padding: 8px 15px;
            }
        }

        .hero-section {
            height: 60vh;
            background: url('assets/hero.jpg') no-repeat center center/cover;
            color: black;
            padding: 40px 20px;
            position: relative;
            border-radius: 0 0 20px 20px;
            object-fit: contain;

        }

        .hero-title {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .hero-subtitle {
            font-size: 20px;
            margin-bottom: 30px;
        }

        .shop-icon {
            width: 64px;
            height: 64px;
            background-color: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .shop-icon:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(255, 147, 147, 0.43);
        }
    </style>
</head>

<body class="home-body">
    <div class="hero-section d-flex align-items-center justify-content-center text-center">
        <div>
            <h1 class="hero-title">Welcome to GoCleckOut</h1>
            <p class="hero-subtitle">Discover fresh and quality products from your local sellers</p>
            <a href="user/product_page.php" class="btn btn-explore">Explore</a>
        </div>
    </div>
    <div class="container-main">
        <div class="popup-cart"></div>
        <?php
        include "home_products_render.php"
        ?>
    </div>
    <?php //include "footer.php" ?>
</body>
</html>