<?php
session_start();
require "../../../Backend/connect.php";
include_once "sidebar.php";

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

$warning_msg = [];
$success_msg = [];

$shops = [];
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $shop_query = oci_parse($conn, "SELECT shop_id, shop_name, shop_category FROM shops WHERE user_id = :user_id");
    oci_bind_by_name($shop_query, ":user_id", $user_id);
    oci_execute($shop_query);

    while ($row = oci_fetch_assoc($shop_query)) {
        $shops[] = [
            'shop_id' => $row['SHOP_ID'],
            'shop_name' => $row['SHOP_NAME'],
            'category' => $row['SHOP_CATEGORY']
        ];
    }
}

if (isset($_POST['add'])) {
    if (!isset($_SESSION['user_id'])) {
        $warning_msg[] = 'User is not logged in.';
    } else {
        $product_name = filter_var($_POST['product_name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $description = filter_var($_POST['description'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $price = filter_var($_POST['price'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $discount = isset($_POST['discount']) && $_POST['discount'] !== '' ? filter_var($_POST['discount'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : null;
        $stock = filter_var($_POST['stock'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $min_order = filter_var($_POST['min_order'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $max_order = filter_var($_POST['max_order'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $product_status = filter_var($_POST['product_status'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $shop_id = $_POST['shop_id'];
        $product_category_name = '';
        foreach ($shops as $shop) {
            if ($shop['shop_id'] == $shop_id) {
                $product_category_name = ucwords(strtolower($shop['category']));
                break;
            }
        }

        $user_id = $_SESSION['user_id'];
        $image = $_FILES['product_image']['name'];
        $image = filter_var($image, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $ext = pathinfo($image, PATHINFO_EXTENSION);
        $rename = uniqid('product_') . '.' . $ext;
        $image_tmp_name = $_FILES['product_image']['tmp_name'];
        $image_size = $_FILES['product_image']['size'];
        $image_folder = 'uploaded_files/' . $rename;

        if ($image_size > 2000000) {
            $warning_msg[] = 'Image size is too large!';
        } else {
            $sql = "INSERT INTO product (
                        product_name, description, price, stock, min_order, max_order,
                        product_image, add_date, product_status,
                        shop_id, user_id, product_category_name
                    ) VALUES (
                        :product_name, :description, :price, :stock, :min_order, :max_order,
                        :product_image, TO_DATE(:add_date, 'YYYY-MM-DD'), :product_status,
                        :shop_id, :user_id, :product_category_name
                    )";

            $stmt = oci_parse($conn, $sql);
            $add_date = date('Y-m-d');

            oci_bind_by_name($stmt, ':product_name', $product_name);
            oci_bind_by_name($stmt, ':description', $description);
            oci_bind_by_name($stmt, ':price', $price);
            oci_bind_by_name($stmt, ':stock', $stock);
            oci_bind_by_name($stmt, ':min_order', $min_order);
            oci_bind_by_name($stmt, ':max_order', $max_order);
            oci_bind_by_name($stmt, ':product_image', $rename);
            oci_bind_by_name($stmt, ':add_date', $add_date);
            oci_bind_by_name($stmt, ':product_status', $product_status);
            oci_bind_by_name($stmt, ':shop_id', $shop_id);
            oci_bind_by_name($stmt, ':user_id', $user_id);
            oci_bind_by_name($stmt, ':product_category_name', $product_category_name);

            $result = oci_execute($stmt);
            if ($result) {
                move_uploaded_file($image_tmp_name, $image_folder);

                // Get the last inserted product_id using RETURNING
                $get_id_sql = "SELECT product_seq.CURRVAL FROM dual";
                $get_id_stmt = oci_parse($conn, $get_id_sql);
                oci_execute($get_id_stmt);
                $product_id_row = oci_fetch_array($get_id_stmt, OCI_ASSOC);
                $product_id = $product_id_row['CURRVAL'];
                oci_free_statement($get_id_stmt);

                // Clear the RFID scan JSON file
                $rfid_data_path = 'rfid_scan.json'; // Adjust path if needed
                file_put_contents($rfid_data_path, json_encode(["data" => null, "scanned_at" => null]));

                // Insert into discount table if discount was provided
                if ($discount !== null && is_numeric($discount)) {
                    $discount_sql = "INSERT INTO discount (discount_percentage, product_id) VALUES (:discount_percentage, :product_id)";
                    $discount_stmt = oci_parse($conn, $discount_sql);
                    oci_bind_by_name($discount_stmt, ':discount_percentage', $discount);
                    oci_bind_by_name($discount_stmt, ':product_id', $product_id);
                    oci_execute($discount_stmt);
                    oci_free_statement($discount_stmt);
                }

                $success_msg[] = 'Product added!';
            } else {
                $e = oci_error($stmt);
                $warning_msg[] = 'Database error: ' . $e['message'];
            }
            oci_free_statement($stmt);
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Product</title>
    <link rel="stylesheet" href="../css/sidebar.css">
    <style>
        /* Main Form Container */
        .form-container {
            max-width: 1000px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin-left: 365px;
            padding: 20px;
            width: calc(100% - 250px);
            box-sizing: border-box;
        }

        /* Form Header */
        .form-header {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
        }

        .form-header h3 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        /* Form Grid Layout */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        /* Form Group Styling */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group label span {
            color: #e74c3c;
        }

        /* Input Fields */
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s;
            background-color: #f9f9f9;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            background-color: #fff;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        /* File Input Customization */
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

        /* Submit Button */
        .submit-btn {
            grid-column: span 2;
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
        }

        .submit-btn:hover {
            background-color: grey;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .submit-btn {
                grid-column: span 1;
            }
        }

        /* Status Messages */
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }

        .success-msg {
            background-color: #d4edda;
            color: #155724;
        }

        .warning-msg {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
</head>

<body>
    <div class="form-container">
        <div class="form-header">
            <h3>Product Information</h3>
            <p>Add new product to your inventory</p>
        </div>

        <?php if (!empty($warning_msg)): ?>
            <div class="message warning-msg">
                <?php echo $warning_msg[0]; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="message success-msg">
                <?php echo $success_msg[0]; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div>
                    <div class="form-group">
                        <label>Product Name <span>*</span></label>
                        <input type="text" name="product_name" class="form-control" required maxlength="50">
                    </div>

                    <div class="form-group">
                        <label>Description <span>*</span></label>
                        <textarea name="description" class="form-control" required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Price <span>*</span></label>
                        <input type="number" name="price" class="form-control" required min="0" step="0.01">
                    </div>

                    <div class="form-group">
                        <label>Discount (%)</label>
                        <input type="number" name="discount" class="form-control" min="0" max="100" step="0.01">
                    </div>
                </div>

                <div>
                    <div class="form-group">
                        <label>Stock Quantity <span>*</span></label>
                        <input type="number" name="stock" class="form-control" required min="0">
                    </div>

                    <div class="form-group">
                        <label>Minimum Order <span>*</span></label>
                        <input type="number" name="min_order" class="form-control" required min="1">
                    </div>

                    <div class="form-group">
                        <label>Maximum Order <span>*</span></label>
                        <input type="number" name="max_order" class="form-control" required min="1">
                    </div>

                    <div class="form-group">
                        <label>Product Status <span>*</span></label>
                        <select name="product_status" class="form-control" required>
                            <option value="In Stock">In Stock</option>
                            <option value="Out of Stock">Out of Stock</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Shop <span>*</span></label>
                        <select name="shop_id" id="shopSelect" class="form-control" required onchange="updateCategory()">
                            <option value="">Select Shop</option>
                            <?php foreach ($shops as $shop): ?>
                                <option value="<?= htmlspecialchars($shop['shop_id']) ?>" data-category="<?= htmlspecialchars($shop['category']) ?>">
                                    <?= htmlspecialchars($shop['shop_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Product Image <span>*</span></label>
                        <div class="file-input-wrapper">
                            <label class="file-input-label">
                                <i class="fas fa-cloud-upload-alt"></i> Choose an image file
                                <input type="file" name="product_image" required accept="image/*">
                            </label>
                        </div>
                    </div>
                </div>
                
                <button type="button" class="submit-btn" id="enable-rfid">🔄 Scan via RFID</button>
                <p id="scan-status" style="color: green; display: none;">Scanning RFID...</p>

                <button type="submit" class="submit-btn" name="add">Add Product</button>
                <a href="trader_dashboard.php" class="submit-btn">Go to Dashboard</a>
            </div>
        </form>
    </div>

    <script>
        
document.querySelectorAll(".delete").forEach(button => {
    button.addEventListener("click", function () {
        const productId = this.dataset.id;
        if (confirm("Are you sure you want to delete this product?")) {
            fetch('delete_product.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `id=${productId}`
            })
            .then(res => res.text())
            .then(data => {
                console.log("Server response:", data); // Debug: Log the actual response
                
                if (data.trim() === "success") {
                    this.closest('.card').remove();
                    alert("Product deleted successfully.");
                } else if (data.trim().startsWith("error:")) {
                    alert("Error: " + data.trim().substring(6));
                } else if (data.trim() === "unauthorized") {
                    alert("You are not authorized to delete this product.");
                } else {
                    alert("Failed to delete product. Please try again.");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("An error occurred while deleting the product. Please try again.");
            });
        }
    });
});

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
</body>

</html>