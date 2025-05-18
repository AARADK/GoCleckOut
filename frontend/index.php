<!-- <?php
    // session_start();
    // $_SESSION["sess_id"] = 'c7466773'; 

    // include "header.php";
    // include "home.php";
    // include "footer.php"; 
?>    
-->

<?php
    session_start();

    // Uncomment the below code to seed the database
    include "../backend/database/setupdb.php";

    $_SESSION["logged_in"] = false;
    
    header("Location: home.php");
    include "login/login_portal.php";
    exit()
?>
