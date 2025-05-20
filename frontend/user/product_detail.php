<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Check if product ID is provided
if (!isset($_GET['id'])) {
    header("Location: /GCO/frontend/home.php");
    exit;
}

include '../../backend/database/connect.php';
$conn = getDBConnection();

// Get product details
$product_id = $_GET['id'];
$product_sql = "
    SELECT p.* 
    FROM product p 
    WHERE p.product_id = :product_id";
$stmt = oci_parse($conn, $product_sql);
oci_bind_by_name($stmt, ":product_id", $product_id);
oci_execute($stmt);
$product = oci_fetch_array($stmt, OCI_ASSOC);
oci_free_statement($stmt);

// If product not found, redirect to home
if (!$product) {
    header("Location: /GCO/frontend/home.php");
    exit;
}

// Get similar products (same category, excluding current product)
$similar_sql = "
    SELECT * FROM product 
    WHERE product_category_name = :category_name 
    AND product_id != :product_id 
    AND ROWNUM <= 4";
$stmt = oci_parse($conn, $similar_sql);
oci_bind_by_name($stmt, ":category_name", $product['PRODUCT_CATEGORY_NAME']);
oci_bind_by_name($stmt, ":product_id", $product_id);
oci_execute($stmt);
$similar_products = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
    $similar_products[] = $row;
}
oci_free_statement($stmt);

