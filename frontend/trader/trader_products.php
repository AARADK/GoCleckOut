<?php
require_once '../../backend/database/db_connection.php';

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

if (isset($_POST['add'])) {
    // Sanitize inputs
    $product_name = filter_var($_POST['product_name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $description = filter_var($_POST['description'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $price = filter_var($_POST['price'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $stock = filter_var($_POST['stock'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $min_order = filter_var($_POST['min_order'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $max_order = filter_var($_POST['max_order'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $product_status = filter_var($_POST['product_status'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Image handling
    $image = filter_var($_FILES['product_image']['name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $ext = pathinfo($image, PATHINFO_EXTENSION);
    $rename = create_unique_id() . '.' . $ext;
    $image_tmp_name = $_FILES['product_image']['tmp_name'];
    $image_size = $_FILES['product_image']['size'];
    $image_folder = 'uploaded_files/' . $rename;

    if ($image_size > 2000000) {
        $warning_msg[] = 'Image size is too large!';
    } else {
        $add_date = date('Y-m-d');

        // Prepare the SQL insert statement
        $sql = "INSERT INTO product 
                (product_name, description, price, stock, min_order, max_order, product_image, add_date, product_status) 
                VALUES 
                (:product_name, :description, :price, :stock, :min_order, :max_order, :product_image, TO_DATE(:add_date, 'YYYY-MM-DD'), :product_status)";

        $stid = oci_parse($conn, $sql);

        // Bind variables
        oci_bind_by_name($stid, ':product_name', $product_name);
        oci_bind_by_name($stid, ':description', $description);
        oci_bind_by_name($stid, ':price', $price);
        oci_bind_by_name($stid, ':stock', $stock);
        oci_bind_by_name($stid, ':min_order', $min_order);
        oci_bind_by_name($stid, ':max_order', $max_order);
        oci_bind_by_name($stid, ':product_image', $rename);
        oci_bind_by_name($stid, ':add_date', $add_date);
        oci_bind_by_name($stid, ':product_status', $product_status);

        // Execute the statement
        $exec = oci_execute($stid);

        if ($exec) {
            move_uploaded_file($image_tmp_name, $image_folder);
            $success_msg[] = 'Product added!';
        } else {
            $e = oci_error($stid);
            $warning_msg[] = 'Failed to add product: ' . $e['message'];
        }

        oci_free_statement($stid);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Products | Cocleckout</title>
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
  </style>
</head>

<body>
    <button
        class="btn btn-pink d-md-none position-fixed top-0 start-0 m-3 z-3"
        id="menuToggle">
        <span class="material-icons">menu</span>
    </button>

    <div class="d-flex min-vh-100">

        <!-- Main Content -->
        <main class="flex-grow-1 p-3">
            <!-- Topbar -->
            <header
                class="bg-white rounded-3 shadow-sm d-flex flex-wrap align-items-center justify-content-between p-3 mb-3">
                <h1 class="h4 mb-2 mb-md-0">Products</h1>

                <div class="position-relative flex-grow-1 mx-3" style="max-width: 400px;">
                    <span
                        class="material-icons position-absolute top-50 start-2 translate-middle-y text-muted">search</span>
                    <input
                        type="text"
                        class="form-control ps-5"
                        placeholder="Search..."
                        aria-label="Search" />
                </div>

                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-pink fw-bold" onclick="location.href='index.php?page=add_product'">+ Add Product</button>
                </div>
            </header>

            <section class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <button type="button" class="btn btn-outline-secondary">All Products</button>

                <select class="form-select form-select-sm w-auto">
                    <option selected>Sort by</option>
                    <option value="price-asc">Price: Low to High</option>
                    <option value="price-desc">Price: High to Low</option>
                </select>

                <select class="form-select form-select-sm w-auto">
                    <option value="10" selected>10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                </select>

                <button type="button" class="btn btn-link text-pink fw-semibold">Shop 1</button>
                <button type="button" class="btn btn-link text-pink fw-semibold">Shop 2</button>

                <div class="dropdown ms-auto">
                    <button
                        class="btn btn-pink fw-bold dropdown-toggle d-flex align-items-center gap-1"
                        type="button"
                        id="actionsDropdown"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                        Actions <span class="material-icons">arrow_drop_down</span>
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="actionsDropdown">
                        <li>
                            <button class="dropdown-item d-flex align-items-center gap-2" type="button">
                                <span class="material-icons">add</span> Add Product
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item d-flex align-items-center gap-2" type="button">
                                <span class="material-icons">edit</span> Update Product
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item d-flex align-items-center gap-2" type="button">
                                <span class="material-icons">share</span> Share
                            </button>
                        </li>
                        <li>
                            <hr class="dropdown-divider" />
                        </li>
                        <li>
                            <button class="dropdown-item d-flex align-items-center gap-2" type="button">
                                <span class="material-icons">delete</span> Delete Product
                            </button>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Main Section -->
            <section class="d-flex flex-column flex-lg-row gap-4">
                <!-- Product Grid -->
                <div class="flex-grow-1">
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                        <!-- Example Product Card -->
                        <div class="col">
                            <div onclick="location.href='index.php?page=product_detail'" class="card h-100 shadow-sm rounded-3">
                                <img
                                    src="https://via.placeholder.com/300x180"
                                    class="card-img-top object-fit-cover"
                                    alt="Product Image"
                                    style="height: 180px;" />
                                <div class="card-body text-center d-flex flex-column justify-content-between">
                                    <h5 class="card-title mb-1">Product Name</h5>
                                    <p class="card-text text-muted mb-2">Short description goes here.</p>
                                    <div>
                                        <span class="fw-bold text-dark fs-6">$19.99</span>
                                        <span class="text-decoration-line-through text-muted ms-2">$24.99</span>
                                    </div>
                                    <div>
                                        <span class="badge bg-success">In Stock</span>
                                        <!-- Use bg-danger and text-danger for out of stock -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <nav class="mt-4 d-flex justify-content-center" aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item"><a class="page-link btn-pink text-white" href="#">1</a></li>
                            <li class="page-item"><a class="page-link btn-pink text-white" href="#">2</a></li>
                            <li class="page-item"><a class="page-link btn-pink text-white" href="#">3</a></li>
                        </ul>
                    </nav>
                </div>

                <!-- Filter Panel -->
                <aside
                    class="bg-white rounded-3 shadow-sm p-3 flex-shrink-0"
                    style="width: 240px;">
                    <div class="mb-3">
                        <input
                            type="text"
                            class="form-control"
                            placeholder="Keyword search..."
                            aria-label="Keyword search" />
                    </div>

                    <h5 class="text-pink mb-3">Filter Products</h5>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="filterBread" />
                        <label class="form-check-label" for="filterBread">Bread</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="filterMilk" />
                        <label class="form-check-label" for="filterMilk">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="filterMilk" />
                                <label class="form-check-label" for="filterMilk">Milk</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="filterEggs" />
                                <label class="form-check-label" for="filterEggs">Eggs</label>
                            </div>

                            <div class="mt-4">
                                <h6 class="text-pink">Price Range</h6>
                                <select class="form-select form-select-sm" aria-label="Price range select">
                                    <option selected>Choose a price range</option>
                                    <option value="0-10">$0 - $10</option>
                                    <option value="10-20">$10 - $20</option>
                                    <option value="20-50">$20 - $50</option>
                                    <option value="50-100">$50 - $100</option>
                                </select>
                            </div>

                            <div class="mt-4">
                                <h6 class="text-pink">Availability</h6>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="availability" id="inStock" checked />
                                    <label class="form-check-label" for="inStock">In Stock</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="availability" id="outOfStock" />
                                    <label class="form-check-label" for="outOfStock">Out of Stock</label>
                                </div>
                            </div>

                            <button type="button" class="btn btn-pink fw-bold mt-4 w-100">
                                Apply Filters
                            </button>
                </aside>
            </section>
        </main>
    </div>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>