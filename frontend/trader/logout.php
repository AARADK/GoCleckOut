<?php
session_start();
print_r($_SESSION);
header('Location: ../login/login_portal.php?sign_in=true');
session_destroy();