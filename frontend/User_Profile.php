<?php
session_start();
require '../backend/database/connect.php';

$user_id = $_SESSION['user_id'] ?? 1;

$conn = getDBConnection();

if ($conn) {
    $sql = "SELECT full_name, email, role, created_date, status FROM users WHERE user_id = :user_id";
    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ':user_id', $user_id);
    oci_execute($stmt);

    $user = oci_fetch_assoc($stmt);

    if (!$user) {
        echo "User not found.";
        exit();
    }
} else {
    echo "Database connection failed.";
    exit();
}

$homePage = ($user["ROLE"] === "trader") ? "trader_dashboard.php" : "home.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Profile Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light">
    <?php include 'header.php'; ?>

    <div class="container py-5 d-flex justify-content-center align-items-center min-vh-100">
        <div class="card shadow p-4 w-100" style="max-width: 600px;">
            <div class="text-center mb-3">
                <div class="display-1">👤</div>
                <h2 class="mb-1"><?php echo htmlspecialchars($user['FULL_NAME']); ?></h2>
                <span class="text-secondary fw-semibold text-capitalize"><?php echo htmlspecialchars($user['ROLE']); ?></span>
                <p class="fst-italic text-muted mt-2"><?php echo htmlspecialchars($user['ABOUT'] ?? 'No bio available.'); ?></p>
            </div>

            <ul class="list-group list-group-flush mb-3">
                <li class="list-group-item"><strong>Name:</strong> <?php echo htmlspecialchars($user['FULL_NAME']); ?></li>
                <li class="list-group-item"><strong>Email:</strong> <?php echo htmlspecialchars($user['EMAIL']); ?></li>
                <li class="list-group-item"><strong>Role:</strong> <?php echo htmlspecialchars($user['ROLE']); ?></li>
                <li class="list-group-item"><strong>Created Date:</strong> <?php echo htmlspecialchars($user['CREATED_DATE'] ?? 'N/A'); ?></li>
                <li class="list-group-item"><strong>Status:</strong> <?php echo htmlspecialchars($user['STATUS']); ?></li>
            </ul>

            <div class="d-flex justify-content-center gap-2">
                <a href="user/update.php" class="btn btn-danger">Update</a>
                <a href="<?php echo $homePage; ?>" class="btn btn-secondary">Home</a>
            </div>
        </div>
    </div>

    <?php include "footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
