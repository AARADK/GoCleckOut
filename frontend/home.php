<?php
if (session_status() != PHP_SESSION_ACTIVE) session_start();

include '../backend/database/connect.php';
$conn = getDBConnection();

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

if (!isset($_SESSION['logged_in'])):
    $_SESSION['logged_in'] = false;
endif;

$user_id = $_SESSION['user_id'] ?? null;

// Add to cart functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
   $product_id = (int) $_POST['product_id'];
   $qty = (int) $_POST['qty'];

   // Get current cart total
   $total_sql = "
       SELECT SUM(cp.quantity) as total_quantity
       FROM cart_product cp
       JOIN cart c ON cp.cart_id = c.cart_id
       WHERE c.user_id = :user_id
   ";
   $stmt = oci_parse($conn, $total_sql);
   oci_bind_by_name($stmt, ":user_id", $user_id);
   oci_execute($stmt);
   $total_row = oci_fetch_array($stmt, OCI_ASSOC);
   $current_total = $total_row['TOTAL_QUANTITY'] ?? 0;
   oci_free_statement($stmt);

   // Check if adding this quantity would exceed the limit
   if ($current_total + $qty > 20) {
      $_SESSION['toast_message'] = "Cannot add items: Cart would exceed 20 items limit";
      $_SESSION['toast_type'] = "error";
      header("Location: " . $_SERVER['REQUEST_URI']);
      exit;
   }

   // Check if user has an existing cart
   $sql = "SELECT * FROM cart WHERE user_id = :user_id";
   $stmt = oci_parse($conn, $sql);
   oci_bind_by_name($stmt, ":user_id", $user_id);
   oci_execute($stmt);
   $cart = oci_fetch_array($stmt, OCI_ASSOC);
   oci_free_statement($stmt);

   if (!$cart) {
      // Insert new cart
      $insert_cart_sql = "INSERT INTO cart (cart_id, user_id, add_date) VALUES (cart_seq.NEXTVAL, :user_id, SYSDATE) RETURNING cart_id INTO :cart_id";
      $stmt = oci_parse($conn, $insert_cart_sql);
      oci_bind_by_name($stmt, ":user_id", $user_id);
      oci_bind_by_name($stmt, ":cart_id", $cart_id, 20);
      oci_execute($stmt);
      oci_free_statement($stmt);
   } else {
      $cart_id = $cart['CART_ID'];
   }

   // Check if product is already in the cart
   $check_product_sql = "SELECT * FROM cart_product WHERE cart_id = :cart_id AND product_id = :product_id";
   $stmt = oci_parse($conn, $check_product_sql);
   oci_bind_by_name($stmt, ":cart_id", $cart_id);
   oci_bind_by_name($stmt, ":product_id", $product_id);
   oci_execute($stmt);
   $product_exists = oci_fetch_array($stmt, OCI_ASSOC);
   oci_free_statement($stmt);

   if ($product_exists) {
      $_SESSION['toast_message'] = "Product already in cart!";
      $_SESSION['toast_type'] = "error";
   } else {
      // Add product to cart
      $insert_product_sql = "INSERT INTO cart_product (cart_id, product_id, quantity) VALUES (:cart_id, :product_id, :qty)";
      $stmt = oci_parse($conn, $insert_product_sql);
      oci_bind_by_name($stmt, ":cart_id", $cart_id);
      oci_bind_by_name($stmt, ":product_id", $product_id);
      oci_bind_by_name($stmt, ":qty", $qty);
      oci_execute($stmt);
      oci_free_statement($stmt);

      // Update stock
      $update_stock_sql = "UPDATE product SET stock = stock - :qty WHERE product_id = :product_id";
      $stmt = oci_parse($conn, $update_stock_sql);
      oci_bind_by_name($stmt, ":qty", $qty);
      oci_bind_by_name($stmt, ":product_id", $product_id);
      oci_execute($stmt);
      oci_free_statement($stmt);

      $_SESSION['toast_message'] = "Product added!";
      $_SESSION['toast_type'] = "success";
   }

   // Redirect to same page to avoid form resubmission
   header("Location: " . $_SERVER['REQUEST_URI']);
   exit;
}
?>

<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoCleckOut - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./css/home.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
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
            background-color: transparent;
            color: #3B82F6;
            border: 1px solid #3B82F6;
            border-radius: 12px;
            cursor: pointer;
            font-size: 12px;
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

        .btn-explore {
            background-color: #ff6b6b;
            color: white;
            padding: 12px 25px;
            font-size: 16px;
            border-radius: 10px;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        .btn-explore:hover {
            background-color: #e55a5a;
        }
    </style>
</head>

<body class="home-body">

    <?php include 'header.php' ?>

    <div class="hero-section d-flex align-items-center justify-content-center text-center">
        <div>
            <h1 class="hero-title">Welcome to GoCleckOut</h1>
            <p class="hero-subtitle">Discover fresh and quality products from your local sellers</p>
            <a href="user/product_page.php" class="btn-explore">Explore</a>
        </div>
        <section class="py-5 bg-white">
    </div>
    <div class="container-main">
            <div class="container">
                <h2 class="text-center fw-bold mb-4">Featured Categories</h2>
                <div class="row text-center g-4">
                    <div class="col">
                        <div class="d-flex flex-column align-items-center">
                            <div class="shop-icon" onclick="location.href='user/product_page.php?category=greengrocer'" style="cursor: hand">
                                <i class="fas fa-apple-alt category-icon"></i>
                            </div>
                            <span class="fw-medium">Greengrocer</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex flex-column align-items-center">
                            <div class="shop-icon" onclick="location.href='user/product_page.php?category=fishmonger'" style="cursor: hand">
                                <i class="fas fa-fish category-icon"></i>
                            </div>
                            <span class="fw-medium">FishMonger</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex flex-column align-items-center">
                            <div class="shop-icon" onclick="location.href='user/product_page.php?category=delicatessen'" style="cursor: hand">
                                <i class="fas fa-cheese category-icon"></i>
                            </div>
                            <span class="fw-medium">Delicatessen</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex flex-column align-items-center">
                            <div class="shop-icon" onclick="location.href='user/product_page.php?category=bakery'" style="cursor: hand">
                                <i class="fas fa-cookie category-icon"></i>
                            </div>
                            <span class="fw-medium">Bakery</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex flex-column align-items-center">
                            <div class="shop-icon" onclick="location.href='user/product_page.php?category=butcher'" style="cursor: hand">
                                <i class="fas fa-drumstick-bite category-icon"></i>
                            </div>
                            <span class="fw-medium">Butcher</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php include 'user/product_list.php' ?>
    </div>
    <?php include "footer.php" ?>

    <?php 

    if (isset($_SESSION['toast_message'])) {
        echo "<script>
            Toastify({
                text: '" . $_SESSION['toast_message'] . "',
                duration: 3000,
                gravity: 'top',
                position: 'right',
                backgroundColor: '" . ($_SESSION['toast_type'] === 'success' ? '#28a745' : '#dc3545') . "',
            }).showToast();
        </script>";
        unset($_SESSION['toast_message']);
        unset($_SESSION['toast_type']);
    }
    ?>
</body>
</html>