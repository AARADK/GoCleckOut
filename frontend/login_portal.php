<?php
session_start();
include '../backend/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $errors = [];

    // Check if email exists
    $check_email_sql = "SELECT * FROM users WHERE email = :email";
    $check_email_stmt = oci_parse($conn, $check_email_sql);
    oci_bind_by_name($check_email_stmt, ":email", $email);
    oci_execute($check_email_stmt);
    $user = oci_fetch_assoc($check_email_stmt);

    if (!$user) {
        $errors[] = "Email not found";
    } else {
        // Verify password
        if ($password !== $user['PASSWORD']) {
            $errors[] = "Incorrect password";
        }
    }

    if (empty($errors)) {
        // Set session variables
        $_SESSION['user_id'] = $user['USER_ID'];
        $_SESSION['email'] = $user['EMAIL'];
        $_SESSION['role'] = $user['ROLE'];
        $_SESSION['name'] = $user['NAME'];

        // Redirect based on role
        switch ($user['ROLE']) {
            case 'admin':
                header('Location: admin/admin_dashboard.php');
                break;
            case 'trader':
                header('Location: trader/trader_dashboard.php');
                break;
            case 'customer':
                header('Location: customer/customer_dashboard.php');
                break;
            default:
                header('Location: index.php');
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Portal - GCO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <?php if (isset($_GET['sign_in']) && $_GET['sign_in'] === 'true'): ?>
                <h2>Login</h2>
                <?php if (!empty($errors)): ?>
                    <div class="error-message">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="?sign_in=true">
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" name="login">Login</button>
                </form>
                <p>Don't have an account? <a href="?sign_in=false">Register here</a></p>
            <?php else: ?>
                <h2>Register</h2>
                <!-- Your existing registration form code here -->
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 