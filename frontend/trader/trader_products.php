<?php
require_once '../../backend/database/db_connection.php';
session_start();

// Check if user is logged in and is a trader
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trader') {
    header('Location: ../login_portal.php?sign_in=true');
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get all products
$sql = "SELECT p.*, s.shop_name 
        FROM product p 
        JOIN shops s ON p.shop_id = s.shop_id 
        ORDER BY p.product_id DESC";

$stmt = oci_parse($conn, $sql);
oci_execute($stmt);

$products = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
    $products[] = $row;
}
oci_free_statement($stmt);

$shops_sql = "SELECT s.shop_id, s.shop_name, s.shop_category, COUNT(p.product_id) as product_count 
              FROM shops s 
              LEFT JOIN product p ON s.shop_id = p.shop_id 
              WHERE s.user_id = :user_id 
              GROUP BY s.shop_id, s.shop_name, s.shop_category 
              ORDER BY s.shop_name";

$shops_stmt = oci_parse($conn, $shops_sql);
if (!$shops_stmt) {
    $e = oci_error($conn);
    die("Error parsing shops query: " . $e['message']);
}

oci_bind_by_name($shops_stmt, ":user_id", $user_id);
if (!oci_execute($shops_stmt)) {
    $e = oci_error($shops_stmt);
    die("Error executing shops query: " . $e['message']);
}

$shops = [];
while ($shop = oci_fetch_array($shops_stmt, OCI_ASSOC)) {
    $shops[] = $shop;
}
oci_free_statement($shops_stmt);

// Get selected shop filter
$selected_shop_id = isset($_GET['shop_id']) ? $_GET['shop_id'] : null;

// Build the products query with proper joins
$products_sql = "SELECT p.*, s.shop_name, s.shop_id, s.shop_category 
                FROM product p 
                JOIN shops s ON p.shop_id = s.shop_id 
                WHERE s.user_id = :user_id";

if ($selected_shop_id) {
    $products_sql .= " AND p.shop_id = :shop_id";
}

$products_sql .= " ORDER BY p.added_date DESC";

$products_stmt = oci_parse($conn, $products_sql);
if (!$products_stmt) {
    $e = oci_error($conn);
    die("Error parsing products query: " . $e['message']);
}

oci_bind_by_name($products_stmt, ":user_id", $user_id);
if ($selected_shop_id) {
    oci_bind_by_name($products_stmt, ":shop_id", $selected_shop_id);
}

if (!oci_execute($products_stmt)) {
    $e = oci_error($products_stmt);
    die("Error executing products query: " . $e['message']);
}

$products = [];
while ($row = oci_fetch_array($products_stmt, OCI_ASSOC)) {
    // Convert any LOB fields to strings
    foreach ($row as $key => $value) {
        if (is_object($value) && get_class($value) === 'OCI-Lob') {
            $row[$key] = $value->load();
        }
    }
    $products[] = $row;
}
oci_free_statement($products_stmt);

