<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../backend/database/connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Please login to add items to cart';
    echo json_encode($response);
    exit;
}

if (!isset($_POST['product_id']) || !isset($_POST['quantity'])) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'];
$quantity = (int)$_POST['quantity'];

$conn = getDBConnection();

// Get current cart total
$total_sql = "
    SELECT SUM(cp.quantity) as total_quantity
    FROM cart_product cp
    JOIN cart c ON cp.cart_id = c.cart_id
    WHERE c.user_id = :user_id
";
$stmt = oci_parse($conn, $total_sql);
oci_bind_by_name($stmt, ":user_id", $user_id);
oci_execute($stmt);
$total_row = oci_fetch_array($stmt, OCI_ASSOC);
$current_total = $total_row['TOTAL_QUANTITY'] ?? 0;
oci_free_statement($stmt);

// Check if adding this quantity would exceed the limit
if ($current_total + $quantity > 20) {
    $response['message'] = 'Cannot add items: Cart would exceed 20 items limit';
    echo json_encode($response);
    exit;
}

// Check if user has an existing cart
$sql = "SELECT * FROM cart WHERE user_id = :user_id";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ":user_id", $user_id);
oci_execute($stmt);
$cart = oci_fetch_array($stmt, OCI_ASSOC);
oci_free_statement($stmt);

if (!$cart) {
    // Insert new cart
    $insert_cart_sql = "INSERT INTO cart (cart_id, user_id, add_date) VALUES (cart_seq.NEXTVAL, :user_id, SYSDATE) RETURNING cart_id INTO :cart_id";
    $stmt = oci_parse($conn, $insert_cart_sql);
    oci_bind_by_name($stmt, ":user_id", $user_id);
    oci_bind_by_name($stmt, ":cart_id", $cart_id, 20);
    oci_execute($stmt);
    oci_free_statement($stmt);
} else {
    $cart_id = $cart['CART_ID'];
}

// Check if product is already in the cart
$check_product_sql = "SELECT * FROM cart_product WHERE cart_id = :cart_id AND product_id = :product_id";
$stmt = oci_parse($conn, $check_product_sql);
oci_bind_by_name($stmt, ":cart_id", $cart_id);
oci_bind_by_name($stmt, ":product_id", $product_id);
oci_execute($stmt);
$product_exists = oci_fetch_array($stmt, OCI_ASSOC);
oci_free_statement($stmt);

if ($product_exists) {
    $response['message'] = 'Product already in cart!';
    echo json_encode($response);
    exit;
}

// Add product to cart
$insert_product_sql = "INSERT INTO cart_product (cart_id, product_id, quantity) VALUES (:cart_id, :product_id, :qty)";
$stmt = oci_parse($conn, $insert_product_sql);
oci_bind_by_name($stmt, ":cart_id", $cart_id);
oci_bind_by_name($stmt, ":product_id", $product_id);
oci_bind_by_name($stmt, ":qty", $quantity);
$success = oci_execute($stmt);
oci_free_statement($stmt);

if ($success) {
    // Update stock
    $update_stock_sql = "UPDATE product SET stock = stock - :qty WHERE product_id = :product_id";
    $stmt = oci_parse($conn, $update_stock_sql);
    oci_bind_by_name($stmt, ":qty", $quantity);
    oci_bind_by_name($stmt, ":product_id", $product_id);
    oci_execute($stmt);
    oci_free_statement($stmt);

    $response['success'] = true;
    $response['message'] = 'Product added to cart successfully';
} else {
    $response['message'] = 'Failed to add product to cart';
}

echo json_encode($response); 