<?php

require_once '../../backend/database/connect.php'; // Ensure we use the correct file to get the Oracle DB connection

// Get the database connection
$conn = getDBConnection();

// Prepare the SQL query using OCI
$sql = "SELECT * FROM USERS WHERE user_id = :user_id";
$stmt = oci_parse($conn, $sql);

// Bind the user_id to the statement
oci_bind_by_name($stmt, ":user_id", $_SESSION['user_id']);

// Execute the query
oci_execute($stmt);

// Fetch the result as an associative array
$data = oci_fetch_assoc($stmt);

// Check if data is returned and contains 'full_name'
if ($data && isset($data['FULL_NAME'])) {
    $name = $data['FULL_NAME'];
    $email = $data['EMAIL'];
    $phone_no = $data['PHONE_NO'];

    echo '<h2>Trader Profile</h2> <br>';
    echo 'Name: ' . htmlspecialchars($name) . '<br>';
    echo 'Email: ' . htmlspecialchars($email) . '<br>';
    echo 'Contact Number: ' . htmlspecialchars($phone_no) . '<br>';
} else {
    echo 'User not found or the "full_name" column does not exist.';
}

?>