// Debug output
// echo "<pre>All Products: "; print_r($products); echo "</pre>";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    try {
        // Check if RFID already exists
        $check_rfid_sql = "SELECT COUNT(*) as count FROM product WHERE rfid = :rfid";
        $check_rfid_stmt = oci_parse($conn, $check_rfid_sql);
        oci_bind_by_name($check_rfid_stmt, ":rfid", $_POST['rfid']);
        oci_execute($check_rfid_stmt);
        $rfid_count = oci_fetch_assoc($check_rfid_stmt)['COUNT'];

        if ($rfid_count > 0) {
            throw new Exception("A product with this RFID already exists.");
        }

        // Simple insert into products
        $product_sql = "INSERT INTO product (
            product_name, description, price, stock,
            product_image, added_date, updated_date, product_status,
            discount_percentage, shop_id, user_id, product_category_name, rfid
        ) VALUES (
            :name, :description, :price, :stock,
            :product_image, SYSDATE, SYSDATE, 'active', 0,
            :shop_id, :user_id, :category, :rfid
        )";

        $stmt = oci_parse($conn, $product_sql);

        // Get shop category
        $shop_category_sql = "SELECT shop_category FROM shops WHERE shop_id = :shop_id";
        $shop_category_stmt = oci_parse($conn, $shop_category_sql);
        oci_bind_by_name($shop_category_stmt, ":shop_id", $_POST['shop_id']);
        oci_execute($shop_category_stmt);
        $shop_data = oci_fetch_assoc($shop_category_stmt);
        $product_category = $shop_data['SHOP_CATEGORY'];
        oci_free_statement($shop_category_stmt);

        // Bind parameters
        oci_bind_by_name($stmt, ':name', $_POST['product_name']);
        oci_bind_by_name($stmt, ':description', $_POST['description']);
        oci_bind_by_name($stmt, ':price', $_POST['price']);
        oci_bind_by_name($stmt, ':stock', $_POST['stock']);
        oci_bind_by_name($stmt, ':shop_id', $_POST['shop_id']);
        oci_bind_by_name($stmt, ':user_id', $user_id);
        oci_bind_by_name($stmt, ':category', $product_category);
        oci_bind_by_name($stmt, ':rfid', $_POST['rfid']);

        // Handle image - Use a default image if none provided
        $default_image_path = __DIR__ . '/../../assets/images/default-product.jpg';
        if (file_exists($default_image_path)) {
            $image_data = file_get_contents($default_image_path);
        } else {
            // Create a simple default image if file doesn't exist
            $image_data = file_get_contents('data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAAIAAoDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=');
        }
        
        $lob = oci_new_descriptor($conn, OCI_D_LOB);
        $lob->writeTemporary($image_data, OCI_TEMP_BLOB);
        oci_bind_by_name($stmt, ':product_image', $lob, -1, OCI_B_BLOB);

        if (oci_execute($stmt)) {
            $_SESSION['success_msg'] = "Product added successfully!";
            header('Location: trader_products.php?shop_id=' . $_POST['shop_id']);
            exit;
        } else {
            $e = oci_error($stmt);
            throw new Exception("Database error: " . $e['message']);
        }

        $lob->free();
        oci_free_statement($stmt);

    } catch (Exception $e) {
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
        header('Location: trader_products.php');
        exit;
    }
}

// Handle product deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $product_id = $_POST['product_id'];
    
    // Verify the product belongs to the trader
    $verify_sql = "SELECT p.* FROM product p 
                  JOIN shops s ON p.shop_id = s.shop_id 
                  WHERE p.product_id = :product_id AND s.user_id = :user_id";
    $verify_stmt = oci_parse($conn, $verify_sql);
    oci_bind_by_name($verify_stmt, ":product_id", $product_id);
    oci_bind_by_name($verify_stmt, ":user_id", $user_id);
    oci_execute($verify_stmt);
    
    if (oci_fetch($verify_stmt)) {
        // Delete the product
        $delete_sql = "DELETE FROM product WHERE product_id = :product_id";
        $delete_stmt = oci_parse($conn, $delete_sql);
        oci_bind_by_name($delete_stmt, ":product_id", $product_id);
        
        if (oci_execute($delete_stmt)) {
            $_SESSION['success_msg'] = "Product deleted successfully!";
        } else {
            $_SESSION['error_msg'] = "Error deleting product.";
        }
    } else {
        $_SESSION['error_msg'] = "You don't have permission to delete this product.";
    }
    
    // Redirect back to the same page with the current shop filter if any
    $redirect_url = 'trader_products.php';
    if (isset($_POST['shop_id'])) {
        $redirect_url .= '?shop_id=' . $_POST['shop_id'];
    }
    header('Location: ' . $redirect_url);
    exit;
}

// Get product details for editing if product_id is provided
$edit_product = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $edit_sql = "SELECT p.*, s.shop_name 
                FROM product p 
                JOIN shops s ON p.shop_id = s.shop_id 
                WHERE p.product_id = :product_id 
                AND s.user_id = :user_id";
    
    $edit_stmt = oci_parse($conn, $edit_sql);
    oci_bind_by_name($edit_stmt, ":product_id", $edit_id);
    oci_bind_by_name($edit_stmt, ":user_id", $user_id);
    oci_execute($edit_stmt);
    
    $edit_product = oci_fetch_array($edit_stmt, OCI_ASSOC);
    
    if ($edit_product) {
        // Handle LOB fields
        foreach ($edit_product as $key => $value) {
            if (is_object($value) && get_class($value) === 'OCI-Lob') {
                if ($key === 'PRODUCT_IMAGE') {
                    $image_data = $value->load();
                    $edit_product[$key] = "data:image/jpeg;base64," . base64_encode($image_data);
                } else {
                    $edit_product[$key] = $value->load();
                }
            }
        }
    }
    
    oci_free_statement($edit_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>My Products | GoCleckOut</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/icon?family=Material+Icons"
        rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .delete-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }

        .delete-btn:hover {
            background-color: #c82333;
        }
    </style>
