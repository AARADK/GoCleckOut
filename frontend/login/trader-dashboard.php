<?php
require_once '../../backend/database/db_connection.php';

$conn = getDBConnection();

if (!$conn) {
    die("Database connection failed");
}

// Get trader statistics
$sql = "SELECT 
        (SELECT COUNT(*) FROM shop) as total_shops,
        (SELECT COUNT(*) FROM product) as total_products,
        (SELECT COUNT(*) FROM orders) as total_orders";

$stmt = oci_parse($conn, $sql);
oci_execute($stmt);
$stats = oci_fetch_array($stmt, OCI_ASSOC);
oci_free_statement($stmt);

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['order_id'])) {
    try {
        $newStatus = '';
        switch ($_POST['action']) {
            case 'process':
                $newStatus = 'processing';
                break;
            case 'complete':
                $newStatus = 'completed';
                break;
            case 'cancel':
                $newStatus = 'cancelled';
                break;
        }
        
        $updateQuery = "UPDATE orders SET status = :status WHERE order_id = :order_id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':status', $newStatus);
        $updateStmt->bindParam(':order_id', $_POST['order_id'], PDO::PARAM_INT);
        $updateStmt->execute();
        
        // Refresh the page to show updated data
        header("Location: trader_dashboard.php");
        exit();
    } catch (PDOException $e) {
        die("Update failed: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trader Dashboard - GoCleckOut</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        h2 {
            color: #3498db;
            margin-top: 30px;
        }
        
        .welcome-message {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .metrics {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .metric-card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            flex: 1;
            min-width: 200px;
        }
        
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: #3498db;
            color: white;
        }
        
        tr:hover {
            background-color: #f5f7fa;
        }
        
        .status-pending {
            color: #e67e22;
            font-weight: bold;
        }
        
        .status-processing {
            color: #3498db;
            font-weight: bold;
        }
        
        .status-completed {
            color: #27ae60;
            font-weight: bold;
        }
        
        .status-cancelled {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .trader-info {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 5px;
        }
        
        .btn-process {
            background-color: #3498db;
            color: white;
        }
        
        .btn-complete {
            background-color: #27ae60;
            color: white;
        }
        
        .btn-cancel {
            background-color: #e74c3c;
            color: white;
        }
        
        .action-form {
            display: inline;
        }
        
        .edit-btn {
            display: inline-block;
            background-color: #d84040;
            color: white;
            padding: 8px 12px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .edit-btn:hover {
            background-color: #c13030;
        }
    </style>
</head>
<body>
    <form action="../trader/add_product.php">
        <button> ADD PRODUCT</button>
    </form>
    <div class="dashboard-container">
        <h1>GoCleckOut</h1>
        <h2>Trader Dashboard</h2>
        
        <div class="welcome-message">
            <h3>Welcome back, <?php echo htmlspecialchars($userData['full_name']); ?>!</h3>
            <p>Ready to manage your shop and keep things running smoothly.</p>
        </div>
        
        <div class="trader-info">
            <h3>Your Account Information</h3>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($userData['full_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($userData['email']); ?></p>
            <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($userData['created_date'])); ?></p>
            <a href="User_Profile.php" class="edit-btn">View Profile</a>
        </div>
        
        <div class="metrics">
            <div class="metric-card">
                <h3>Total Shops</h3>
                <div class="metric-value"><?php echo $stats['total_shops'] ?? 0; ?></div>
            </div>
            
            <div class="metric-card">
                <h3>Total Products</h3>
                <div class="metric-value"><?php echo $stats['total_products'] ?? 0; ?></div>
            </div>
            
            <div class="metric-card">
                <h3>Total Orders</h3>
                <div class="metric-value"><?php echo $stats['total_orders'] ?? 0; ?></div>
            </div>
        </div>
        
        <h2>Recent Orders</h2>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recentOrders)): ?>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td><?php echo date('m/d/Y', strtotime($order['order_date'])); ?></td>
                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td class="status-<?php echo htmlspecialchars($order['status']); ?>">
                                <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                            </td>
                            <td>
                                <?php if ($order['status'] == 'pending'): ?>
                                    <form class="action-form" method="POST">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <input type="hidden" name="action" value="process">
                                        <button type="submit" class="btn btn-process">Process</button>
                                    </form>
                                <?php elseif ($order['status'] == 'processing'): ?>
                                    <form class="action-form" method="POST">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <input type="hidden" name="action" value="complete">
                                        <button type="submit" class="btn btn-complete">Complete</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($order['status'] != 'completed' && $order['status'] != 'cancelled'): ?>
                                    <form class="action-form" method="POST">
                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <button type="submit" class="btn btn-cancel">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No recent orders found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <a href="logout.php" class="edit-btn" style="margin-top: 20px;">Logout</a>
    </div>
</body>
</html>