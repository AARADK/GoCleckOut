<!-- <head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        footer {
            background-color: #fff;
            padding: 60px 0 20px;
            border-top: 1px solid #ddd;
        }

        footer h6 {
            font-weight: bold;
            margin-bottom: 15px;
        }

        footer a {
            text-decoration: none;
            color: #000;
            display: block;
            margin-bottom: 8px;
        }

        .footer-logo {
            font-weight: bold;
            color: #f26262;
            font-size: 18px;
        }

        .copyright {
            border-top: 1px solid #eee;
            margin-top: 30px;
            padding-top: 20px;
            font-size: 14px;
        }
    </style>
</head>
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
                <a href="#">Terms &amp; Conditions</a>
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
                    <img src="https://upload.wikimedia.org/wikipedia/commons/b/b5/PayPal.svg" width="60" alt="PayPal">
                </p>
            </div>
        </div>

        <div class="text-center footer-bottom">
            © 2025 <strong>GoCleckOut</strong>, All rights reserved.
        </div>
    </div>
</footer> -->

<footer>
    <div class="footer-container">
        <div class="footer-section">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="shop.php">Shop</a></li>
                <li><a href="about.php">About Us</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="faq.php">FAQ</a></li>
            </ul>
        </div>

        <div class="footer-section">
            <h4>Customer Service</h4>
            <ul>
                <li><a href="returns.php">Returns</a></li>
                <li><a href="shipping.php">Shipping</a></li>
                <li><a href="terms.php">Terms & Conditions</a></li>
                <li><a href="privacy.php">Privacy Policy</a></li>
            </ul>
        </div>

        <div class="footer-section">
            <h4>Contact Us</h4>
            <p>Email: support@GoCleckOut.com</p>
            <p>Phone: +1 234 567 890</p>
            <p>Address: Thapathali, Trade tower</p>
        </div>

        <div class="footer-section social-icons">
            <h4>Follow Us</h4>
            <a href="#"><img src="assets/facebook.png" alt="Facebook"></a>
            <a href="#"><img src="assets/instagram.png" alt="Instagram"></a>
            <a href="#"><img src="assets/twitter.png" alt="Twitter"></a>
            <a href="#"><img src="assets/youtube.png" alt="YouTube"></a>
        </div>
    </div>

    <div class="footer-bottom">
        <p>&copy; <?php echo date("Y"); ?> GoCleckOut. All rights reserved.</p>
    </div>
</footer>

<style>
    footer {
        background-color: rgb(240, 240, 240);
        color: black;
        padding: 15px 0;
        text-align: center;
        font-size: 12px;
    }
    .footer-container {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        padding: 15px 10%;
        gap: 10px;
    }
    .footer-section {
        flex: 1;
        min-width: 150px;
    }
    .footer-section h4 {
        margin-bottom: 8px;
        font-size: 13px;
        font-weight: 600;
    }
    .footer-section ul {
        list-style: none;
        padding: 0;
    }
    .footer-section ul li {
        margin: 4px 0;
    }
    .footer-section ul li a {
        color: black;
        text-decoration: none;
        font-size: 11px;
    }
    .footer-section ul li a:hover {
        text-decoration: underline;
    }
    .footer-section p {
        font-size: 11px;
        margin: 4px 0;
    }
    .social-icons img {
        width: 20px;
        margin: 3px;
    }
    .footer-bottom {
        background: rgba(0,0,0,0.5);
        color: rgb(255, 255, 255);
        padding: 8px;
        font-size: 11px;
    }
    .footer-bottom p {
        margin: 0;
        padding: 0;
    }
</style>