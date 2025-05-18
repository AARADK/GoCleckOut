<?php
require_once '../database/db_connection.php';
if (session_status() == PHP_SESSION_ACTIVE) session_destroy();
session_start();
require 'otp_sender.php';

echo "<pre>";
print_r($_POST);
print_r($_SESSION);
print(isset($_SESSION['otp']) ? gettype($_SESSION['otp']) : 'otpnotset');
echo "</pre>";

require '../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$user_mail = $_ENV["USER_EMAIL"];
$user_password = $_ENV["USER_PASSWORD"];

$email = htmlspecialchars($_POST['email'] ?? 'No email provided');
$password = htmlspecialchars($_POST['password'] ?? '');

$type = $_GET['type'] ?? '';

$role = $_SESSION['role'];

$otp = '';
if ($type === 'fromregister>') {
    echo "yeeeees";
    $otp = sendOTP($email);
    $_SESSION['otp'] = $otp;

    $stmt = $db->prepare("SELECT user_id FROM users WHERE email = '$email'");
    $full_name = $_SESSION['full_name'];
    $email = $_SESSION['email'];
    $phone_no = $_SESSION['phone_no'];
    $password = $_SESSION['password'];
    $confirm_password = $_SESSION['confirm_password'];
    $role = $_SESSION['type'] ?? 'customer';

    if ($password != $confirm_password) {
        echo "Passwords do not match!";
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // ✅ Check if email already exists
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo "Email already registered!";
        exit();
    }

    // check if catgeory already exists
    if ($role === 'trader' && !empty($shop_type)) { 
        $stmt = $db->prepare("SELECT COUNT(*) FROM shops WHERE shop_type = '$shop_type'");
        // $stmt->bindParam("s", $shop_type);
        $stmt->execute();
        // $stmt->execute([$shop_type]);
        $count = $stmt->fetchColumn(); // ✅ Get the count
    
        if ($count > 0) {  // ✅ If shop_type already exists, block registration
            echo "This category is already taken!";
            exit();
        }

        $stmt = $db->prepare("SELECT user_id FROM users WHERE email = '$email'");
    
        if($stmt->execute()) {
            $_SESSION['user_id'] = $stmt->fetchColumn();
            exit();
        } else {
            $_SESSION['user_id'] = 0;
            exit();
        }
    }
    

    // ✅ Now, insert user data (only after passing checks)
    $stmt = $db->prepare("INSERT INTO users (full_name, email, password, phone_no, role, status) 
                          VALUES (?, ?, ?, ?, ?, 'pending')");
    
    if ($stmt->execute([$full_name, $email, $hashed_password, $phone_no, $role])) {
        // ✅ OTP is only sent after successful insert
        $_SESSION['otp'] = sendOTP($email);
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;  // Store role in session
        
        header("Location: verify.php?type=fromregister");
    } else {
        echo "Error occurred while registering.";
    }
    if($stmt->execute()) {
        $_SESSION['user_id'] = $stmt->fetchColumn();
    } else {
        $_SESSION['user_id'] = 0;
    }
} elseif ($type === 'fromlogin') {
    header("Location: ../home.php?logged_in=true&email=$email");
    $stmt = $db->prepare("SELECT user_id FROM users WHERE email = '$email'");
    
    if($stmt->execute()) {
        $_SESSION['user_id'] = $stmt->fetchColumn();
        exit();
    } else {
        $_SESSION['user_id'] = 0;
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    $enteredOtp = $_POST['verification_code'] ?? '';
    header("Location: ../home.php?logged_in=true&id=$encrypted_data");
    exit();
    if ($enteredOtp == $_SESSION['otp']) {
        $encrypted_data = base64_encode($email . '/' . $password);
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
    <style>
        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f9f9f9;
            font-family: Arial, sans-serif;
        }

        .verify-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .verify-container h2 {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-align: left;
        }

        .email-label, .code-label {
            font-size: 0.9rem;
            font-weight: 500;
            text-align: left;
            display: block;
            margin-bottom: 5px;
        }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .submit-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px;
            width: 100%;
            font-size: 0.9rem;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
        }

        .submit-btn:hover {
            background-color: #0056b3;
        }

        .help-text {
            font-size: 0.8rem;
            color: #007bff;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="verify-container">
    <h2>Verify email address</h2>
    <p class="email-label">Email: <strong><?php echo $email; ?></strong></p>
    
    <label class="code-label">Verification Code:</label>
    <form action="" method="POST">
        <input type="text" class="form-control" name="verification_code" placeholder="Enter code" required>
        <p>Check the code sent to your email.</p>
        <button type="submit" class="submit-btn" name="submit">Submit</button>
    </form>

    <p class="help-text">Need help? Please contact online service</p>
</div>

</body>
</html>