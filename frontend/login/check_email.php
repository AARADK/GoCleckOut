<?php
require_once '../../backend/database/connect.php';

header('Content-Type: application/json');

if (!isset($_GET['email'])) {
    echo json_encode(['error' => 'Email parameter is required']);
    exit;
}

$email = $_GET['email'];
$conn = getDBConnection();

if (!$conn) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Check if email exists
$sql = "SELECT COUNT(*) as count FROM users WHERE email = :email";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':email', $email);
oci_execute($stmt);

$row = oci_fetch_array($stmt, OCI_ASSOC);
$exists = $row['COUNT'] > 0;

oci_free_statement($stmt);
oci_close($conn);

echo json_encode([
    'exists' => $exists,
    'message' => $exists ? 'This email is already registered' : 'Email is available'
]); 