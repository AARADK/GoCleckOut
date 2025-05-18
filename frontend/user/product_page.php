<?php 
session_start();
?>

<head>
  <title>Product Category Page</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    body {
      background-color: #f8f9fa;
      font-family: Arial, sans-serif;
    }

    .header {
      background-color: #ffffff;
      padding: 10px 0;
      border-bottom: 1px solid #eee;
    }

    .header .form-control {
      border-radius: 20px;
      border: 1px solid #ccc;
    }

    .header h4 {
      color: #ff6b6b;
      font-weight: bold;
    }

    .breadcrumb {
      background: none;
      font-size: 14px;
      font-weight: 500;
    }

    .breadcrumb a {
      text-decoration: none;
      color: #ff6b6b;
    }

    .breadcrumb-item.active {
      color: #6c757d;
    }

    .card {
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      transition: 0.3s;
    }

    .card:hover {
      transform: scale(1.02);
    }

    .card .btn {
      border-radius: 20px;
      background-color: #ff6b6b;
      color: white;
      border: none;
    }

    .card .btn:hover {
      background-color: #d7374a;
    }

    .wishlist-btn {
      color: #ff6b6b;
      background: none;
      border: none;
      font-weight: bold;
    }

    .footer {
      background-color: #fff;
      padding: 40px 0;
      border-top: 1px solid #ddd;
      margin-top: 40px;
    }

    .footer h6 {
      font-weight: bold;
    }

    .footer p,
    .footer a {
      color: #6c757d;
      text-decoration: none;
    }

    .category-box {
      background-color: #f1f1f1;
      padding: 20px;
      border-radius: 10px;
    }
  </style>
</head>

<body>
  <!-- Header -->
  <?php include "../header.php" ?>

  <!-- Breadcrumb with background and centered text -->
<div class="container-fluid" style="background-color: #ff6b6b;">
    <div class="container py-2">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 justify-content-center" style="background-color: transparent;">
          <li class="breadcrumb-item">
            <a href="#" style="color: white; text-decoration: none;">Product</a>
          </li>
          <li class="breadcrumb-item">
            <a href="#" style="color: white; text-decoration: none;">Category Page</a>
          </li>
          <li class="breadcrumb-item active text-white" aria-current="page">Butcher</li>
        </ol>
      </nav>
    </div>
  </div>
  

  <div class="container-fluid">
    <div class="row">
      <!-- Sidebar -->
      <div class="col-md-2 p-4">
        <div class="category-box">
          <h5>Product Category</h5>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="category" id="butcher" checked />
            <label class="form-check-label" for="butcher">Butcher</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="category" id="fishmonger" />
            <label class="form-check-label" for="fishmonger">Fishmonger</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="category" id="bakery" />
            <label class="form-check-label" for="bakery">Bakery</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="category" id="delicatessen" />
            <label class="form-check-label" for="delicatessen">Delicatessen</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="category" id="greengrocer" />
            <label class="form-check-label" for="greengrocer">Greengrocer</label>
          </div>

          <label class="mt-4">Filter By Price</label>
          <input type="range" class="form-range" min="20" max="350" />
          <button class="btn btn-sm mt-2 w-100" style="background-color: #ff6b6b; color: #fff;">Filter</button>

          <label class="mt-4">Filter By Shop</label>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="shop1" />
            <label class="form-check-label" for="shop1">Shop 1</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="shop2" />
            <label class="form-check-label" for="shop2">Shop 2</label>
          </div>
          <button class="btn btn-sm mt-2 w-100" style="background-color: #ff6b6b; color: #fff;">Filter</button>
        </div>
      </div>

      <div class="col-md-10">
        <?php include 'product_list.php' ?>
      </div>

    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    <div class="container">
      <div class="row">
        <div class="col-md-3">
          <h6>GoCleckOut</h6>
          <p>GoCleckOut is the biggest market of grocery products. Get your daily needs from our store.</p>
          <p><i class="fa fa-envelope"></i> GoCleckOutHelp@gmail.com</p>
          <p><i class="fa fa-phone"></i> +977 9800000000</p>
        </div>
        <div class="col-md-3">
          <h6>Company</h6>
          <p><a href="#">About Us</a></p>
          <p><a href="#">Privacy Policy</a></p>
          <p><a href="#">Terms & Conditions</a></p>
          <p><a href="#">Contact Us</a></p>
        </div>
        <div class="col-md-3">
          <h6>Category</h6>
          <p><a href="#">Butcher</a></p>
          <p><a href="#">Fishmonger</a></p>
          <p><a href="#">Delicatessen</a></p>
          <p><a href="#">Greengrocer</a></p>
        </div>
        <div class="col-md-3">
          <h6>Connect with us</h6>
          <p>
            <i class="fab fa-facebook"></i>
            <i class="fab fa-twitter"></i>
            <i class="fab fa-instagram"></i>
          </p>
          <img src="paypal.png" alt="Paypal" style="height: 30px" />
        </div>
      </div>
    </div>
    <div class="text-center py-3 text-muted">
      &copy; 2025 <span style="color: #ff6b6b">GoCleckOut</span>, All rights reserved.
    </div>
  </div>
</body>
