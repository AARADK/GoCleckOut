<?php
require_once "../../backend/database/connect.php"; // Use connect.php for OCI connection
include "otp_sender.php";

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

if (isset($_GET['resend'])) {
  $otp = sendOTP($email);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['submit'])) {
    $button = $_POST['submit'];

    if ($button == 'register') {
      $email = $_POST['email'];
      $name = $_POST['username'];
      $password = $_POST['password'];
      $c_password = $_POST['confirm_password'];
      $phone = $_POST['phone'];
      $role = $_POST['role'];
      $status = ($role == 'customer') ? 'active' : 'pending';

      if ($password != $c_password) {
        echo 'Passwords do not match.';
        exit();
      }

      // Check if email already exists
      $conn = getDBConnection();
      $sql = "SELECT COUNT(*) FROM users WHERE email = :email";
      $stmt = oci_parse($conn, $sql);
      oci_bind_by_name($stmt, ":email", $email);

      oci_execute($stmt);
      $count = oci_fetch_assoc($stmt)['COUNT(*)'];
      if ($count > 0) {
        echo 'Email already registered!';
        exit();
      }

      // Send OTP and store registration data
      $otp = sendOTP($email);
      $hashed_password = password_hash($password, PASSWORD_DEFAULT);

      $_SESSION['otp'] = $otp;
      $_SESSION['reg_data'] = [
        'email' => $email,
        'username' => $name,
        'password' => $hashed_password,
        'phone' => $phone,
        'role' => $role,
        'status' => $status,
        'category_id' => isset($_POST['category']) ? $_POST['category'] : null
      ];

    } else if ($button == 'otp') {
      $user_otp = $_POST['digit1'] . $_POST['digit2'] . $_POST['digit3'] . $_POST['digit4'] . $_POST['digit5'];

      if (!isset($_SESSION['otp']) || !isset($_SESSION['reg_data'])) {
        echo "Session expired. Please register again.";
        exit();
      }

      if ($_SESSION['otp'] == $user_otp) {
        $data = $_SESSION['reg_data'];

        // Insert user into the database
        $conn = getDBConnection();
        $sql = "INSERT INTO users (
            full_name, email, password, phone_no, role, verification_code, status, category_id
        ) VALUES (
            :full_name, :email, :password, :phone_no, :role, :verification_code, :status, :category_id
        )";
        $stmt = oci_parse($conn, $sql);

        // Bind parameters
        oci_bind_by_name($stmt, ":full_name", $data['username']);
        oci_bind_by_name($stmt, ":email", $data['email']);
        oci_bind_by_name($stmt, ":password", $data['password']);
        oci_bind_by_name($stmt, ":phone_no", $data['phone']);
        oci_bind_by_name($stmt, ":role", $data['role']);
        oci_bind_by_name($stmt, ":verification_code", $_SESSION['otp']);
        oci_bind_by_name($stmt, ":status", $data['status']);
        oci_bind_by_name($stmt, ":category_id", $data['category_id']);

        // Execute the insert query
        $inserted = oci_execute($stmt);

        if ($inserted) {
          $_SESSION['email'] = $data['email'];
          $_SESSION['role'] = $data['role'];
          $_SESSION['status'] = 'active';

          // Fetch user_id after registration
          $sql = "SELECT user_id FROM users WHERE email = :email";
          $stmt = oci_parse($conn, $sql);
          oci_bind_by_name($stmt, ":email", $data['email']);
          oci_execute($stmt);
          $_SESSION['user_id'] = oci_fetch_assoc($stmt)['USER_ID'];

          $_SESSION['logged_in'] = true;

          unset($_SESSION['otp'], $_SESSION['reg_data']);

          // Redirect based on role
          if ($data['role'] == 'trader') {
            header("Location: ../trader/index.php");
            exit();
          } else {
            header("Location: ../home.php?logged_in=true");
            exit();
          }

        } else {
          echo "Database error.";
          print_r(oci_error($stmt)); // Show error message from OCI
        }

      } else {
        echo "Invalid OTP.";
      }
    }
  }
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>OTP Verification</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #fff;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .container {
      text-align: center;
    }

    h2 {
      margin-bottom: 20px;
    }

    .otp-img {
      width: 100px;
      margin: 20px auto;
    }

    .info-text {
      font-size: 12px;
      color: #444;
      margin-bottom: 20px;
    }

    .otp-inputs {
      display: flex;
      justify-content: center;
      gap: 10px;
      margin-bottom: 20px;
    }

    .otp-inputs input {
      width: 40px;
      height: 50px;
      text-align: center;
      font-size: 18px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }

    .resend {
      font-size: 12px;
      margin-bottom: 20px;
    }

    .resend a {
      color: #000;
      font-weight: 500;
      text-decoration: underline;
      cursor: pointer;
    }

    .verify-btn {
      background-color: #f26a6a;
      color: white;
      border: none;
      padding: 12px 40px;
      border-radius: 10px;
      font-size: 14px;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }

    .verify-btn:hover {
      background-color: #e25555;
    }
  </style>
</head>

<body>
  <div class="container">
    <h2>OTP Verification</h2>
    <img src="https://cdn-icons-png.flaticon.com/512/747/747376.png" alt="Lock Icon" class="otp-img" />
    <p class="info-text">OTP code has been sent your e-mail.</p>

    <form method="POST" action="reg.php">
      <div class="otp-inputs">
        <input type="text" name="digit1" maxlength="1" required autocomplete="off" />
        <input type="text" name="digit2" maxlength="1" required autocomplete="off" />
        <input type="text" name="digit3" maxlength="1" required autocomplete="off" />
        <input type="text" name="digit4" maxlength="1" required autocomplete="off" />
        <input type="text" name="digit5" maxlength="1" required autocomplete="off" />
      </div>

      <div class="resend">
        Didn't receive a code? <a href="?resend">Resend</a>
      </div>

      <button type="submit" name="submit" value="otp" class="verify-btn">Verify OTP</button>
    </form>
  </div>

  <script>
    const inputs = document.querySelectorAll(".otp-inputs input");

    inputs.forEach((input, index) => {
      input.addEventListener("input", (e) => {
        const value = e.target.value;
        if (/[^0-9]/.test(value)) {
          input.value = "";
          return;
        }

        if (value && index < inputs.length - 1) {
          inputs[index + 1].focus();
        }
      });

      input.addEventListener("keydown", (e) => {
        if (e.key === "Backspace" && !input.value && index > 0) {
          inputs[index - 1].focus();
        }
      });
    });
  </script>
</body>

</html>