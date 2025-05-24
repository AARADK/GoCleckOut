<?php
function getDBConnection() {
    $username = "DB"; 
    $password = "DB";  
    $connection_string = "//localhost/xe"; 
    $conn = oci_connect($username, $password, $connection_string);

    if (!$conn) {
        $e = oci_error();
        die ("Connection Error: " . $e['message']);
    }

    return $conn;
}

?>
