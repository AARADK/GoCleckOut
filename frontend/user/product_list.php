<?php
require "/xampp/htdocs/GCO/backend/database/connect.php";
// require "../backend/database/connect.php";

$conn = getDBConnection();
if (!$conn) {
   die("Database connection failed");
}

// Start session to use session user_id
if (session_status() == PHP_SESSION_NONE) {
   session_start();
}
$user_id = $_SESSION['user_id'] ?? null;

// Handle Add to Cart request
if (isset($_POST['add_to_cart'])) {
   $product_id = (int) $_POST['product_id'];
   $qty = (int) $_POST['qty'];

   // Check if user has an existing cart
   $sql = "SELECT * FROM cart WHERE user_id = :user_id";
   $stmt = oci_parse($conn, $sql);
   oci_bind_by_name($stmt, ":user_id", $user_id);
   oci_execute($stmt);
   $cart = oci_fetch_array($stmt, OCI_ASSOC);
   oci_free_statement($stmt);

   if (!$cart) {
      // Insert new cart
      $insert_cart_sql = "INSERT INTO cart (cart_id, user_id, add_date) VALUES (cart_seq.NEXTVAL, :user_id, SYSDATE) RETURNING cart_id INTO :cart_id";
      $stmt = oci_parse($conn, $insert_cart_sql);
      oci_bind_by_name($stmt, ":user_id", $user_id);
      oci_bind_by_name($stmt, ":cart_id", $cart_id, 20);
      oci_execute($stmt);
      oci_free_statement($stmt);
   } else {
      $cart_id = $cart['CART_ID'];
   }

   // Check if product is already in the cart
   $check_product_sql = "SELECT * FROM product_cart WHERE cart_id = :cart_id AND product_id = :product_id";
   $stmt = oci_parse($conn, $check_product_sql);
   oci_bind_by_name($stmt, ":cart_id", $cart_id);
   oci_bind_by_name($stmt, ":product_id", $product_id);
   oci_execute($stmt);
   $product_exists = oci_fetch_array($stmt, OCI_ASSOC);
   oci_free_statement($stmt);

   if ($product_exists) {
      $message = "Product already in cart!";
      $messageType = "error";
   } else {
      // Add product to cart
      $insert_product_sql = "INSERT INTO product_cart (cart_id, product_id, quantity) VALUES (:cart_id, :product_id, :qty)";
      $stmt = oci_parse($conn, $insert_product_sql);
      oci_bind_by_name($stmt, ":cart_id", $cart_id);
      oci_bind_by_name($stmt, ":product_id", $product_id);
      oci_bind_by_name($stmt, ":qty", $qty);
      oci_execute($stmt);
      oci_free_statement($stmt);

      // Update stock
      $update_stock_sql = "UPDATE product SET stock = stock - :qty WHERE product_id = :product_id";
      $stmt = oci_parse($conn, $update_stock_sql);
      oci_bind_by_name($stmt, ":qty", $qty);
      oci_bind_by_name($stmt, ":product_id", $product_id);
      oci_execute($stmt);
      oci_free_statement($stmt);

      $message = "Product added to cart successfully!";
      $messageType = "success";
   }
}
?>

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>View Products</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
   <!-- Toastify CSS -->
   <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

   <!-- Toastify JS -->
   <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

</head>

<body>
   <section class="products">
      <div class="product-grid">

         <?php
         $select_products_sql = "SELECT * FROM product";
         $stmt = oci_parse($conn, $select_products_sql);
         oci_execute($stmt);

         $products_found = false;
         while ($fetch_product = oci_fetch_array($stmt, OCI_ASSOC)) {
            $products_found = true;
            $description = $fetch_product['DESCRIPTION']??'Desc';
            if ($description instanceof OCILob) {
               $description = $description->load();
            }
         ?>
            <form action="" method="POST" class="box">
               <!-- <img src="../BackEnd/admin/uploaded_files/<? //$fetch_product['PRODUCT_IMAGE']; ?>" class="image" alt=""> -->
               <img src="../trader/uploaded_files/4d109807c6c812d896e1b4c45dea4d06.png" class="image" alt="">
               <div class="product-info">
                  <h3 class="name"><?= $fetch_product['PRODUCT_NAME']; ?></h3>
                  <p class="stars">★★★★★</p>
                  <input type="hidden" name="product_id" value="<?= $fetch_product['PRODUCT_ID']; ?>">
                  <p class="description"><?= htmlspecialchars($description) ?></p>
                  <div class="flex">
                     <p class="price">RS. <?= $fetch_product['PRICE']; ?></p>
                     <p class="stock">stock: <?= $fetch_product['STOCK']; ?></p>
                     <input type="number" name="qty" required min="1" max="<?= $fetch_product['STOCK']; ?>" value="1" class="qty">
                  </div>
               </div>
               <input type="submit" name="add_to_cart" value="Add to Cart" class="add-to-cart">
               <a href="shopping_cart.php?get_id=<?= $fetch_product['PRODUCT_ID']; ?>" class="delete-btn">Buy Now</a>
            </form>
         <?php
         }

         oci_free_statement($stmt);

         if (!$products_found) {
            echo '<p class="empty">No products found!</p>';
         }

         ?>

      </div>
   </section>

   <script>
      document.addEventListener("DOMContentLoaded", function() {
         // Get message from PHP
         let message = "<?= isset($message) ? $message : ''; ?>";
         let messageType = "<?= isset($messageType) ? $messageType : ''; ?>";

         // Check if there's a message to show
         if (message !== "") {
            Toastify({
               text: message,
               duration: 3000,
               close: true,
               gravity: 'top',
               position: 'right',
               backgroundColor: messageType === "success" ? "green" : "red",
            }).showToast();
         }
      });
   </script>


</body>
