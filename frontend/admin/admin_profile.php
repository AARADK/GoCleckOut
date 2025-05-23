<?php
require_once '../../backend/database/db_connection.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login/login_portal.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get user data
$sql = "SELECT full_name, email, phone_no 
        FROM users 
        WHERE user_id = :user_id";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":user_id", $user_id);
oci_execute($stmt);
$data = oci_fetch_array($stmt, OCI_ASSOC);
oci_free_statement($stmt);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $phone_no = $_POST['phone_no'];
    
    $update_sql = "UPDATE users 
                   SET full_name = :full_name,
                       phone_no = :phone_no
                   WHERE user_id = :user_id";
    
    $update_stmt = oci_parse($conn, $update_sql);
    oci_bind_by_name($update_stmt, ":full_name", $full_name);
    oci_bind_by_name($update_stmt, ":phone_no", $phone_no);
    oci_bind_by_name($update_stmt, ":user_id", $user_id);
    
    if (oci_execute($update_stmt)) {
        $_SESSION['success_msg'] = "Profile updated successfully!";
        header('Location: admin_profile.php');
        exit;
    } else {
        $_SESSION['error_msg'] = "Failed to update profile.";
    }
    oci_free_statement($update_stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | GoCleckOut</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-card {
            max-width: 800px;
            margin: 0 auto;
        }
        .form-control:disabled {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include 'admin_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'admin_sidebar.php'; ?>
            </div>
            <div class="col-md-9 col-lg-10">
                <div class="container my-5">
                    <div class="profile-card">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">My Profile</h5>
                                <button type="button" class="btn btn-outline-primary" id="editButton" style="background-color: #ff6b6b; border-color: #ff6b6b; color: white">
                                    <i class="material-icons">edit</i> Edit Profile
                                </button>
                            </div>
                            <div class="card-body">
                                <form id="profileForm" method="POST" class="needs-validation" novalidate>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Full Name</label>
                                            <input type="text" class="form-control" name="full_name" 
                                                   value="<?= htmlspecialchars($data['FULL_NAME']) ?>" disabled required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?= htmlspecialchars($data['EMAIL']) ?>" disabled required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" name="phone_no" 
                                                   value="<?= htmlspecialchars($data['PHONE_NO']) ?>" disabled required>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4 d-none" id="saveButtons">
                                        <button type="submit" name="update_profile" class="btn btn-primary" style="background-color: #ff6b6b; border-color: #ff6b6b;">
                                            <i class="material-icons">save</i> Save Changes
                                        </button>
                                        <button type="button" class="btn btn-secondary" id="cancelButton">
                                            <i class="material-icons">close</i> Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const editButton = document.getElementById('editButton');
            const cancelButton = document.getElementById('cancelButton');
            const saveButtons = document.getElementById('saveButtons');
            const form = document.getElementById('profileForm');
            const inputs = form.querySelectorAll('input[name]');
            const originalValues = {};

            // Store original values
            inputs.forEach(input => {
                originalValues[input.name] = input.value;
            });

            editButton.addEventListener('click', function() {
                // Enable inputs except email
                inputs.forEach(input => {
                    if (input.name !== 'email') {
                        input.disabled = false;
                    }
                });
                
                // Show save buttons
                saveButtons.classList.remove('d-none');
                
                // Hide edit button
                editButton.classList.add('d-none');
            });

            cancelButton.addEventListener('click', function() {
                // Restore original values and disable all inputs
                inputs.forEach(input => {
                    input.value = originalValues[input.name];
                    input.disabled = true;
                });
                
                // Hide save buttons
                saveButtons.classList.add('d-none');
                
                // Show edit button
                editButton.classList.remove('d-none');
            });

            // Form validation
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });

        // Show success/error messages
        <?php if (isset($_SESSION['success_msg'])): ?>
            const toast = new bootstrap.Toast(document.createElement('div'));
            toast._element.classList.add('toast', 'bg-success', 'text-white', 'position-fixed', 'top-0', 'end-0', 'm-3');
            toast._element.innerHTML = `
                <div class="toast-body">
                    <?= $_SESSION['success_msg'] ?>
                </div>
            `;
            document.body.appendChild(toast._element);
            toast.show();
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
            const errorToast = new bootstrap.Toast(document.createElement('div'));
            errorToast._element.classList.add('toast', 'bg-danger', 'text-white', 'position-fixed', 'top-0', 'end-0', 'm-3');
            errorToast._element.innerHTML = `
                <div class="toast-body">
                    <?= $_SESSION['error_msg'] ?>
                </div>
            `;
            document.body.appendChild(errorToast._element);
            errorToast.show();
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
