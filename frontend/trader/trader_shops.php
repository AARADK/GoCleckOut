<?php

require_once '../../backend/database/db_connection.php'; // assumes PDO $db is set up in this file

if (session_status() === PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id || $_SESSION['role'] !== 'trader') {
    header('Location: ../login.php');
    exit;
}

// Function to safely read LOB data
function readLob($lob) {
    if (!$lob) return '';
    
    if (is_object($lob)) {
        if (get_class($lob) === 'OCI-Lob') {
            try {
                if ($lob->isTemporary()) {
                    $lob->rewind();
                    $data = $lob->load();
                    return $data ?: '';
                } else {
                    $size = $lob->size();
                    if ($size > 0) {
                        $lob->rewind();
                        return $lob->read($size) ?: '';
                    }
                }
            } catch (Exception $e) {
                error_log("Error reading LOB: " . $e->getMessage());
                return '';
            }
        }
        // For any other object type, try to get a string representation
        if (method_exists($lob, '__toString')) {
            return (string)$lob;
        }
        return '';
    }
    
    // For non-objects, return as is
    return $lob;
}

// Function to safely display data
function safeDisplay($data) {
    $string_data = readLob($data);
    return htmlspecialchars((string)$string_data);
}

// Get trader's category
$trader_sql = "SELECT u.category_id, pc.category_name 
               FROM users u 
               JOIN product_category pc ON u.category_id = pc.category_id 
               WHERE u.user_id = :user_id";
$trader_stmt = oci_parse($conn, $trader_sql);
oci_bind_by_name($trader_stmt, ":user_id", $user_id);
oci_execute($trader_stmt);
$trader = oci_fetch_array($trader_stmt, OCI_ASSOC);
oci_free_statement($trader_stmt);

if (!$trader) {
    header('Location: ../login.php');
    exit;
}

// Convert category name to lowercase and ensure it's a string
$trader_category = strtolower(readLob($trader['CATEGORY_NAME']));

// Get existing shops for this trader
$shops_sql = "SELECT * FROM shops WHERE user_id = :user_id ORDER BY shop_id";
$shops_stmt = oci_parse($conn, $shops_sql);
oci_bind_by_name($shops_stmt, ":user_id", $user_id);
oci_execute($shops_stmt);

$existing_shops = [];
while ($shop = oci_fetch_array($shops_stmt, OCI_ASSOC)) {
    // Convert any LOB fields to strings
    foreach ($shop as $key => $value) {
        $shop[$key] = readLob($value);
    }
    $existing_shops[] = $shop;
}
oci_free_statement($shops_stmt);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && count($existing_shops) < 2) {
    $shop_name = $_POST['shop_name'];
    $shop_email = $_POST['shop_email'];
    $phone_no = $_POST['phone_no'];

    if ($shop_name && $shop_email && $phone_no) {
        $description = "Welcome to " . $shop_name; // Default description
        
        $insert_sql = "INSERT INTO shops (user_id, shop_category, shop_name, description, shop_email, shop_contact_no)
                      VALUES (:user_id, :shop_category, :shop_name, :description, :shop_email, :shop_contact_no)";
        
        $stmt = oci_parse($conn, $insert_sql);
        
        oci_bind_by_name($stmt, ':user_id', $user_id);
        oci_bind_by_name($stmt, ':shop_category', $trader_category);
        oci_bind_by_name($stmt, ':shop_name', $shop_name);
        oci_bind_by_name($stmt, ':description', $description);
        oci_bind_by_name($stmt, ':shop_email', $shop_email);
        oci_bind_by_name($stmt, ':shop_contact_no', $phone_no);

        if (oci_execute($stmt)) {
            $_SESSION['toast_message'] = "✅ Shop added successfully!";
            $_SESSION['toast_type'] = "success";
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $error = oci_error($stmt);
            $_SESSION['toast_message'] = "❌ Error adding shop: " . $error['message'];
            $_SESSION['toast_type'] = "error";
        }
        oci_free_statement($stmt);
    } else {
        $_SESSION['toast_message'] = "❌ Please fill all required fields.";
        $_SESSION['toast_type'] = "error";
    }
}

