document.getElementById("addProductForm").addEventListener("submit", function (event) {
    event.preventDefault();

    let shopName = document.getElementById("shop-name").value.trim();
    let productName = document.getElementById("product-name").value.trim();
    let productPrice = document.getElementById("product-price").value.trim();

    if (!shopName || !productName || !productPrice) {
        alert("Please fill in all fields.");
        return;
    }

    fetch("trader-dashboard.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `shop_name=${shopName}&product_name=${productName}&product_price=${productPrice}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            document.getElementById("addProductForm").reset();
        } else {
            alert("Failed to send product.");
        }
    })
    .catch(error => console.error("Error:", error));
});
