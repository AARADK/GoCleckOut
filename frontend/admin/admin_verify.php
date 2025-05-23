<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login_portal.php?sign_in=true");
    exit;
}

include '../../backend/database/connect.php';
$conn = getDBConnection();

// Function to check if category has active trader
function hasActiveTraderInCategory($conn, $category_id) {
    $check_sql = "
        SELECT COUNT(*) as active_count 
        FROM users 
        WHERE role = 'trader' 
        AND status = 'active' 
        AND category_id = :category_id
    ";
    $check_stmt = oci_parse($conn, $check_sql);
    oci_bind_by_name($check_stmt, ":category_id", $category_id);
    oci_execute($check_stmt);
    $result = oci_fetch_assoc($check_stmt);
    return $result['ACTIVE_COUNT'] > 0;
}

// Get pending traders
$pending_traders_sql = "
    SELECT u.*, pc.category_name 
    FROM users u 
    LEFT JOIN product_category pc ON u.category_id = pc.category_id 
    WHERE u.role = 'trader' AND u.status = 'pending'
    ORDER BY u.created_date DESC
";
$pending_stmt = oci_parse($conn, $pending_traders_sql);
oci_execute($pending_stmt);

// Get approved traders
$approved_traders_sql = "
    SELECT u.*, pc.category_name 
    FROM users u 
    LEFT JOIN product_category pc ON u.category_id = pc.category_id 
    WHERE u.role = 'trader' AND u.status = 'active'
    ORDER BY u.created_date DESC
";
$approved_stmt = oci_parse($conn, $approved_traders_sql);
oci_execute($approved_stmt);

// Get rejected traders
$rejected_traders_sql = "
    SELECT u.*, pc.category_name 
    FROM users u 
    LEFT JOIN product_category pc ON u.category_id = pc.category_id 
    WHERE u.role = 'trader' AND u.status = 'inactive'
    ORDER BY u.created_date DESC
";
$rejected_stmt = oci_parse($conn, $rejected_traders_sql);
oci_execute($rejected_stmt);

