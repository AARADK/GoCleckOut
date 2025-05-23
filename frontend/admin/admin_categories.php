<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

include '../../backend/database/connect.php';
$conn = getDBConnection();

// Handle form submission for adding new category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    if (!empty($category_name)) {
        $insert_sql = "INSERT INTO product_category (category_name) VALUES (:category_name)";
        $stmt = oci_parse($conn, $insert_sql);
        oci_bind_by_name($stmt, ':category_name', $category_name);
        oci_execute($stmt);
    }
}

// Get all categories
$categories_sql = "SELECT * FROM product_category ORDER BY category_name";
$categories_stmt = oci_parse($conn, $categories_sql);
oci_execute($categories_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .categories-card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .category-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            background-color: #e9ecef;
            color: #495057;
            display: inline-block;
            margin: 0.25rem;
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
                        <h2 class="h3 mb-0">Product Categories</h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus"></i> Add Category
                        </button>
                    </div>

                    <div class="card categories-card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Category ID</th>
                                            <th>Category Name</th>
                                            <th>Products Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($category = oci_fetch_assoc($categories_stmt)): ?>
                                            <tr>
                                                <td>#<?= $category['CATEGORY_ID'] ?></td>
                                                <td>
                                                    <span class="category-badge">
                                                        <?= htmlspecialchars($category['CATEGORY_NAME']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Get count of products in this category
                                                    $count_sql = "SELECT COUNT(*) as count FROM product WHERE product_category_name = :category_name";
                                                    $count_stmt = oci_parse($conn, $count_sql);
                                                    oci_bind_by_name($count_stmt, ':category_name', $category['CATEGORY_NAME']);
                                                    oci_execute($count_stmt);
                                                    $count = oci_fetch_assoc($count_stmt)['COUNT'];
                                                    echo $count;
                                                    ?>
                                                </td>
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

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="category_name" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="category_name" name="category_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 