<?php
  session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Interface</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../css/trader/sidebar.css">
  <link rel="stylesheet" href="../css/trader/header.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
</head>
<body>

  <div class="container-fluid">

    <div class="row">
      <div class="col-12">
        <?php require 'admin_header.php'; ?>
      </div>
    </div>

    <div class="row">
      <!-- Sidebar column -->
      <div class="col-md-3 col-lg-2">
        <?php require 'admin_sidebar.php'; ?>
      </div>

      <!-- Main content column -->
      <div class="col-md-9 col-lg-10">
        <div class="content">
          <?php
            if (isset($_GET['page'])) {
                $page = $_GET['page'];
                include "admin_$page.php";
            } else {
                include "admin_dashboard.php";
            }
          ?>
        </div>
      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
