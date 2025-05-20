<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<footer class="bg-light text-black pt-3">
  <div class="container py-3">
    <div class="row text-start gy-4">
      <!-- Quick Links -->
      <div class="col-6 col-md-3">
        <h6 class="fw-semibold mb-2">Quick Links</h6>
        <ul class="list-unstyled small">
          <li><a href="<?= $_SERVER['HTTP_HOST'] ?>/index.php" class="text-decoration-none text-black d-block py-1">Home</a></li>
          <li><a href="shop.php" class="text-decoration-none text-black d-block py-1">Shop</a></li>
          <li><a href="aboutus.php" class="text-decoration-none text-black d-block py-1">About Us</a></li>
          <li><a href="contact.php" class="text-decoration-none text-black d-block py-1">Contact</a></li>
          <li><a href="faq.php" class="text-decoration-none text-black d-block py-1">FAQ</a></li>
        </ul>
      </div>

      <!-- Customer Service -->
      <div class="col-6 col-md-3">
        <h6 class="fw-semibold mb-2">Customer Service</h6>
        <ul class="list-unstyled small">
          <li><a href="returns.php" class="text-decoration-none text-black d-block py-1">Returns</a></li>
          <li><a href="shipping.php" class="text-decoration-none text-black d-block py-1">Shipping</a></li>
          <li><a href="terms.php" class="text-decoration-none text-black d-block py-1">Terms & Conditions</a></li>
          <li><a href="privacy.php" class="text-decoration-none text-black d-block py-1">Privacy Policy</a></li>
        </ul>
      </div>

      <!-- Contact Us -->
      <div class="col-12 col-md-3">
        <h6 class="fw-semibold mb-2">Contact Us</h6>
        <p class="small mb-1">Email: gocleckout@gmail.com</p>
        <p class="small mb-1">Phone: +977 9823526109</p>
        <p class="small mb-0">Address: Thapathali, Trade tower</p>
      </div>

      <!-- Social Icons -->
      <div class="col-12 col-md-3">
        <h6 class="fw-semibold mb-2">Follow Us</h6>
        <div class="d-flex align-items-center gap-2">
          <a href="#"><img src="/GCO/frontend/assets/facebook.png" alt="Facebook" width="20" /></a>
          <a href="#"><img src="/GCO/frontend/assets/instagram.png" alt="Instagram" width="20" /></a>
          <a href="#"><img src="/GCO/frontend/assets/twitter.png" alt="Twitter" width="20" /></a>
          <a href="#"><img src="/GCO/frontend/assets/youtube.png" alt="YouTube" width="20" /></a>
        </div>
      </div>
    </div>
  </div>

  <div class="bg-dark text-white text-center py-2 small">
    <p class="mb-0">&copy; <?php echo date("Y"); ?> GoCleckOut. All rights reserved.</p>
  </div>
</footer>
