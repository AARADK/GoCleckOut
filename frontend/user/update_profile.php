<?php
session_start();
require_once '../../backend/database/connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$user_id = $_SESSION['user_id'];
$fullname = $_POST['fullname'] ?? '';
$phone = $_POST['phone'] ?? '';

if (empty($fullname) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

$conn = getDBConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

try {
    $sql = "UPDATE users SET full_name = :fullname, phone_no = :phone WHERE user_id = :user_id";
    $stmt = oci_parse($conn, $sql);
    
    oci_bind_by_name($stmt, ':fullname', $fullname);
    oci_bind_by_name($stmt, ':phone', $phone);
    oci_bind_by_name($stmt, ':user_id', $user_id);
    
    if (oci_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error updating profile: ' . $e->getMessage()]);
}

oci_close($conn); 