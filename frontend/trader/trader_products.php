<?php
require_once '../../backend/database/db_connection.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id || $_SESSION['role'] !== 'trader') {
    header('Location: ../login.php');
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

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
        $required_fields = ['shop_id', 'product_name', 'description', 'price', 'stock', 'min_order', 'max_order'];
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
        if (!is_numeric($_POST['min_order']) || $_POST['min_order'] < 1) {
            $errors[] = "Minimum order must be at least 1";
        }
        if (!is_numeric($_POST['max_order']) || $_POST['max_order'] < $_POST['min_order']) {
            $errors[] = "Maximum order must be greater than minimum order";
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
                product_name, description, price, stock, min_order, max_order,
                product_image, added_date, updated_date, product_status,
                discount_percentage, shop_id, user_id, product_category_name
            ) VALUES (
                :name, :description, :price, :stock, :min_order, :max_order,
                :product_image, SYSDATE, SYSDATE, 'active', 0,
                :shop_id, :user_id, :category
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
            oci_bind_by_name($stmt, ':min_order', $_POST['min_order']);
            oci_bind_by_name($stmt, ':max_order', $_POST['max_order']);
            oci_bind_by_name($stmt, ':product_image', $lob, -1, OCI_B_BLOB);
            oci_bind_by_name($stmt, ':shop_id', $shop_id);
            oci_bind_by_name($stmt, ':user_id', $user_id);
            oci_bind_by_name($stmt, ':category', $shop['SHOP_CATEGORY']);

            if (oci_execute($stmt)) {
                $_SESSION['success_msg'] = "Product added successfully!";
                // Redirect back to the same page with the current shop filter if any
                $redirect_url = 'trader_products.php';
                if ($selected_shop_id) {
                    $redirect_url .= '?shop_id=' . $selected_shop_id;
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
    $redirect_url = 'trader_products.php';
    if ($selected_shop_id) {
        $redirect_url .= '?shop_id=' . $selected_shop_id;
    }
    header('Location: ' . $redirect_url);
    exit;
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
        cursor: pointer;
    }
    .product-card:hover {
        transform: translateY(-5px);
    }
    .shop-filter.active {
        background-color: #ff6b6b;
        color: white !important;
    }
    .product-image {
        height: 180px;
        object-fit: cover;
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
  </style>
</head>

<body>
    <!-- Header -->
    <?php include '../header.php' ?>

    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">My Products</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
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

                                <!-- Min and Max Order -->
                                <div class="col-md-6">
                                    <label for="min_order" class="form-label">Minimum Order <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="min_order" name="min_order" min="1" required>
                                    <div class="invalid-feedback">Minimum order must be at least 1.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="max_order" class="form-label">Maximum Order <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="max_order" name="max_order" min="1" required>
                                    <div class="invalid-feedback">Maximum order must be greater than minimum order.</div>
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
                <?php 
                // Debug output
                // echo "<pre>Number of products: " . count($products) . "</pre>";
                foreach ($products as $index => $product): 
                    // Debug output
                    // echo "<pre>Product $index: "; print_r($product); echo "</pre>";
                ?>
                    <div class="col">
                        <div class="card h-100 product-card shadow-sm">
                            <?php if (isset($product['DISCOUNT_PERCENTAGE']) && $product['DISCOUNT_PERCENTAGE'] > 0): ?>
                                <div class="discount-badge">
                                    -<?= $product['DISCOUNT_PERCENTAGE'] ?>%
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($product['PRODUCT_IMAGE'])): ?>
                                <img src="data:image/jpeg;base64,<?= base64_encode($product['PRODUCT_IMAGE']) ?>" 
                                     class="card-img-top product-image" 
                                     alt="<?= htmlspecialchars($product['PRODUCT_NAME']) ?>" />
                            <?php else: ?>
                                <img src="../../assets/images/default-product.jpg" 
                                     class="card-img-top product-image" 
                                     alt="No image available" />
                            <?php endif; ?>

                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($product['PRODUCT_NAME']) ?></h5>
                                <p class="card-text text-muted small">
                                    <?= htmlspecialchars(substr($product['DESCRIPTION'], 0, 100)) ?>...
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
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
                                <div class="mt-2 small text-muted">
                                    Shop: <?= htmlspecialchars($product['SHOP_NAME']) ?>
                                </div>
                                <div class="mt-2">
                                    <span class="badge bg-info">Stock: <?= $product['STOCK'] ?></span>
                                    <span class="badge bg-secondary">Min: <?= $product['MIN_ORDER'] ?></span>
                                    <span class="badge bg-secondary">Max: <?= $product['MAX_ORDER'] ?></span>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-top-0">
                                <div class="btn-group w-100">
                                    <a href="edit_product.php?id=<?= $product['PRODUCT_ID'] ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="material-icons">edit</i> Edit
                                    </a>
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

    <!-- Footer -->
    <?php include '../footer.php' ?>

    <!-- Make sure Bootstrap JS is loaded before our scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteProduct(productId) {
            if (confirm('Are you sure you want to delete this product?')) {
                // Add your delete logic here
                console.log('Deleting product:', productId);
            }
        }

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

        // Image preview
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

        // Validate max order is greater than min order
        document.getElementById('max_order').addEventListener('change', function() {
            const minOrder = parseInt(document.getElementById('min_order').value);
            const maxOrder = parseInt(this.value);
            
            if (maxOrder <= minOrder) {
                this.setCustomValidity('Maximum order must be greater than minimum order');
            } else {
                this.setCustomValidity('');
            }
        });

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

        // Initialize Bootstrap components when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize the modal
            var addProductModal = new bootstrap.Modal(document.getElementById('addProductModal'));
            
            // Add click handler to the Add Product button
            document.querySelector('[data-bs-target="#addProductModal"]').addEventListener('click', function() {
                addProductModal.show();
            });

            // Test if modal can be opened
            console.log('Modal element:', document.getElementById('addProductModal'));
            console.log('Modal instance:', addProductModal);
        });

        // Handle form submission and modal closing
        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            const shopSelect = document.getElementById('shop_id');
            const selectedOption = shopSelect.options[shopSelect.selectedIndex];
            
            if (selectedOption.disabled) {
                e.preventDefault();
                alert('This shop has reached its maximum product limit of 10 items.');
                return false;
            }
            
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.add('was-validated');
                return false;
            }

            // If form is valid, let it submit
            // The modal will be closed by the redirect after successful submission
            // But we'll also add a fallback to ensure it closes
            const modal = bootstrap.Modal.getInstance(document.getElementById('addProductModal'));
            if (modal) {
                modal.hide();
                // Remove any remaining backdrop
                document.querySelectorAll('.modal-backdrop').forEach(function(backdrop) {
                    backdrop.remove();
                });
                // Remove modal-open class from body
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
        });

        // Clear form when modal is closed
        document.getElementById('addProductModal').addEventListener('hidden.bs.modal', function () {
            const form = document.getElementById('addProductForm');
            form.reset();
            form.classList.remove('was-validated');
            // Clear image preview
            const preview = document.getElementById('imagePreview');
            if (preview) {
                preview.style.display = 'none';
                preview.querySelector('img').src = '';
            }
        });
    </script>
</body>

</html>