// Handle trader verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_trader'])) {
    $trader_id = $_POST['trader_id'];
    $action = $_POST['action'];
    
    // If trying to activate, check if category already has active trader
    if ($action === 'active') {
        $check_category_sql = "SELECT category_id FROM users WHERE user_id = :user_id";
        $check_category_stmt = oci_parse($conn, $check_category_sql);
        oci_bind_by_name($check_category_stmt, ":user_id", $trader_id);
        oci_execute($check_category_stmt);
        $trader_data = oci_fetch_assoc($check_category_stmt);
        
        if (hasActiveTraderInCategory($conn, $trader_data['CATEGORY_ID'])) {
            $_SESSION['toast_message'] = "Cannot activate trader: Another trader is already active in this category";
            $_SESSION['toast_type'] = "error";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    
    $update_sql = "UPDATE users SET status = :status WHERE user_id = :user_id";
    $update_stmt = oci_parse($conn, $update_sql);
    oci_bind_by_name($update_stmt, ":status", $action);
    oci_bind_by_name($update_stmt, ":user_id", $trader_id);
    
    if (oci_execute($update_stmt)) {
        $_SESSION['toast_message'] = "Trader status updated successfully";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Failed to update trader status";
        $_SESSION['toast_type'] = "error";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Handle shop verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_shop'])) {
    $shop_id = $_POST['shop_id'];
    $action = $_POST['action'];
    
    $update_sql = "UPDATE shops SET status = :status WHERE shop_id = :shop_id";
    $update_stmt = oci_parse($conn, $update_sql);
    oci_bind_by_name($update_stmt, ":status", $action);
    oci_bind_by_name($update_stmt, ":shop_id", $shop_id);
    
    if (oci_execute($update_stmt)) {
        $_SESSION['toast_message'] = "Shop status updated successfully";
        $_SESSION['toast_type'] = "success";
    } else {
        $_SESSION['toast_message'] = "Failed to update shop status";
        $_SESSION['toast_type'] = "error";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .verification-card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .verification-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 10px 10px 0 0;
        }
        .verification-body {
            padding: 20px;
        }
        .btn-verify {
            background-color: #28a745;
            color: white;
        }
        .btn-reject {
            background-color: #dc3545;
            color: white;
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
        .tooltip-inner {
            max-width: 200px;
            padding: 8px;
            color: #fff;
            text-align: center;
            background-color: #000;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php' ?>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'admin_sidebar.php' ?>
            </div>
            <div class="col-md-9 col-lg-10">
                <div class="container-fluid py-4">
                    <div class="toast-container">
                        <?php if (isset($_SESSION['toast_message'])): ?>
                            <div class="toast <?= $_SESSION['toast_type'] ?>" style="display: block;">
                                <div><?= htmlspecialchars($_SESSION['toast_message']) ?></div>
                                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
                            </div>
                            <?php unset($_SESSION['toast_message'], $_SESSION['toast_type']); ?>
                        <?php endif; ?>
                    </div>

                    <h2 class="mb-4">Verification Dashboard</h2>
                    
                    <!-- Pending Traders Section -->
                    <div class="card verification-card">
                        <div class="verification-header">
                            <h5 class="mb-0">Pending Traders</h5>
                        </div>
                        <div class="verification-body">
                            <?php 
                            $pending_traders = [];
                            while ($row = oci_fetch_assoc($pending_stmt)) {
                                $pending_traders[] = $row;
                            }
                            if (!empty($pending_traders)): 
                            ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Category</th>
                                                <th>Registered Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pending_traders as $trader): 
                                                $hasActiveTrader = hasActiveTraderInCategory($conn, $trader['CATEGORY_ID']);
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($trader['FULL_NAME']) ?></td>
                                                    <td><?= htmlspecialchars($trader['EMAIL']) ?></td>
                                                    <td><?= htmlspecialchars($trader['PHONE_NO']) ?></td>
                                                    <td><?= htmlspecialchars($trader['CATEGORY_NAME']) ?></td>
                                                    <td><?= date('Y-m-d', strtotime($trader['CREATED_DATE'])) ?></td>
                                                    <td>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="trader_id" value="<?= $trader['USER_ID'] ?>">
                                                            <input type="hidden" name="action" value="active">
                                                            <button type="submit" name="verify_trader" class="btn btn-sm btn-verify" 
                                                                <?= $hasActiveTrader ? 'disabled' : '' ?>
                                                                data-bs-toggle="tooltip" 
                                                                data-bs-placement="top" 
                                                                title="<?= $hasActiveTrader ? 'Another trader is already active in this category' : '' ?>">
                                                                <i class="fas fa-check me-1"></i>Verify
                                                            </button>
                                                        </form>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="trader_id" value="<?= $trader['USER_ID'] ?>">
                                                            <input type="hidden" name="action" value="inactive">
                                                            <button type="submit" name="verify_trader" class="btn btn-sm btn-reject">
                                                                <i class="fas fa-times me-1"></i>Reject
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No pending traders to verify.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Approved Traders Section -->
                    <div class="card verification-card">
                        <div class="verification-header">
                            <h5 class="mb-0">Approved Traders</h5>
                        </div>
                        <div class="verification-body">
                            <?php 
                            $approved_traders = [];
                            while ($row = oci_fetch_assoc($approved_stmt)) {
                                $approved_traders[] = $row;
                            }
                            if (!empty($approved_traders)): 
                            ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Category</th>
                                                <th>Registered Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($approved_traders as $trader): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($trader['FULL_NAME']) ?></td>
                                                    <td><?= htmlspecialchars($trader['EMAIL']) ?></td>
                                                    <td><?= htmlspecialchars($trader['PHONE_NO']) ?></td>
                                                    <td><?= htmlspecialchars($trader['CATEGORY_NAME']) ?></td>
                                                    <td><?= date('Y-m-d', strtotime($trader['CREATED_DATE'])) ?></td>
                                                    <td>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="trader_id" value="<?= $trader['USER_ID'] ?>">
                                                            <input type="hidden" name="action" value="inactive">
                                                            <button type="submit" name="verify_trader" class="btn btn-sm btn-reject">
                                                                <i class="fas fa-times me-1"></i>Reject
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No approved traders.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Rejected Traders Section -->
                    <div class="card verification-card">
                        <div class="verification-header">
                            <h5 class="mb-0">Rejected Traders</h5>
                        </div>
                        <div class="verification-body">
                            <?php 
                            $rejected_traders = [];
                            while ($row = oci_fetch_assoc($rejected_stmt)) {
                                $rejected_traders[] = $row;
                            }
                            if (!empty($rejected_traders)): 
                            ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Category</th>
                                                <th>Registered Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rejected_traders as $trader): 
                                                $hasActiveTrader = hasActiveTraderInCategory($conn, $trader['CATEGORY_ID']);
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($trader['FULL_NAME']) ?></td>
                                                    <td><?= htmlspecialchars($trader['EMAIL']) ?></td>
                                                    <td><?= htmlspecialchars($trader['PHONE_NO']) ?></td>
                                                    <td><?= htmlspecialchars($trader['CATEGORY_NAME']) ?></td>
                                                    <td><?= date('Y-m-d', strtotime($trader['CREATED_DATE'])) ?></td>
                                                    <td>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="trader_id" value="<?= $trader['USER_ID'] ?>">
                                                            <input type="hidden" name="action" value="active">
                                                            <button type="submit" name="verify_trader" class="btn btn-sm btn-verify"
                                                                <?= $hasActiveTrader ? 'disabled' : '' ?>
                                                                data-bs-toggle="tooltip" 
                                                                data-bs-placement="top" 
                                                                title="<?= $hasActiveTrader ? 'Another trader is already active in this category' : '' ?>">
                                                                <i class="fas fa-check me-1"></i>Approve
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No rejected traders.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide toasts after 3 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        });
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html> 