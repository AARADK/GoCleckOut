<?php
require '../../backend/database/connect.php';

include "timeslot_date.php";

$timeslots = getTimeslots(10);

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

session_start();
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = 3;

    echo ("User not logged in.");
}


// Fetch cart items
$cart_items = [];
$sql = "SELECT c.cart_id, p.product_id, p.product_name, p.product_image, p.price, p.stock, pc.quantity
        FROM cart c
        JOIN cart_product pc ON c.cart_id = pc.cart_id
        JOIN product p ON pc.product_id = p.product_id
        WHERE c.user_id = :user_id";

$stid = oci_parse($conn, $sql);
oci_bind_by_name($stid, ":user_id", $user_id);

if (oci_execute($stid)) {
    while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
        // If product_image is a LOB
        if (is_object($row['PRODUCT_IMAGE']) && $row['PRODUCT_IMAGE'] instanceof OCILob) {
            $row['PRODUCT_IMAGE'] = $row['PRODUCT_IMAGE']->load();
        }
        $cart_items[] = $row;
    }
} else {
    $e = oci_error($stid);
    die("Error fetching cart: " . $e['message']);
}

oci_free_statement($stid);
$total_price = 0;
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
        }

        .shopping-cart {
            max-width: 1100px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .heading {
            text-align: center;
            font-size: 32px;
            color: #333;
            margin-bottom: 30px;
        }

        .box-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .box {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.2s;
        }

        .box:hover {
            transform: translateY(-5px);
        }

        .box .image {
            max-width: 100%;
            height: 180px;
            object-fit: contain;
            margin-bottom: 10px;
        }

        .name {
            font-size: 20px;
            margin: 10px 0;
            color: #333;
        }

        .price {
            font-size: 18px;
            color: #555;
            margin: 10px 0;
        }

        .flex {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 15px 0;
            gap: 10px;
        }

        .qty {
            width: 50px;
            text-align: center;
            font-size: 16px;
            padding: 5px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        .qty-btn {
            padding: 6px 12px;
            font-size: 16px;
            background-color: #f4a259;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .qty-btn:hover {
            background-color: #e08e3c;
        }

        .delete-btn {
            margin-top: 10px;
            background-color: #f44336;
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .delete-btn:hover {
            background-color: #d32f2f;
        }

        .total-price {
            text-align: right;
            font-size: 20px;
            margin-top: 30px;
            color: #222;
            font-weight: bold;
        }

        .checkout {
            text-align: center;
            margin-top: 20px;
        }

        .btn {
            background-color: #f4a259;
            color: white;
            font-weight: bold;
            padding: 12px 28px;
            border-radius: 10px;
            font-size: 18px;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #e08e3c;
        }

        .empty {
            text-align: center;
            font-size: 20px;
            color: #777;
        }
    </style>

</head>

<body>

    <section class="shopping-cart">
        <h1 class="heading">Your Cart</h1>
        <div class="box-container">

            <?php foreach ($cart_items as $item):
                $subtotal = $item['PRICE'] * $item['QUANTITY'];
                $total_price += $subtotal;
            ?>
                <div class="box">
                    <img src="../BackEnd/admin/uploaded_files/<?= htmlspecialchars($item['PRODUCT_IMAGE']) ?>" class="image" alt="">
                    <h3 class="name"><?= htmlspecialchars($item['PRODUCT_NAME']) ?></h3>
                    <p class="price">RS. <span class="item-price" data-price="<?= $item['PRICE'] ?>"><?= $subtotal ?></span></p>
                    <div class="flex">
                        <button type="button" class="qty-btn" onclick="updateQuantity(<?= $item['PRODUCT_ID']; ?>, -1, this)">-</button>
                        <input type="number" name="qty" readonly min="1" max="<?= $item['STOCK'] ?>" value="<?= $item['QUANTITY'] ?>" class="qty">
                        <button type="button" class="qty-btn" onclick="updateQuantity(<?= $item['PRODUCT_ID']; ?>, 1, this)">+</button>
                    </div>
                    <button type="button" class="delete-btn" onclick="removeItem(<?= $item['PRODUCT_ID']; ?>, this)">Remove</button>
                </div>
            <?php endforeach; ?>

        </div>

        <div class="total-price">
            <h3>Total Price: RS. <span id="total-price"><?= $total_price ?></span></h3>
        </div>

        <div class="checkout">
            <a href="checkout.php" class="btn">Proceed to Checkout</a>
        </div>

    </section>

    <div class="mt-4">
        Timeslots:<br>
        <select name="timeslot">
            <?php foreach ($timeslots as $ts): ?>
                <option value="<?= htmlspecialchars($ts['timestamp']) ?>">
                    <?= htmlspecialchars($ts['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <script>
        function updateQuantity(productId, change, button) {
            let qtyInput = button.parentElement.querySelector('.qty');
            let newQty = parseInt(qtyInput.value) + change;
            if (newQty < 1) return;
            qtyInput.value = newQty;

            let priceElement = button.parentElement.parentElement.querySelector('.item-price');
            let pricePerItem = parseFloat(priceElement.getAttribute('data-price'));
            priceElement.textContent = (newQty * pricePerItem).toFixed(2);

            updateTotalPrice();

            let formData = new FormData();
            formData.append("product_id", productId);
            formData.append("qty", newQty);

            fetch("update_quantity.php", {
                method: "POST",
                body: formData
            });
        }

        function updateTotalPrice() {
            let total = 0;
            document.querySelectorAll('.item-price').forEach(price => {
                total += parseFloat(price.textContent);
            });
            document.getElementById('total-price').textContent = total.toFixed(2);
        }

        function removeItem(productId, button) {
            if (confirm("Are you sure you want to remove this item?")) {
                let formData = new FormData();
                formData.append("product_id", productId);

                fetch("remove_item.php", {
                        method: "POST",
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        if (data.trim() === "success") {
                            let itemBox = button.closest(".box");
                            itemBox.remove();
                            updateTotalPrice();
                        } else {
                            alert("Error removing item. Please try again.");
                        }
                    })
                    .catch(error => console.error("Error:", error));
            }
        }
    </script>

</body>

</html>