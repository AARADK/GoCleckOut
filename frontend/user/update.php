<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Update Profile</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f8f8f8;
    }

    .navbar .nav-link {
      color: #000;
      font-weight: 500;
    }

    .search-bar input {
      border-radius: 20px;
      border: 1px solid #ccc;
      padding: 5px 15px;
      width: 100%;
    }

    .search-bar .btn {
      border-radius: 20px;
      background-color: #f26262;
      color: white;
    }

    .update-box {
      background-color: #fff;
      border: 1px solid #ccc;
      padding: 30px;
      margin: 50px auto;
      max-width: 800px;
    }

    .form-control, .form-select {
      border-radius: 5px;
    }

    .form-label {
      font-weight: 500;
    }

    .btn-save {
      background-color: #f26262;
      color: white;
      border: none;
      padding: 6px 18px;
      font-size: 14px;
    }

    .btn-cancel {
      border: none;
      background: none;
      color: #888;
      font-size: 14px;
    }

    footer {
      background-color: #fff;
      padding: 60px 0 20px;
      border-top: 1px solid #ddd;
      font-size: 15px;
    }

    footer a {
      text-decoration: none;
      color: #000;
      display: block;
      margin-bottom: 6px;
    }

    .footer-logo {
      font-weight: bold;
      color: #f26262;
      font-size: 20px;
    }

    .footer-bottom {
      border-top: 1px solid #eee;
      margin-top: 20px;
      padding-top: 15px;
      font-size: 14px;
      color: gray;
    }

    .upload-label {
      font-size: 14px;
      color: #444;
    }

    .small-muted {
      font-size: 12px;
      color: #888;
    }
  </style>
</head>
<body>

<!-- Navbar -->
<!-- <nav class="navbar navbar-expand-lg navbar-light bg-white px-4 py-3 border-bottom">
  <a class="navbar-brand d-flex align-items-center gap-2" href="#">
    <img src="logo.png" alt="GoCleckOut Logo" style="height: 32px;">
    <span class="footer-logo mb-0">GoCleckOut</span>
  </a>

  <div class="mx-auto search-bar" style="width: 400px;">
    <form class="d-flex">
      <input class="form-control me-2" type="search" placeholder="Search for items..." aria-label="Search">
      <button class="btn" type="submit">Search</button>
    </form>
  </div>

  <div class="text-dark d-flex align-items-center">
    <i class="bi bi-person mx-2"></i> Account
    <i class="bi bi-heart mx-2"></i> Wishlist
    <i class="bi bi-cart mx-2"></i> Cart
    <i class="bi bi-box mx-2"></i> Product
  </div>
</nav> -->

<?php include '../header.php' ?>
<!-- Update Profile Box -->
<div class="container">
  <div class="update-box shadow-sm">
    <h5 class="fw-bold mb-4">Update profile</h5>

    <!-- Profile Photo Upload -->
    <div class="mb-4">
      <label class="form-label">Profile photo</label>
      <div class="d-flex align-items-center gap-3">
        <img src="https://upload.wikimedia.org/wikipedia/commons/9/99/Sample_User_Icon.png" width="80" height="80" alt="User Avatar" class="border rounded">
        <div>
          <p class="mb-1 upload-label">Upload your photo</p>
          <p class="small-muted">Your photo should be in PNG or JPG format</p>
          <div class="d-flex align-items-center gap-2">
            <input class="form-control form-control-sm w-auto" type="file" aria-label="Upload photo">
            <button type="button" class="btn btn-link text-muted p-0 small">Remove</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Full Name -->
    <div class="mb-3">
      <label for="fullname" class="form-label">Full name</label>
      <input type="text" class="form-control" id="fullname" placeholder="Your full name">
    </div>

    <!-- Email -->
    <div class="mb-3">
      <label for="email" class="form-label">Email</label>
      <input type="email" class="form-control" id="email" placeholder="Your email">
    </div>

    <!-- Phone -->
    <div class="mb-3">
      <label for="phone" class="form-label">Phone number</label>
      <input type="text" class="form-control" id="phone" placeholder="Your phone number">
    </div>

    <!-- DOB -->
    <div class="mb-4">
      <label for="dob" class="form-label">Date of Birth</label>
      <input type="date" class="form-control" id="dob">
    </div>

    <!-- Buttons -->
    <div class="d-flex justify-content-end gap-3">
      <button type="button" class="btn btn-cancel" onclick="window.history.back()">Cancel</button>
      <button type="submit" class="btn btn-save">Save profile</button>
    </div>
  </div>
</div>

<!-- Footer -->
<footer class="text-start mt-5">
  <div class="container">
    <div class="row">
      <div class="col-md-3 mb-4">
        <div class="footer-logo">GoCleckOut</div>
        <p class="text-muted small mt-2">GoCleckOut is the biggest market of grocery products. Get your daily needs from our store.</p>
        <p class="text-muted small mb-1">📍 The British College, Thapathali</p>
        <p class="text-muted small mb-1">📧 gocleckout@gmail.com</p>
        <p class="text-muted small">📞 +977 9825261819</p>
      </div>

      <div class="col-md-3 mb-4">
        <h6>Company</h6>
        <a href="#">About us</a>
        <a href="#">Privacy Policy</a>
        <a href="#">Terms & Conditions</a>
        <a href="#">Contact us</a>
      </div>

      <div class="col-md-3 mb-4">
        <h6>Category</h6>
        <a href="#">Bakeries</a>
        <a href="#">Butcher</a>
        <a href="#">Fishmonger</a>
        <a href="#">Delicatessen</a>
        <a href="#">Groceries</a>
      </div>

      <div class="col-md-3 mb-4">
        <h6>Connect with us</h6>
        <p class="mb-2">
          <img src="https://upload.wikimedia.org/wikipedia/commons/4/41/Visa_Logo.png" width="50" class="me-2" alt="Visa">
          <img src="https://upload.wikimedia.org/wikipedia/commons/0/04/Mastercard-logo.png" width="50" class="me-2" alt="MasterCard">
          <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg" width="60" alt="PayPal">
        </p>
      </div>
    </div>

    <div class="text-center footer-bottom">
      &copy; 2025 <strong>GoCleckOut</strong>, All rights reserved.
    </div>
  </div>
</footer>

</body>
</html>