</head>

<body>
    <?php include 'trader_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'trader_sidebar.php'; ?>
            </div>
            <div class="col-md-9 col-lg-10">
        <div class="row">
            <!-- Main content -->
            <div class="main-content">
                    <!-- Products content -->
                    <div class="container my-5">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h1 class="h3">My Products</h1>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="material-icons">add</i> Add New Product
                            </button>
                        </div>

                        <!-- Shop Filter -->
                        <div class="mb-4">
                            <div class="btn-group" role="group">
                                <a href="trader_products.php" class="btn btn-outline-primary <?= !$selected_shop_id ? 'active' : '' ?>" onclick="window.location.href='trader_products.php'; return false;">
                                    All Shops
                                </a>
                                <?php foreach ($shops as $shop): ?>
                                    <a href="trader_products.php?shop_id=<?= $shop['SHOP_ID'] ?>" 
                                       class="btn btn-outline-primary <?= $selected_shop_id == $shop['SHOP_ID'] ? 'active' : '' ?>"
                                       onclick="window.location.href='trader_products.php?shop_id=<?= $shop['SHOP_ID'] ?>'; return false;">
                                        <?= htmlspecialchars($shop['SHOP_NAME']) ?>
                                        <span class="badge bg-secondary ms-1"><?= $shop['PRODUCT_COUNT'] ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Products Grid -->
                        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-3">
                            <?php if (!empty($products)): ?>
                                <?php foreach ($products as $product): ?>
                                    <div class="col">
                                        <div class="card h-100 product-card shadow-sm">
                                            <?php if (isset($product['DISCOUNT_PERCENTAGE']) && $product['DISCOUNT_PERCENTAGE'] > 0): ?>
                                                <div class="position-absolute top-0 end-0 m-2">
                                                    <span class="badge bg-danger">-<?= $product['DISCOUNT_PERCENTAGE'] ?>%</span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($product['PRODUCT_IMAGE'])): ?>
                                                <?php
                                                $product_image = $product['PRODUCT_IMAGE'];
                                                if ($product_image instanceof OCILob) {
                                                    $image_data = $product_image->load();
                                                    $base64_image = base64_encode($image_data);
                                                    $img_src = "data:image/jpeg;base64," . $base64_image;
                                                } else {
                                                    $img_src = "";
                                                }
                                                if (!empty($img_src)) {
                                                    echo "<img src=\"$img_src\" class=\"card-img-top\" alt=\"" . htmlspecialchars($product['PRODUCT_NAME']) . "\" style=\"height: 120px; object-fit: cover;\">";
                                                } else {
                                                    echo "<div class=\"card-img-top bg-light d-flex align-items-center justify-content-center\" style=\"height: 120px;\">No Image</div>";
                                                }
                                                ?>
                                            <?php else: ?>
                                                <img src="../../assets/images/default-product.jpg" 
                                                     class="card-img-top" 
                                                     alt="No image available"
                                                     style="height: 120px; object-fit: cover;" />
                                            <?php endif; ?>

                                            <div class="card-body p-2">
                                                <h6 class="card-title mb-1 text-truncate"><?= htmlspecialchars($product['PRODUCT_NAME']) ?></h6>
                                                <p class="card-text text-muted small mb-1" style="font-size: 0.8rem;">
                                                    <?php 
                                                    $description = $product['DESCRIPTION'];
                                                    if ($description instanceof OCILob) {
                                                        $description = $description->load();
                                                    }
                                                    echo htmlspecialchars(substr($description, 0, 50)) . '...';
                                                    ?>
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <div>
                                                        <?php 
                                                        $price = $product['PRICE'];
                                                        if (isset($product['DISCOUNT_PERCENTAGE']) && $product['DISCOUNT_PERCENTAGE'] > 0) {
                                                            $discounted_price = $price * (1 - $product['DISCOUNT_PERCENTAGE']/100);
                                                            echo '<span class="text-decoration-line-through text-muted small">£' . number_format($price, 2) . '</span>';
                                                            echo '<span class="text-danger fw-bold ms-1">£' . number_format($discounted_price, 2) . '</span>';
                                                        } else {
                                                            echo '<span class="fw-bold">£' . number_format($price, 2) . '</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                    <span class="badge <?= $product['PRODUCT_STATUS'] === 'active' ? 'bg-success' : 'bg-danger' ?> small">
                                                        <?= ucfirst($product['PRODUCT_STATUS']) ?>
                                                    </span>
                                                </div>
                                                <div class="small text-muted mb-1">
                                                    <?= htmlspecialchars($product['SHOP_NAME']) ?>
                                                </div>
                                                <div class="btn-group btn-group-sm w-100">
                                                    <button type="button" class="btn btn-outline-primary btn-sm py-0 edit-product-btn" 
                                                            data-product-id="<?= $product['PRODUCT_ID'] ?>"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editProductModal">
                                                        <i class="material-icons" style="font-size: 1rem;">edit</i>
                                                    </button>
                                                    <form method="POST" action="" style="display: inline;">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['PRODUCT_ID']; ?>">
                                                        <?php if (isset($_GET['shop_id'])): ?>
                                                            <input type="hidden" name="shop_id" value="<?php echo $_GET['shop_id']; ?>">
                                                        <?php endif; ?>
                                                        <button type="submit" name="delete_product" class="delete-btn" onclick="return confirm('Are you sure you want to delete this product?')">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12 text-center py-5">
                                    <i class="material-icons" style="font-size: 48px; color: #ccc;">inventory_2</i>
                                    <h3 class="mt-3 text-muted">No Products Found</h3>
                                    <p class="text-muted">Start by adding your first product!</p>
                                    <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                        <i class="material-icons">add</i> Add Product
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addProductForm" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="modal-body">
                        <div class="row g-3">
                            <!-- Shop Selection -->
                            <div class="col-md-12">
                                <div class="d-flex gap-2 align-items-center">
                                    <div class="flex-grow-1">
                                        <label for="shop_id" class="form-label">Select Shop <span class="text-danger">*</span></label>
                                        <select class="form-select" id="shop_id" name="shop_id" required>
                                            <option value="">Choose a shop...</option>
                                            <?php foreach ($shops as $shop): ?>
                                                <?php if ($shop['PRODUCT_COUNT'] < 10): ?>
                                                    <option value="<?= $shop['SHOP_ID'] ?>">
                                                        <?= htmlspecialchars($shop['SHOP_NAME']) ?>
                                                        (<?= $shop['PRODUCT_COUNT'] ?>/10 products)
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Please select a shop.</div>
                                        <div class="form-text">Maximum 10 products per shop. Shops with 10 products are not shown.</div>
                                    </div>
                                    <div class="mt-4">
                                        <button type="button" class="btn btn-secondary" id="scanRfid">
                                            <i class="fas fa-barcode"></i> Scan
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Product Name and RFID in same row -->
                            <div class="col-md-12">
                                <div class="row g-3">
                                    <div class="col-md-9">
                                        <label for="product_name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="product_name" name="product_name" required maxlength="100">
                                        <div class="invalid-feedback">Please enter a product name.</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="rfid" class="form-label">RFID</label>
                                        <input type="text" class="form-control" id="rfid" name="rfid" maxlength="8" pattern="[0-9]{8}" title="RFID must be exactly 8 digits">
                                        <div class="invalid-feedback">RFID must be exactly 8 digits</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="col-md-12">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="3" required maxlength="1000"></textarea>
                                <div class="invalid-feedback">Please enter a description.</div>
                            </div>

                            <!-- Price, Stock, and Image in one line -->
                            <div class="col-md-12">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="price" class="form-label">Price (£) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="price" name="price" step="0.01" min="0.01" required>
                                        <div class="invalid-feedback">Please enter a valid price.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="stock" class="form-label">Stock <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="stock" name="stock" min="0" required>
                                        <div class="invalid-feedback">Please enter a valid stock quantity.</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="product_image" class="form-label">Product Image</label>
                                        <input type="file" class="form-control" id="product_image" name="product_image" accept="image/jpeg,image/png,image/gif">
                                    </div>
                                </div>
                                <div id="imagePreview" class="mt-2" style="display: none;">
                                    <img src="" alt="Preview" class="img-thumbnail" style="max-height: 200px;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProductModalLabel">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editProductForm" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="modal-body">
                        <input type="hidden" id="edit_product_id" name="product_id" value="<?= $edit_product['PRODUCT_ID'] ?? '' ?>">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="edit_product_name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_product_name" name="product_name" required maxlength="100" value="<?= isset($edit_product['PRODUCT_NAME']) ? (is_object($edit_product['PRODUCT_NAME']) ? $edit_product['PRODUCT_NAME']->load() : htmlspecialchars($edit_product['PRODUCT_NAME'])) : '' ?>">
                            </div>

                            <div class="col-md-12">
                                <label for="edit_description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3" required maxlength="1000"><?= isset($edit_product['DESCRIPTION']) ? (is_object($edit_product['DESCRIPTION']) ? $edit_product['DESCRIPTION']->load() : htmlspecialchars($edit_product['DESCRIPTION'])) : '' ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label for="edit_price" class="form-label">Price (£) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_price" name="price" step="0.01" min="0.01" required value="<?= isset($edit_product['PRICE']) ? (is_object($edit_product['PRICE']) ? $edit_product['PRICE']->load() : htmlspecialchars($edit_product['PRICE'])) : '' ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="edit_stock" class="form-label">Stock <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_stock" name="stock" min="0" required value="<?= isset($edit_product['STOCK']) ? (is_object($edit_product['STOCK']) ? $edit_product['STOCK']->load() : htmlspecialchars($edit_product['STOCK'])) : '' ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="edit_rfid" class="form-label">RFID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_rfid" name="rfid" required maxlength="50" pattern="[A-Za-z0-9-]+" title="Only letters, numbers, and hyphens are allowed" value="<?= isset($edit_product['RFID']) ? (is_object($edit_product['RFID']) ? $edit_product['RFID']->load() : htmlspecialchars($edit_product['RFID'])) : '' ?>">
                                <div class="invalid-feedback">Please enter a valid RFID.</div>
                            </div>

                            <div class="col-md-12">
                                <label for="edit_product_image" class="form-label">Product Image</label>
                                <input type="file" class="form-control" id="edit_product_image" name="product_image" accept="image/jpeg,image/png,image/gif">
                                <div class="form-text">Max file size: 5MB. Allowed types: JPG, PNG, GIF</div>
                                <div id="editImagePreview" class="mt-2" <?= !empty($edit_product['PRODUCT_IMAGE']) ? '' : 'style="display: none;"' ?>>
                                    <?php
                                    if (isset($edit_product['PRODUCT_IMAGE'])) {
                                        if (is_object($edit_product['PRODUCT_IMAGE']) && get_class($edit_product['PRODUCT_IMAGE']) === 'OCI-Lob') {
                                            $image_data = $edit_product['PRODUCT_IMAGE']->load();
                                            $base64_image = base64_encode($image_data);
                                            echo '<img src="data:image/jpeg;base64,' . $base64_image . '" alt="Preview" class="img-thumbnail" style="max-height: 200px;">';
                                        } else {
                                            echo '<img src="" alt="Preview" class="img-thumbnail" style="max-height: 200px;">';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_product" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // RFID Scanning
            const scanButton = document.getElementById('scanRfid');
            let isScanning = false;
            let scanInterval;

            scanButton.addEventListener('click', async function() {
                if (!isScanning) {
                    try {
                        // Start scanning
                        isScanning = true;
                        scanButton.textContent = 'Scanning';
                        
                        // Trigger Python script via PHP backend
                        console.log('Triggering RFID scan...');
                        const response = await fetch('rfid_read.php', {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json'
                            }
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        
                        const result = await response.json();
                        console.log('RFID scan triggered:', result);
                        
                        // Start polling for new scan
                        scanInterval = setInterval(checkRfidData, 1000);
                    } catch (error) {
                        console.error('Error triggering RFID scan:', error);
                        isScanning = false;
                        scanButton.textContent = 'Scan';
                    }
                } else {
                    // Stop scanning
                    isScanning = false;
                    scanButton.textContent = 'Scan';
                    clearInterval(scanInterval);
                }
            });

            function checkRfidData() {
                fetch('rfid_scan.json?' + new Date().getTime(), {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Invalid JSON response:', text);
                            throw new Error('Invalid JSON response from server');
                        }
                    });
                })
                .then(data => {
                    if (data && data.rfid) {
                        // Stop scanning
                        isScanning = false;
                        scanButton.textContent = 'Scan';
                        clearInterval(scanInterval);

                        // Populate form fields
                        document.getElementById('rfid').value = data.rfid;
                        if (data.data) {
                            if (data.data.product_name) document.getElementById('product_name').value = data.data.product_name;
                            if (data.data.description) document.getElementById('description').value = data.data.description;
                            if (data.data.price) document.getElementById('price').value = data.data.price;
                            if (data.data.stock) document.getElementById('stock').value = data.data.stock;
                            if (data.data.shop_id) document.getElementById('shop_id').value = data.data.shop_id;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking RFID data:', error);
                });
            }
        });
    </script>
</body>

</html>