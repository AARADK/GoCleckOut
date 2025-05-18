<?php

require_once '../database/db_connection.php';

$err_msg = '';
$success_msg = '';

// Insert new category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['category'])) {
    $category = $_POST['category'];

    if ($category) {
        try {
            $stmt = $db->prepare("INSERT INTO product_category (category_name) VALUES (:category_name)");

            $executed = $stmt->execute([
                ':category_name' => $category,
            ]);

            if ($executed) {
                $success_msg = "<p style='color:green;'>✅ Category added successfully!</p>";
                $err_msg = '';
            } else {
                $err_msg = 'Error while adding category. Try Again!';
            }

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $err_msg = "<p style='color:red;'>❌ Category already exists! Please choose a different name.</p>";
            } else {
                $err_msg =  "<p style='color:red;'>❌ An error occurred: " . $e->getMessage() . "</p>";
            }
        }
    } else {
        $err_msg = "<p style='color:red;'>❌ Please fill all required fields.</p>";
    }
}

// Delete category
if (isset($_GET['delete_id'])) {
    $product_category_id = $_GET['delete_id'];

    try {
        // Check if category exists
        $stmt = $db->prepare("SELECT * FROM product_category WHERE product_category_id = :product_category_id");
        $stmt->execute([':product_category_id' => $product_category_id]);
        $category = $stmt->fetch();

        if ($category) {
            // Delete category
            $stmt = $db->prepare("DELETE FROM product_category WHERE product_category_id = :product_category_id");
            $stmt->execute([':product_category_id' => $product_category_id]);

            $success_msg = "<p style='color:green;'>✅ Category deleted successfully!</p>";
        } else {
            $err_msg = "<p style='color:red;'>❌ Category not found!</p>";
        }
    } catch (PDOException $e) {
        $err_msg =  "<p style='color:red;'>❌ An error occurred while deleting: " . $e->getMessage() . "</p>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management</title>
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

        .category-list {
            width: 320px;
            margin: 30px auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 10px;
            background: #f9f9f9;
        }

        .category-list ul {
            list-style: none;
            padding: 0;
        }

        .category-list li {
            margin-bottom: 10px;
            padding: 5px;
            background-color: #f1f1f1;
            border-radius: 4px;
        }

        .category-list button {
            background-color: red;
            color: white;
            padding: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .category-list button:hover {
            background-color: darkred;
        }
    </style>
</head>
<body>

    <form method="POST">
        <h2>Add Category</h2>

        <label>Category Name:</label>
        <input type="text" name="category" required>

        <button type="submit">Submit</button>

        <!-- Show success or error message -->
        <div><?=$success_msg?></div>
        <div><?=$err_msg?></div>
    </form>

    <!-- Display existing categories with delete option -->
    <div class="category-list">
        <h2>Existing Categories</h2>

        <ul>
            <?php
            // Fetch categories from database
            $stmt = $db->query("SELECT * FROM product_category");
            $categories = $stmt->fetchAll();

            if (count($categories) > 0) {
                foreach ($categories as $category) {
                    echo '<li>';
                    echo htmlspecialchars($category['category_name']);  // Display category name
                    echo ' <a href="?delete_id=' . $category['product_category_id'] . '" onclick="return confirm(\'Are you sure you want to delete this category?\')">';
                    echo '<button>Delete</button>';
                    echo '</a>';
                    echo '</li>';
                }
            } else {
                echo '<p>No categories found.</p>';
            }
            ?>
        </ul>
    </div>

</body>
</html>
