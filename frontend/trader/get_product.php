<?php
require_once '../../backend/database/db_connection.php';
session_start();

// Check if user is logged in and is a trader
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trader') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Product ID is required']);
    exit;
}

$product_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

$conn = getDBConnection();

// Get product details
$sql = "SELECT p.*, s.shop_name 
        FROM product p 
        JOIN shops s ON p.shop_id = s.shop_id 
        WHERE p.product_id = :product_id 
        AND s.user_id = :user_id";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":product_id", $product_id);
oci_bind_by_name($stmt, ":user_id", $user_id);
oci_execute($stmt);

$product = oci_fetch_array($stmt, OCI_ASSOC);

if ($product) {
    // Handle LOB fields
    foreach ($product as $key => $value) {
        if (is_object($value) && get_class($value) === 'OCI-Lob') {
            if ($key === 'PRODUCT_IMAGE') {
                $image_data = $value->load();
                $product[$key] = "data:image/jpeg;base64," . base64_encode($image_data);
            } else {
                $product[$key] = $value->load();
            }
        }
    }
    echo json_encode($product);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Product not found']);
}

oci_free_statement($stmt);
oci_close($conn); 