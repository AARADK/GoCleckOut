<?php
require_once "../../backend/database/connect.php";

if (session_status() != PHP_SESSION_ACTIVE) session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getDBConnection();

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $sql = "SELECT COUNT(*) AS count FROM USERS WHERE email = :email";
    $stmt = oci_parse($conn, $sql);
    oci_bind_by_name($stmt, ':email', $email);
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);

    if ($row && $row['COUNT'] == 1) {

        $sql = "SELECT password FROM USERS WHERE email = :email";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':email', $email);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        $hashedPassword = $row['PASSWORD'];

        if (password_verify((string)$password, (string)$hashedPassword)) {

            $sql = "SELECT user_id FROM USERS WHERE email = :email";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':email', $email);
            oci_execute($stmt);
            $row = oci_fetch_assoc($stmt);
            $user_id = $row['USER_ID'];

            $_SESSION['user_id'] = $user_id;

            $sql = "SELECT role FROM USERS WHERE user_id = :user_id";
            $stmt = oci_parse($conn, $sql);
            oci_bind_by_name($stmt, ':user_id', $user_id);
            oci_execute($stmt);
            $row = oci_fetch_assoc($stmt);
            $role = $row['ROLE'];

            $_SESSION['role']  = $role;
            $_SESSION['logged_in'] = true;

            // Step 6: Redirect based on role
            switch ($role) {
                case 'admin':
                    header('Location: ../admin/index.php');
                    exit();

                case 'trader':
                    header('Location: ../trader/index.php');
                    exit();

                case 'customer':
                    header('Location: ../home.php?logged_in=true');
                    exit();

                default:
                    echo 'Invalid role';
                    break;
            }

        } else {
            echo 'Invalid password';
        }

    } else {
        echo 'Email not found';
    }
}
