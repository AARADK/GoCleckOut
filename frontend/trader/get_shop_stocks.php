<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once "../../backend/database/connect.php";

// Check if shop_id is provided
if (!isset($_GET['shop_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Shop ID is required']);
    exit;
}

$shop_id = $_GET['shop_id'];
$conn = getDBConnection();

// Get stock data for the specified shop
$sql = "SELECT 
    product_name,
    stock
FROM product 
WHERE shop_id = :shop_id
ORDER BY stock DESC";

$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":shop_id", $shop_id);

if (!oci_execute($stmt)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch stock data']);
    exit;
}

$products = [];
$stocks = [];

while ($row = oci_fetch_assoc($stmt)) {
    $products[] = $row['PRODUCT_NAME'];
    $stocks[] = $row['STOCK'];
}

oci_free_statement($stmt);
oci_close($conn);

// Return the data as JSON
header('Content-Type: application/json');
echo json_encode([
    'products' => $products,
    'stocks' => $stocks
]); 