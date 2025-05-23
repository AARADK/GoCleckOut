<?php

$user_id = $_SESSION['user_id'] ?? null;

$wishlist_items = [];
if ($user_id) {
    $wishlist_sql = "
        SELECT wp.product_id 
        FROM wishlist_product wp 
        JOIN wishlist w ON wp.wishlist_id = w.wishlist_id 
        WHERE w.user_id = :user_id
    ";
    $stmt = oci_parse($conn, $wishlist_sql);
    oci_bind_by_name($stmt, ":user_id", $user_id);
    oci_execute($stmt);
    while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
        $wishlist_items[] = $row['PRODUCT_ID'];
    }
    oci_free_statement($stmt);
}

// Function to display products
function displayProducts($products, $wishlist_items) {
    if (empty($products)) {
        echo '<div class="col-12 text-center py-5">
                <p class="text-muted h5">No products found matching your filters!</p>
                <p class="text-muted">Try adjusting your filters or browse all products.</p>
              </div>';
        return;
    }

    foreach ($products as $product) {
        $description = $product['DESCRIPTION'] ?? 'Desc';
        if ($description instanceof OCILob) {
            $description = $description->load();
        }
        ?>
        <div class="col">
            <div class="card h-100 shadow-sm">
                <form action="<?= basename($_SERVER['PHP_SELF']) ?>" method="POST">
                    <div class="card-img-container">
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
                            echo "<img src=\"$img_src\" class=\"card-img-top\" style=\"height: 150px; object-fit: cover;\" alt=\"Product Image\">";
                        } else {
                            echo "<div class=\"card-img-top bg-light d-flex align-items-center justify-content-center\" style=\"height: 150px;\">No Image</div>";
                        }
                        ?>
                        <button type="button" class="wishlist-btn <?= in_array($product['PRODUCT_ID'], $wishlist_items) ? 'active' : '' ?>" 
                                data-product-id="<?= $product['PRODUCT_ID']; ?>" 
                                onclick="toggleWishlist(this)">
                            <i class="<?= in_array($product['PRODUCT_ID'], $wishlist_items) ? 'fas' : 'far' ?> fa-heart"></i>
                        </button>
                    </div>
                    <div class="card-body d-flex flex-column p-3">
                        <h5 class="card-title text-truncate mb-1"><?= $product['PRODUCT_NAME']; ?></h5>
                        <div class="text-warning mb-1" style="font-size: 0.9rem;">★★★★★</div>
                        <input type="hidden" name="product_id" value="<?= $product['PRODUCT_ID']; ?>">
                        <p class="card-text text-muted small mb-2" style="font-size: 0.85rem;"><?= htmlspecialchars($description) ?></p>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="h6 text-danger mb-0">£<?= number_format($product['PRICE'], 2); ?></span>
                            <span class="text-muted small">Stock: <?= $product['STOCK']; ?></span>
                        </div>
                        <div class="text-muted small mb-2">
                            Shop: <?= htmlspecialchars($product['SHOP_NAME']); ?>
                        </div>
                        <div class="input-group mb-2">
                            <input type="number" name="qty" required 
                                   min="1" 
                                   max="<?= $product['STOCK'] ?>" 
                                   value="1" 
                                   class="form-control form-control-sm quantity-input" 
                                   style="max-width: 70px;"
                                   data-product-id="<?= $product['PRODUCT_ID'] ?>"
                                   onchange="validateQuantity(this)">
                        </div>
                        <div class="d-grid gap-1 mt-auto">
                            <a href="/GCO/frontend/user/product_detail.php?id=<?= $product['PRODUCT_ID']; ?>" class="btn btn-sm btn-secondary">Details</a>
                            <?php print_r($_SESSION); if (isset($_SESSION['user_id'])): ?>
                                <button type="submit" name="add_to_cart" class="btn btn-sm btn-danger">Add to Cart</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-danger" onclick="location.href='/GCO/frontend/login/login_portal.php?sign_in=true'">Add to Cart</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
}
?>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Products</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <style>
        .card {
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
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
        .card-img-container {
            position: relative;
        }
    </style>
</head>

<body>
    <div class="container-fluid py-4">
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
            <?php
            // Get filter parameters
            $category = $_GET['category'] ?? 'all';
            $price_range = $_GET['price_range'] ?? null;
            $selected_shops = isset($_GET['shops']) ? explode(',', $_GET['shops']) : [];
            $searchTerm = $_GET['name'] ?? null;

            // Build the base query
            $select_products_sql = "SELECT p.*, s.shop_name 
                                   FROM product p 
                                   JOIN shops s ON p.shop_id = s.shop_id 
                                   WHERE p.product_status = 'active'";

            // Add category filter
            if ($category !== 'all') {
                $select_products_sql .= " AND s.shop_category = :category";
            }

            // Add price range filter
            if ($price_range !== null && $price_range !== '') {
                $select_products_sql .= " AND p.price <= :price_range";
            }

            // Add shop filter
            if (!empty($selected_shops)) {
                $select_products_sql .= " AND p.shop_id IN (" . implode(',', array_fill(0, count($selected_shops), '?')) . ")";
            }

            // Add search filter
            if ($searchTerm) {
                $select_products_sql .= " AND LOWER(p.product_name) LIKE '%' || LOWER(:searchTerm) || '%'";
            }

            $select_products_sql .= " ORDER BY p.added_date DESC";

            $stmt = oci_parse($conn, $select_products_sql);

            // Bind parameters
            $param_index = 1;
            if ($category !== 'all') {
                oci_bind_by_name($stmt, ":category", $category);
            }
            if ($price_range !== null && $price_range !== '') {
                oci_bind_by_name($stmt, ":price_range", $price_range);
            }
            if (!empty($selected_shops)) {
                foreach ($selected_shops as $shop_id) {
                    oci_bind_by_name($stmt, $param_index, $shop_id);
                    $param_index++;
                }
            }
            if ($searchTerm) {
                oci_bind_by_name($stmt, ":searchTerm", $searchTerm);
            }

            oci_execute($stmt);

            $products_found = false;
            while ($fetch_product = oci_fetch_array($stmt, OCI_ASSOC)) {
                $products_found = true;
                $description = $fetch_product['DESCRIPTION'] ?? 'Desc';
                if ($description instanceof OCILob) {
                    $description = $description->load();
                }
                ?>
                <div class="col">
                    <div class="card h-100 shadow-sm">
                        <form action="<?= basename($_SERVER['PHP_SELF']) ?>" method="POST">
                            <div class="card-img-container">
                                <?php
                                $product_image = $fetch_product['PRODUCT_IMAGE'];

                                if ($product_image instanceof OCILob) {
                                    $image_data = $product_image->load();
                                    $base64_image = base64_encode($image_data);
                                    $img_src = "data:image/jpeg;base64," . $base64_image;
                                } else {
                                    $img_src = "";
                                }

                                if (!empty($img_src)) {
                                    echo "<img src=\"$img_src\" class=\"card-img-top\" style=\"height: 150px; object-fit: cover;\" alt=\"Product Image\">";
                                } else {
                                    echo "<div class=\"card-img-top bg-light d-flex align-items-center justify-content-center\" style=\"height: 150px;\">No Image</div>";
                                }
                                ?>
                                <button type="button" class="wishlist-btn <?= in_array($fetch_product['PRODUCT_ID'], $wishlist_items) ? 'active' : '' ?>" 
                                        data-product-id="<?= $fetch_product['PRODUCT_ID']; ?>" 
                                        onclick="toggleWishlist(this)">
                                    <i class="<?= in_array($fetch_product['PRODUCT_ID'], $wishlist_items) ? 'fas' : 'far' ?> fa-heart"></i>
                                </button>
                            </div>
                            <div class="card-body d-flex flex-column p-3">
                                <h5 class="card-title text-truncate mb-1"><?= $fetch_product['PRODUCT_NAME']; ?></h5>
                                <div class="text-warning mb-1" style="font-size: 0.9rem;">★★★★★</div>
                                <input type="hidden" name="product_id" value="<?= $fetch_product['PRODUCT_ID']; ?>">
                                <p class="card-text text-muted small mb-2" style="font-size: 0.85rem;"><?= htmlspecialchars($description) ?></p>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="h6 text-danger mb-0">£<?= number_format($fetch_product['PRICE'], 2); ?></span>
                                    <span class="text-muted small">Stock: <?= $fetch_product['STOCK']; ?></span>
                                </div>
                                <div class="text-muted small mb-2">
                                    Shop: <?= htmlspecialchars($fetch_product['SHOP_NAME']); ?>
                                </div>
                                <div class="input-group mb-2">
                                    <input type="number" name="qty" required 
                                           min="1" 
                                           max="<?= $fetch_product['STOCK'] ?>" 
                                           value="1" 
                                           class="form-control form-control-sm quantity-input" 
                                           style="max-width: 70px;"
                                           data-product-id="<?= $fetch_product['PRODUCT_ID'] ?>"
                                           onchange="validateQuantity(this)">
                                </div>
                                <div class="d-grid gap-1 mt-auto">
                                    <a href="/GCO/frontend/user/product_detail.php?id=<?= $fetch_product['PRODUCT_ID']; ?>" class="btn btn-sm btn-secondary">Details</a>
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <button type="submit" name="add_to_cart" class="btn btn-sm btn-danger">Add to Cart</button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="location.href='/GCO/frontend/login/login_portal.php?sign_in=true'">Add to Cart</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php
            }

            oci_free_statement($stmt);

            if (!$products_found) {
                echo '<div class="col-12 text-center py-5">
                        <p class="text-muted h5">No products found matching your filters!</p>
                        <p class="text-muted">Try adjusting your filters or browse all products.</p>
                      </div>';
            }
            ?>
        </div>
    </div>

    <script>
        // Define toggleWishlist function globally
        function toggleWishlist(button) {
            const productId = button.dataset.productId;
            const icon = button.querySelector('i');
            
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
                    // Toggle heart icon
                    if (action === 'add') {
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                        button.classList.add('active');
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
                        button.classList.remove('active');
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

        document.addEventListener("DOMContentLoaded", function() {
            <?php
            $toastMessage = $_SESSION['toast_message'] ?? '';
            $toastType = $_SESSION['toast_type'] ?? '';
            unset($_SESSION['toast_message'], $_SESSION['toast_type']);
            ?>
            let message = "<?= $toastMessage ?>";
            let messageType = "<?= $toastType ?>";

            if (message !== "") {
                Toastify({
                    text: message,
                    duration: 3000,
                    close: true,
                    gravity: 'top',
                    position: 'right',
                    backgroundColor: messageType === "success" ? "#198754" : "#dc3545",
                }).showToast();
            }
        });

        function validateQuantity(input) {
            const productId = input.dataset.productId;
            const quantity = parseInt(input.value);
            const maxStock = parseInt(input.max);
            
            if (quantity < 1) {
                input.value = 1;
                showToast('Minimum quantity is 1', 'error');
            } else if (quantity > maxStock) {
                input.value = maxStock;
                showToast(`Maximum available stock is ${maxStock}`, 'error');
            }
        }

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type} position-fixed top-0 end-0 m-3`;
            toast.style.zIndex = '1050';
            toast.innerHTML = `
                <div class="toast-body">
                    ${message}
                </div>
            `;
            document.body.appendChild(toast);
            
            const bsToast = new bootstrap.Toast(toast, {
                animation: true,
                autohide: true,
                delay: 3000
            });
            bsToast.show();
            
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }

        // Add event listeners to all quantity inputs when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('change', function() {
                    validateQuantity(this);
                });
            });
        });
    </script>
</body>