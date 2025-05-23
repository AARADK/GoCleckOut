<?php
require_once '../../backend/database/db_connection.php';
session_start();

// Check if user is logged in and is a trader
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'trader') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID is required']);
    exit;
}

$order_id = $_POST['order_id'];
$user_id = $_SESSION['user_id'];

$conn = getDBConnection();

// First verify that this order contains products from this trader's shops
$verify_sql = "SELECT 1 FROM orders o
               JOIN order_item oi ON o.order_id = oi.order_id
               JOIN product p ON oi.product_id = p.product_id
               JOIN shops s ON p.shop_id = s.shop_id
               WHERE o.order_id = :order_id
               AND s.user_id = :user_id";

$verify_stmt = oci_parse($conn, $verify_sql);
oci_bind_by_name($verify_stmt, ":order_id", $order_id);
oci_bind_by_name($verify_stmt, ":user_id", $user_id);
oci_execute($verify_stmt);

if (!oci_fetch($verify_stmt)) {
    http_response_code(403);
    echo json_encode(['error' => 'Order not found or unauthorized']);
    exit;
}

// Update the order status
$update_sql = "UPDATE orders 
               SET order_status = 'completed' 
               WHERE order_id = :order_id";

$update_stmt = oci_parse($conn, $update_sql);
oci_bind_by_name($update_stmt, ":order_id", $order_id);

if (oci_execute($update_stmt)) {
    echo json_encode(['success' => true, 'message' => 'Order marked as completed']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update order status']);
}

oci_free_statement($verify_stmt);
oci_free_statement($update_stmt);
oci_close($conn); 