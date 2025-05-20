<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../backend/database/connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not logged in';
    echo json_encode($response);
    exit;
}

if (!isset($_POST['product_id']) || !isset($_POST['action'])) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'];
$action = $_POST['action']; // 'add' or 'remove'

$conn = getDBConnection();

// Get or create wishlist for user
$wishlist_sql = "SELECT wishlist_id FROM wishlist WHERE user_id = :user_id";
$stmt = oci_parse($conn, $wishlist_sql);
oci_bind_by_name($stmt, ":user_id", $user_id);
oci_execute($stmt);
$wishlist = oci_fetch_array($stmt, OCI_ASSOC);
oci_free_statement($stmt);

if (!$wishlist) {
    // Create new wishlist
    $insert_wishlist_sql = "INSERT INTO wishlist (wishlist_id, no_of_items, user_id) VALUES (wishlist_seq.NEXTVAL, 0, :user_id) RETURNING wishlist_id INTO :wishlist_id";
    $stmt = oci_parse($conn, $insert_wishlist_sql);
    oci_bind_by_name($stmt, ":user_id", $user_id);
    oci_bind_by_name($stmt, ":wishlist_id", $wishlist_id, 20);
    oci_execute($stmt);
    oci_free_statement($stmt);
    $wishlist_id = $wishlist_id;
} else {
    $wishlist_id = $wishlist['WISHLIST_ID'];
}

if ($action === 'add') {
    // Check if product is already in wishlist
    $check_sql = "SELECT * FROM wishlist_product WHERE wishlist_id = :wishlist_id AND product_id = :product_id";
    $stmt = oci_parse($conn, $check_sql);
    oci_bind_by_name($stmt, ":wishlist_id", $wishlist_id);
    oci_bind_by_name($stmt, ":product_id", $product_id);
    oci_execute($stmt);
    $exists = oci_fetch_array($stmt, OCI_ASSOC);
    oci_free_statement($stmt);

    if ($exists) {
        $response['message'] = 'Product already in wishlist';
    } else {
        // Add to wishlist
        $insert_sql = "INSERT INTO wishlist_product (wishlist_id, product_id, added_date) VALUES (:wishlist_id, :product_id, SYSDATE)";
        $stmt = oci_parse($conn, $insert_sql);
        oci_bind_by_name($stmt, ":wishlist_id", $wishlist_id);
        oci_bind_by_name($stmt, ":product_id", $product_id);
        $success = oci_execute($stmt);
        oci_free_statement($stmt);

        if ($success) {
            // Update wishlist item count
            $update_count_sql = "UPDATE wishlist SET no_of_items = no_of_items + 1 WHERE wishlist_id = :wishlist_id";
            $stmt = oci_parse($conn, $update_count_sql);
            oci_bind_by_name($stmt, ":wishlist_id", $wishlist_id);
            oci_execute($stmt);
            oci_free_statement($stmt);

            $response['success'] = true;
            $response['message'] = 'Product added to wishlist';
        } else {
            $response['message'] = 'Failed to add product to wishlist';
        }
    }
} else if ($action === 'remove') {
    // Remove from wishlist
    $delete_sql = "DELETE FROM wishlist_product WHERE wishlist_id = :wishlist_id AND product_id = :product_id";
    $stmt = oci_parse($conn, $delete_sql);
    oci_bind_by_name($stmt, ":wishlist_id", $wishlist_id);
    oci_bind_by_name($stmt, ":product_id", $product_id);
    $success = oci_execute($stmt);
    oci_free_statement($stmt);

    if ($success) {
        // Update wishlist item count
        $update_count_sql = "UPDATE wishlist SET no_of_items = no_of_items - 1 WHERE wishlist_id = :wishlist_id";
        $stmt = oci_parse($conn, $update_count_sql);
        oci_bind_by_name($stmt, ":wishlist_id", $wishlist_id);
        oci_execute($stmt);
        oci_free_statement($stmt);

        $response['success'] = true;
        $response['message'] = 'Product removed from wishlist';
    } else {
        $response['message'] = 'Failed to remove product from wishlist';
    }
}

echo json_encode($response);
?> 