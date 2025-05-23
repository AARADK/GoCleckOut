<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login_portal.php?sign_in=true");
    exit;
}

include '../../backend/database/connect.php';
$conn = getDBConnection();

// Get filter parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build the SQL query with filters
$users_sql = "
    SELECT 
        u.user_id,
        u.full_name,
        u.email,
        u.phone_no,
        u.role,
        u.status,
        u.created_date,
        pc.category_name,
        LISTAGG(s.shop_name, ', ') WITHIN GROUP (ORDER BY s.shop_name) as shop_names
    FROM users u
    LEFT JOIN product_category pc ON u.category_id = pc.category_id
    LEFT JOIN shops s ON u.user_id = s.user_id
    WHERE 1=1
";

if ($role_filter) {
    $users_sql .= " AND u.role = :role_filter";
}
if ($status_filter) {
    $users_sql .= " AND u.status = :status_filter";
}

$users_sql .= " GROUP BY u.user_id, u.full_name, u.email, u.phone_no, u.role, u.status, u.created_date, pc.category_name";
$users_sql .= " ORDER BY u.created_date DESC";

$users_stmt = oci_parse($conn, $users_sql);

// Bind filter parameters if they exist
if ($role_filter) {
    oci_bind_by_name($users_stmt, ':role_filter', $role_filter);
}
if ($status_filter) {
    oci_bind_by_name($users_stmt, ':status_filter', $status_filter);
}

oci_execute($users_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .users-card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .status-active { background-color: #c3e6cb; color: #155724; }
        .status-inactive { background-color: #f5c6cb; color: #721c24; }
        .status-pending { background-color: #ffeeba; color: #856404; }
        .role-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        .role-admin { background-color: #cce5ff; color: #004085; }
        .role-trader { background-color: #d4edda; color: #155724; }
        .role-customer { background-color: #e2e3e5; color: #383d41; }
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h3 mb-0">Users Management</h2>
                        <div class="d-flex gap-2">
                            <form method="GET" class="d-flex gap-2">
                                <select name="role" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Roles</option>
                                    <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="trader" <?= $role_filter === 'trader' ? 'selected' : '' ?>>Trader</option>
                                    <option value="customer" <?= $role_filter === 'customer' ? 'selected' : '' ?>>Customer</option>
                                </select>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    <div class="card users-card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Category</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($user = oci_fetch_assoc($users_stmt)): ?>
                                            <tr>
                                                <td>#<?= $user['USER_ID'] ?></td>
                                                <td><?= htmlspecialchars($user['FULL_NAME']) ?></td>
                                                <td><?= htmlspecialchars($user['EMAIL']) ?></td>
                                                <td><?= htmlspecialchars($user['PHONE_NO']) ?></td>
                                                <td>
                                                    <span class="role-badge role-<?= strtolower($user['ROLE']) ?>">
                                                        <?= ucfirst($user['ROLE']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?= strtolower($user['STATUS']) ?>">
                                                        <?= ucfirst($user['STATUS']) ?>
                                                    </span>
                                                </td>
                                                <td><?= $user['CATEGORY_NAME'] ? htmlspecialchars($user['CATEGORY_NAME']) : '-' ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
