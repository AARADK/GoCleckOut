<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login_portal.php?sign_in=true");
    exit;
}

include '../../backend/database/connect.php';
$conn = getDBConnection();

$trader_filter = isset($_GET['trader_id']) ? $_GET['trader_id'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

$traders_sql = "
    SELECT u.user_id, u.full_name 
    FROM users u 
    WHERE u.role = 'trader' 
    ORDER BY u.full_name
";
$traders_stmt = oci_parse($conn, $traders_sql);
oci_execute($traders_stmt);

$categories_sql = "SELECT DISTINCT shop_category FROM shops ORDER BY shop_category";
$categories_stmt = oci_parse($conn, $categories_sql);
oci_execute($categories_stmt);

$shops_sql = "
    SELECT 
        s.shop_id,
        s.shop_name,
        s.shop_category,
        s.description,
        s.shop_email,
        s.shop_contact_no,
        u.full_name as trader_name,
        u.email as trader_email,
        pc.category_name as trader_category
    FROM shops s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN product_category pc ON u.category_id = pc.category_id
    WHERE 1=1
";

if ($trader_filter) {
    $shops_sql .= " AND s.user_id = :trader_filter";
}
if ($category_filter) {
    $shops_sql .= " AND s.shop_category = :category_filter";
}

$shops_stmt = oci_parse($conn, $shops_sql);

if ($trader_filter) {
    oci_bind_by_name($shops_stmt, ':trader_filter', $trader_filter);
}
if ($category_filter) {
    oci_bind_by_name($shops_stmt, ':category_filter', $category_filter);
}

oci_execute($shops_stmt);

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
    <title>Trader Shops - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .shops-card {
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
        .shop-description {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
                        <h2 class="h3 mb-0">Trader Shops</h2>
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
                                <select name="category" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Categories</option>
                                    <?php while ($category = oci_fetch_assoc($categories_stmt)): ?>
                                        <option value="<?= $category['SHOP_CATEGORY'] ?>" <?= $category_filter === $category['SHOP_CATEGORY'] ? 'selected' : '' ?>>
                                            <?= ucfirst(htmlspecialchars($category['SHOP_CATEGORY'])) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="card shops-card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Shop Name</th>
                                            <th>Category</th>
                                            <th>Description</th>
                                            <th>Contact</th>
                                            <th>Trader</th>
                                            <th>Trader Category</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($shop = oci_fetch_assoc($shops_stmt)): ?>
                                            <tr>
                                                <td>#<?= $shop['SHOP_ID'] ?></td>
                                                <td><?= htmlspecialchars($shop['SHOP_NAME']) ?></td>
                                                <td>
                                                    <span class="category-badge">
                                                        <?= ucfirst(htmlspecialchars($shop['SHOP_CATEGORY'])) ?>
                                                    </span>
                                                </td>
                                                <td class="shop-description" title="<?= htmlspecialchars(readClob($shop['DESCRIPTION'])) ?>">
                                                    <?= htmlspecialchars(readClob($shop['DESCRIPTION'])) ?>
                                                </td>
                                                <td>
                                                    <div><?= htmlspecialchars($shop['SHOP_EMAIL']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($shop['SHOP_CONTACT_NO']) ?></small>
                                                </td>
                                                <td>
                                                    <div><?= htmlspecialchars($shop['TRADER_NAME']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($shop['TRADER_EMAIL']) ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($shop['TRADER_CATEGORY']) ?></td>
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
</body>
</html> 