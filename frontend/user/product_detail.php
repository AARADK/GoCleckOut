<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Initialize variables
$rfid = '';
$product_id = '';

// Check for RFID parameter
if (isset($_GET['rfid'])) {
    $rfid = htmlspecialchars($_GET['rfid']);
}

// Get product ID
$product_id = $_GET['id'] ?? '';

include '../../backend/database/connect.php';
$conn = getDBConnection();

// Get product details based on either ID or RFID
$product_sql = "
    SELECT p.* 
    FROM product p 
    WHERE ";
$params = array();

if (!empty($rfid)) {
    $product_sql .= "p.rfid = :rfid";
    $params[':rfid'] = $rfid;
} elseif (is_numeric($product_id)) {
    $product_sql .= "p.product_id = :product_id";
    $params[':product_id'] = $product_id;
} else {
    $product = null;
}

if (!empty($params)) {
    $stmt = oci_parse($conn, $product_sql);
    foreach ($params as $key => $value) {
        oci_bind_by_name($stmt, $key, $value);
    }
    oci_execute($stmt);
    $product = oci_fetch_array($stmt, OCI_ASSOC);
    oci_free_statement($stmt);
}

// If product not found, use dummy data
if (!$product) {
    $product = [
        'PRODUCT_ID' => $product_id ?: 'DUMMY001',
        'PRODUCT_NAME' => 'Sample Product',
        'PRICE' => 999.99,
        'STOCK' => 10,
        'PRODUCT_CATEGORY_NAME' => 'General',
        'DESCRIPTION' => 'This is a sample product description.',
        'MIN_ORDER' => 1,
        'PRODUCT_IMAGE' => null,
        'IS_DUMMY' => true
    ];
} else {
    $product['IS_DUMMY'] = false;
}

// Get wishlist status if user is logged in
$in_wishlist = false;
$wishlist_items = [];
if (isset($_SESSION['user_id'])) {
    // Get all wishlist items for the user
    $wishlist_sql = "
        SELECT wp.product_id 
        FROM wishlist_product wp 
        JOIN wishlist w ON wp.wishlist_id = w.wishlist_id 
        WHERE w.user_id = :user_id
    ";
    $stmt = oci_parse($conn, $wishlist_sql);
    oci_bind_by_name($stmt, ":user_id", $_SESSION['user_id']);
    oci_execute($stmt);
    while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
        $wishlist_items[] = $row['PRODUCT_ID'];
    }
    oci_free_statement($stmt);

    // Check if current product is in wishlist
    if (!$product['IS_DUMMY']) {
        $in_wishlist = in_array($product['PRODUCT_ID'], $wishlist_items);
    }
}

// Get similar products (same category, excluding current product)
$similar_sql = "
    SELECT p.*, s.shop_name 
    FROM product p 
    JOIN shops s ON p.shop_id = s.shop_id 
    WHERE p.product_category_name = :category_name 
    AND p.product_id != :product_id 
    AND p.product_status = 'active'
    AND ROWNUM <= 4";
$stmt = oci_parse($conn, $similar_sql);
oci_bind_by_name($stmt, ":category_name", $product['PRODUCT_CATEGORY_NAME']);
oci_bind_by_name($stmt, ":product_id", $product['PRODUCT_ID']);
oci_execute($stmt);
$similar_products = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
    $row['IS_DUMMY'] = false; // Set IS_DUMMY flag for real products
    $similar_products[] = $row;
}
oci_free_statement($stmt);

