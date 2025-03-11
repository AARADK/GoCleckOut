<?php
    session_start();

    function get_login() {
        if (isset($_GET['sign_in'])) {
            $signIn = $_GET['sign_in'];
    
            if ($signIn === 'true') {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewp   ort" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../frontend/css/login.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body>
    <div class="container mt-4">
        <div class="container mt-6">
            <form method="post" action="">
                <?php
                
                    get_login()
                
                ?>
            </form>
        </div>
    </div>
</body>
</html>