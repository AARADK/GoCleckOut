<?php
session_start();

require_once '../../backend/database/connect.php';

$categories = [];
$conn = getDBConnection();

if ($conn) {
    $query = "
        SELECT p.category_id, p.category_name
        FROM product_category p
        WHERE p.category_id NOT IN (
        SELECT u.category_id FROM users u
        WHERE u.role = 'trader'
        AND u.status = 'active'
        )
    ";
    $stid = oci_parse($conn, $query);
    oci_execute($stid);

    while ($row = oci_fetch_array($stid, OCI_ASSOC + OCI_RETURN_NULLS)) {
        // $categories[] = $row['CATEGORY_NAME'];
        $categories[] = $row;
    }

    oci_free_statement($stid);
    oci_close($conn);
}

function get_login()
{
    return isset($_GET['sign_in']) ? $_GET['sign_in'] === 'true' : null;
}

$error_message = '';
$success_message = '';
$submitted_name = '';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .bg-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1;
        }

        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Poppins', sans-serif;
        }

        .auth-container {
            background: rgb(83 83 83 / 51%);
            backdrop-filter: blur(10px);
            padding: 12px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 360px;
            text-align: center;
            color: white;
        }

        h2 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 6px;
        }

        label {
            font-size: 0.7rem;
            font-weight: 500;
            display: block;
            text-align: left;
            margin-bottom: 3px;
            color: white;
        }

        .form-control {
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 5px 10px;
            font-size: 0.75rem;
            background: transparent !important;
            color: white;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .form-control:focus {
            color: white;
            background: transparent !important;
            border-color: #3B82F6;
            outline: none;
        }

        .btn {
            width: 100%;
            border-radius: 15px;
            padding: 5px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: 0.3s;
        }

        .btn-primary {
            background-color: #f75f5f;
            border: none;
        }

        .btn-primary:hover {
            background-color: rgb(83, 83, 83);
        }

        select option {
            background: rgb(83 83 83 / 51%);
        }

        select option:hover {
            background: rgb(155 83 83 / 51%);
        }

    </style>
</head>

<body>
    <img src="../assets/3417733.jpg" alt="Background Image" class="bg-image">

    <div class="auth-container">
        <?php
        $login_status = get_login();
        if ($login_status === true): ?>
            <h2>Login</h2>
            <form method="post" action="login.php">
                <div class="mb-1">
                    <label for="email">Email</label>
                    <input type="text" class="form-control" id="email" name="email" placeholder="Enter email" value="<?php echo htmlspecialchars($submitted_name); ?>">
                </div>
                <div class="mb-1">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter password">
                </div>                                                                    
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
            <p class="small-text">
                <?php echo $login_status === true ? 'No account? <a href="?sign_in=false" style="color: #FFF;">Register</a>' : 'Have an account? <a href="?sign_in=true" style="color: #FFF;">Login</a>'; ?>
            </p>
        <?php elseif ($login_status === false): ?>
            <h2>Register</h2>
            <form method="post" action="reg.php" autocomplete="off">
                <div class="mb-1">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Choose a username" value="Aarjan">
                </div>
                <div class="mb-1">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter email" value="<?php echo htmlspecialchars($submitted_name); ?>" oninput="updateHiddenEmail()">
                </div>
                <div class="mb-1">
                    <label for="phone">Phone</label>
                    <input type="text" class="form-control" id="phone" name="phone" placeholder="Enter phone number" value="9840204215">
                </div>
                <div class="mb-1">
                    <label for="role">Role</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="role" id="inlineRadio1" value="customer">
                        <label class="form-check-label" for="inlineRadio1">User</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="role" id="inlineRadio2" value="trader">
                        <label class="form-check-label" for="inlineRadio2">Trader</label>
                    </div>
                </div>
                <div class="mb-1" id="categoryDropdown" style="display: none;">
                    <label for="category">Product Category</label>
                    <select class="form-control" id="category" name="category">
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['CATEGORY_ID']); ?>">
                                <?php echo htmlspecialchars($cat['CATEGORY_NAME']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-1">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" value="123" placeholder="Create password">
                </div>
                <div class="mb-1">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" value="123" placeholder="Confirm password">
                </div>                                                                                               
                <button type="submit" name="submit" class="btn btn-primary" value="register">Register</button>
            </form>
            <p class="small-text">Have an account? <a href="?sign_in=true" style="color: #FFF;">Login</a></p>
        <?php else: ?>
            <p class="text-center text-danger" style="font-size: 0.75rem;">Invalid access. Please use a valid sign_in parameter.</p>
        <?php endif; ?>
    </div>

    </div>

    <script>
        const traderRadio = document.getElementById('inlineRadio2');
        const userRadio = document.getElementById('inlineRadio1');
        const dropdown = document.getElementById('categoryDropdown');

        traderRadio.addEventListener('change', function() {
            if (traderRadio.checked) {
                dropdown.style.display = 'block';
            }
        });

        userRadio.addEventListener('change', function() {
            if (userRadio.checked) {
                dropdown.style.display = 'none';
            }
        });

        window.addEventListener('DOMContentLoaded', () => {
            if (traderRadio.checked) {
                dropdown.style.display = 'block';
            }
        });
    </script>

</body>

</html>