// Get wishlist status if user is logged in
$in_wishlist = false;
if (isset($_SESSION['user_id'])) {
    $wishlist_sql = "
        SELECT 1 
        FROM wishlist_product wp 
        JOIN wishlist w ON wp.wishlist_id = w.wishlist_id 
        WHERE w.user_id = :user_id AND wp.product_id = :product_id";
    $stmt = oci_parse($conn, $wishlist_sql);
    oci_bind_by_name($stmt, ":user_id", $_SESSION['user_id']);
    oci_bind_by_name($stmt, ":product_id", $product_id);
    oci_execute($stmt);
    $in_wishlist = (oci_fetch_array($stmt, OCI_ASSOC) !== false);
    oci_free_statement($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($product['PRODUCT_NAME']) ?> - GoCleckOut</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
  <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
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
      color: #f44357;
      font-weight: bold;
    }

    .breadcrumb {
      background: none;
      font-size: 14px;
      font-weight: 500;
    }

    .breadcrumb a {
      text-decoration: none;
      color: #f44357;
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
      background-color: #f44357;
      color: white;
      border: none;
    }

    .btn-primary {
      background-color: #f44357;
      color: white;
      border: none;
      border-radius: 20px;
    }

    .btn-primary:hover {
      background-color: #d7374a;
    }

    .card .btn:hover {
      background-color: #d7374a;
    }

    .wishlist-btn {
      color: #f44357;
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

    .product-image {
      border-radius: 10px;
      border: 1px solid #eee;
      padding: 20px;
      background-color: white;
    }

    .product-info {
      padding: 20px;
      background-color: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .product-meta {
      border-radius: 5px;
      border: 1px solid #eee;
      padding: 10px;
      text-align: center;
      margin-right: 10px;
    }

    .review-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background-color: #ddd;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 15px;
    }

    .review-item {
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid #eee;
    }

    .review-item:last-child {
      border-bottom: none;
    }

    .similar-products .card {
      height: 100%;
    }

    .product-title {
      font-size: 24px;
      font-weight: bold;
      margin-bottom: 15px;
    }

    .product-price {
      font-size: 20px;
      font-weight: bold;
      color: #f44357;
      margin-bottom: 20px;
    }

    .quantity-selector {
      display: flex;
      align-items: center;
      margin-bottom: 20px;
    }

    .quantity-selector input {
      width: 50px;
      text-align: center;
      margin: 0 10px;
    }

    .quantity-selector button {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background-color: #f1f1f1;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
    }
  </style>
</head>

<body>
  <?php include "../header.php" ?>

  <div class="container my-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/GCO/frontend/home.php">Home</a></li>
        <li class="breadcrumb-item"><a href="/GCO/frontend/user/product_list.php">Products</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($product['PRODUCT_NAME']) ?></li>
      </ol>
    </nav>

    <div class="row">
      <!-- Product Detail Content -->
      <div class="col-lg-8">
        <div class="product-image mb-4">
          <?php
          $img_src = '';
          if ($product['PRODUCT_IMAGE'] instanceof OCILob) {
              $img_data = $product['PRODUCT_IMAGE']->load();
              $img_src = "data:image/jpeg;base64," . base64_encode($img_data);
          }
          if (!empty($img_src)) {
              echo "<img src=\"$img_src\" class=\"img-fluid\" alt=\"" . htmlspecialchars($product['PRODUCT_NAME']) . "\">";
          } else {
              echo "<div class=\"bg-light d-flex align-items-center justify-content-center\" style=\"height: 300px;\">No Image</div>";
          }
          ?>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="product-info">
          <h1 class="product-title"><?= htmlspecialchars($product['PRODUCT_NAME']) ?></h1>
          <div class="product-price">Rs. <?= number_format($product['PRICE'], 2) ?></div>
          
          <div class="mb-4">
            <span class="badge bg-primary"><?= htmlspecialchars($product['PRODUCT_CATEGORY_NAME']) ?></span>
            <span class="badge bg-<?= $product['STOCK'] > 0 ? 'success' : 'danger' ?>">
              <?= $product['STOCK'] > 0 ? 'In Stock' : 'Out of Stock' ?>
            </span>
          </div>

          <p class="mb-4"><?= htmlspecialchars($product['DESCRIPTION'] instanceof OCILob ? $product['DESCRIPTION']->load() : $product['DESCRIPTION']) ?></p>

          <form action="cart.php" method="POST" class="mb-4">
            <input type="hidden" name="product_id" value="<?= $product['PRODUCT_ID'] ?>">
            <div class="quantity-selector mb-3">
              <button type="button" class="decrease-btn" onclick="decreaseQuantity()">-</button>
              <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?= $product['STOCK'] ?>" class="form-control" style="width: 70px;">
              <button type="button" class="increase-btn" onclick="increaseQuantity()">+</button>
            </div>
            <div class="d-grid gap-2">
              <button type="submit" name="add_to_cart" class="btn btn-primary" <?= $product['STOCK'] <= 0 ? 'disabled' : '' ?>>
                <i class="fas fa-shopping-cart me-2"></i>Add to Cart
              </button>
              <button type="button" class="wishlist-btn" onclick="toggleWishlist(this)" data-product-id="<?= $product['PRODUCT_ID'] ?>">
                <i class="<?= $in_wishlist ? 'fas' : 'far' ?> fa-heart me-2"></i>
                <?= $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist' ?>
              </button>
            </div>
          </form>

          <div class="product-meta">
            <small class="text-muted">Available Stock: <?= $product['STOCK'] ?></small>
          </div>
        </div>
      </div>
    </div>

    <!-- Similar Products -->
    <?php if (!empty($similar_products)): ?>
    <div class="similar-products mt-5">
      <h3 class="mb-4">Similar Products</h3>
      <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
        <?php foreach ($similar_products as $similar): ?>
          <div class="col">
            <div class="card h-100">
              <?php
              $similar_img_src = '';
              if ($similar['PRODUCT_IMAGE'] instanceof OCILob) {
                  $similar_img_data = $similar['PRODUCT_IMAGE']->load();
                  $similar_img_src = "data:image/jpeg;base64," . base64_encode($similar_img_data);
              }
              ?>
              <img src="<?= $similar_img_src ?>" class="card-img-top" alt="<?= htmlspecialchars($similar['PRODUCT_NAME']) ?>" style="height: 200px; object-fit: cover;">
              <div class="card-body">
                <h5 class="card-title"><?= htmlspecialchars($similar['PRODUCT_NAME']) ?></h5>
                <p class="card-text text-danger">Rs. <?= number_format($similar['PRICE'], 2) ?></p>
                <a href="?id=<?= $similar['PRODUCT_ID'] ?>" class="btn btn-primary">View Details</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <?php include "../footer.php" ?>

  <script>
    function decreaseQuantity() {
      const input = document.getElementById('quantity');
      if (input.value > 1) {
        input.value = parseInt(input.value) - 1;
      }
    }

    function increaseQuantity() {
      const input = document.getElementById('quantity');
      if (input.value < <?= $product['STOCK'] ?>) {
        input.value = parseInt(input.value) + 1;
      }
    }

    function toggleWishlist(button) {
      const productId = button.dataset.productId;
      const icon = button.querySelector('i');
      const text = button.querySelector('span');
      
      // Determine the action based on current state
      const action = icon.classList.contains('far') ? 'add' : 'remove';
      
      // Make API call to update wishlist
      fetch('/GCO/frontend/user/update_wishlist.php', {
          method: 'POST',
          headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `product_id=${productId}&action=${action}`
      })
      .then(response => response.json())
      .then(data => {
          if (data.success) {
              // Toggle heart icon and text
              if (action === 'add') {
                  icon.classList.remove('far');
                  icon.classList.add('fas');
                  button.innerHTML = '<i class="fas fa-heart me-2"></i>Remove from Wishlist';
                  Toastify({
                      text: "Added to wishlist",
                      duration: 2000,
                      close: true,
                      gravity: 'top',
                      position: 'right',
                      backgroundColor: "#198754",
                  }).showToast();
              } else {
                  icon.classList.remove('fas');
                  icon.classList.add('far');
                  button.innerHTML = '<i class="far fa-heart me-2"></i>Add to Wishlist';
                  Toastify({
                      text: "Removed from wishlist",
                      duration: 2000,
                      close: true,
                      gravity: 'top',
                      position: 'right',
                      backgroundColor: "#dc3545",
                  }).showToast();
              }
          } else {
              Toastify({
                  text: data.message || "Failed to update wishlist",
                  duration: 2000,
                  close: true,
                  gravity: 'top',
                  position: 'right',
                  backgroundColor: "#dc3545",
              }).showToast();
          }
      })
      .catch(error => {
          Toastify({
              text: "An error occurred",
              duration: 2000,
              close: true,
              gravity: 'top',
              position: 'right',
              backgroundColor: "#dc3545",
          }).showToast();
      });
    }
  </script>
</body>

</html>