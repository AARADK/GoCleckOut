<?php include 'header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Your Cart</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody id="cart-table-body">
                <!-- Cart items will be loaded here -->
            </tbody>
        </table>
        <h3>Total: <span id="cart-total">$0.00</span></h3>
        <button class="btn btn-danger" id="clear-cart">Clear Cart</button>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            let cart = JSON.parse(localStorage.getItem("cart")) || [];
            let tableBody = document.getElementById("cart-table-body");
            let totalAmount = 0;

            if (cart.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="5" class="text-center">Your cart is empty.</td></tr>`;
            } else {
                cart.forEach(item => {
                    let total = parseFloat(item.price.replace("$", "")) * item.quantity;
                    totalAmount += total;

                    let row = `
                        <tr>
                            <td><img src="${item.image}" width="50" height="50"></td>
                            <td>${item.name}</td>
                            <td>${item.price}</td>
                            <td>${item.quantity}</td>
                            <td>$${total.toFixed(2)}</td>
                        </tr>
                    `;
                    tableBody.innerHTML += row;
                });
            }

            document.getElementById("cart-total").textContent = `$${totalAmount.toFixed(2)}`;

            document.getElementById("clear-cart").addEventListener("click", function () {
                localStorage.removeItem("cart");
                location.reload();
            });
        });
    </script>
</body>
</html>
