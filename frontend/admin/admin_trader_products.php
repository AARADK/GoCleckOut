<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login_portal.php?sign_in=true");
    exit;
}

include '../../backend/database/connect.php';
$conn = getDBConnection();

// Get filter parameters
$trader_filter = isset($_GET['trader_id']) ? $_GET['trader_id'] : '';
$shop_filter = isset($_GET['shop_id']) ? $_GET['shop_id'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get all traders for the filter dropdown
$traders_sql = "
    SELECT u.user_id, u.full_name 
    FROM users u 
    WHERE u.role = 'trader' 
    ORDER BY u.full_name
";
$traders_stmt = oci_parse($conn, $traders_sql);
oci_execute($traders_stmt);

// Get shops based on selected trader
$shops_sql = "
    SELECT s.shop_id, s.shop_name 
    FROM shops s
    WHERE 1=1
";
if ($trader_filter) {
    $shops_sql .= " AND s.user_id = :trader_filter";
}
$shops_sql .= " ORDER BY s.shop_name";
$shops_stmt = oci_parse($conn, $shops_sql);
if ($trader_filter) {
    oci_bind_by_name($shops_stmt, ':trader_filter', $trader_filter);
}
oci_execute($shops_stmt);

// Get all product categories
$categories_sql = "SELECT DISTINCT product_category_name FROM product ORDER BY product_category_name";
$categories_stmt = oci_parse($conn, $categories_sql);
oci_execute($categories_stmt);

// Build the main query for products
$products_sql = "
    SELECT 
        p.product_id,
        p.product_name,
        p.description,
        p.price,
        p.stock,
        p.product_status,
        p.discount_percentage,
        p.added_date,
        p.updated_date,
        p.product_category_name,
        s.shop_name,
        u.full_name as trader_name,
        u.email as trader_email
    FROM product p
    JOIN shops s ON p.shop_id = s.shop_id
    JOIN users u ON p.user_id = u.user_id
    WHERE 1=1
";

if ($trader_filter) {
    $products_sql .= " AND p.user_id = :trader_filter";
}
if ($shop_filter) {
    $products_sql .= " AND p.shop_id = :shop_filter";
}
if ($category_filter) {
    $products_sql .= " AND p.product_category_name = :category_filter";
}
if ($status_filter) {
    $products_sql .= " AND p.product_status = :status_filter";
}

$products_sql .= " ORDER BY p.added_date DESC";

$products_stmt = oci_parse($conn, $products_sql);

// Bind filter parameters if they exist
if ($trader_filter) {
    oci_bind_by_name($products_stmt, ':trader_filter', $trader_filter);
}
if ($shop_filter) {
    oci_bind_by_name($products_stmt, ':shop_filter', $shop_filter);
}
if ($category_filter) {
    oci_bind_by_name($products_stmt, ':category_filter', $category_filter);
}
if ($status_filter) {
    oci_bind_by_name($products_stmt, ':status_filter', $status_filter);
}

oci_execute($products_stmt);

// Function to read CLOB data
function readClob($clob) {
    if ($clob) {
        return $clob->read($clob->size());
    } else {
        return '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trader Products - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .products-card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .category-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            background-color: #e9ecef;
            color: #495057;
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .status-active { background-color: #c3e6cb; color: #155724; }
        .status-inactive { background-color: #f5c6cb; color: #721c24; }
        .product-description {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .discount-badge {
            background-color: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php' ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'admin_sidebar.php' ?>
            </div>
            <div class="col-md-9 col-lg-10">
                <div class="container-fluid py-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h3 mb-0">Trader Products</h2>
                        <div class="d-flex gap-2">
                            <form method="GET" class="d-flex gap-2">
                                <select name="trader_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Traders</option>
                                    <?php while ($trader = oci_fetch_assoc($traders_stmt)): ?>
                                        <option value="<?= $trader['USER_ID'] ?>" <?= $trader_filter == $trader['USER_ID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($trader['FULL_NAME']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <select name="shop_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Shops</option>
                                    <?php while ($shop = oci_fetch_assoc($shops_stmt)): ?>
                                        <option value="<?= $shop['SHOP_ID'] ?>" <?= $shop_filter == $shop['SHOP_ID'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($shop['SHOP_NAME']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <select name="category" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Categories</option>
                                    <?php while ($category = oci_fetch_assoc($categories_stmt)): ?>
                                        <option value="<?= $category['PRODUCT_CATEGORY_NAME'] ?>" <?= $category_filter === $category['PRODUCT_CATEGORY_NAME'] ? 'selected' : '' ?>>
                                            <?= ucfirst(htmlspecialchars($category['PRODUCT_CATEGORY_NAME'])) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="card products-card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Product Name</th>
                                            <th>Category</th>
                                            <th>Description</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Status</th>
                                            <th>Shop</th>
                                            <th>Trader</th>
                                            <th>Added Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($product = oci_fetch_assoc($products_stmt)): ?>
                                            <tr>
                                                <td>#<?= $product['PRODUCT_ID'] ?></td>
                                                <td>
                                                    <?= htmlspecialchars($product['PRODUCT_NAME']) ?>
                                                    <?php if ($product['DISCOUNT_PERCENTAGE'] > 0): ?>
                                                        <span class="discount-badge ms-2">
                                                            -<?= $product['DISCOUNT_PERCENTAGE'] ?>%
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="category-badge">
                                                        <?= ucfirst(htmlspecialchars($product['PRODUCT_CATEGORY_NAME'])) ?>
                                                    </span>
                                                </td>
                                                <td class="product-description" title="<?= htmlspecialchars(readClob($product['DESCRIPTION'])) ?>">
                                                    <?= htmlspecialchars(readClob($product['DESCRIPTION'])) ?>
                                                </td>
                                                <td>
                                                    $<?= number_format($product['PRICE'], 2) ?>
                                                    <?php if ($product['DISCOUNT_PERCENTAGE'] > 0): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            $<?= number_format($product['PRICE'] * (1 - $product['DISCOUNT_PERCENTAGE']/100), 2) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $product['STOCK'] ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= strtolower($product['PRODUCT_STATUS']) ?>">
                                                        <?= ucfirst($product['PRODUCT_STATUS']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($product['SHOP_NAME']) ?></td>
                                                <td>
                                                    <div><?= htmlspecialchars($product['TRADER_NAME']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($product['TRADER_EMAIL']) ?></small>
                                                </td>
                                                <td><?= date('Y-m-d', strtotime($product['ADDED_DATE'])) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update shops dropdown when trader is selected
        document.querySelector('select[name="trader_id"]').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html> 