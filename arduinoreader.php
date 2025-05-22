<?php
    session_start();

    function get_login() {
        return isset($_GET['sign_in']) ? $_GET['sign_in'] === 'true' : null;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .bg-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1;
        }

        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Poppins', sans-serif;
        }

        .auth-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 12px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 360px;
            text-align: center;
            color: white;
        }

        h2 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 6px;
        }

        label {
            font-size: 0.7rem;
            font-weight: 500;
            display: block;
            text-align: left;
            margin-bottom: 3px;
            color: white;
        }

        .form-control {
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 5px 10px;
            font-size: 0.75rem;
            background: transparent !important;
            color: white;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .form-control:focus {
            background: transparent !important;
            border-color: #3B82F6;
            outline: none;
        }

        .btn {
            width: 100%;
            border-radius: 15px;
            padding: 5px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: 0.3s;
        }

        .btn-primary {
            background-color: #3B82F6;
            border: none;
        }

        .btn-primary:hover {
            background-color: #2563EB;
        }

        .btn-success {
            background-color: #22C55E;
            border: none;
        }

        .btn-success:hover {
            background-color: #16A34A;
        }

        .small-text {
            font-size: 0.7rem;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <img src="frontend/assets/3417733.jpg" alt="Background Image" class="bg-image">

    <div class="auth-container">
        <?php 
            $login_status = get_login();
            if ($login_status === true): ?>
            <h2>Login</h2>
            <form method="post" action="logintohome.php">
                <div class="mb-1">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter username" required>
                </div>
                <div class="mb-1">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
            <p class="small-text">No account? <a href="?sign_in=false" style="color: #FFF;">Register</a></p>
        <?php elseif ($login_status === false): ?>
            <h2>Register</h2>
            <form method="post" action="logintohome.php">
                <input type="hidden" name="email" id="hiddenEmail">
                <div class="mb-1">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Choose a username" required>
                </div>
                <div class="mb-1">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter email" required oninput="updateHiddenEmail()">
                </div>
                <div class="mb-1">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Create password" required>
                </div>
                <div class="mb-1">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
                </div>
                <div class="mb-1">
                    <label for="phone">Phone</label>
                    <input type="text" class="form-control" id="phone" name="phone" placeholder="Enter phone number" required>
                </div>
                <div class="mb-1">
                    <label for="address">Address</label>
                    <input type="text" class="form-control" id="address" name="address" placeholder="Enter address" required>
                </div>
                <div class="mb-1">
                    <label for="category">Category</label>
                    <select class="form-control" id="category" name="category" required>
                        <option value="" disabled selected>Select your business</option>
                        <option value="butcher">Butcher</option>
                        <option value="delicatessen">Delicatessen</option>
                        <option value="bakery">Bakery</option>
                        <option value="groceries">Groceries</option>
                        <option value="fishmonger">Fishmonger</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Register</button>
            </form>
            <p class="small-text">Have an account? <a href="?sign_in=true" style="color: #FFF;">Login</a></p>
        <?php else: ?>
            <p class="text-center text-danger" style="font-size: 0.75rem;">Invalid access. Please use a valid sign_in parameter.</p>
        <?php endif; ?>
    </div>

    <script>
        function updateHiddenEmail() {
            document.getElementById("hiddenEmail").value = document.getElementById("email").value;
        }
    </script>
</body>
</html>