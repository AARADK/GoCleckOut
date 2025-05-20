<?php
session_start();
require_once '/xampp/htdocs/GCO/backend/database/connect.php';

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

$user_id = $_SESSION['user_id'] ?? null;

// Get filter parameters from URL
$category = $_GET['category'] ?? 'all';
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
$search_name = $_GET['name'] ?? '';

// Fetch products based on filters
$select_products_sql = "SELECT p.*, s.shop_name 
                       FROM product p 
                       JOIN shops s ON p.shop_id = s.shop_id 
                       WHERE p.product_status = 'active'";

// Add category filter
if ($category !== 'all') {
    $select_products_sql .= " AND s.shop_category = :category";
}

// Add price range filter
if ($min_price !== null) {
    $select_products_sql .= " AND p.price >= :min_price";
}
if ($max_price !== null) {
    $select_products_sql .= " AND p.price <= :max_price";
}

// Add name search filter
if (!empty($search_name)) {
    $select_products_sql .= " AND LOWER(p.product_name) LIKE '%' || LOWER(:search_name) || '%'";
}

$select_products_sql .= " ORDER BY p.added_date DESC";

$stmt = oci_parse($conn, $select_products_sql);

// Bind category parameter
if ($category !== 'all') {
    oci_bind_by_name($stmt, ":category", $category);
}

// Bind price range parameters
if ($min_price !== null) {
    oci_bind_by_name($stmt, ":min_price", $min_price);
}
if ($max_price !== null) {
    oci_bind_by_name($stmt, ":max_price", $max_price);
}

// Bind search name parameter
if (!empty($search_name)) {
    oci_bind_by_name($stmt, ":search_name", $search_name);
}

oci_execute($stmt);

// Fetch all products into an array
$products = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
    $products[] = $row;
}
oci_free_statement($stmt);

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

// Handle filter form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_filters'])) {
    $filters = [];
    
    // Get category
    $filters['category'] = $_POST['category'] ?? 'all';
    
    // Get price range
    if (isset($_POST['min_price']) && $_POST['min_price'] !== '') {
        $filters['min_price'] = number_format((float)$_POST['min_price'], 2, '.', '');
    }
    if (isset($_POST['max_price']) && $_POST['max_price'] !== '') {
        $filters['max_price'] = number_format((float)$_POST['max_price'], 2, '.', '');
    }
    
    // Get search name
    if (isset($_POST['name']) && $_POST['name'] !== '') {
        $filters['name'] = trim($_POST['name']);
    }
    
    // Build query string
    $query_string = http_build_query($filters);
    
    // Redirect to same page with filters
    header("Location: product_page.php?" . $query_string);
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
          <form method="POST" action="">
            <h5>Product Category</h5>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="category" id="all" value="all"
                <?= $category === 'all' ? 'checked' : '' ?>>
              <label class="form-check-label" for="all">All Categories</label>
            </div>
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

            <div class="mt-4">
              <label class="form-label">Search by Name</label>
              <div class="mb-3">
                <input type="text" class="form-control" id="name" name="name" 
                       value="<?= htmlspecialchars($search_name) ?>" 
                       placeholder="Enter product name">
              </div>
            </div>

            <div class="mt-4">
              <label class="form-label">Filter By Price (£)</label>
              <div class="mb-2">
                <div class="input-group">
                  <span class="input-group-text">£</span>
                  <input type="number" class="form-control" id="min_price" name="min_price" 
                         min="0" max="1000" step="0.01" 
                         value="<?= $min_price ?? '' ?>" 
                         placeholder="Min price"
                         oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');">
                </div>
              </div>
              <div class="mb-2">
                <div class="input-group">
                  <span class="input-group-text">£</span>
                  <input type="number" class="form-control" id="max_price" name="max_price" 
                         min="0" max="1000" step="0.01" 
                         value="<?= $max_price ?? '' ?>" 
                         placeholder="Max price"
                         oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');">
                </div>
              </div>
            </div>

            <button type="submit" name="apply_filters" class="btn btn-sm mt-3 w-100" style="background-color: #ff6b6b; color: #fff;">
                Apply Filters
            </button>
          </form>
        </div>
      </div>

      <div class="col-md-10">
        <?php 
        // Pass the products array to product_list.php
        include 'product_list.php';
        ?>
      </div>

    </div>
  </div>

  <!-- Footer -->
  <?php include "../footer.php" ?>
</body>