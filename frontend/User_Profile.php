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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .profile-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .profile-card {
            background-color: #fdfaf5;
            padding: 20px;
            width: 50%;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .profile-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }

        h2 {
            margin: 10px 0;
        }

        strong {
            color: #333;
        }

        .rating {
            color: #ffb400;
            font-size: 20px;
        }

        .about {
            font-style: italic;
            margin: 10px 0;
        }

        .profile-details {
            text-align: left;
            margin-top: 15px;
        }

        .detail-item {
            margin: 5px 0;
        }

        .edit-btn {
            display: inline-block;
            background-color: #d84040;
            color: white;
            padding: 8px 12px;
            text-decoration: none;
            margin-top: 10px;
            border-radius: 5px;
        }

        .edit-btn:hover {
            background-color: #c13030;
        }

        /* Footer Styles */
        .footer {
            background-color: #f0f0f0;
            padding: 40px 5% 0;
            color: #333;
            font-family: 'Poppins', sans-serif;
        }

        .footer-container {
            display: flex;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .footer-column {
            flex: 1;
            min-width: 180px;
            margin-bottom: 30px;
        }

        .footer-column h3 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            font-weight: 700;
            color: #333;
        }

        .footer-column ul {
            list-style: none;
            padding: 0;
        }

        .footer-column ul li {
            margin-bottom: 10px;
        }

        .footer-column ul li a {
            text-decoration: none;
            color: #333;
            transition: color 0.3s;
        }

        .footer-column ul li a:hover {
            color: #007bff;
        }

        .footer-column p {
            font-size: 1rem;
            color: #555;
        }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-top: 1px solid #ddd;
            margin-top: 20px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .footer-bottom p {
            margin: 0;
        }

        .footer-bottom a {
            text-decoration: none;
            color: #007bff;
        }

        .footer-bottom .social-icons a {
            margin-left: 10px;
            color: #333;
            font-size: 1.2rem;
            transition: color 0.3s;
        }

        .footer-bottom .social-icons a:hover {
            color: #007bff;
        }

        @media (max-width: 768px) {
            .nav {
                display: none;
                position: absolute;
                top: 60px;
                left: 0;
                width: 100%;
                background-color: white;
                box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
            }

            .nav ul {
                flex-direction: column;
                align-items: center;
            }

            .nav ul li {
                margin: 10px 0;
            }

            .product-container {
                flex-direction: column;
                padding: 20px;
                gap: 20px;
            }

            .product-image,
            .product-info {
                min-width: 100%;
            }

            .footer-container {
                flex-direction: column;
            }

            .footer-column {
                min-width: 100%;
            }

            .footer-bottom {
                flex-direction: column;
                text-align: center;
            }

            .footer-bottom .social-icons {
                margin-top: 15px;
            }
        }
    </style>
</head>

<body>
    <?php include 'header.php' ?>
    <div class="profile-container">
        <div class="profile-card">
            <div class="profile-icon">👤</div>
            <h2><?php echo htmlspecialchars($user['FULL_NAME']); ?></h2>
            <strong><?php echo htmlspecialchars(ucfirst($user['ROLE'])); ?></strong>
            <p class="about"><?php echo htmlspecialchars($user['ABOUT'] ?? 'No bio available.'); ?></p>

            <div class="profile-details">
                <div class="detail-item"><strong>Name:</strong> <?php echo htmlspecialchars($user['FULL_NAME']); ?></div>
                <div class="detail-item"><strong>Email:</strong> <?php echo htmlspecialchars($user['EMAIL']); ?></div>
                <div class="detail-item"><strong>Role:</strong> <?php echo htmlspecialchars($user['ROLE']); ?></div>
                <div class="detail-item"><strong>Created Date:</strong> <?php echo htmlspecialchars($user['CREATED_DATE'] ?? 'N/A'); ?></div>
                <div class="detail-item"><strong>Status:</strong> <?php echo htmlspecialchars($user['STATUS']); ?></div>
                <div>
                    <a href="user/update.php" class="edit-btn">Update</a>
                    <a href="<?php echo $homePage; ?>" class="edit-btn">Home</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>