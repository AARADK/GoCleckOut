<?php
require '../../backend/database/db_connection.php';

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

if (isset($_POST['add'])) {
    $product_name = $_POST['product_name'];
    $product_name = filter_var($product_name, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $description = $_POST['description'];
    $description = filter_var($description, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $price = $_POST['price'];
    $price = filter_var($price, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $stock = $_POST['stock'];
    $stock = filter_var($stock, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $min_order = $_POST['min_order'];
    $min_order = filter_var($min_order, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $max_order = $_POST['max_order'];
    $max_order = filter_var($max_order, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $product_status = $_POST['product_status'];
    $product_status = filter_var($product_status, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $image = $_FILES['product_image']['name'];
    $image = filter_var($image, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $ext = pathinfo($image, PATHINFO_EXTENSION);
    $rename = create_unique_id() . '.' . $ext;
    $image_tmp_name = $_FILES['product_image']['tmp_name'];
    $image_size = $_FILES['product_image']['size'];
    $image_folder = 'uploaded_files/' . $rename;

    if ($image_size > 2000000) {
        $warning_msg[] = 'Image size is too large!';
    } else {
        $add_product = $conn->prepare("INSERT INTO `product`(product_name, description, price, stock, min_order, max_order, product_image, add_date, product_status) VALUES(?,?,?,?,?,?,?,?,?)");
        $add_date = date('Y-m-d');

        $add_product->execute([$product_name, $description, $price, $stock, $min_order, $max_order, $rename, $add_date, $product_status]);

        move_uploaded_file($image_tmp_name, $image_folder);
        $success_msg[] = 'Product added!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product</title>

    <!-- Bootstrap 4 CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet">

    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">

    <!-- Custom Styles -->
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header text-center">
                        <h3>Product Info</h3>
                    </div>
                    <div class="card-body">

                        <!-- Form Start -->
                        <form action="" method="POST" enctype="multipart/form-data">

                            <!-- Product Name -->
                            <div class="form-group">
                                <label for="product_name">Product Name <span class="text-danger">*</span></label>
                                <input type="text" id="product_name" name="product_name" class="form-control" placeholder="Enter product name" required maxlength="100">
                            </div>

                            <!-- Description -->
                            <div class="form-group">
                                <label for="description">Description <span class="text-danger">*</span></label>
                                <textarea id="description" name="description" class="form-control" placeholder="Enter product description" required></textarea>
                            </div>

                            <!-- Price -->
                            <div class="form-group">
                                <label for="price">Price <span class="text-danger">*</span></label>
                                <input type="number" id="price" name="price" class="form-control" placeholder="Enter product price" required min="0">
                            </div>

                            <!-- Stock Quantity -->
                            <div class="form-group">
                                <label for="stock">Stock Quantity <span class="text-danger">*</span></label>
                                <input type="number" id="stock" name="stock" class="form-control" placeholder="Enter stock quantity" required min="0">
                            </div>

                            <!-- Minimum Order -->
                            <div class="form-group">
                                <label for="min_order">Minimum Order <span class="text-danger">*</span></label>
                                <input type="number" id="min_order" name="min_order" class="form-control" placeholder="Enter minimum order quantity" required min="1">
                            </div>

                            <!-- Maximum Order -->
                            <div class="form-group">
                                <label for="max_order">Maximum Order <span class="text-danger">*</span></label>
                                <input type="number" id="max_order" name="max_order" class="form-control" placeholder="Enter maximum order quantity" required min="1">
                            </div>

                            <!-- Product Status -->
                            <div class="form-group">
                                <label for="product_status">Product Status <span class="text-danger">*</span></label>
                                <select id="product_status" name="product_status" class="form-control" required>
                                    <option value="In Stock">In Stock</option>
                                    <option value="Out of Stock">Out of Stock</option>
                                </select>
                            </div>

                            <!-- Product Image -->
                            <div class="form-group">
                                <label for="product_image">Product Image <span class="text-danger">*</span></label>
                                <input type="file" id="product_image" name="product_image" class="form-control-file" required accept="image/*">
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="btn bg-dark text-white" name="add">Add Product</button>

                        </form>
                        <!-- Form End -->

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS & Dependencies -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>

</body>

</html>