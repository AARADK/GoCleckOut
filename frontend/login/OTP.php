<?php

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
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
        <input type="text" name="digit1" maxlength="1" required />
        <input type="text" name="digit2" maxlength="1" required />
        <input type="text" name="digit3" maxlength="1" required />
        <input type="text" name="digit4" maxlength="1" required />
        <input type="text" name="digit5" maxlength="1" required />
      </div>

      <div class="resend">
        Didn't receive a code? <a href="#">Resend</a>
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
