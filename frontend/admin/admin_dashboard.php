<?php
require_once '../../backend/database/connect.php';

if (session_status() != PHP_SESSION_ACTIVE) session_start();

// Ensure only admin can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
  echo "Access denied!";
}

$conn = getDBConnection(); // Use OCI connection

// Approve Trader
if (isset($_POST['approve'])) {
  $user_id = $_POST['user_id'];
  $sql = "UPDATE users SET status = 'active' WHERE user_id = :user_id";
  $stmt = oci_parse($conn, $sql);
  oci_bind_by_name($stmt, ':user_id', $user_id);
  oci_execute($stmt);
  header("Location: index.php?page=dashboard");
  exit();
}

// Reject Trader
if (isset($_POST['reject'])) {
  $user_id = $_POST['user_id'];
  $sql = "UPDATE users SET status = 'inactive' WHERE user_id = :user_id";
  $stmt = oci_parse($conn, $sql);
  oci_bind_by_name($stmt, ':user_id', $user_id);
  oci_execute($stmt);
  header("Location: admin.php");
  exit();
}

// Fetch pending traders
$pendingTraders = [];
$sql = "SELECT * FROM users WHERE role = 'trader' AND status = 'pending'";
$stmt = oci_parse($conn, $sql);
oci_execute($stmt);
while ($row = oci_fetch_assoc($stmt)) {
  $pendingTraders[] = $row;
}

// Fetch rejected traders
$rejectedTraders = [];
$sql = "SELECT * FROM users WHERE role = 'trader' AND status = 'inactive'";
$stmt = oci_parse($conn, $sql);
oci_execute($stmt);
while ($row = oci_fetch_assoc($stmt)) {
  $rejectedTraders[] = $row;
}
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

    .table th,
    .table td {
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
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingTraders as $trader): ?>
            <tr>
              <td><?= htmlspecialchars($trader['USER_ID']) ?></td>
              <td><?= htmlspecialchars($trader['FULL_NAME']) ?></td>
              <td><?= htmlspecialchars($trader['EMAIL']) ?></td>
              <td><?= htmlspecialchars($trader['PHONE_NO']) ?></td>
              <td>
                <form method="post" class="d-inline">
                  <input type="hidden" name="user_id" value="<?= $trader['USER_ID'] ?>">
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
              <td><?= htmlspecialchars($trader['USER_ID']) ?></td>
              <td><?= htmlspecialchars($trader['FULL_NAME']) ?></td>
              <td><?= htmlspecialchars($trader['EMAIL']) ?></td>
              <td><?= htmlspecialchars($trader['PHONE_NO']) ?></td>
              <td><?= htmlspecialchars($trader['SHOP_TYPE'] ?? 'N/A') ?></td>
              <td>
                <form method="post" class="d-inline">
                  <input type="hidden" name="user_id" value="<?= $trader['USER_ID'] ?>">
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