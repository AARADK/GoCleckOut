<?php
require_once '../../backend/database/db_connection.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

// Get trader's shops
$shops_sql = "SELECT shop_id, shop_name FROM shops WHERE user_id = :user_id ORDER BY shop_name";
$shops_stmt = oci_parse($conn, $shops_sql);
oci_bind_by_name($shops_stmt, ":user_id", $user_id);
oci_execute($shops_stmt);

$shops = ['shop_id' => 1, 'shop_name' => 'Shop 1'];
while ($shop = oci_fetch_array($shops_stmt, OCI_ASSOC)) {
    $shops[] = $shop;
}
oci_free_statement($shops_stmt);

if (empty($shops)) {
    $_SESSION['toast_message'] = "❌ You need to create a shop first before adding products.";
    $_SESSION['toast_type'] = "error";
    header('Location: trader_shops.php');
    exit();
}

if (isset($_POST['add'])) {
    // Sanitize inputs
    $product_name = filter_var($_POST['product_name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $description = filter_var($_POST['description'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $price = filter_var($_POST['price'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $stock = filter_var($_POST['stock'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $shop_id = filter_var($_POST['shop_id'], FILTER_SANITIZE_NUMBER_INT);
    $rfid = filter_var($_POST['rfid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Validate shop_id belongs to the trader
    $valid_shop = false;
    foreach ($shops as $shop) {
        if ($shop['SHOP_ID'] == $shop_id) {
            $valid_shop = true;
            break;
        }
    }

    if (!$valid_shop) {
        $warning_msg[] = 'Invalid shop selected!';
    } else {
        // Image handling
        $image_tmp_name = $_FILES['product_image']['tmp_name'];
        $image_size = $_FILES['product_image']['size'];
        $image_type = $_FILES['product_image']['type'];

        if ($image_size > PHP_MAX_INT_SIZE) {
            $warning_msg[] = 'Image size is too large!';
        } else {
            // Read image and convert to base64
            $image_data = file_get_contents($image_tmp_name);
            $base64_image = 'data:' . $image_type . ';base64,' . base64_encode($image_data);

            // Get shop category for product_category_name
            $shop_category_sql = "SELECT shop_category FROM shops WHERE shop_id = :shop_id";
            $shop_category_stmt = oci_parse($conn, $shop_category_sql);
            oci_bind_by_name($shop_category_stmt, ":shop_id", $shop_id);
            oci_execute($shop_category_stmt);
            $shop_data = oci_fetch_assoc($shop_category_stmt);
            $product_category = $shop_data['SHOP_CATEGORY'];

            // Prepare the SQL insert statement
            $sql = "INSERT INTO product (
                product_name, description, price, stock,
                product_image, added_date, updated_date, product_status,
                discount_percentage, shop_id, user_id, product_category_name, rfid
            ) VALUES (
                :product_name, :description, :price, :stock,
                :product_image, SYSDATE, SYSDATE, 'active', 0,
                :shop_id, :user_id, :category, :rfid
            )";

            $stid = oci_parse($conn, $sql);

            // Bind variables
            oci_bind_by_name($stid, ':product_name', $product_name);
            oci_bind_by_name($stid, ':description', $description);
            oci_bind_by_name($stid, ':price', $price);
            oci_bind_by_name($stid, ':stock', $stock);
            oci_bind_by_name($stid, ':product_image', $base64_image);
            oci_bind_by_name($stid, ':shop_id', $shop_id);
            oci_bind_by_name($stid, ':user_id', $user_id);
            oci_bind_by_name($stid, ':category', $product_category);
            oci_bind_by_name($stid, ':rfid', $rfid);

            // Execute the statement
            $exec = oci_execute($stid);

            if ($exec) {
                $_SESSION['toast_message'] = "✅ Product added successfully!";
                $_SESSION['toast_type'] = "success";
                header('Location: trader_products.php?shop_id=' . $shop_id);
                exit();
            } else {
                $e = oci_error($stid);
                $warning_msg[] = 'Failed to add product: ' . $e['message'];
            }

            oci_free_statement($stid);
        }
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

                            <!-- Shop Selection -->
                            <div class="form-group">
                                <label for="shop_id">Select Shop <span class="text-danger">*</span></label>
                                <select id="shop_id" name="shop_id" class="form-control" required>
                                    <option value="">Select a shop</option>
                                    <?php foreach ($shops as $shop): ?>
                                        <option value="<?= htmlspecialchars($shop['SHOP_ID']) ?>">
                                            <?= htmlspecialchars($shop['SHOP_NAME']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Product Name and RFID -->
                            <div class="row">
                                <div class="col-md-9">
                                    <div class="form-group">
                                        <label for="product_name">Product Name <span class="text-danger">*</span></label>
                                        <input type="text" id="product_name" name="product_name" class="form-control" placeholder="Enter product name" required maxlength="100">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="rfid">RFID</label>
                                        <div class="input-group">
                                            <input type="text" id="rfid" name="rfid" class="form-control" maxlength="8" pattern="[0-9]{8}" title="RFID must be exactly 8 digits">
                                            <button type="button" id="scanRfid" class="btn btn-secondary">
                                                <i class="fas fa-barcode"></i> Scan
                                            </button>
                                        </div>
                                    </div>
                                </div>
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
                    const response = await fetch('trigger_rfid.php', {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
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
                // Don't stop scanning on error, just log it
            });
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