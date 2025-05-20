<?php
$productsData = [
    "butchers" => [
        ["name" => "Steak", "image" => "assets/steak.jpg", "price" => "$12.99", "inStock" => 20],
        ["name" => "Ground Beef", "image" => "assets/beef.jpg", "price" => "$8.99", "inStock" => 20],
        ["name" => "Lamb Chops", "image" => "assets/lamb.jpg", "price" => "$14.99", "inStock" => 20],
        ["name" => "Chicken Breast", "image" => "assets/chicken.jpg", "price" => "$6.99", "inStock" => 20],
        ["name" => "Pork Ribs", "image" => "assets/ribs.jpg", "price" => "$11.99", "inStock" => 20],
        ["name" => "Turkey", "image" => "assets/turkey.jpg", "price" => "$19.99", "inStock" => 20]
    ],
    "fishmongers" => [
        ["name" => "Salmon", "image" => "assets/salmon.jpg", "price" => "$10.99", "inStock" => 20],
        ["name" => "Tuna", "image" => "assets/tuna.jpg", "price" => "$9.99", "inStock" => 20],
        ["name" => "Shrimp", "image" => "assets/shrimp.jpg", "price" => "$15.99", "inStock" => 20],
        ["name" => "Lobster", "image" => "assets/lobster.jpg", "price" => "$22.99", "inStock" => 20],
        ["name" => "Crab Legs", "image" => "assets/crab.jpg", "price" => "$18.99", "inStock" => 20],
        ["name" => "Cod Fillet", "image" => "assets/cod.jpg", "price" => "$8.99", "inStock" => 20]
    ],
    "delicatessen" => [
        ["name" => "Salami", "image" => "assets/salami.jpg", "price" => "$7.99", "inStock" => 20],
        ["name" => "Prosciutto", "image" => "assets/prosciutto.jpg", "price" => "$9.99", "inStock" => 20],
        ["name" => "Roast Beef", "image" => "assets/roastbeef.jpg", "price" => "$8.99", "inStock" => 20],
        ["name" => "Smoked Turkey", "image" => "assets/smokedturkey.jpg", "price" => "$10.99", "inStock" => 20],
        ["name" => "Pastrami", "image" => "assets/pastrami.jpg", "price" => "$7.49", "inStock" => 20],
        ["name" => "Mortadella", "image" => "assets/mortadella.jpg", "price" => "$6.99", "inStock" => 20]
    ]
];

$category = $_GET['category'] ?? 'all';
$selectedProducts = ($category === "all") ? array_merge(...array_values($productsData)) : $productsData[$category];
$topSelling = array_slice($selectedProducts, 0, 3);
?>

