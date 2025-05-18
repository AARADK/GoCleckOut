<?php
session_start();
require_once '../../database/connect.php';
require 'otp_sender.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone_no = $_POST['phone_no'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_GET['type'] ?? 'customer';

    if ($password != $confirm_password) {
        echo "Passwords do not match!";
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $conn = getDBConnection();
    $sql = "SELECT * FROM users WHERE email = :email";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":email", $email);

    oci_execute($stmt);
    $user = oci_fetch_assoc($stmt);

    if ($user) {
        echo "Email already registered!";
        exit();
    }

    if ($role === 'trader' && !empty($shop_type)) {
        $sql = "SELECT COUNT(*) FROM shops WHERE shop_type = :shop_type";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ":shop_type", $shop_type);

        oci_execute($stmt);
        $count = oci_fetch_assoc($stmt)['COUNT(*)']; 

        if ($count > 0) {
            echo "This category is already taken!";
            exit();
        }

        $sql = "SELECT user_id FROM users WHERE email = :email";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ":email", $email);

        oci_execute($stmt);
        $user_id = oci_fetch_assoc($stmt)['USER_ID'];

        if ($user_id) {
            $_SESSION['user_id'] = $user_id;
            exit();
        } else {
            $_SESSION['user_id'] = 0;
            exit();
        }
    }

    $sql = "INSERT INTO users (full_name, email, password, phone_no, role, status) 
            VALUES (:full_name, :email, :password, :phone_no, :role, 'pending')";
    
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":full_name", $full_name);
    oci_bind_by_name($stmt, ":email", $email);
    oci_bind_by_name($stmt, ":password", $hashed_password);
    oci_bind_by_name($stmt, ":phone_no", $phone_no);
    oci_bind_by_name($stmt, ":role", $role);

    if (oci_execute($stmt)) {
        $_SESSION['otp'] = sendOTP($email);
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role; 

        header("Location: verify.php?type=fromregister");
        exit();
    } else {
        echo "Error occurred while registering.";
    }

    $sql = "SELECT user_id FROM users WHERE email = :email";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ":email", $email);

    oci_execute($stmt);
    $user_id = oci_fetch_assoc($stmt)['USER_ID'];

    if ($user_id) {
        $_SESSION['user_id'] = $user_id;
        exit();
    } else {
        $_SESSION['user_id'] = 0;
        exit();
    }
}
?>
