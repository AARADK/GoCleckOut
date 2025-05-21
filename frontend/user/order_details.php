<?php
require_once '../../backend/database/db_connection.php';

if (!isset($_GET['tx'])) {
    header('Location: orders.php');
    exit;
}

$transaction_id = $_GET['tx'];
$conn = getDBConnection();

if (!$conn) {
    die("Database connection failed");
}

// Get payment details directly from payment table
$payment_sql = "SELECT transaction_id, amount 
                FROM payment 
                WHERE transaction_id = :transaction_id";

$payment_stmt = oci_parse($conn, $payment_sql);
oci_bind_by_name($payment_stmt, ":transaction_id", $transaction_id);
oci_execute($payment_stmt);

$payment = oci_fetch_array($payment_stmt, OCI_ASSOC);
oci_free_statement($payment_stmt);

if (!$payment) {
    header('Location: orders.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details - GoCleckOut</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include "../header.php" ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Payment Details</h5>
                        <p class="card-text">
                            Transaction ID: <?= htmlspecialchars($payment['TRANSACTION_ID']) ?><br>
                            Amount: £<?= number_format($payment['AMOUNT'], 2) ?>
                        </p>
                        <a href="orders.php" class="btn btn-primary">Back to Orders</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include "../footer.php" ?>
</body>
</html>
<?php
oci_close($conn);
?> 