<?php
    session_start();

    // Uncomment the below code to seed the database
    // include "../backend/database/setupdb.php";

    $_SESSION["logged_in"] = false;
    
    header("Location: home.php");
    exit()
?>
