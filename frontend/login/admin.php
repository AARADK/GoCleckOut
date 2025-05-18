<?php
session_start();
require_once '../database/db_connection.php';

// Ensure only admin can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo "Access denied!";
    // exit();
}

// Approve Trader
if (isset($_POST['approve'])) {
    $user_id = $_POST['user_id'];
    $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: admin.php");
    exit();
}

// Reject Trader
if (isset($_POST['reject'])) {
    $user_id = $_POST['user_id'];
    $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: admin.php");
    exit();
}

// Fetch pending and rejected traders
$pendingTraders = $db->query("SELECT * FROM users WHERE role = 'trader' AND status = 'pending'")->fetchAll();
$rejectedTraders = $db->query("SELECT * FROM users WHERE role = 'trader' AND status = 'rejected'")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Traders</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background-color: #f8f8f8;
    }

    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: 240px;
      height: 100vh;
      background-color: white;
      border-right: 1px solid #e0e0e0;
      padding: 10px 15px;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 20px;
    }

    .logo img {
      width: 32px;
    }

    .logo span {
      color: #F96C6E;
      font-weight: bold;
      font-size: 14px;
    }

    .nav-link {
      color: black;
      display: flex;
      align-items: center;
      padding: 8px 12px;
      border-radius: 8px;
      font-size: 14px;
      text-decoration: none;
    }

    .nav-link.active,
    .nav-link:hover {
      background-color: #F96C6E;
      color: white;
    }

    .main {
      margin-left: 240px;
      padding: 30px;
    }

    .topbar {
      background-color: #F96C6E;
      color: white;
      padding: 12px 24px;
      font-size: 16px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .section-title {
      margin-top: 30px;
      margin-bottom: 10px;
      font-weight: bold;
      font-size: 18px;
      color: #333;
    }

    .table th, .table td {
      vertical-align: middle;
    }

    .signout {
      position: absolute;
      bottom: 20px;
      left: 15px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    form button {
      margin-right: 5px;
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <div class="logo">
      <img src= "assets/Logo.png" height="50%" width="50%" alt="Logo" />
      <span>GOCLECKOUT</span>
    </div>
    <div class="fw-bold text-muted small mb-2">Admin</div>

    <div class="nav-section">
      <a href="#" class="nav-link">👥 Customers</a>
      <a href="#" class="nav-link">📦 Orders</a>
      <a href="#" class="nav-link active">💼 Traders</a>
      <a href="#" class="nav-link">🛍 Products</a>
      <a href="#" class="nav-link">📝 Blogs</a>
      <a href="#" class="nav-link">📊 Models</a>
    </div>

    <div class="fw-bold text-muted small mt-3 mb-2">Pages</div>
    <div class="nav-section">
      <a href="#" class="nav-link">👤 Profile</a>
      <a href="#" class="nav-link">📚 Users</a>
      <a href="#" class="nav-link">🔒 Authentication</a>
      <a href="#" class="nav-link">⚙️ Settings</a>
      <a href="#" class="nav-link d-flex align-items-center">
        💳 Pricing Trial <span class="badge bg-danger text-white badge-new">New</span>
      </a>
    </div>

    <a href="#" class="nav-link signout">🔙 Sign out</a>
  </div>

  <!-- Main Content -->
  <div class="main">
    <div class="topbar">
      <img src="assets/Logo.png" alt="Logo" width="22" />
      Admin - Manage Traders
    </div>

    <div class="section-title">Pending Traders</div>
    <div class="table-responsive">
      <table class="table table-bordered bg-white">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Contact No</th>
            <th>Shop Type</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingTraders as $trader): ?>
          <tr>
            <td><?= htmlspecialchars($trader['user_id']) ?></td>
            <td><?= htmlspecialchars($trader['full_name']) ?></td>
            <td><?= htmlspecialchars($trader['email']) ?></td>
            <td><?= htmlspecialchars($trader['phone_no']) ?></td>
            <td><?= htmlspecialchars($trader['shop_type']) ?></td>
            <td>
              <form method="post" class="d-inline">
                <input type="hidden" name="user_id" value="<?= $trader['user_id'] ?>">
                <button type="submit" name="approve" class="btn btn-success btn-sm">Approve</button>
                <button type="submit" name="reject" class="btn btn-danger btn-sm">Reject</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="section-title">Rejected Traders</div>
    <div class="table-responsive">
      <table class="table table-bordered bg-white">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Contact No</th>
            <th>Shop Type</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rejectedTraders as $trader): ?>
          <tr>
            <td><?= htmlspecialchars($trader['user_id']) ?></td>
            <td><?= htmlspecialchars($trader['full_name']) ?></td>
            <td><?= htmlspecialchars($trader['email']) ?></td>
            <td><?= htmlspecialchars($trader['phone_no']) ?></td>
            <td><?= htmlspecialchars($trader['shop_type']) ?></td>
            <td>
              <form method="post" class="d-inline">
                <input type="hidden" name="user_id" value="<?= $trader['user_id'] ?>">
                <button type="submit" name="approve" class="btn btn-warning btn-sm">Approve Again</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</body>
</html>