<?php
require_once '../../backend/database/db_connection.php';

$conn = getDBConnection();

if (!$conn) {
    die("Database connection failed");
}

// Get all products
$sql = "SELECT p.*, s.shop_name 
        FROM product p 
        JOIN shop s ON p.shop_id = s.shop_id 
        ORDER BY p.product_id DESC";

$stmt = oci_parse($conn, $sql);
oci_execute($stmt);

$products = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
    $products[] = $row;
}
oci_free_statement($stmt);

// Get all shops for this trader with product count
$shops_sql = "SELECT s.shop_id, s.shop_name, COUNT(p.product_id) as product_count 
              FROM shops s 
              LEFT JOIN product p ON s.shop_id = p.shop_id 
              WHERE s.user_id = :user_id 
              GROUP BY s.shop_id, s.shop_name 
              ORDER BY s.shop_id";
$shops_stmt = oci_parse($conn, $shops_sql);
oci_bind_by_name($shops_stmt, ":user_id", $user_id);
oci_execute($shops_stmt);

$shops = [];
while ($shop = oci_fetch_array($shops_stmt, OCI_ASSOC)) {
    $shops[] = $shop;
}
oci_free_statement($shops_stmt);

// Get selected shop filter
$selected_shop_id = isset($_GET['shop_id']) ? $_GET['shop_id'] : null;

// Build the products query with proper joins
$products_sql = "SELECT p.*, s.shop_name, s.shop_id 
                FROM product p 
                JOIN shops s ON p.shop_id = s.shop_id 
                WHERE s.user_id = :user_id";

if ($selected_shop_id) {
    $products_sql .= " AND p.shop_id = :shop_id";
}

$products_sql .= " ORDER BY p.added_date DESC";

$products_stmt = oci_parse($conn, $products_sql);
oci_bind_by_name($products_stmt, ":user_id", $user_id);
if ($selected_shop_id) {
    oci_bind_by_name($products_stmt, ":shop_id", $selected_shop_id);
}
oci_execute($products_stmt);

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
        // Validate required fields
        $required_fields = ['shop_id', 'product_name', 'description', 'price', 'stock'];
        $errors = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
            }
        }

        // Validate numeric fields
        if (!is_numeric($_POST['price']) || $_POST['price'] <= 0) {
            $errors[] = "Price must be a positive number";
        }
        if (!is_numeric($_POST['stock']) || $_POST['stock'] < 0) {
            $errors[] = "Stock must be a non-negative number";
        }

        // Validate shop ownership
        $shop_id = $_POST['shop_id'];
        $shop_sql = "SELECT shop_category FROM shops WHERE shop_id = :shop_id AND user_id = :user_id";
        $shop_stmt = oci_parse($conn, $shop_sql);
        oci_bind_by_name($shop_stmt, ":shop_id", $shop_id);
        oci_bind_by_name($shop_stmt, ":user_id", $user_id);
        oci_execute($shop_stmt);
        $shop = oci_fetch_array($shop_stmt, OCI_ASSOC);
        oci_free_statement($shop_stmt);

        if (!$shop) {
            $errors[] = "Invalid shop selection";
        }

        if (empty($errors)) {
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
            
            // Handle image upload
            $image_data = null;
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                // Validate image
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($_FILES['product_image']['type'], $allowed_types)) {
                    throw new Exception("Invalid image type. Only JPG, PNG and GIF are allowed.");
                }
                if ($_FILES['product_image']['size'] > $max_size) {
                    throw new Exception("Image size must be less than 5MB.");
                }
                
                $image_data = file_get_contents($_FILES['product_image']['tmp_name']);
            } else {
                // Use default image
                $default_image = file_get_contents('../../assets/images/default-product.jpg');
                if ($default_image === false) {
                    throw new Exception("Could not load default image.");
                }
                $image_data = $default_image;
            }

            // Create LOB for image
            $lob = oci_new_descriptor($conn, OCI_D_LOB);
            $lob->writeTemporary($image_data, OCI_TEMP_BLOB);

            // Bind parameters
            oci_bind_by_name($stmt, ':name', $_POST['product_name']);
            oci_bind_by_name($stmt, ':description', $_POST['description']);
            oci_bind_by_name($stmt, ':price', $_POST['price']);
            oci_bind_by_name($stmt, ':stock', $_POST['stock']);
            oci_bind_by_name($stmt, ':product_image', $lob, -1, OCI_B_BLOB);
            oci_bind_by_name($stmt, ':shop_id', $shop_id);
            oci_bind_by_name($stmt, ':user_id', $user_id);
            oci_bind_by_name($stmt, ':category', $shop['SHOP_CATEGORY']);
            oci_bind_by_name($stmt, ':rfid', $_POST['rfid']);

            if (oci_execute($stmt)) {
                $_SESSION['success_msg'] = "Product added successfully!";
                // Redirect back to the same page with the current shop filter if any
                $redirect_url = 'index.php?page=products';
                if ($selected_shop_id) {
                    $redirect_url .= '&shop_id=' . $selected_shop_id;
                }
                header('Location: ' . $redirect_url);
                exit;
            } else {
                $e = oci_error($stmt);
                throw new Exception("Database error: " . $e['message']);
            }

            $lob->free();
            oci_free_statement($stmt);
        } else {
            $_SESSION['error_msg'] = implode("<br>", $errors);
        }
    } catch (Exception $e) {
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
    }
    
    // If we get here, there was an error
    // Redirect back to the same page with the current shop filter if any
    $redirect_url = 'index.php?page=products';
    if ($selected_shop_id) {
        $redirect_url .= '&shop_id=' . $selected_shop_id;
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
    <style>
    .nav-item, .nav-link {
      color: #ff6b6b !important;
    }
    .nav-item:hover, .nav-link:hover {
      color: rgb(70, 70, 70) !important;
    }
    .product-card {
        transition: transform 0.2s;
        border: 1px solid #eee;
        border-radius: 8px;
        overflow: hidden;
    }
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .product-image {
        height: 200px;
        object-fit: cover;
        width: 100%;
    }
    .discount-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background-color: #ff6b6b;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.8rem;
    }
    .modal-dialog {
        max-width: 800px;
    }
    .modal-content {
        border-radius: 8px;
    }
    .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }
    .modal-footer {
        background-color: #f8f9fa;
        border-top: 1px solid #dee2e6;
    }
    .btn-group {
        gap: 5px;
    }
    .btn-group .btn {
        flex: 1;
    }
  </style>
