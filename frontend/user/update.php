<?php 
session_start();
require_once '../../backend/database/connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login_portal.php');
    exit();
}

$user_id = $_SESSION['user_id'];
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Update Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .form-label {
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }
        .form-control {
            font-size: 0.9rem;
            padding: 0.4rem 0.75rem;
        }
        .form-control:disabled {
            background-color: #f8f9fa;
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }
    </style>
</head>
<body>
    <?php include '../header.php' ?> 

    <div class="container py-4">
        <div class="card shadow-sm mx-auto" style="max-width: 500px;">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">Update Profile</h5>

                <div class="mb-2">
                    <label for="fullname" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['FULL_NAME']); ?>">
                </div>

                <div class="mb-2">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['EMAIL']); ?>" disabled>
                </div>

                <div class="mb-2">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['PHONE_NO']); ?>">
                </div>

                <div class="mb-2">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['ROLE']); ?>" disabled>
                </div>

                <div class="mb-2">
                    <label class="form-label">Account Created</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['CREATED_DATE']); ?>" disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['STATUS']); ?>" disabled>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.history.back()">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="updateProfile()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <?php include '../footer.php' ?> 

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showToast(message, type = 'success') {
            const toastContainer = document.querySelector('.toast-container');
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
            bsToast.show();
            
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }

        function updateProfile() {
            const fullname = document.getElementById('fullname').value;
            const phone = document.getElementById('phone').value;

            if (!fullname || !phone) {
                showToast('Please fill in all required fields', 'danger');
                return;
            }

            const formData = new FormData();
            formData.append('fullname', fullname);
            formData.append('phone', phone);

            fetch('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Profile updated successfully');
                    setTimeout(() => {
                        window.location.href = '../User_Profile.php';
                    }, 1000);
                } else {
                    showToast(data.message || 'Failed to update profile', 'danger');
                }
            })
            .catch(error => {
                showToast('Error updating profile', 'danger');
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>
