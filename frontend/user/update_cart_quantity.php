<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../backend/database/connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'new_total' => 0];

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
$action = $_POST['action']; // 'increase' or 'decrease'

$conn = getDBConnection();

// Get current cart total quantity
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

// Get current item quantity
$item_sql = "
    SELECT cp.quantity
    FROM cart_product cp
    JOIN cart c ON cp.cart_id = c.cart_id
    WHERE c.user_id = :user_id AND cp.product_id = :product_id
";
$stmt = oci_parse($conn, $item_sql);
oci_bind_by_name($stmt, ":user_id", $user_id);
oci_bind_by_name($stmt, ":product_id", $product_id);
oci_execute($stmt);
$item_row = oci_fetch_array($stmt, OCI_ASSOC);
$current_quantity = $item_row['QUANTITY'] ?? 0;
oci_free_statement($stmt);

// Get product stock information
$stock_sql = "
    SELECT stock, min_order, max_order
    FROM product
    WHERE product_id = :product_id
    FOR UPDATE";  // Add FOR UPDATE to lock the row
$stmt = oci_parse($conn, $stock_sql);
oci_bind_by_name($stmt, ":product_id", $product_id);
oci_execute($stmt);
$stock_row = oci_fetch_array($stmt, OCI_ASSOC);
$available_stock = $stock_row['STOCK'] ?? 0;
$min_order = $stock_row['MIN_ORDER'] ?? 1;
$max_order = $stock_row['MAX_ORDER'] ?? 1;
oci_free_statement($stmt);

// Calculate new quantity
$new_quantity = $current_quantity;
if ($action === 'increase') {
    // Check if increasing would exceed max order or available stock
    if ($current_quantity >= $max_order) {
        $response['message'] = "Maximum order quantity is $max_order";
        echo json_encode($response);
        exit;
    }
    if ($current_quantity >= $available_stock) {
        $response['message'] = "Only $available_stock items available in stock";
        echo json_encode($response);
        exit;
    }
    $new_quantity = $current_quantity + 1;
} else if ($action === 'decrease') {
    // Check if decreasing would go below min order
    if ($current_quantity <= $min_order) {
        $response['message'] = "Minimum order quantity is $min_order";
        echo json_encode($response);
        exit;
    }
    $new_quantity = max($min_order, $current_quantity - 1);
}

// Check if new total would exceed limit
$new_total = $current_total - $current_quantity + $new_quantity;
if ($new_total > 20) {
    $response['message'] = 'Cart cannot exceed 20 items total';
    echo json_encode($response);
    exit;
}

try {
    // Start transaction properly
    oci_execute($conn, "SET TRANSACTION ISOLATION LEVEL SERIALIZABLE");
    
    if ($new_quantity === 0) {
        // Remove item from cart and update stock
        $delete_sql = "
            DELETE FROM cart_product
            WHERE cart_id IN (SELECT cart_id FROM cart WHERE user_id = :user_id)
            AND product_id = :product_id
        ";
        $stmt = oci_parse($conn, $delete_sql);
        oci_bind_by_name($stmt, ":user_id", $user_id);
        oci_bind_by_name($stmt, ":product_id", $product_id);
        $success = oci_execute($stmt);
        oci_free_statement($stmt);

        if ($success) {
            // Update stock
            $update_stock_sql = "
                UPDATE product 
                SET stock = stock + :quantity 
                WHERE product_id = :product_id
            ";
            $stmt = oci_parse($conn, $update_stock_sql);
            oci_bind_by_name($stmt, ":quantity", $current_quantity);
            oci_bind_by_name($stmt, ":product_id", $product_id);
            oci_execute($stmt);
            oci_free_statement($stmt);
        }
    } else {
        // Calculate stock change
        $stock_change = $new_quantity - $current_quantity;
        
        // Update stock first with validation
        $update_stock_sql = "
            UPDATE product 
            SET stock = stock - :stock_change 
            WHERE product_id = :product_id
            AND stock >= :stock_change
        ";
        $stmt = oci_parse($conn, $update_stock_sql);
        oci_bind_by_name($stmt, ":stock_change", $stock_change);
        oci_bind_by_name($stmt, ":product_id", $product_id);
        $stock_update = oci_execute($stmt);
        oci_free_statement($stmt);

        if (!$stock_update) {
            throw new Exception("Insufficient stock available");
        }

        // Then update cart quantity
        $update_sql = "
            UPDATE cart_product cp
            SET cp.quantity = :quantity
            WHERE cp.cart_id IN (SELECT cart_id FROM cart WHERE user_id = :user_id)
            AND cp.product_id = :product_id
        ";
        $stmt = oci_parse($conn, $update_sql);
        oci_bind_by_name($stmt, ":quantity", $new_quantity);
        oci_bind_by_name($stmt, ":user_id", $user_id);
        oci_bind_by_name($stmt, ":product_id", $product_id);
        $success = oci_execute($stmt);
        oci_free_statement($stmt);

        if (!$success) {
            throw new Exception("Failed to update cart quantity");
        }
    }

    // Commit transaction
    oci_execute($conn, "COMMIT");

    $response['success'] = true;
    $response['message'] = 'Cart updated successfully';
    $response['new_quantity'] = $new_quantity;
    $response['new_total'] = $new_total;
} catch (Exception $e) {
    // Rollback transaction on error
    oci_execute($conn, "ROLLBACK");
    $response['message'] = 'Failed to update cart: ' . $e->getMessage();
}

echo json_encode($response);
?> 