</head>

<body>
    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">My Products</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal" onclick="e => console.log(this.e)">
                <i class="material-icons">add</i> Add New Product
            </button>
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

                                <!-- Product Name -->
                                <div class="col-md-12">
                                    <label for="product_name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="product_name" name="product_name" required maxlength="100">
                                    <div class="invalid-feedback">Please enter a product name.</div>
                                </div>

                                <!-- Description -->
                                <div class="col-md-12">
                                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="3" required maxlength="1000"></textarea>
                                    <div class="invalid-feedback">Please enter a description.</div>
                                </div>

                                <!-- Price and Stock -->
                                <div class="col-md-6">
                                    <label for="price" class="form-label">Price (£) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0.01" required>
                                    <div class="invalid-feedback">Please enter a valid price.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="stock" class="form-label">Stock <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="stock" name="stock" min="0" required>
                                    <div class="invalid-feedback">Please enter a valid stock quantity.</div>
                                </div>

                                <!-- Product Image -->
                                <div class="col-md-12">
                                    <label for="product_image" class="form-label">Product Image</label>
                                    <input type="file" class="form-control" id="product_image" name="product_image" accept="image/jpeg,image/png,image/gif">
                                    <div class="form-text">Max file size: 5MB. Allowed types: JPG, PNG, GIF</div>
                                    <div id="imagePreview" class="mt-2" style="display: none;">
                                        <img src="" alt="Preview" class="img-thumbnail" style="max-height: 200px;">
                                    </div>
                                </div>

                                <!-- RFID Scan Button -->
                                <div class="col-md-12">
                                    <button type="button" class="btn btn-success w-100" id="enable-rfid">
                                        <i class="fas fa-barcode"></i> Scan via RFID
                                    </button>
                                    <p id="scan-status" class="text-center mt-2" style="display: none;">Scanning RFID...</p>
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

        <!-- Shop Filter -->
        <div class="mb-4">
            <div class="btn-group" role="group">
                <a href="?page=products" class="btn btn-outline-primary <?= !$selected_shop_id ? 'active' : '' ?>">
                    All Shops
                </a>
                <?php foreach ($shops as $shop): ?>
                    <a href="?page=products&shop_id=<?= $shop['SHOP_ID'] ?>" 
                       class="btn btn-outline-primary <?= $selected_shop_id == $shop['SHOP_ID'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($shop['SHOP_NAME']) ?>
                        <span class="badge bg-secondary ms-1"><?= $shop['PRODUCT_COUNT'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                    <div class="col">
                        <div class="card h-100 product-card">
                            <?php if (isset($product['DISCOUNT_PERCENTAGE']) && $product['DISCOUNT_PERCENTAGE'] > 0): ?>
                                <div class="discount-badge">
                                    -<?= $product['DISCOUNT_PERCENTAGE'] ?>%
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
                                    echo "<img src=\"$img_src\" class=\"product-image\" alt=\"" . htmlspecialchars($product['PRODUCT_NAME']) . "\">";
                                } else {
                                    echo "<div class=\"product-image bg-light d-flex align-items-center justify-content-center\">No Image</div>";
                                }
                                ?>
                            <?php else: ?>
                                <img src="../../assets/images/default-product.jpg" 
                                     class="product-image" 
                                     alt="No image available" />
                            <?php endif; ?>

                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($product['PRODUCT_NAME']) ?></h5>
                                <p class="card-text text-muted small">
                                    <?php 
                                    $description = $product['DESCRIPTION'];
                                    if ($description instanceof OCILob) {
                                        $description = $description->load();
                                    }
                                    echo htmlspecialchars(substr($description, 0, 100)) . '...';
                                    ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <?php 
                                        $price = $product['PRICE'];
                                        if (isset($product['DISCOUNT_PERCENTAGE']) && $product['DISCOUNT_PERCENTAGE'] > 0) {
                                            $discounted_price = $price * (1 - $product['DISCOUNT_PERCENTAGE']/100);
                                            echo '<span class="text-decoration-line-through text-muted">£' . number_format($price, 2) . '</span>';
                                            echo '<span class="text-danger fw-bold ms-2">£' . number_format($discounted_price, 2) . '</span>';
                                        } else {
                                            echo '<span class="fw-bold">£' . number_format($price, 2) . '</span>';
                                        }
                                        ?>
                                    </div>
                                    <span class="badge <?= $product['PRODUCT_STATUS'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                                        <?= ucfirst($product['PRODUCT_STATUS']) ?>
                                    </span>
                                </div>
                                <div class="small text-muted mb-3">
                                    Shop: <?= htmlspecialchars($product['SHOP_NAME']) ?>
                                </div>
                                <div class="btn-group w-100">
                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                            onclick="window.location.href='?page=products<?= $selected_shop_id ? '&shop_id=' . $selected_shop_id : '' ?>&edit_id=<?= $product['PRODUCT_ID'] ?>'">
                                        <i class="material-icons">edit</i> Edit
                                    </button>
                                    <button onclick="deleteProduct(<?= $product['PRODUCT_ID'] ?>)" 
                                            class="btn btn-outline-danger btn-sm">
                                        <i class="material-icons">delete</i> Delete
                                    </button>
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
                                <input type="text" class="form-control" id="edit_product_name" name="product_name" required maxlength="100" value="<?= htmlspecialchars($edit_product['PRODUCT_NAME'] ?? '') ?>">
                            </div>

                            <div class="col-md-12">
                                <label for="edit_description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3" required maxlength="1000"><?= htmlspecialchars($edit_product['DESCRIPTION'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label for="edit_price" class="form-label">Price (£) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_price" name="price" step="0.01" min="0.01" required value="<?= htmlspecialchars($edit_product['PRICE'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="edit_stock" class="form-label">Stock <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_stock" name="stock" min="0" required value="<?= htmlspecialchars($edit_product['STOCK'] ?? '') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="edit_rfid" class="form-label">RFID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_rfid" name="rfid" required maxlength="50" pattern="[A-Za-z0-9-]+" title="Only letters, numbers, and hyphens are allowed" value="<?= htmlspecialchars($edit_product['RFID'] ?? '') ?>">
                                <div class="invalid-feedback">Please enter a valid RFID.</div>
                            </div>

                            <div class="col-md-12">
                                <label for="edit_product_image" class="form-label">Product Image</label>
                                <input type="file" class="form-control" id="edit_product_image" name="product_image" accept="image/jpeg,image/png,image/gif">
                                <div class="form-text">Max file size: 5MB. Allowed types: JPG, PNG, GIF</div>
                                <div id="editImagePreview" class="mt-2" <?= !empty($edit_product['PRODUCT_IMAGE']) ? '' : 'style="display: none;"' ?>>
                                    <img src="<?= $edit_product['PRODUCT_IMAGE'] ?? '' ?>" alt="Preview" class="img-thumbnail" style="max-height: 200px;">
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

    <!-- Footer -->
    <?php include '../footer.php' ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let scanning = false;

        document.getElementById('enable-rfid').addEventListener('click', () => {
            scanning = true;
            document.getElementById('scan-status').style.display = 'block';
            document.getElementById('scan-status').textContent = "🔄 Scanning RFID...";

            // Trigger Python script via PHP backend
            fetch('trigger_rfid.php')
                .then(() => pollRFID());
        });

        function pollRFID() {
            if (!scanning) return;

            fetch('rfid_scan.json?' + new Date().getTime())
                .then(res => res.json())
                .then(data => {
                    if (data && data.data && data.data.product_name) {
                        // Fill the form
                        document.querySelector('[name="product_name"]').value = data.data.product_name;
                        document.querySelector('[name="description"]').value = data.data.description;
                        document.querySelector('[name="price"]').value = data.data.price;
                        document.querySelector('[name="stock"]').value = data.data.stock;
                        document.querySelector('[name="shop_id"]').value = data.data.shop_id;

                        document.getElementById('scan-status').textContent = "✔️ RFID scanned successfully!";
                        scanning = false;
                    } else {
                        setTimeout(pollRFID, 1000);
                    }
                })
                .catch(() => setTimeout(pollRFID, 1000));
        }

        // Show edit modal if edit_id is present
        <?php if (isset($_GET['edit_id'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const editModal = new bootstrap.Modal(document.getElementById('editProductModal'));
            editModal.show();
        });
        <?php endif; ?>

        // Handle edit button clicks
        document.querySelectorAll('.btn-group .btn-outline-primary').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const url = new URL(this.href);
                const editId = url.searchParams.get('edit_id');
                if (editId) {
                    const editModal = new bootstrap.Modal(document.getElementById('editProductModal'));
                    editModal.show();
                }
            });
        });

        // Image preview for add form
        document.getElementById('product_image').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            const file = e.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.querySelector('img').src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });

        // Image preview for edit form
        document.getElementById('edit_product_image').addEventListener('change', function(e) {
            const preview = document.getElementById('editImagePreview');
            const file = e.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.querySelector('img').src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });

        // Form validation
        (function () {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation')
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()

        // Show success/error messages
        <?php if (isset($_SESSION['success_msg'])): ?>
            const toast = new bootstrap.Toast(document.createElement('div'));
            toast._element.classList.add('toast', 'bg-success', 'text-white', 'position-fixed', 'top-0', 'end-0', 'm-3');
            toast._element.innerHTML = `
                <div class="toast-body">
                    <?= $_SESSION['success_msg'] ?>
                </div>
            `;
            document.body.appendChild(toast._element);
            toast.show();
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
            const errorToast = new bootstrap.Toast(document.createElement('div'));
            errorToast._element.classList.add('toast', 'bg-danger', 'text-white', 'position-fixed', 'top-0', 'end-0', 'm-3');
            errorToast._element.innerHTML = `
                <div class="toast-body">
                    <?= $_SESSION['error_msg'] ?>
                </div>
            `;
            document.body.appendChild(errorToast._element);
            errorToast.show();
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>
    </script>
</body>

</html>