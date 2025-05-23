<?php
require_once '../../backend/database/db_connection.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'trader') {
    // header('Location: ../login/login_portal.php');
    // exit();
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

if (!$conn) {
    die("Database connection failed");
}

// Get all shops with product count
$sql = "SELECT s.*, 
        (SELECT COUNT(*) FROM product WHERE shop_id = s.shop_id) as product_count
        FROM shops s 
        ORDER BY s.shop_id DESC";

$stmt = oci_parse($conn, $sql);
oci_execute($stmt);

$shops = [];
while ($row = oci_fetch_array($stmt, OCI_ASSOC)) {
    $shops[] = $row;
}
oci_free_statement($stmt);

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
    $_SESSION['toast_message'] = "❌ Error: Trader information not found.";
    $_SESSION['toast_type'] = "error";
    header('Location: trader_dashboard.php');
    exit();
}

// Convert category name to lowercase and ensure it's a string
$trader_category = strtolower($trader['CATEGORY_NAME']);

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

// Handle form submission for shop updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_shop') {
        $shop_id = $_POST['shop_id'];
    $shop_name = $_POST['shop_name'];
        $shop_email = $_POST['shop_email'] ?? '';
        $shop_contact_no = $_POST['shop_contact_no'];
        $description = $_POST['description'] ?? '';

        $update_sql = "UPDATE shops 
                      SET shop_name = :shop_name,
                          shop_email = :shop_email,
                          shop_contact_no = :shop_contact_no,
                          description = :description
                      WHERE shop_id = :shop_id AND user_id = :user_id";

        $stmt = oci_parse($conn, $update_sql);
        oci_bind_by_name($stmt, ":shop_name", $shop_name);
        oci_bind_by_name($stmt, ":shop_email", $shop_email);
        oci_bind_by_name($stmt, ":shop_contact_no", $shop_contact_no);
        oci_bind_by_name($stmt, ":description", $description);
        oci_bind_by_name($stmt, ":shop_id", $shop_id);
        oci_bind_by_name($stmt, ":user_id", $user_id);

        if (oci_execute($stmt)) {
            $success_message = "Shop updated successfully!";
        } else {
            $error_message = "Failed to update shop. Please try again.";
        }
        oci_free_statement($stmt);
    } elseif ($_POST['action'] === 'add_shop') {
        $shop_name = $_POST['shop_name'];
        $shop_email = $_POST['shop_email'] ?? '';
        $shop_contact_no = $_POST['shop_contact_no'];
        $description = $_POST['description'] ?: "Welcome to " . $shop_name;

        if (empty($shop_name) || empty($shop_email) || empty($shop_contact_no)) {
            $error_message = "Please fill all required fields.";
        } else {
        $insert_sql = "INSERT INTO shops (user_id, shop_category, shop_name, description, shop_email, shop_contact_no)
                      VALUES (:user_id, :shop_category, :shop_name, :description, :shop_email, :shop_contact_no)";
        
        $stmt = oci_parse($conn, $insert_sql);
        
        oci_bind_by_name($stmt, ':user_id', $user_id);
        oci_bind_by_name($stmt, ':shop_category', $trader_category);
        oci_bind_by_name($stmt, ':shop_name', $shop_name);
        oci_bind_by_name($stmt, ':description', $description);
        oci_bind_by_name($stmt, ':shop_email', $shop_email);
            oci_bind_by_name($stmt, ':shop_contact_no', $shop_contact_no);

        if (oci_execute($stmt)) {
                $success_message = "Shop added successfully!";
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
                $error_message = "Failed to add shop. Please try again.";
            }
            oci_free_statement($stmt);
        }
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

// Get all shops for the trader
$sql = "SELECT s.shop_id, s.shop_name, s.shop_category, s.shop_email, s.shop_contact_no, 
               TO_CHAR(s.description) as description, s.register_date
        FROM shops s 
        WHERE s.user_id = :user_id 
        ORDER BY s.register_date DESC";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":user_id", $user_id);
oci_execute($stmt);

$shops = [];
while ($row = oci_fetch_assoc($stmt)) {
    $shops[] = $row;
}

oci_free_statement($stmt);
oci_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Shops | GoCleckOut</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .shop-card {
            transition: transform 0.2s;
        }
        .shop-card:hover {
            transform: translateY(-2px);
        }
        .form-control:disabled {
            background-color: #f8f9fa;
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
    </style>
</head>
<body>
    <?php include 'trader_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'trader_sidebar.php'; ?>
            </div>
            <div class="col-md-9 col-lg-10">
                <div class="container my-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">My Shops</h1>
            </div>
            
                    <div class="row g-4">
                        <?php if (!empty($shops)): ?>
                            <?php foreach ($shops as $shop): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card shop-card h-100">
                                        <div class="card-body">
                                            <form class="shop-form" data-shop-id="<?= $shop['SHOP_ID'] ?>">
                                                <input type="hidden" name="action" value="update_shop">
                                                <input type="hidden" name="shop_id" value="<?= $shop['SHOP_ID'] ?>">
                                                
                                <div class="mb-3">
                                    <label class="form-label">Shop Name</label>
                                                    <input type="text" class="form-control" name="shop_name" 
                                                           value="<?= htmlspecialchars($shop['SHOP_NAME']) ?>" disabled>
                                </div>

                                <div class="mb-3">
                                                    <label class="form-label">Category</label>
                                                    <input type="text" class="form-control" name="shop_category"
                                                           value="<?= ucfirst(htmlspecialchars($shop['SHOP_CATEGORY'])) ?>" disabled>
                                </div>

                                <div class="mb-3">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" class="form-control" name="shop_email" 
                                                           value="<?= htmlspecialchars($shop['SHOP_EMAIL']) ?>" disabled>
            </div>

                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                                    <input type="text" class="form-control" name="shop_contact_no" 
                                                           value="<?= htmlspecialchars($shop['SHOP_CONTACT_NO']) ?>" disabled>
                                </div>

                                <div class="mb-3">
                                                    <label class="form-label">Description</label>
                                                    <textarea class="form-control" name="description" rows="3" 
                                                              disabled><?= htmlspecialchars($shop['DESCRIPTION']) ?></textarea>
                                </div>

                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        Registered: <?= date('d M Y', strtotime($shop['REGISTER_DATE'])) ?>
                                                    </small>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-outline-primary edit-btn">
                                                            <i class="material-icons align-middle">edit</i>
                                                            Edit
                                                        </button>
                                                        <button type="submit" class="btn btn-primary save-btn" style="display: none;">
                                                            <i class="material-icons align-middle">save</i>
                                                            Save
                                                        </button>
                                                        <button type="button" class="btn btn-outline-secondary cancel-btn" style="display: none;">
                                                            <i class="material-icons align-middle">close</i>
                                                            Cancel
                                </button>
                </div>
            </div>
                                            </form>
        </div>
        </div>
    </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (count($shops) < 2): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card shop-card h-100">
                                    <div class="card-body">
                                        <form class="shop-form add-shop-form" method="POST">
                                            <input type="hidden" name="action" value="add_shop">
                                            
                                <div class="mb-3">
                                    <label class="form-label">Shop Name</label>
                                                <input type="text" class="form-control" name="shop_name" required>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Category</label>
                                                <input type="text" class="form-control" value="<?= ucfirst($trader_category) ?>" disabled>
                                </div>

                                <div class="mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="email" class="form-control" name="shop_email" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                                <input type="text" class="form-control" name="shop_contact_no" required>
                                </div>

                                <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="description" rows="3"></textarea>
                                </div>

                                            <div class="d-flex justify-content-end">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="material-icons align-middle">add</i>
                                                    Add Shop
                                </button>
                                            </div>
                            </form>
                        </div>
                                </div>
                                </div>
                        <?php endif; ?>
                                </div>

                    <?php if (empty($shops)): ?>
                        <div class="text-center py-5">
                            <i class="material-icons" style="font-size: 48px; color: #ccc;">store</i>
                            <h3 class="mt-3 text-muted">No Shops Found</h3>
                            <p class="text-muted">You haven't added any shops yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to show toast message
            function showToast(message, type = 'success') {
                const toastContainer = document.querySelector('.toast-container');
            const toast = document.createElement('div');
                toast.className = `toast align-items-center text-white bg-${type} border-0`;
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                
            toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;
                
                toastContainer.appendChild(toast);
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
                
                toast.addEventListener('hidden.bs.toast', function() {
                    toast.remove();
                });
            }

            // Handle edit button clicks
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const form = this.closest('form');
                    const inputs = form.querySelectorAll('input:not([type="hidden"]), textarea');
                    const saveBtn = form.querySelector('.save-btn');
                    const cancelBtn = form.querySelector('.cancel-btn');
                    const editBtn = this;

                    // Store original values
                    form.originalValues = {};
                    inputs.forEach(input => {
                        form.originalValues[input.name] = input.value;
                    });

                    // Enable only specific fields
                    inputs.forEach(input => {
                        if (input.name === 'shop_name' || input.name === 'shop_contact_no' || input.name === 'description') {
                            input.disabled = false;
                        }
                    });

                    // Show/hide buttons
                    editBtn.style.display = 'none';
                    saveBtn.style.display = 'inline-block';
                    cancelBtn.style.display = 'inline-block';
                });
            });

            // Handle cancel button clicks
            document.querySelectorAll('.cancel-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const form = this.closest('form');
                    const inputs = form.querySelectorAll('input:not([type="hidden"]), textarea');
                    const saveBtn = form.querySelector('.save-btn');
                    const cancelBtn = this;
                    const editBtn = form.querySelector('.edit-btn');

                    // Restore original values and disable all inputs
                    inputs.forEach(input => {
                        input.value = form.originalValues[input.name];
                        input.disabled = true;
                    });

                    // Show/hide buttons
                    editBtn.style.display = 'inline-block';
                    saveBtn.style.display = 'none';
                    cancelBtn.style.display = 'none';
                });
            });

            // Handle form submissions
            document.querySelectorAll('.shop-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);

                    // Log form data for debugging
                    for (let pair of formData.entries()) {
                        console.log(pair[0] + ': ' + pair[1]);
                    }

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(() => {
                        if (this.classList.contains('add-shop-form')) {
                            // For add shop form, reload the page
                            window.location.reload();
                        } else {
                            // For edit form
                            const inputs = this.querySelectorAll('input:not([type="hidden"]), textarea');
                            const saveBtn = this.querySelector('.save-btn');
                            const cancelBtn = this.querySelector('.cancel-btn');
                            const editBtn = this.querySelector('.edit-btn');

                            // Disable inputs
                            inputs.forEach(input => {
                                input.disabled = true;
                            });

                            // Show/hide buttons
                            editBtn.style.display = 'inline-block';
                            saveBtn.style.display = 'none';
                            cancelBtn.style.display = 'none';

                            showToast('Shop updated successfully!');
                        }
                    })
                    .catch(error => {
                        showToast('Failed to update shop. Please try again.', 'danger');
                    });
                });
            });
        });
    </script>
</body>
</html>
