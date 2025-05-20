<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /GCO/frontend/login/login_portal.php?sign_in=true');
    exit;
}

include '../../backend/database/connect.php';
$conn = getDBConnection();

// Get wishlist items
$sql = "SELECT p.*, wp.added_date 
        FROM wishlist_product wp 
        JOIN product p ON wp.product_id = p.product_id 
        JOIN wishlist w ON wp.wishlist_id = w.wishlist_id 
        WHERE w.user_id = :user_id 
        ORDER BY wp.added_date DESC";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":user_id", $_SESSION['user_id']);
oci_execute($stmt);

$wishlist_items = array();
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
    // Handle product image
    if ($row['PRODUCT_IMAGE'] instanceof OCILob) {
        $image_data = $row['PRODUCT_IMAGE']->load();
        $row['IMAGE_SRC'] = "data:image/jpeg;base64," . base64_encode($image_data);
    } else {
        $row['IMAGE_SRC'] = ""; // or a default image path
    }
    $wishlist_items[] = $row;
}
oci_free_statement($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - GCO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .wishlist-item {
            transition: all 0.3s ease;
        }
        .wishlist-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .remove-wishlist {
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .remove-wishlist:hover {
            color: #dc3545;
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .toast {
            background-color: white;
            border-left: 4px solid #28a745;
        }
        .toast.error {
            border-left-color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include '../header.php'; ?>

    <div class="container py-5">
        <h2 class="mb-4">My Wishlist</h2>
        
        <?php if (!empty($wishlist_items)): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($wishlist_items as $item): ?>
                    <div class="col">
                        <div class="card h-100 wishlist-item">
                            <?php if (!empty($item['IMAGE_SRC'])): ?>
                                <img src="<?= htmlspecialchars($item['IMAGE_SRC']) ?>" 
                                     class="card-img-top" 
                                     alt="<?= htmlspecialchars($item['PRODUCT_NAME']) ?>"
                                     style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                     style="height: 200px;">
                                    <span class="text-muted">No Image</span>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($item['PRODUCT_NAME']) ?></h5>
                                <p class="card-text text-muted">
                                    Added on <?= date('M d, Y', strtotime($item['ADDED_DATE'])) ?>
                                </p>
                                <p class="card-text">
                                    <strong>Price: Rs. <?= number_format($item['PRICE'], 2) ?></strong>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="/GCO/frontend/user/product_detail.php?id=<?= $item['PRODUCT_ID'] ?>" 
                                       class="btn btn-primary">View Details</a>
                                    <i class="bi bi-heart-fill text-danger remove-wishlist" 
                                       data-product-id="<?= $item['PRODUCT_ID'] ?>"
                                       title="Remove from wishlist"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-heart text-muted" style="font-size: 4rem;"></i>
                <h4 class="mt-3">Your wishlist is empty</h4>
                <p class="text-muted">Start adding items to your wishlist!</p>
                <a href="/GCO/frontend/home.php" class="btn btn-primary">Browse Products</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showToast(message, isError = false) {
            const toastContainer = document.querySelector('.toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${isError ? 'error' : ''} show`;
            toast.innerHTML = `
                <div class="toast-body">
                    ${message}
                </div>
            `;
            toastContainer.appendChild(toast);
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        document.querySelectorAll('.remove-wishlist').forEach(button => {
            button.addEventListener('click', async function() {
                const productId = this.dataset.productId;
                try {
                    const response = await fetch('update_wishlist.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `product_id=${productId}&action=remove`
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        // Remove the item from the UI
                        const card = this.closest('.col');
                        card.style.opacity = '0';
                        setTimeout(() => {
                            card.remove();
                            // If no items left, show empty state
                            if (document.querySelectorAll('.wishlist-item').length === 0) {
                                location.reload();
                            }
                        }, 300);
                        showToast(data.message);
                    } else {
                        showToast(data.message, true);
                    }
                } catch (error) {
                    showToast('An error occurred', true);
                }
            });
        });
    </script>
</body>
</html>