// Map category names to display names
$category_display_names = [
    'butcher' => 'Butcher Shop',
    'greengrocer' => 'Greengrocer Shop',
    'fishmonger' => 'Fishmonger Shop',
    'bakery' => 'Bakery Shop',
    'delicatessen' => 'Delicatessen Shop'
];
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Shops - GoCleckOut</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        .shop-card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: 0.3s;
            height: 100%;
            min-height: 400px;
            background: #fff;
        }

        .shop-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .shop-header {
            background-color: #f8f9fa;
            border-radius: 10px 10px 0 0;
            padding: 15px;
        }

        .shop-category {
            color: #f44357;
            font-weight: 600;
            text-transform: capitalize;
        }

        .shop-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 10px 0;
        }

        .shop-details {
            padding: 15px;
        }

        .shop-contact {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .shop-contact i {
            width: 20px;
            color: #f44357;
        }

        .add-shop-form {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
        }

        .btn-primary {
            background-color: #f44357;
            border-color: #f44357;
        }

        .btn-primary:hover {
            background-color: #d7374a;
            border-color: #d7374a;
        }

        .empty-shop-card {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 20px;
            height: 100%;
            color: #6c757d;
        }

        .empty-shop-card i {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .toast {
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            padding: 15px 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-width: 250px;
            animation: slideIn 0.3s ease-out;
        }

        .toast.success {
            border-left: 4px solid #28a745;
        }

        .toast.error {
            border-left: 4px solid #dc3545;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container"></div>

    <!-- Header -->
    <?php include '../header.php' ?>

    <div class="container my-5">
        <h2 class="mb-4">My <?= safeDisplay($category_display_names[$trader_category] ?? 'Shops') ?></h2>
        
        <div class="row">
            <!-- First Shop Card -->
            <div class="col-md-6 mb-4">
                <div class="shop-card">
                    <?php if (isset($existing_shops[0])): ?>
                        <!-- Display first shop if exists -->
                        <div class="shop-header">
                            <span class="shop-category"><?= safeDisplay($category_display_names[$existing_shops[0]['SHOP_CATEGORY']] ?? $existing_shops[0]['SHOP_CATEGORY']) ?></span>
                            <h3 class="shop-name"><?= safeDisplay($existing_shops[0]['SHOP_NAME']) ?></h3>
                        </div>
                        <div class="shop-details">
                            <p class="shop-description"><?= safeDisplay($existing_shops[0]['DESCRIPTION']) ?></p>
                            <div class="shop-contact">
                                <p><i class="fas fa-envelope"></i> <?= safeDisplay($existing_shops[0]['SHOP_EMAIL']) ?></p>
                                <p><i class="fas fa-phone"></i> <?= safeDisplay($existing_shops[0]['SHOP_CONTACT_NO']) ?></p>
                                <p><i class="fas fa-calendar"></i> Registered: <?= date('M d, Y', strtotime($existing_shops[0]['REGISTER_DATE'])) ?></p>
                            </div>
                            <div class="mt-3">
                                <a href="manage_products.php?shop_id=<?= safeDisplay($existing_shops[0]['SHOP_ID']) ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-box me-2"></i>Manage Products
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Show shop creation form if no first shop -->
                        <div class="shop-header">
                            <h3 class="shop-name">Add Your First <?= safeDisplay($category_display_names[$trader_category] ?? 'Shop') ?></h3>
                        </div>
                        <div class="shop-details add-shop-form">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Shop Name</label>
                                    <input type="text" name="shop_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Shop Email</label>
                                    <input type="email" name="shop_email" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" name="phone_no" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Shop Category</label>
                                    <input type="text" class="form-control" value="<?= safeDisplay($category_display_names[$trader_category] ?? $trader_category) ?>" disabled>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 mt-auto">
                                    <i class="fas fa-plus me-2"></i>Add Shop
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Second Shop Card -->
            <div class="col-md-6 mb-4">
                <div class="shop-card">
                    <?php if (isset($existing_shops[1])): ?>
                        <!-- Display second shop if exists -->
                        <div class="shop-header">
                            <span class="shop-category"><?= safeDisplay($category_display_names[$existing_shops[1]['SHOP_CATEGORY']] ?? $existing_shops[1]['SHOP_CATEGORY']) ?></span>
                            <h3 class="shop-name"><?= safeDisplay($existing_shops[1]['SHOP_NAME']) ?></h3>
                        </div>
                        <div class="shop-details">
                            <p class="shop-description"><?= safeDisplay($existing_shops[1]['DESCRIPTION']) ?></p>
                            <div class="shop-contact">
                                <p><i class="fas fa-envelope"></i> <?= safeDisplay($existing_shops[1]['SHOP_EMAIL']) ?></p>
                                <p><i class="fas fa-phone"></i> <?= safeDisplay($existing_shops[1]['SHOP_CONTACT_NO']) ?></p>
                                <p><i class="fas fa-calendar"></i> Registered: <?= date('M d, Y', strtotime($existing_shops[1]['REGISTER_DATE'])) ?></p>
                            </div>
                            <div class="mt-3">
                                <a href="manage_products.php?shop_id=<?= safeDisplay($existing_shops[1]['SHOP_ID']) ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-box me-2"></i>Manage Products
                                </a>
                            </div>
                        </div>
                    <?php elseif (count($existing_shops) === 1): ?>
                        <!-- Show shop creation form if first shop exists but second doesn't -->
                        <div class="shop-header">
                            <h3 class="shop-name">Add Your Second <?= safeDisplay($category_display_names[$trader_category] ?? 'Shop') ?></h3>
                        </div>
                        <div class="shop-details add-shop-form">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Shop Name</label>
                                    <input type="text" name="shop_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Shop Email</label>
                                    <input type="email" name="shop_email" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" name="phone_no" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Shop Category</label>
                                    <input type="text" class="form-control" value="<?= safeDisplay($category_display_names[$trader_category] ?? $trader_category) ?>" disabled>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 mt-auto">
                                    <i class="fas fa-plus me-2"></i>Add Shop
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Show empty state if no shops exist -->
                        <div class="empty-shop-card">
                            <i class="fas fa-store"></i>
                            <h4>Add Your First <?= safeDisplay($category_display_names[$trader_category] ?? 'Shop') ?></h4>
                            <p class="text-muted">You need to create your first shop before adding a second one.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include '../footer.php' ?>

    <script>
        // Show toast message if exists
        <?php if (isset($_SESSION['toast_message'])): ?>
            const toast = document.createElement('div');
            toast.className = `toast ${$_SESSION['toast_type']}`;
            toast.innerHTML = `
                <div><?= $_SESSION['toast_message'] ?></div>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            document.querySelector('.toast-container').appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
            <?php unset($_SESSION['toast_message'], $_SESSION['toast_type']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
