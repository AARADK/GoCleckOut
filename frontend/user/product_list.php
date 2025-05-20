<?php
// include "../../backend/database/connect.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
   session_start();
}
$user_id = $_SESSION['user_id'] ?? null;

$wishlist_items = [];
if ($user_id) {
    $wishlist_sql = "
        SELECT wp.product_id 
        FROM wishlist_product wp 
        JOIN wishlist w ON wp.wishlist_id = w.wishlist_id 
        WHERE w.user_id = :user_id
    ";
    $stmt = oci_parse($conn, $wishlist_sql);
    oci_bind_by_name($stmt, ":user_id", $user_id);
    oci_execute($stmt);
    while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
        $wishlist_items[] = $row['PRODUCT_ID'];
    }
    oci_free_statement($stmt);
}
?>

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>View Products</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
   <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
   <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
   <style>
      .card {
         transition: transform 0.2s;
      }
      .card:hover {
         transform: translateY(-5px);
      }
      .wishlist-btn {
         position: absolute;
         top: 10px;
         right: 10px;
         background: rgba(255, 255, 255, 0.9);
         border: none;
         border-radius: 50%;
         width: 35px;
         height: 35px;
         display: flex;
         align-items: center;
         justify-content: center;
         cursor: pointer;
         transition: all 0.2s;
         z-index: 1;
      }
      .wishlist-btn:hover {
         background: white;
         transform: scale(1.1);
      }
      .wishlist-btn i {
         color: #dc3545;
         font-size: 1.1rem;
      }
      .wishlist-btn.active i {
         color: #dc3545;
      }
      .card-img-container {
         position: relative;
      }
   </style>
</head>

