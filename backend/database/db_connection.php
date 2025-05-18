<?php
include_once 'connect.php';
$conn = getDBConnection();

function executeQuery($conn, $sql)
{
    $stmt = oci_parse($conn, $sql);
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        echo "<p style='color:red;'>Error: {$e['message']}</p>";
    }
    oci_free_statement($stmt);
}