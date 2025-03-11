<!-- <?php
    // session_start();
    // $_SESSION["sess_id"] = 'c7466773'; 

    // include_once "header.php";
    // include "home.php";
    // include "footer.php"; 
?>    
-->

<?php

    session_start();

    $_SESSION["logged_in"] = false;

    header("Location: home.php");
    // include "login/login_portal.php";