<body>
   <div class="container-fluid py-4">
      <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
         <?php
         $searchTerm = $_GET['name'] ?? null;

         if ($searchTerm) {
            $select_products_sql = "SELECT * FROM product WHERE LOWER(product_name) LIKE '%' || LOWER(:searchTerm) || '%'";
            $stmt = oci_parse($conn, $select_products_sql);
            oci_bind_by_name($stmt, ":searchTerm", $searchTerm);
         } else {
            $select_products_sql = "SELECT * FROM product";
            $stmt = oci_parse($conn, $select_products_sql);
         }

         oci_execute($stmt);

         $products_found = false;
         while ($fetch_product = oci_fetch_array($stmt, OCI_ASSOC)) {
            $products_found = true;
            $description = $fetch_product['DESCRIPTION'] ?? 'Desc';
            if ($description instanceof OCILob) {
               $description = $description->load();
            }
         ?>
            <div class="col">
               <div class="card h-100 shadow-sm">
                  <form action="<?= basename($_SERVER['PHP_SELF']) ?>" method="POST">
                     <div class="card-img-container">
                        <?php
                        $product_image = $fetch_product['PRODUCT_IMAGE'];

                        if ($product_image instanceof OCILob) {
                           $image_data = $product_image->load();
                           $base64_image = base64_encode($image_data);
                           $img_src = "data:image/jpeg;base64," . $base64_image;
                        } else {
                           $img_src = "";
                        }

                        if (!empty($img_src)) {
                           echo "<img src=\"$img_src\" class=\"card-img-top\" style=\"height: 150px; object-fit: cover;\" alt=\"Product Image\">";
                        } else {
                           echo "<div class=\"card-img-top bg-light d-flex align-items-center justify-content-center\" style=\"height: 150px;\">No Image</div>";
                        }
                        ?>
                        <button type="button" class="wishlist-btn <?= in_array($fetch_product['PRODUCT_ID'], $wishlist_items) ? 'active' : '' ?>" 
                                data-product-id="<?= $fetch_product['PRODUCT_ID']; ?>" 
                                onclick="toggleWishlist(this)">
                           <i class="<?= in_array($fetch_product['PRODUCT_ID'], $wishlist_items) ? 'fas' : 'far' ?> fa-heart"></i>
                        </button>
                     </div>
                     <div class="card-body d-flex flex-column p-3">
                        <h5 class="card-title text-truncate mb-1"><?= $fetch_product['PRODUCT_NAME']; ?></h5>
                        <div class="text-warning mb-1" style="font-size: 0.9rem;">★★★★★</div>
                        <input type="hidden" name="product_id" value="<?= $fetch_product['PRODUCT_ID']; ?>">
                        <p class="card-text text-muted small mb-2" style="font-size: 0.85rem;"><?= htmlspecialchars($description) ?></p>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                           <span class="h6 text-danger mb-0">RS. <?= $fetch_product['PRICE']; ?></span>
                           <span class="text-muted small">Stock: <?= $fetch_product['STOCK']; ?></span>
                        </div>
                        <div class="input-group mb-2">
                           <input type="number" name="qty" required min="1" max="<?= $fetch_product['STOCK']; ?>" value="1" class="form-control form-control-sm" style="max-width: 70px;">
                        </div>
                        <div class="d-grid gap-1 mt-auto">
                           <a href="/GCO/frontend/user/product_detail.php?id=<?= $fetch_product['PRODUCT_ID']; ?>" class="btn btn-sm btn-secondary">Details</a>
                           <button type="submit" name="add_to_cart" class="btn btn-sm btn-danger">Add to Cart</button>
                           <a href="/GCO/frontend/user/cart.php?get_id=<?= $fetch_product['PRODUCT_ID']; ?>" class="btn btn-sm btn-success">Buy Now</a>
                        </div>
                     </div>
                  </form>
               </div>
            </div>
         <?php
         }

         oci_free_statement($stmt);

         if (!$products_found) {
            echo '<div class="col-12 text-center py-5">
                    <p class="text-muted h5">No products found!</p>
                  </div>';
         }
         ?>
      </div>
   </div>

   <script>
      // Define toggleWishlist function globally
      function toggleWishlist(button) {
         const productId = button.dataset.productId;
         const icon = button.querySelector('i');
         
         // Determine the action based on current state
         const action = icon.classList.contains('far') ? 'add' : 'remove';
         
         // Make API call to update wishlist with absolute path
         fetch('/GCO/frontend/user/update_wishlist.php', {
             method: 'POST',
             headers: {
                 'Content-Type': 'application/x-www-form-urlencoded',
             },
             body: `product_id=${productId}&action=${action}`
         })
         .then(response => response.json())
         .then(data => {
             if (data.success) {
                 // Toggle heart icon
                 if (action === 'add') {
                     icon.classList.remove('far');
                     icon.classList.add('fas');
                     button.classList.add('active');
                     Toastify({
                         text: "Added to wishlist",
                         duration: 2000,
                         close: true,
                         gravity: 'top',
                         position: 'right',
                         backgroundColor: "#198754",
                     }).showToast();
                 } else {
                     icon.classList.remove('fas');
                     icon.classList.add('far');
                     button.classList.remove('active');
                     Toastify({
                         text: "Removed from wishlist",
                         duration: 2000,
                         close: true,
                         gravity: 'top',
                         position: 'right',
                         backgroundColor: "#dc3545",
                     }).showToast();
                 }
             } else {
                 Toastify({
                     text: data.message || "Failed to update wishlist",
                     duration: 2000,
                     close: true,
                     gravity: 'top',
                     position: 'right',
                     backgroundColor: "#dc3545",
                 }).showToast();
             }
         })
         .catch(error => {
             Toastify({
                 text: "An error occurred",
                 duration: 2000,
                 close: true,
                 gravity: 'top',
                 position: 'right',
                 backgroundColor: "#dc3545",
             }).showToast();
         });
      }

      document.addEventListener("DOMContentLoaded", function() {
         <?php
         $toastMessage = $_SESSION['toast_message'] ?? '';
         $toastType = $_SESSION['toast_type'] ?? '';
         unset($_SESSION['toast_message'], $_SESSION['toast_type']);
         ?>
         let message = "<?= $toastMessage ?>";
         let messageType = "<?= $toastType ?>";

         if (message !== "") {
            Toastify({
               text: message,
               duration: 3000,
               close: true,
               gravity: 'top',
               position: 'right',
               backgroundColor: messageType === "success" ? "#198754" : "#dc3545",
            }).showToast();
         }
      });
   </script>
</body>