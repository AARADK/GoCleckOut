<?php
require '../database/db_connection.php';
$conn = getDBConnection();
if (isset($_POST['product_id'])) {
    $product_id = $_POST['product_id'];

    $query = "DELETE FROM product_cart WHERE product_id = :product_id";
    $stmt = oci_parse($conn, $query);

    // Bind the product_id parameter
    oci_bind_by_name($stmt, ":product_id", $product_id);

    // Execute the query
    if (oci_execute($stmt)) {
        echo "success";
    } else {
        $e = oci_error($stmt);
        echo "Error: " . $e['message'];
    }

    oci_free_statement($stmt);
}
?>
