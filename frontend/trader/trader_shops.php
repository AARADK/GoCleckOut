<?php

require_once '../../backend/database/db_connection.php'; // assumes PDO $db is set up in this file

// $stmt = $db->prepare('SELECT  ')

$categories = [
    'fashion' => 'Fashion',
    'electronics' => 'Electronics',
    'grocery' => 'Grocery',
    'books' => 'Books',
    'toys' => 'Toys',
    'beauty' => 'Beauty & Health'
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $shop_name = $_POST['shop_name'];
    $shop_email = $_POST['shop_email'];
    $phone_no = $_POST['phone_no'];
    $shop_category = $_POST['shop_category'];
    $user_id = $_SESSION['user_id'] ?? null;

    if ($user_id && $shop_name && $shop_email && $phone_no && $shop_category) {
        $stmt = $db->prepare("INSERT INTO shop (shop_name, shop_email, phone_no, shop_category, user_id, created_date, shop_status)
                              VALUES (:shop_name, :shop_email, :phone_no, :shop_category, :user_id, CURDATE(), 'pending')");
        $stmt->execute([
            ':shop_name' => $shop_name,
            ':shop_email' => $shop_email,
            ':phone_no' => $phone_no,
            ':shop_category' => $shop_category,
            ':user_id' => $user_id
        ]);

        echo "<p>✅ Shop added successfully!</p>";
    } else {
        echo "<p style='color:red;'>❌ Please fill all required fields.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Shop</title>
    <style>
        form {
            width: 320px;
            margin: 30px auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 10px;
            background: #f9f9f9;
        }

        h2 {
            text-align: center;
        }

        label {
            display: block;
            margin-top: 12px;
        }

        input[type='text'], input[type='email'], select {
            width: 100%;
            padding: 8px;
            margin-top: 6px;
        }

        button {
            margin-top: 20px;
            width: 100%;
            padding: 10px;
            background-color: #ff6b6b;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background-color:rgb(94, 94, 94);
        }
    </style>
</head>
<body>
    <form method="POST">
        <h2>Add Shop</h2>

        <label>Shop Name:</label>
        <input type="text" name="shop_name" required>

        <label>Shop Email:</label>
        <input type="email" name="shop_email" required>

        <label>Contact No:</label>
        <input type="text" name="phone_no" required>

        <label>Shop Category:</label>
        <select name="shop_category" required>
            <option value="" disabled selected>Select Category</option>
            <?php foreach ($categories as $value => $label): ?>
                <option value="<?= $value ?>"><?= $label ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Submit</button>
    </form>
</body>
</html>
