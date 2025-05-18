<?php
session_start();
require 'otp_sender.php';

if (!isset($_SESSION['email']) || !isset($_SESSION['otp']) || !isset($_SESSION['role'])) {
    echo "Session expired. Please try again.";
    exit();
}

$email = $_SESSION['email'];
$otp = $_SESSION['otp'];
$role = $_SESSION['role']; // Retrieve user role

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $enteredOtp = $_POST['verification_code'];
    if ($enteredOtp == $otp) {
        // Clear session to avoid security issues
        session_unset();
        session_destroy();
        
        // Start a fresh session
        session_start();
        session_regenerate_id(true);
        
        $_SESSION['email'] = $email;  // Store email in session for logged-in user
        $_SESSION['role'] = $role;    // Store role in session

        // Redirect based on role
        if ($role === 'customer') {
            header("Location: http://localhost/GoCleckOut2/GoCleckOut/frontend/home.php?logged_in=true&email=" . urlencode($email));
            // header("Location: http://localhost/GoCleckOut/frontend/home.php");

        } elseif ($role === 'trader') {
            header("Location: trader-dashboard.php?logged_in=true&email=" . urlencode($email));
        } else {
            header("Location: admin.php?logged_in=true&email=" . urlencode($email));
        }
        exit();
    } else {
        echo "<script>alert('Invalid OTP. Please try again.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="verify-container">
        <h2>Verify email address</h2>
        <p class="email-label">Email: <strong><?php echo htmlspecialchars($email); ?></strong></p>
        
        <label class="code-label">Verification Code:</label>
        <form action="" method="POST">
            <input type="text" class="form-control" name="verification_code" placeholder="Enter code" required>
            <p>Check the code sent to your email.</p>
            <button type="submit" class="submit-btn" name="submit">Submit</button>
        </form>
    </div>
</body>
</html>
