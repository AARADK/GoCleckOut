<?php
session_start();
require '../backend/database/connect.php';

$user_id = $_SESSION['user_id'] ?? 1;

$conn = getDBConnection();

if ($conn) {
    $sql = "SELECT full_name, email, phone_no, role, created_date, status FROM users WHERE user_id = :user_id";
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
    <style>
        .profile-info {
            font-size: 0.9rem;
        }
        .profile-info .list-group-item {
            padding: 0.5rem 1rem;
        }
        .profile-info strong {
            min-width: 120px;
            display: inline-block;
        }
    </style>
</head>

<body class="bg-light">
    <?php include 'header.php'; ?>

    <div class="container py-4">
        <div class="card shadow-sm mx-auto" style="max-width: 500px;">
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <h4 class="mb-1"><?php echo htmlspecialchars($user['FULL_NAME']); ?></h4>
                    <span class="badge bg-secondary text-capitalize"><?php echo htmlspecialchars($user['ROLE']); ?></span>
                </div>

                <ul class="list-group list-group-flush profile-info">
                    <li class="list-group-item d-flex align-items-center">
                        <strong>Name:</strong> <?php echo htmlspecialchars($user['FULL_NAME']); ?>
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <strong>Email:</strong> <?php echo htmlspecialchars($user['EMAIL']); ?>
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <strong>Phone:</strong> <?php echo htmlspecialchars($user['PHONE_NO']); ?>
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <strong>Created:</strong> <?php echo htmlspecialchars($user['CREATED_DATE'] ?? 'N/A'); ?>
                    </li>
                    <li class="list-group-item d-flex align-items-center">
                        <strong>Status:</strong> 
                        <span class="badge <?php echo $user['STATUS'] === 'active' ? 'bg-success' : 'bg-warning'; ?>">
                            <?php echo htmlspecialchars($user['STATUS']); ?>
                        </span>
                    </li>
                </ul>

                <div class="d-flex justify-content-center gap-2 mt-3">
                    <a href="user/update.php" class="btn btn-danger btn-sm">Update Profile</a>
                    <a href="<?php echo $homePage; ?>" class="btn btn-secondary btn-sm">Back to Home</a>
                </div>
            </div>
        </div>
    </div>

    <?php include "footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
