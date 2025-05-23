<?php
session_start();

require_once '../../backend/database/connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $erroremail = $_GET['error_email'] ?? '';
    $errorpassword = $_GET['error_password'] ?? '';
    $tempemail = $_GET['email'] ?? '';
    unset($_GET['error_email']);
    unset($_GET['error_password']);
    unset($_GET['email']);
}

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

        .btn-primary:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }

        .form-disabled {
            opacity: 0.7;
            pointer-events: none;
        }

        select option {
            background: rgb(83 83 83 / 51%);
        }

        select option:hover {
            background: rgb(155 83 83 / 51%);
        }

        .password-requirements {
            font-size: 0.7rem;
            color: #ff6b6b;
            margin-top: 2px;
            text-align: left;
        }

        .requirement {
            color: #ccc;
            margin: 2px 0;
        }

        .requirement.valid {
            color: #4CAF50;
        }

        .requirement.invalid {
            color: #ff6b6b;
        }

        .error-message {
            color: #ff6b6b;
            font-size: 0.7rem;
            margin-top: 2px;
            text-align: left;
        }

        .password-requirement-bubble {
            position: absolute;
            background: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.7rem;
            margin-left: 10px;
            display: none;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .password-requirement-bubble::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 50%;
            transform: translateY(-50%);
            border-width: 6px 6px 6px 0;
            border-style: solid;
            border-color: transparent #333 transparent transparent;
        }

        .password-input-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .requirement-met {
            color: #4CAF50;
        }

        .requirement-not-met {
            color: #ff6b6b;
        }

        .password-requirement {
            font-size: 0.7rem;
            margin-top: 4px;
            text-align: left;
            min-height: 16px;
        }

        .email-status {
            font-size: 0.7rem;
            margin-top: 4px;
            text-align: left;
            min-height: 16px;
        }

        .email-valid {
            color: #4CAF50;
        }

        .email-invalid {
            color: #ff6b6b;
        }

        .email-checking {
            color: #666;
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
                    <input type="text" class="form-control" id="email" name="email" placeholder="Enter email" value="<?php echo htmlspecialchars($tempemail); ?>">
                    <small class="text-danger"><?php echo $erroremail; ?></small>
                </div>
                <div class="mb-1">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter password">
                    <small class="text-danger"><?php echo $errorpassword; ?></small>
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
                    <input type="text" class="form-control" id="username" name="username" placeholder="Choose a username" value="">
                </div>
                <div class="mb-1">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter email" value="<?php echo htmlspecialchars($submitted_name); ?>" oninput="validateEmail(this)">
                    <div id="emailStatus" class="email-status"></div>
                </div>
                <div class="mb-1">
                    <label for="phone">Phone</label>
                    <input type="text" class="form-control" id="phone" name="phone" placeholder="Enter phone number" value="">
                </div>
                <div class="mb-1">
                    <label for="role">Role</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="role" id="inlineRadio1" value="customer" checked>
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
                    <input type="password" class="form-control" id="password" name="password" placeholder="Create password" oninput="validatePassword(this)">
                    <div id="passwordRequirement" class="password-requirement"></div>
                </div>
                <div class="mb-1">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" value="" placeholder="Confirm password">
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

        let emailTimeout;
        let isEmailRegistered = false;

        function validateEmail(input) {
            const email = input.value;
            const emailStatus = document.getElementById('emailStatus');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const submitButton = document.querySelector('button[type="submit"]');
            
            // Clear any existing timeout
            if (emailTimeout) {
                clearTimeout(emailTimeout);
            }

            // Reset button state
            isEmailRegistered = false;
            submitButton.disabled = false;

            // Basic format validation
            if (!email) {
                emailStatus.textContent = '';
                emailStatus.className = 'email-status';
                return;
            }

            if (!emailRegex.test(email)) {
                emailStatus.textContent = 'Please enter a valid email address';
                emailStatus.className = 'email-status email-invalid';
                return;
            }

            // Show checking status
            emailStatus.textContent = 'Checking email availability...';
            emailStatus.className = 'email-status email-checking';

            // Set timeout to prevent too many requests
            emailTimeout = setTimeout(() => {
                // Check email availability
                fetch(`check_email.php?email=${encodeURIComponent(email)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            emailStatus.textContent = 'Error checking email';
                            emailStatus.className = 'email-status email-invalid';
                            return;
                        }

                        if (data.exists) {
                            emailStatus.textContent = data.message;
                            emailStatus.className = 'email-status email-invalid';
                            isEmailRegistered = true;
                            submitButton.disabled = true;
                        } else {
                            emailStatus.textContent = data.message;
                            emailStatus.className = 'email-status email-valid';
                        }
                    })
                    .catch(error => {
                        emailStatus.textContent = 'Error checking email';
                        emailStatus.className = 'email-status email-invalid';
                    });
            }, 500);
        }

        const passwordRequirements = [
            { id: 'length', text: 'At least 8 characters', regex: /.{8,}/ },
            { id: 'lowercase', text: 'At least one lowercase letter', regex: /[a-z]/ },
            { id: 'uppercase', text: 'At least one uppercase letter', regex: /[A-Z]/ },
            { id: 'number', text: 'At least one number', regex: /[0-9]/ },
            { id: 'special', text: 'At least one special character', regex: /[!@#$%^&*(),.?":{}|<>]/ }
        ];

        let currentRequirementIndex = 0;

        function validatePassword(input) {
            const password = input.value;
            const requirementElement = document.getElementById('passwordRequirement');
            
            // If password is empty, clear the requirement text
            if (!password) {
                requirementElement.textContent = '';
                requirementElement.className = 'password-requirement';
                return;
            }

            // Check current requirement
            const currentRequirement = passwordRequirements[currentRequirementIndex];
            const isMet = currentRequirement.regex.test(password);

            // Update requirement text and style
            requirementElement.textContent = currentRequirement.text;
            requirementElement.className = 'password-requirement ' + 
                (isMet ? 'requirement-met' : 'requirement-not-met');

            // If current requirement is met, move to next one
            if (isMet) {
                currentRequirementIndex = (currentRequirementIndex + 1) % passwordRequirements.length;
                // If we've checked all requirements and they're all met, clear the text
                if (currentRequirementIndex === 0 && passwordRequirements.every(req => req.regex.test(password))) {
                    requirementElement.textContent = '';
                    requirementElement.className = 'password-requirement';
                }
            }
        }

        // Update form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const emailStatus = document.getElementById('emailStatus');
            
            if (!emailRegex.test(email)) {
                e.preventDefault();
                emailStatus.textContent = 'Please enter a valid email address';
                emailStatus.className = 'email-status email-invalid';
                return;
            }

            if (isEmailRegistered) {
                e.preventDefault();
                return;
            }

            // Password validation remains the same
            const password = document.getElementById('password').value;
            const allRequirementsMet = passwordRequirements.every(req => req.regex.test(password));
            if (!allRequirementsMet) {
                e.preventDefault();
                currentRequirementIndex = passwordRequirements.findIndex(req => !req.regex.test(password));
                validatePassword(document.getElementById('password'));
                return;
            }
        });
    </script>

</body>

</html>