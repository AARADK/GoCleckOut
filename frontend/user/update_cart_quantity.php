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
$action = $_POST['action']; // 'increase', 'decrease', 'remove', or 'validate'

$conn = getDBConnection();

// Get current item quantity and stock
$check_sql = "
    SELECT cp.quantity, p.stock, c.cart_id
    FROM cart_product cp
    JOIN product p ON cp.product_id = p.product_id
    JOIN cart c ON cp.cart_id = c.cart_id
    WHERE cp.product_id = :product_id AND c.user_id = :user_id
";
$stmt = oci_parse($conn, $check_sql);
oci_bind_by_name($stmt, ":product_id", $product_id);
oci_bind_by_name($stmt, ":user_id", $user_id);
oci_execute($stmt);
$row = oci_fetch_array($stmt, OCI_ASSOC);
oci_free_statement($stmt);

if (!$row) {
    $response['success'] = false;
    $response['message'] = "Item not found in cart";
    echo json_encode($response);
    exit;
}

$current_quantity = $row['QUANTITY'];
$available_stock = $row['STOCK'];
$cart_id = $row['CART_ID'];

// Validate quantity based on action
if ($action === 'increase') {
    // Check if increasing would exceed available stock
    if ($current_quantity + 1 > $available_stock) {
        $response['success'] = false;
        $response['message'] = "Cannot increase quantity: Not enough stock available";
        echo json_encode($response);
        exit;
    }
} elseif ($action === 'decrease') {
    // Only check if quantity would go below 1
    if ($current_quantity - 1 < 1) {
        $response['success'] = false;
        $response['message'] = "Cannot decrease quantity below 1";
        echo json_encode($response);
        exit;
    }
}

// Check cart total limit
$total_sql = "
    SELECT SUM(quantity) as total_quantity
    FROM cart_product
    WHERE cart_id = :cart_id
";
$stmt = oci_parse($conn, $total_sql);
oci_bind_by_name($stmt, ":cart_id", $cart_id);
oci_execute($stmt);
$total_row = oci_fetch_array($stmt, OCI_ASSOC);
oci_free_statement($stmt);

$cart_total = $total_row['TOTAL_QUANTITY'] ?? 0;
$new_total = $cart_total;

if ($action === 'increase') {
    $new_total = $cart_total + 1;
} elseif ($action === 'decrease') {
    $new_total = $cart_total - 1;
} elseif ($action === 'remove') {
    $new_total = $cart_total - $current_quantity;
}

if ($new_total > 20) {
    $response['success'] = false;
    $response['message'] = "Cannot update quantity: Cart would exceed 20 items limit";
    echo json_encode($response);
    exit;
}

try {
    // Start transaction
    $stmt = oci_parse($conn, "ALTER SESSION SET ISOLATION_LEVEL = SERIALIZABLE");
    oci_execute($stmt);
    oci_free_statement($stmt);

    if ($action === 'remove') {
        // Remove item from cart
        $delete_sql = "
            DELETE FROM cart_product
            WHERE cart_id = :cart_id
            AND product_id = :product_id
        ";
        $stmt = oci_parse($conn, $delete_sql);
        oci_bind_by_name($stmt, ":cart_id", $cart_id);
        oci_bind_by_name($stmt, ":product_id", $product_id);
        $success = oci_execute($stmt);
        oci_free_statement($stmt);

        if (!$success) {
            throw new Exception("Failed to remove item from cart");
        }

        // Update stock - return the quantity to stock
        $update_stock_sql = "
            UPDATE product 
            SET stock = stock + :quantity 
            WHERE product_id = :product_id
        ";
        $stmt = oci_parse($conn, $update_stock_sql);
        oci_bind_by_name($stmt, ":quantity", $current_quantity);
        oci_bind_by_name($stmt, ":product_id", $product_id);
        $stock_update = oci_execute($stmt);
        oci_free_statement($stmt);

        if (!$stock_update) {
            throw new Exception("Failed to update product stock");
        }

        $response['success'] = true;
        $response['message'] = 'Item removed from cart';
        $response['new_quantity'] = 0;
        $response['new_total'] = $new_total;
    } else {
        // Handle increase/decrease actions
        $new_quantity = $current_quantity;
        if ($action === 'increase') {
            $new_quantity = $current_quantity + 1;
        } elseif ($action === 'decrease') {
            $new_quantity = $current_quantity - 1;
        }

        // Calculate stock change
        $stock_change = $new_quantity - $current_quantity;
        
        // Update stock
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
        $rows_affected = oci_num_rows($stmt);
        oci_free_statement($stmt);

        if (!$stock_update || $rows_affected === 0) {
            throw new Exception("Insufficient stock available");
        }

        // Update cart quantity
        $update_sql = "
            UPDATE cart_product 
            SET quantity = :quantity
            WHERE cart_id = :cart_id
            AND product_id = :product_id
        ";
        $stmt = oci_parse($conn, $update_sql);
        oci_bind_by_name($stmt, ":quantity", $new_quantity);
        oci_bind_by_name($stmt, ":cart_id", $cart_id);
        oci_bind_by_name($stmt, ":product_id", $product_id);
        $success = oci_execute($stmt);
        oci_free_statement($stmt);

        if (!$success) {
            throw new Exception("Failed to update cart quantity");
        }

        $response['success'] = true;
        $response['message'] = 'Cart updated successfully';
        $response['new_quantity'] = $new_quantity;
        $response['new_total'] = $new_total;
    }

    // Commit transaction
    $stmt = oci_parse($conn, "COMMIT");
    oci_execute($stmt);
    oci_free_statement($stmt);
} catch (Exception $e) {
    // Rollback transaction on error
    $stmt = oci_parse($conn, "ROLLBACK");
    oci_execute($stmt);
    oci_free_statement($stmt);
    
    $response['message'] = $e->getMessage();
    $response['success'] = false;
} finally {
    // Ensure connection is closed
    if (isset($conn)) {
        oci_close($conn);
    }
}

echo json_encode($response);
?> 