// If no similar products found, use dummy data
if (empty($similar_products)) {
    $similar_products = [
        [
            'PRODUCT_ID' => 'SIM001',
            'PRODUCT_NAME' => 'Similar Product 1',
            'PRICE' => 899.99,
            'PRODUCT_IMAGE' => null,
            'IS_DUMMY' => true,
            'SHOP_NAME' => 'Sample Shop'
        ],
        [
            'PRODUCT_ID' => 'SIM002',
            'PRODUCT_NAME' => 'Similar Product 2',
            'PRICE' => 799.99,
            'PRODUCT_IMAGE' => null,
            'IS_DUMMY' => true,
            'SHOP_NAME' => 'Sample Shop'
        ]
    ];
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

    .card-img-container {
        position: relative;
    }
    .wishlist-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(255, 255, 255, 0.9);
        border: none;
        border-radius: 50%;
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        z-index: 1;
    }
    .wishlist-btn:hover {
        background: white;
        transform: scale(1.1);
    }
    .wishlist-btn i {
        color: #dc3545;
        font-size: 1.1rem;
    }
    .wishlist-btn.active i {
        color: #dc3545;
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

          <form id="addToCartForm" class="mb-4" action="cart.php" method="POST">
            <input type="hidden" name="product_id" value="<?= $product['PRODUCT_ID'] ?>">
            <div class="quantity-selector mb-3">
              <button type="button" class="btn btn-outline-secondary" onclick="decreaseQuantity()" <?= $product['IS_DUMMY'] ? 'disabled' : '' ?>>-</button>
              <input type="number" name="quantity" id="quantity" value="1" min="1" max="<?= $product['STOCK'] ?>" class="form-control" style="width: 70px;" <?= $product['IS_DUMMY'] ? 'disabled' : '' ?>>
              <button type="button" class="btn btn-outline-secondary" onclick="increaseQuantity()" <?= $product['IS_DUMMY'] ? 'disabled' : '' ?>>+</button>
            </div>
            <div class="d-grid gap-2">
              <button type="button" onclick="addToCart(<?= $product['PRODUCT_ID'] ?>)" class="btn btn-primary" <?= ($product['STOCK'] <= 0 || $product['IS_DUMMY']) ? 'disabled' : '' ?>>
                <i class="fas fa-shopping-cart me-2"></i>Add to Cart
              </button>
              <button type="button" class="wishlist-btn" onclick="toggleWishlist(this)" data-product-id="<?= $product['PRODUCT_ID'] ?>" <?= $product['IS_DUMMY'] ? 'disabled' : '' ?>>
                <i class="<?= $in_wishlist ? 'fas' : 'far' ?> fa-heart me-2"></i>
                <?= $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist' ?>
              </button>
            </div>
          </form>

          <div class="product-meta">
            <small class="text-muted">Available Stock: <?= $product['STOCK'] ?></small>
            <?php if ($product['IS_DUMMY']): ?>
              <br><small class="text-muted">(Sample Product - Not Available for Purchase)</small>
            <?php endif; ?>
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
            <div class="card h-100 shadow-sm">
              <div class="card-img-container">
                <?php
                $similar_img_src = '';
                if ($similar['PRODUCT_IMAGE'] instanceof OCILob) {
                    $similar_img_data = $similar['PRODUCT_IMAGE']->load();
                    $similar_img_src = "data:image/jpeg;base64," . base64_encode($similar_img_data);
                }
                if (!empty($similar_img_src)) {
                    echo "<img src=\"$similar_img_src\" class=\"card-img-top\" style=\"height: 200px; object-fit: cover;\" alt=\"" . htmlspecialchars($similar['PRODUCT_NAME']) . "\">";
                } else {
                    echo "<div class=\"card-img-top bg-light d-flex align-items-center justify-content-center\" style=\"height: 200px;\">No Image</div>";
                }
                ?>
                <?php if (!isset($similar['IS_DUMMY']) || !$similar['IS_DUMMY']): ?>
                <button type="button" class="wishlist-btn <?= in_array($similar['PRODUCT_ID'], $wishlist_items) ? 'active' : '' ?>" 
                        data-product-id="<?= $similar['PRODUCT_ID']; ?>" 
                        onclick="toggleWishlist(this)">
                    <i class="<?= in_array($similar['PRODUCT_ID'], $wishlist_items) ? 'fas' : 'far' ?> fa-heart"></i>
                </button>
                <?php endif; ?>
              </div>
              <div class="card-body d-flex flex-column p-3">
                <h5 class="card-title text-truncate mb-1"><?= htmlspecialchars($similar['PRODUCT_NAME']); ?></h5>
                <div class="text-warning mb-1" style="font-size: 0.9rem;">★★★★★</div>
                <p class="card-text text-muted small mb-2" style="font-size: 0.85rem;">
                  <?= htmlspecialchars($similar['DESCRIPTION'] instanceof OCILob ? $similar['DESCRIPTION']->load() : $similar['DESCRIPTION']) ?>
                </p>
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="h6 text-danger mb-0">£<?= number_format($similar['PRICE'], 2); ?></span>
                  <span class="text-muted small">Stock: <?= $similar['STOCK']; ?></span>
                </div>
                <div class="text-muted small mb-2">
                  Shop: <?= htmlspecialchars($similar['SHOP_NAME']); ?>
                </div>
                <div class="d-grid gap-1 mt-auto">
                  <a href="?id=<?= $similar['PRODUCT_ID'] ?>" class="btn btn-sm btn-secondary">Details</a>
                  <?php if (!isset($similar['IS_DUMMY']) || !$similar['IS_DUMMY']): ?>
                  <button type="button" class="btn btn-sm btn-danger" onclick="addToCart(<?= $similar['PRODUCT_ID'] ?>)">Add to Cart</button>
                  <a href="cart.php?get_id=<?= $similar['PRODUCT_ID'] ?>" class="btn btn-sm btn-success">Buy Now</a>
                  <?php endif; ?>
                </div>
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
    function showToast(message, type = 'success') {
        Toastify({
            text: message,
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: type === 'success' ? "#4CAF50" : "#f44336",
        }).showToast();
    }

    function decreaseQuantity() {
        const input = document.getElementById('quantity');
        const currentValue = parseInt(input.value);
        if (currentValue > 1) {
            input.value = currentValue - 1;
        }
    }

    function increaseQuantity() {
        const input = document.getElementById('quantity');
        const currentValue = parseInt(input.value);
        const maxStock = <?= $product['STOCK'] ?>;
        if (currentValue < maxStock) {
            input.value = currentValue + 1;
        } else {
            showToast('Maximum stock limit reached', 'error');
        }
    }

    // Add input validation for quantity
    document.getElementById('quantity').addEventListener('input', function(e) {
        const value = parseInt(e.target.value);
        const maxStock = <?= $product['STOCK'] ?>;
        
        if (value < 1) {
            e.target.value = 1;
        } else if (value > maxStock) {
            e.target.value = maxStock;
            showToast('Maximum stock limit reached', 'error');
        }
    });

    async function addToCart(productId) {
        const quantity = document.getElementById('quantity').value;
        const submitBtn = document.querySelector('button[onclick="addToCart(' + productId + ')"]');
        
        // Disable button and show loading state
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';

        try {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
            
            const response = await fetch('add_to_cart.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showToast('Product added to cart successfully');
                // Reset quantity to 1
                document.getElementById('quantity').value = 1;
            } else {
                throw new Error(result.message || 'Failed to add product to cart');
            }
        } catch (error) {
            showToast(error.message || 'Failed to add product to cart', 'error');
        } finally {
            // Restore button state
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }

    async function toggleWishlist(button) {
        const productId = button.dataset.productId;
        const icon = button.querySelector('i');
        const text = button.querySelector('span') || button;

        try {
            const response = await fetch('wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&action=${icon.classList.contains('far') ? 'add' : 'remove'}`
            });

            const result = await response.json();
            
            if (result.success) {
                if (icon.classList.contains('far')) {
                    icon.classList.replace('far', 'fas');
                    text.textContent = 'Remove from Wishlist';
                } else {
                    icon.classList.replace('fas', 'far');
                    text.textContent = 'Add to Wishlist';
                }
                showToast(result.message);
            } else {
                showToast(result.message, 'error');
            }
        } catch (error) {
            showToast('Failed to update wishlist', 'error');
        }
    }
  </script>
</body>

</html>