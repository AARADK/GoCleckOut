<?php
session_start();
require_once '/xampp/htdocs/GCO/backend/database/connect.php';

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

$user_id = $_SESSION['user_id'] ?? null;

// category: butcher, greengrocer, fishmonger, delicatessen, bakery
$category = $_GET['category'] ?? 'butcher';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
   $product_id = (int) $_POST['product_id'];
   $qty = (int) $_POST['qty'];

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

<head>
  <title>Product Category Page</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    body {
      background-color: #f8f9fa;
      font-family: Arial, sans-serif;
    }

    .header {
      background-color: #ffffff;
      padding: 10px 0;
      border-bottom: 1px solid #eee;
    }

    .header .form-control {
      border-radius: 20px;
      border: 1px solid #ccc;
    }

    .header h4 {
      color: #ff6b6b;
      font-weight: bold;
    }

    .breadcrumb {
      background: none;
      font-size: 14px;
      font-weight: 500;
    }

    .breadcrumb a {
      text-decoration: none;
      color: #ff6b6b;
    }

    .breadcrumb-item.active {
      color: #6c757d;
    }

    .card {
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      transition: 0.3s;
    }

    .card:hover {
      transform: scale(1.02);
    }

    .card .btn {
      border-radius: 20px;
      background-color: #ff6b6b;
      color: white;
      border: none;
    }

    .card .btn:hover {
      background-color: #d7374a;
    }

    .wishlist-btn {
      color: #ff6b6b;
      background: none;
      border: none;
      font-weight: bold;
    }

    .footer {
      background-color: #fff;
      padding: 40px 0;
      border-top: 1px solid #ddd;
      margin-top: 40px;
    }

    .footer h6 {
      font-weight: bold;
    }

    .footer p,
    .footer a {
      color: #6c757d;
      text-decoration: none;
    }

    .category-box {
      background-color: #f1f1f1;
      padding: 20px;
      border-radius: 10px;
    }
  </style>
</head>

<body>
  <!-- Header -->
  <?php include "../header.php" ?>

  <div class="container-fluid">
    <div class="row">
      <!-- Sidebar -->
      <div class="col-md-2 p-4">
        <div class="category-box">
          <h5>Product Category</h5>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="category" id="butcher" value="butcher"
              <?= $category === 'butcher' ? 'checked' : '' ?>>
            <label class="form-check-label" for="butcher">Butcher</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="category" id="fishmonger" value="fishmonger"
              <?= $category === 'fishmonger' ? 'checked' : '' ?>>
            <label class="form-check-label" for="fishmonger">Fishmonger</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="category" id="bakery" value="bakery"
              <?= $category === 'bakery' ? 'checked' : '' ?>>
            <label class="form-check-label" for="bakery">Bakery</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="category" id="delicatessen" value="delicatessen"
              <?= $category === 'delicatessen' ? 'checked' : '' ?>>
            <label class="form-check-label" for="delicatessen">Delicatessen</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="category" id="greengrocer" value="greengrocer"
              <?= $category === 'greengrocer' ? 'checked' : '' ?>>
            <label class="form-check-label" for="greengrocer">Greengrocer</label>
          </div>


          <label class="mt-4">Filter By Price</label>
          <input type="range" class="form-range" min="20" max="350" />
          <button class="btn btn-sm mt-2 w-100" style="background-color: #ff6b6b; color: #fff;">Filter</button>

          <label class="mt-4">Filter By Shop</label>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="shop1" />
            <label class="form-check-label" for="shop1">Shop 1</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="shop2" />
            <label class="form-check-label" for="shop2">Shop 2</label>
          </div>
          <button class="btn btn-sm mt-2 w-100" style="background-color: #ff6b6b; color: #fff;">Filter</button>
        </div>
      </div>

      <div class="col-md-10">
        <?php include 'product_list.php' ?>
      </div>

    </div>
  </div>

  <!-- Footer -->
  <?php include "../footer.php" ?>
</body>