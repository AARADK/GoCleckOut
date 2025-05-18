<?php

if (session_status() != PHP_SESSION_ACTIVE) session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$user_mail = $_ENV['USER_EMAIL'];
$user_password = $_ENV['USER_PASSWORD'];



function sendOTP($email): bool|int {
    global $user_mail, $user_password;

    $otp = rand(10000, 99999);
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $user_mail;
        $mail->Password = $user_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom($user_mail, 'GoCleckOut');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Code';
        $mail->Body = "Your OTP code is <b>$otp</b>";

        if ($mail->send()) {
            echo "<script>console.log($otp)</script>";
            return $otp;
        } else {
            return false;
        }
    } catch (Exception $e) {
        echo "<br>Error: {$mail->ErrorInfo}";
        return false;
    }
}