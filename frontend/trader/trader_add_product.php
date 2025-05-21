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
    $image_tmp_name = $_FILES['product_image']['tmp_name'];
    $image_size = $_FILES['product_image']['size'];
    $image_type = $_FILES['product_image']['type']; // e.g., image/jpeg

    if ($image_size > 2000000) {
        $warning_msg[] = 'Image size is too large!';
    } else {
        // Read image and convert to base64
        $image_data = file_get_contents($image_tmp_name);
        $base64_image = 'data:' . $image_type . ';base64,' . base64_encode($image_data);

        $add_date = date('Y-m-d');

        // Prepare the SQL insert statement
        $sql = "INSERT INTO product 
                (product_name, description, price, stock, min_order, max_order, product_image, add_date, product_status) 
                VALUES 
                (:product_name, :description, :price, :stock, :min_order, :max_order, :product_image, TO_DATE((SELECT SYSDATE FROM DUAL), 'YYYY-MM-DD'), :product_status)";

        $stid = oci_parse($conn, $sql);

        // Bind variables
        oci_bind_by_name($stid, ':product_name', $product_name);
        oci_bind_by_name($stid, ':description', $description);
        oci_bind_by_name($stid, ':price', $price);
        oci_bind_by_name($stid, ':stock', $stock);
        oci_bind_by_name($stid, ':min_order', $min_order);
        oci_bind_by_name($stid, ':max_order', $max_order);
        oci_bind_by_name($stid, ':product_image', $rename);
        // oci_bind_by_name($stid, ':add_date', $add_date);
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
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product</title>

    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <style>
        .submit-btn {
            background-color: rgb(254, 148, 74);
            color: white;
            border: none;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 15px;
            text-decoration: none;
            text-align: center;
            width: 100%;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .submit-btn:hover {
            background-color: grey;
            color: white;
            text-decoration: none;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type="file"] {
            position: absolute;
            font-size: 100px;
            opacity: 0;
            right: 0;
            top: 0;
            cursor: pointer;
        }

        .file-input-label {
            display: block;
            padding: 12px;
            background: #f9f9f9;
            border: 1px dashed #ddd;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-input-label:hover {
            border-color: #3498db;
            background: #f0f7fd;
        }

        #scan-status {
            text-align: center;
            margin: 10px 0;
            font-weight: 500;
            color: #28a745;
        }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }

        .rfid-btn {
            background-color: #28a745;
        }

        .rfid-btn:hover {
            background-color: #218838;
        }
    </style>
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
                                <label>Product Image <span>*</span></label>
                                <div class="file-input-wrapper">
                                    <label class="file-input-label">
                                        <i class="fas fa-cloud-upload-alt"></i> Choose an image file
                                        <input type="file" name="product_image" required accept="image/*">
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="submit-btn" name="add">Add Product</button>
                                <a href="trader_dashboard.php" class="submit-btn">Go to Dashboard</a>
                            </div>
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

    <script>
        // Modal focus management
        $('#addProductModal').on('shown.bs.modal', function () {
            $(this).removeAttr('aria-hidden');
            $('#product_name').focus();
        });

        $('#addProductModal').on('hidden.bs.modal', function () {
            $(this).attr('aria-hidden', 'true');
        });

        // RFID scanning functionality
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
    </script>

    <!-- Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" role="dialog" aria-labelledby="addProductModalLabel" aria-modal="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Your existing form content -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary">Save changes</button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>