<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .products {
            flex: 1;
            padding: 20px;
            width: 100%;
            overflow: hidden;
        }
        
        .btn-explore {
            background-color: #ff6b6b;
            color: white;
            padding: 12px 25px;
            font-size: 16px;
            border-radius: 10px;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        .btn-explore:hover {
            background-color: #e55a5a;
        }
    </style>
</head>

<body>
    <div class="container">
        <section class="py-5 bg-white">
            <div class="container">
                <h2 class="text-center fw-bold mb-4">Featured Categories</h2>
                <div class="row text-center g-4">
                    <div class="col">
                        <div class="d-flex flex-column align-items-center" onclick="location.href='user/product_page.php?category=greengrocer'">
                            <div class="shop-icon">
                                <i class="fas fa-apple-alt category-icon"></i>
                            </div>
                            <span class="fw-medium">Greengrocer</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex flex-column align-items-center" onclick="location.href='user/product_page.php?category=fishmonger'">
                            <div class="shop-icon">
                                <i class="fas fa-fish category-icon"></i>
                            </div>
                            <span class="fw-medium">FishMonger</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex flex-column align-items-center" onclick="location.href='user/product_page.php?category=delicatessen'">
                            <div class="shop-icon">
                                <i class="fas fa-cheese category-icon"></i>
                            </div>
                            <span class="fw-medium">Delicatessen</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex flex-column align-items-center" onclick="location.href='user/product_page.php?category=bakery'">
                            <div class="shop-icon">
                                <i class="fas fa-cookie category-icon"></i>
                            </div>
                            <span class="fw-medium">Bakery</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex flex-column align-items-center" onclick="location.href='user/product_page.php?category=butcher'">
                            <div class="shop-icon">
                                <i class="fas fa-drumstick-bite category-icon"></i>
                            </div>
                            <span class="fw-medium">Butcher</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="container">
            <?php require 'user/product_list.php' ?>
        </div>
    
        <!-- <div class="products">
            <h2>Top Selling Items</h2>
            <div class="top-selling">
                <?php //foreach ($topSelling as $item): ?>
                    <div class="product-card">
                        <img src="<?= $item['image'] ?>" alt="<?= $item['name'] ?>">
                        <p><?= $item['name'] ?></p>
                        <p class="price"><?= $item['price'] ?></p>
                        <button class="cart-btn" onclick="addToCart(<?= htmlspecialchars(json_encode($item)) ?>)">🛒 Add to Cart</button>
                    </div>
                <?php //endforeach; ?>
            </div>
    
            <h2><?php echo $category == "all" ? "Products" : ucfirst($category) ?></h2>
            <div class="product-grid">
                <?php //foreach ($selectedProducts as $item): ?>
                    <div class="product-card">
                        <img src="<?= $item['image'] ?>" alt="<?= $item['name'] ?>">
                        <p><?= $item['name'] ?></p>
                        <p class="price"><?= $item['price'] ?></p>
                        <button class="cart-btn" onclick="addToCart(<?= htmlspecialchars(json_encode($item)) ?>)">🛒 Add to Cart</button>
                    </div>
                <?php //endforeach; ?>
            </div>
        </div> -->
    </div>

    <div class="popup-cart" style="display: none;"></div>

    <script>
        function addToCart(item) {
            let cart = JSON.parse(localStorage.getItem('cart')) || [];
            openCartPopup();

            const existingItemIndex = cart.findIndex(cartItem => cartItem.id === item.id);

            if (cart == []) return;

            if (existingItemIndex !== -1) {
                const existingItem = cart[existingItemIndex];
                let quantityToAdd = existingItem.quantity + 1;
                if (quantityToAdd <= item.inStock) {
                    existingItem.quantity = quantityToAdd;
                } else {
                    alert(`Cannot add more than ${item.inStock} of this item to the cart.`);
                    return;
                }
            } else {
                cart.push({
                    ...item,
                    quantity: 1
                });
            }

            localStorage.setItem('cart', JSON.stringify(cart));

        }

        function openCartPopup() {
            const cart = document.querySelector(".popup-cart");
            const item = JSON.parse(localStorage.getItem('CurrentItem'));

            cart.innerHTML = `
                <div id="cart-container" style="display: flex; z-index: 9999; position: fixed; height: 80vh; width: 20vw; flex-direction: column">
                    <div style="align-items: space-between; color: rgb(255,255,255); background-color: #0d6efd; padding: 5px; width: 25vw; display: flex">
                        <span>Cart</span>
                        <button onclick="closeCart()">X</button>
                    </div>
                    <div style="display: flex; flex-direction: column; background-color: rgb(206, 211, 224); padding: 5px; height: 80vh; width: 25vw"> 
                        <p>${item.name}</p>
                        <img src="${item.image}" alt="${item.name}" class="cart-img">
                        <p>${item.price}</p>
                        <span style="align-self: center">
                            Quantity:
                            <input type="number" value="1" min="1" max="${item.inStock}" style="width: 25%" id="cart-quantity" onchange="handleQuantityCap(${item.inStock})">
                        </span>
                        <p style="align-self: center; color: #198754; font-weight: bold;">In Stock: ${item.inStock}</p>
                        <button onclick="addToLocalStorage(<?php htmlspecialchars(json_encode($item)) ?>)">Add</button>
                    </div>
                </div>
            `;
            cart.style.display = "block";
        }

        function addToLocalStorage(item) {
            storageItem = localStorage.getItem(item['id']) ?? null;
            quantity = storageItem === null ? 1 : storageItem['id']['quantity']
            item = {
                id: item['id'],
                quantity: item['quantity']
            }
            localStorage.setItem(item)
        }

        function openCartPopup() {
            const cart = document.querySelector(".popup-cart");
            const storedCart = JSON.parse(localStorage.getItem('cart')) || [];

            let cartHTML = `
                <div id="cart-container" style="display: flex; z-index: 9999; position: fixed; height: 80vh; width: 20vw; flex-direction: column">
                    <div style="align-items: space-between; color: rgb(255,255,255); background-color: #0d6efd; padding: 5px; width: 25vw; display: flex">
                        <span>Cart</span>
                        <button onclick="closeCart()">X</button>
                    </div>
                    <div style="display: flex; flex-direction: column; background-color: rgb(206, 211, 224); padding: 5px; height: 80vh; width: 25vw">
            `;

            storedCart.forEach(item => {
                cartHTML += `
                    <div class="cart-item">
                        <p>${item.name}</p>
                        <img src="${item.image}" alt="${item.name}" style="width: 50px;">
                        <p>${item.price}</p>
                        <p>Quantity: <input type="number" value="${item.quantity}" min="1" max="${item.inStock}" id="quantity-${item.id}" onchange="updateQuantity(${item.id}, this.value)"></p>
                        <p>In Stock: ${item.inStock}</p>
                        <button onclick="addItemToCart(${item.id})">Add</button>
                    </div>
                `;
            });

            cartHTML += `</div></div>`;

            cart.innerHTML = cartHTML;
            cart.style.display = "block";
        }

        function closeCart() {
            const cart = document.querySelector(".popup-cart");
            cart.style.display = "none";
        }

        function updateQuantity(id, value) {
            let cart = JSON.parse(localStorage.getItem('cart')) || [];
            let item = cart.find(cartItem => cartItem.id === id);
            if (item) {
                if (value <= item.inStock && value >= 1) {
                    item.quantity = value;
                    localStorage.setItem('cart', JSON.stringify(cart));
                } else {
                    alert(`You cannot add more than ${item.inStock} or less than 1.`);
                }
            }
        }

        function addItemToCart(id) {
            let cart = JSON.parse(localStorage.getItem('cart')) || [];
            let item = cart.find(cartItem => cartItem.id === id);
            if (item) {
                let quantity = document.getElementById(`quantity-${id}`).value;
                item.quantity = parseInt(quantity);
                localStorage.setItem('cart', JSON.stringify(cart));
                closeCart();
            }
        }
    </script>
</body>