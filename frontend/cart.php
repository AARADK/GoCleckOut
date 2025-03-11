<?php
session_start();

$username = "";
$password = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";
    $_SESSION["saved_name"] = $username;
    header("Location: ../home.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../frontend/css/login.css">
</head>
<body>  
    <form method="post" action="" autocomplete="off">
        <label for="username">Name:</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>">
        <input type="password" id="password" name="password" value="<?php echo htmlspecialchars($password); ?>">
        <button type="submit">Submit</button>
    </form>

    <?php if (!empty($username)) : ?>
        <p><strong>Submitted Name:</strong> <?php echo htmlspecialchars($username); ?></p>
    <?php endif; ?>

    <?php if (!empty($_SESSION["saved_name"])) : ?>
        <p><strong>Session Stored Name:</strong> <?php echo htmlspecialchars($_SESSION["saved_name"]); ?></p>
    <?php endif; ?>
</body>
</html>