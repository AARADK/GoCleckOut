<?php
include 'connect.php';
require_once 'base_product_blobs.php';

$conn = getDBConnection();

function executeSQL($conn, $sql)
{
    $stmt = oci_parse($conn, $sql);
    if (oci_execute($stmt)) {
        echo "<p style='color:green;'>Success: $sql</p>";
    } else {
        $e = oci_error($stmt);
        echo "<p style='color:red;'>Error: {$e['message']}<br>SQL: $sql</p>";
    }
    oci_free_statement($stmt);
}

function executeSQLNoWarnings($conn, $sql)
{
    $stmt = oci_parse($conn, $sql);
    if (@oci_execute($stmt)) {
        echo "<p style='color:green;'>Dropped (if existed): $sql</p>";
    } else {
        $e = oci_error($stmt);
        echo "<p style='color:orange;'>Drop failed or object did not exist: {$e['message']}<br>SQL: $sql</p>";
    }
    oci_free_statement($stmt);
}

executeSQLNoWarnings($conn, "DROP TRIGGER trg_users_pk");
executeSQLNoWarnings($conn, "DROP SEQUENCE user_seq");
executeSQLNoWarnings($conn, "DROP TABLE users CASCADE CONSTRAINTS");

executeSQLNoWarnings($conn, "DROP TRIGGER trg_shops_pk");
executeSQLNoWarnings($conn, "DROP SEQUENCE shop_seq");
executeSQLNoWarnings($conn, "DROP TABLE shops CASCADE CONSTRAINTS");

executeSQLNoWarnings($conn, "DROP TRIGGER trg_product_pk");
executeSQLNoWarnings($conn, "DROP SEQUENCE product_seq");
executeSQLNoWarnings($conn, "DROP TABLE product CASCADE CONSTRAINTS");

executeSQLNoWarnings($conn, "DROP TRIGGER trg_cart_pk");
executeSQLNoWarnings($conn, "DROP SEQUENCE cart_seq");
executeSQLNoWarnings($conn, "DROP TABLE cart CASCADE CONSTRAINTS");

executeSQLNoWarnings($conn, "DROP TRIGGER trg_review_pk");
executeSQLNoWarnings($conn, "DROP SEQUENCE review_seq");
executeSQLNoWarnings($conn, "DROP TABLE reviews CASCADE CONSTRAINTS");

executeSQLNoWarnings($conn, "DROP TRIGGER trg_orders_pk");
executeSQLNoWarnings($conn, "DROP SEQUENCE orders_seq");
executeSQLNoWarnings($conn, "DROP TABLE orders CASCADE CONSTRAINTS");

executeSQLNoWarnings($conn, "DROP TABLE cart_product CASCADE CONSTRAINTS");

executeSQLNoWarnings($conn, "DROP TRIGGER trg_collection_slot_pk");
executeSQLNoWarnings($conn, "DROP SEQUENCE collection_slot_seq");
executeSQLNoWarnings($conn, "DROP TABLE collection_slot CASCADE CONSTRAINTS");

executeSQLNoWarnings($conn, "DROP TRIGGER trg_payment_pk");
executeSQLNoWarnings($conn, "DROP SEQUENCE payment_seq");
executeSQLNoWarnings($conn, "DROP TABLE payment CASCADE CONSTRAINTS");

executeSQLNoWarnings($conn, "DROP TRIGGER trg_report_pk");
executeSQLNoWarnings($conn, "DROP SEQUENCE report_seq");
executeSQLNoWarnings($conn, "DROP TABLE report CASCADE CONSTRAINTS");

executeSQLNoWarnings($conn, "DROP TABLE product_report CASCADE CONSTRAINTS");

executeSQLNoWarnings($conn, "DROP TRIGGER trg_wishlist_pk");
executeSQLNoWarnings($conn, "DROP SEQUENCE wishlist_seq");
executeSQLNoWarnings($conn, "DROP TABLE wishlist CASCADE CONSTRAINTS");

executeSQLNoWarnings($conn, "DROP TABLE wishlist_product CASCADE CONSTRAINTS");

executeSQLNoWarnings($conn, "DROP TABLE product_category CASCADE CONSTRAINTS");

executeSQLNoWarnings($conn, "DROP TRIGGER trg_coupon_pk");
executeSQLNoWarnings($conn, "DROP SEQUENCE coupon_seq");
executeSQLNoWarnings($conn, "DROP TABLE coupon CASCADE CONSTRAINTS");

// Reorder the table creation sequence
if ($conn) {

    executeSQL($conn, "
        CREATE TABLE product_category (
            category_id NUMBER PRIMARY KEY,
            category_name VARCHAR2(50)
        )
    ");

    // USERS TABLE
    executeSQL($conn, "
        CREATE TABLE users (
            user_id NUMBER PRIMARY KEY,
            full_name VARCHAR2(150) NOT NULL,
            email VARCHAR2(100) UNIQUE NOT NULL,
            phone_no VARCHAR2(20) NOT NULL,
            password VARCHAR2(255) NOT NULL,
            verification_code VARCHAR2(100),
            role VARCHAR2(10) DEFAULT 'customer' CHECK (role IN ('customer', 'trader', 'admin')),
            category_id NUMBER,
            created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR2(10) DEFAULT 'pending' CHECK (status IN ('active', 'inactive', 'pending')),
            CONSTRAINT fk_category_id FOREIGN KEY (category_id) REFERENCES product_category(category_id)
        )
    ");
    executeSQL($conn, "CREATE SEQUENCE user_seq START WITH 1 INCREMENT BY 1");
    executeSQL($conn, "
        CREATE OR REPLACE TRIGGER trg_users_pk
        BEFORE INSERT ON users
        FOR EACH ROW
        BEGIN
            SELECT user_seq.NEXTVAL INTO :new.user_id FROM dual;
        END;");
    executeSQL($conn, "
        CREATE OR REPLACE TRIGGER trg_trader_active
        AFTER UPDATE ON users
        FOR EACH ROW
        WHEN (
            NEW.status = 'active' AND 
            NEW.role = 'trader' AND 
            OLD.status != 'active'
        )
        BEGIN
            UPDATE users
            SET status = 'inactive'
            WHERE role = 'trader'
            AND category_id = :NEW.category_id
            AND user_id != :NEW.user_id
            AND status = 'active';
        END;
    ");

    // CART TABLE - create before PRODUCT for proper referencing
    executeSQL($conn, "
        CREATE TABLE cart (
            cart_id NUMBER PRIMARY KEY,
            user_id NUMBER NOT NULL,
            add_date DATE,
            CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(user_id)
        )
    ");
    executeSQL($conn, "CREATE SEQUENCE cart_seq START WITH 1 INCREMENT BY 1");
    executeSQL($conn, "
        CREATE OR REPLACE TRIGGER trg_cart_pk
        BEFORE INSERT ON cart
        FOR EACH ROW
        BEGIN
            SELECT cart_seq.NEXTVAL INTO :new.cart_id FROM dual;
        END;
    ");

    // SHOPS TABLE
    executeSQL($conn, "
        CREATE TABLE shops (
            shop_id NUMBER PRIMARY KEY,
            user_id NUMBER NOT NULL,
            shop_category VARCHAR2(20) CHECK (shop_category IN ('butcher', 'greengrocer', 'fishmonger', 'bakery', 'delicatessen')),
            shop_name VARCHAR2(255) NOT NULL,
            register_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            description CLOB,
            shop_email VARCHAR2(100),
            shop_contact_no NUMBER NOT NULL,
            CONSTRAINT fk_shop_user FOREIGN KEY (user_id) REFERENCES users(user_id)
        )
    ");

    executeSQL($conn, "CREATE SEQUENCE shop_seq START WITH 1 INCREMENT BY 1");
    executeSQL($conn, "
        CREATE OR REPLACE TRIGGER trg_shops_pk
        BEFORE INSERT ON shops
        FOR EACH ROW
        BEGIN
            SELECT shop_seq.NEXTVAL INTO :new.shop_id FROM dual;
        END;
    ");

    // PRODUCTS TABLE
    executeSQL($conn, "
        CREATE TABLE product (
            product_id NUMBER PRIMARY KEY,
            product_name VARCHAR2(100),
            description CLOB,
            price NUMBER NOT NULL,
            stock NUMBER NOT NULL,
            min_order NUMBER NOT NULL,
            max_order NUMBER NOT NULL,
            product_image BLOB,
            added_date DATE,
            updated_date DATE,
            product_status VARCHAR2(20),
            discount_percentage NUMBER,
            shop_id NUMBER NOT NULL,
            user_id NUMBER NOT NULL,
            product_category_name VARCHAR2(20) NOT NULL,
            CONSTRAINT fk_product_user FOREIGN KEY (user_id) REFERENCES users(user_id),
            CONSTRAINT fk_product_shop FOREIGN KEY (shop_id) REFERENCES shops(shop_id)
        )
    ");

    executeSQL($conn, "CREATE SEQUENCE product_seq START WITH 1 INCREMENT BY 1");

    executeSQL($conn, "
        CREATE OR REPLACE TRIGGER trg_product_pk
        BEFORE INSERT ON product
        FOR EACH ROW
        BEGIN
            SELECT product_seq.NEXTVAL INTO :new.product_id FROM dual;
        END;
    ");

    // cart_product TABLE
    executeSQL($conn, "
        CREATE TABLE cart_product (
            cart_id NUMBER,
            product_id NUMBER,
            quantity NUMBER NOT NULL,
            CONSTRAINT pk_cart_product PRIMARY KEY (cart_id, product_id),
            CONSTRAINT fk_pc_cart FOREIGN KEY (cart_id) REFERENCES cart(cart_id),
            CONSTRAINT fk_pc_product FOREIGN KEY (product_id) REFERENCES product(product_id)
        )
    ");

    // Add trigger to limit cart items to 20
    executeSQL($conn, "
        CREATE OR REPLACE TRIGGER trg_cart_item_limit
        BEFORE INSERT OR UPDATE ON cart_product
        FOR EACH ROW
        DECLARE
            v_total_items NUMBER;
        BEGIN
            -- Calculate total items in cart (including the new/updated item)
            SELECT NVL(SUM(CASE 
                WHEN :NEW.cart_id = cart_id AND :NEW.product_id = product_id 
                THEN :NEW.quantity 
                ELSE quantity 
            END), 0)
            INTO v_total_items
            FROM cart_product
            WHERE cart_id = :NEW.cart_id;

            -- If total items would exceed 20, raise an error
            IF v_total_items > 20 THEN
                RAISE_APPLICATION_ERROR(-20001, 'Cart cannot contain more than 20 items total');
            END IF;
        END;
    ");

    // REVIEW TABLE
    executeSQL($conn, "
        CREATE TABLE reviews (
            review_id NUMBER PRIMARY KEY,
            review_rating NUMBER,
            review_description CLOB,
            review_date DATE,
            user_id NUMBER NOT NULL,
            product_id NUMBER NOT NULL,
            CONSTRAINT fk_review_user FOREIGN KEY (user_id) REFERENCES users(user_id),
            CONSTRAINT fk_review_product FOREIGN KEY (product_id) REFERENCES product(product_id)
        )
    ");

    executeSQL($conn, "CREATE SEQUENCE review_seq START WITH 1 INCREMENT BY 1");
    executeSQL($conn, "
        CREATE OR REPLACE TRIGGER trg_review_pk
        BEFORE INSERT ON reviews
        FOR EACH ROW
        BEGIN
            SELECT review_seq.NEXTVAL INTO :new.review_id FROM dual;
        END;
    ");

    // COLLECTION_SLOT TABLE
    executeSQL($conn, "
        CREATE TABLE collection_slot (
            collection_slot_id NUMBER PRIMARY KEY,
            slot_date DATE,
            slot_day VARCHAR2(10),
            slot_time TIMESTAMP NOT NULL,
            total_order NUMBER
        )
    ");

    executeSQL($conn, "CREATE SEQUENCE collection_slot_seq START WITH 1 INCREMENT BY 1");
    executeSQL($conn, "
        CREATE OR REPLACE TRIGGER trg_collection_slot_pk
        BEFORE INSERT ON collection_slot
        FOR EACH ROW
        BEGIN
            SELECT collection_slot_seq.NEXTVAL INTO :new.collection_slot_id FROM dual;
        END;
    ");

    // COUPON TABLE
    executeSQL($conn, "
        CREATE TABLE coupon (
            coupon_id NUMBER PRIMARY KEY,
            coupon_code VARCHAR2(20) NOT NULL,
            coupon_start_date DATE NOT NULL,
            coupon_end_date DATE NOT NULL,
            coupon_description VARCHAR2(200) NOT NULL,
            coupon_discount_percent NUMBER(5,2) NOT NULL
        )
    ");

    executeSQL($conn, "CREATE SEQUENCE coupon_seq START WITH 1 INCREMENT BY 1");
    executeSQL($conn, "
        CREATE OR REPLACE TRIGGER trg_coupon_pk
        BEFORE INSERT ON coupon
        FOR EACH ROW
        BEGIN
            SELECT coupon_seq.NEXTVAL INTO :new.coupon_id FROM dual;
        END;
    ");

    // ORDERS TABLE
    executeSQL($conn, "
        CREATE TABLE orders (
            order_id NUMBER PRIMARY KEY,
            order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            total_item_count NUMBER,
            total_amount NUMBER,
            coupon_id NUMBER,
            order_status VARCHAR2(50),
            collection_slot_id NUMBER,
            user_id NUMBER NOT NULL,
            cart_id NUMBER NOT NULL,
            CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(user_id),
            CONSTRAINT fk_order_cart FOREIGN KEY (cart_id) REFERENCES cart(cart_id),
            CONSTRAINT fk_order_coupon FOREIGN KEY (coupon_id) REFERENCES coupon(coupon_id),
            CONSTRAINT fk_order_slot FOREIGN KEY (collection_slot_id) REFERENCES collection_slot(collection_slot_id)
        )
    ");
    executeSQL($conn, "CREATE SEQUENCE orders_seq START WITH 1 INCREMENT BY 1");
    executeSQL($conn, "
        CREATE OR REPLACE TRIGGER trg_orders_pk
        BEFORE INSERT ON orders
        FOR EACH ROW
        BEGIN
            SELECT orders_seq.NEXTVAL INTO :new.order_id FROM dual;
        END;
    ");

    // PAYMENT TABLE
    executeSQL($conn, "
        CREATE TABLE payment (
            payment_id NUMBER PRIMARY KEY,
            payment_date DATE,
            amount NUMBER,
            payment_method VARCHAR2(50),
            payment_status VARCHAR2(20),
            order_id NUMBER NOT NULL,
            user_id NUMBER NOT NULL,
            CONSTRAINT fk_payment_user FOREIGN KEY (user_id) REFERENCES users(user_id),
            CONSTRAINT fk_payment_order FOREIGN KEY (order_id) REFERENCES orders(order_id)
        )
    ");

    executeSQL($conn, "CREATE SEQUENCE payment_seq START WITH 1 INCREMENT BY 1");
    executeSQL($conn, "
        CREATE OR REPLACE TRIGGER trg_payment_pk
        BEFORE INSERT ON payment
        FOR EACH ROW
        BEGIN
            SELECT payment_seq.NEXTVAL INTO :new.payment_id FROM dual;
        END;
    ");

    // REPORT TABLE
    executeSQL($conn, "
        CREATE TABLE report (
            report_id NUMBER PRIMARY KEY,
            report_for VARCHAR2(50),
            report_subject VARCHAR2(100),
            report_date DATE,
            report_description VARCHAR2(4000) NOT NULL,
            order_id NUMBER,
            user_id NUMBER NOT NULL,
            CONSTRAINT fk_report_order FOREIGN KEY (order_id) REFERENCES orders(order_id),
            CONSTRAINT fk_report_user FOREIGN KEY (user_id) REFERENCES users(user_id)
        )
    ");

    executeSQL($conn, "CREATE SEQUENCE report_seq START WITH 1 INCREMENT BY 1");
    executeSQL($conn, "
        CREATE OR REPLACE TRIGGER trg_report_pk
        BEFORE INSERT ON report
        FOR EACH ROW
        BEGIN
            SELECT report_seq.NEXTVAL INTO :new.report_id FROM dual;
        END;
    ");

    // PRODUCT_REPORT TABLE
    executeSQL($conn, "
        CREATE TABLE product_report (
            product_id NUMBER NOT NULL,
            report_id NUMBER NOT NULL,
            CONSTRAINT pk_product_report PRIMARY KEY (product_id, report_id),
            CONSTRAINT fk_pr_product FOREIGN KEY (product_id) REFERENCES product(product_id),
            CONSTRAINT fk_pr_report FOREIGN KEY (report_id) REFERENCES report(report_id)
        )
    ");

    // WISHLIST TABLE
    executeSQL($conn, "
        CREATE TABLE wishlist (
            wishlist_id NUMBER PRIMARY KEY,
            no_of_items NUMBER NOT NULL,
            user_id NUMBER NOT NULL,
            CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES users(user_id)
        )
    ");

    executeSQL($conn, "CREATE SEQUENCE wishlist_seq START WITH 1 INCREMENT BY 1");
    executeSQL($conn, "
        CREATE OR REPLACE TRIGGER trg_wishlist_pk
        BEFORE INSERT ON wishlist
        FOR EACH ROW
        BEGIN
            SELECT wishlist_seq.NEXTVAL INTO :new.wishlist_id FROM dual;
        END;
    ");

    // WISHLIST_PRODUCT TABLE
    executeSQL($conn, "
        CREATE TABLE wishlist_product (
            wishlist_id NUMBER NOT NULL,
            product_id NUMBER NOT NULL,
            added_date DATE NOT NULL,
            CONSTRAINT pk_wishlist_product PRIMARY KEY (wishlist_id, product_id),
            CONSTRAINT fk_wp_wishlist FOREIGN KEY (wishlist_id) REFERENCES wishlist(wishlist_id),
            CONSTRAINT fk_wp_product FOREIGN KEY (product_id) REFERENCES product(product_id)
        )
    ");

    executeSQL($conn, "INSERT INTO product_category VALUES (1, 'butcher')");
    executeSQL($conn, "INSERT INTO product_category VALUES (2, 'delicatessen')");
    executeSQL($conn, "INSERT INTO product_category VALUES (3, 'fishmonger')");
    executeSQL($conn, "INSERT INTO product_category VALUES (4, 'bakery')");
    executeSQL($conn, "INSERT INTO product_category VALUES (5, 'greengrocer')");

    // INSERT USERS
    $users = [
        ['full_name' => 'Admin User', 'email' => 'admin@example.com', 'phone_no' => '1234567890', 'password' => 'admin123', 'role' => 'admin'],
        ['full_name' => 'Trader User 1', 'email' => 'trader1@example.com', 'phone_no' => '0987654321', 'password' => 'trader123', 'role' => 'trader', 'category_id' => 1],
        ['full_name' => 'Customer User', 'email' => 'customer@example.com', 'phone_no' => '1122334455', 'password' => 'customer123', 'role' => 'customer'],
        ['full_name' => 'Trader User 2', 'email' => 'trader2@example.com', 'phone_no' => '0981654321', 'password' => 'trader1234', 'role' => 'trader', 'category_id' => 2],
        ['full_name' => 'Trader User 3', 'email' => 'trader3@example.com', 'phone_no' => '0987653321', 'password' => 'trader12345', 'role' => 'trader', 'category_id' => 3]
    ];

    $insert_sql = "INSERT INTO users (full_name, email, phone_no, password, role, category_id, status)
               VALUES (:full_name, :email, :phone_no, :password, :role, :category_id, 'active')";

    foreach ($users as $user) {
        $stmt = oci_parse($conn, $insert_sql);
        $hashed_password = password_hash($user['password'], PASSWORD_BCRYPT);
        oci_bind_by_name($stmt, ":full_name", $user['full_name']);
        oci_bind_by_name($stmt, ":email", $user['email']);
        oci_bind_by_name($stmt, ":phone_no", $user['phone_no']);
        oci_bind_by_name($stmt, ":password", $hashed_password);
        oci_bind_by_name($stmt, ":role", $user['role']);

        // Provide a default NULL value if category_id is not set
        $category_id = $user['category_id'] ?? null;
        oci_bind_by_name($stmt, ":category_id", $category_id);

        if (oci_execute($stmt)) {
            echo "Inserted user: {$user['full_name']}<br>";
        } else {
            echo "Failed to insert: {$user['full_name']}<br>";
        }

        oci_free_statement($stmt);
    }

    // Function to create a shop and return its ID
    function createShop($conn, $shop_data) {
        $shop_sql = "INSERT INTO shops (user_id, shop_category, shop_name, description, shop_email, shop_contact_no)
                    VALUES (:user_id, :shop_category, :shop_name, :description, :shop_email, :shop_contact_no)
                    RETURNING shop_id INTO :shop_id";
        
        $shop_stmt = oci_parse($conn, $shop_sql);
        
        // Bind input parameters
        oci_bind_by_name($shop_stmt, ':user_id', $shop_data['user_id']);
        oci_bind_by_name($shop_stmt, ':shop_category', $shop_data['shop_category']);
        oci_bind_by_name($shop_stmt, ':shop_name', $shop_data['shop_name']);
        oci_bind_by_name($shop_stmt, ':description', $shop_data['description']);
        oci_bind_by_name($shop_stmt, ':shop_email', $shop_data['shop_email']);
        oci_bind_by_name($shop_stmt, ':shop_contact_no', $shop_data['shop_contact_no']);
        
        // Bind output parameter for shop_id
        $shop_id = null;
        oci_bind_by_name($shop_stmt, ':shop_id', $shop_id, -1, SQLT_INT);
        
        if (oci_execute($shop_stmt)) {
            echo "Inserted shop: {$shop_data['shop_name']} (ID: $shop_id)<br>";
            oci_free_statement($shop_stmt);
            return $shop_id;
        } else {
            echo "Failed to insert shop: {$shop_data['shop_name']}<br>";
            oci_free_statement($shop_stmt);
            return null;
        }
    }

    // Create shops for trader 2 (delicatessen)
    $delicatessen_shops = [
        [
            'user_id' => 4,
            'shop_category' => 'delicatessen',
            'shop_name' => 'Gourmet Delights',
            'description' => 'Premium imported and local delicatessen products.',
            'shop_email' => 'gourmet@example.com',
            'shop_contact_no' => 555234567
        ],
        [
            'user_id' => 4,
            'shop_category' => 'delicatessen',
            'shop_name' => 'Artisan Foods',
            'description' => 'Handcrafted artisanal delicatessen items.',
            'shop_email' => 'artisan@example.com',
            'shop_contact_no' => 555345678
        ]
    ];

    // Create shops and store their IDs
    $shop_ids = [];
    foreach ($delicatessen_shops as $shop) {
        $shop_id = createShop($conn, $shop);
        if ($shop_id) {
            $shop_ids[] = $shop_id;
        }
    }

    // Create shops for trader 3 (fishmonger)
    $fishmonger_shops = [
        [
            'user_id' => 5,
            'shop_category' => 'fishmonger',
            'shop_name' => 'Ocean Fresh',
            'description' => 'Fresh seafood and fish products daily.',
            'shop_email' => 'ocean@example.com',
            'shop_contact_no' => 555456789
        ],
        [
            'user_id' => 5,
            'shop_category' => 'fishmonger',
            'shop_name' => 'Coastal Catch',
            'description' => 'Premium quality seafood from local fishermen.',
            'shop_email' => 'coastal@example.com',
            'shop_contact_no' => 555567890
        ]
    ];

    // Create shops and store their IDs
    foreach ($fishmonger_shops as $shop) {
        $shop_id = createShop($conn, $shop);
        if ($shop_id) {
            $shop_ids[] = $shop_id;
        }
    }

    // Verify we have all shop IDs before proceeding
    if (count($shop_ids) !== 4) {
        die("Error: Not all shops were created successfully. Expected 4 shops, got " . count($shop_ids));
    }

    require_once 'base_product_blobs.php'; // Make sure this file defines the PROD_BLOBS constant

    // Products for Gourmet Delights (first shop of trader 2)
    $gourmet_products = [
        ['name' => 'Prosciutto di Parma', 'desc' => 'Aged Italian dry-cured ham.', 'price' => 24.99, 'stock' => 30, 'min' => 1, 'max' => 3, 'category' => 'delicatessen'],
        ['name' => 'Truffle Cheese', 'desc' => 'Creamy cheese with black truffle.', 'price' => 18.50, 'stock' => 25, 'min' => 1, 'max' => 4, 'category' => 'delicatessen'],
        ['name' => 'Olive Tapenade', 'desc' => 'Mediterranean olive spread.', 'price' => 12.99, 'stock' => 40, 'min' => 1, 'max' => 5, 'category' => 'delicatessen'],
        ['name' => 'Artisanal Salami', 'desc' => 'Handcrafted Italian salami.', 'price' => 15.99, 'stock' => 35, 'min' => 1, 'max' => 4, 'category' => 'delicatessen'],
        ['name' => 'Balsamic Vinegar', 'desc' => 'Aged balsamic vinegar.', 'price' => 22.99, 'stock' => 20, 'min' => 1, 'max' => 3, 'category' => 'delicatessen'],
        ['name' => 'Goat Cheese', 'desc' => 'Creamy French goat cheese.', 'price' => 14.99, 'stock' => 30, 'min' => 1, 'max' => 4, 'category' => 'delicatessen'],
        ['name' => 'Sun-dried Tomatoes', 'desc' => 'Italian sun-dried tomatoes.', 'price' => 9.99, 'stock' => 45, 'min' => 1, 'max' => 6, 'category' => 'delicatessen'],
        ['name' => 'Pesto Sauce', 'desc' => 'Fresh basil pesto.', 'price' => 11.99, 'stock' => 35, 'min' => 1, 'max' => 5, 'category' => 'delicatessen'],
        ['name' => 'Cured Olives', 'desc' => 'Mixed Mediterranean olives.', 'price' => 13.99, 'stock' => 40, 'min' => 1, 'max' => 5, 'category' => 'delicatessen'],
        ['name' => 'Artisanal Mustard', 'desc' => 'Handcrafted Dijon mustard.', 'price' => 8.99, 'stock' => 50, 'min' => 1, 'max' => 6, 'category' => 'delicatessen']
    ];

    // Products for Artisan Foods (second shop of trader 2)
    $artisan_products = [
        ['name' => 'Smoked Salmon', 'desc' => 'Cold-smoked Norwegian salmon.', 'price' => 19.99, 'stock' => 25, 'min' => 1, 'max' => 3, 'category' => 'delicatessen'],
        ['name' => 'Aged Cheddar', 'desc' => '24-month aged English cheddar.', 'price' => 16.99, 'stock' => 30, 'min' => 1, 'max' => 4, 'category' => 'delicatessen'],
        ['name' => 'Marinated Artichokes', 'desc' => 'Italian marinated artichoke hearts.', 'price' => 10.99, 'stock' => 40, 'min' => 1, 'max' => 5, 'category' => 'delicatessen'],
        ['name' => 'Chorizo', 'desc' => 'Spanish cured chorizo.', 'price' => 14.99, 'stock' => 35, 'min' => 1, 'max' => 4, 'category' => 'delicatessen'],
        ['name' => 'Truffle Honey', 'desc' => 'Honey infused with black truffle.', 'price' => 21.99, 'stock' => 20, 'min' => 1, 'max' => 3, 'category' => 'delicatessen'],
        ['name' => 'Blue Cheese', 'desc' => 'French Roquefort blue cheese.', 'price' => 17.99, 'stock' => 25, 'min' => 1, 'max' => 4, 'category' => 'delicatessen'],
        ['name' => 'Capers', 'desc' => 'Sicilian capers in brine.', 'price' => 7.99, 'stock' => 45, 'min' => 1, 'max' => 6, 'category' => 'delicatessen'],
        ['name' => 'Anchovy Paste', 'desc' => 'Italian anchovy paste.', 'price' => 9.99, 'stock' => 35, 'min' => 1, 'max' => 5, 'category' => 'delicatessen'],
        ['name' => 'Dried Porcini', 'desc' => 'Dried Italian porcini mushrooms.', 'price' => 15.99, 'stock' => 30, 'min' => 1, 'max' => 4, 'category' => 'delicatessen'],
        ['name' => 'Herb Butter', 'desc' => 'Handcrafted herb butter.', 'price' => 8.99, 'stock' => 40, 'min' => 1, 'max' => 5, 'category' => 'delicatessen']
    ];

    // Products for Ocean Fresh (first shop of trader 3)
    $ocean_products = [
        ['name' => 'Fresh Salmon', 'desc' => 'Wild-caught Atlantic salmon.', 'price' => 22.99, 'stock' => 30, 'min' => 1, 'max' => 3, 'category' => 'fishmonger'],
        ['name' => 'Sea Bass', 'desc' => 'Fresh Mediterranean sea bass.', 'price' => 19.99, 'stock' => 25, 'min' => 1, 'max' => 4, 'category' => 'fishmonger'],
        ['name' => 'Tuna Steak', 'desc' => 'Fresh yellowfin tuna steak.', 'price' => 24.99, 'stock' => 20, 'min' => 1, 'max' => 3, 'category' => 'fishmonger'],
        ['name' => 'Prawns', 'desc' => 'Large tiger prawns.', 'price' => 18.99, 'stock' => 35, 'min' => 1, 'max' => 4, 'category' => 'fishmonger'],
        ['name' => 'Mussels', 'desc' => 'Fresh black mussels.', 'price' => 12.99, 'stock' => 40, 'min' => 1, 'max' => 5, 'category' => 'fishmonger'],
        ['name' => 'Cod Fillet', 'desc' => 'Fresh Atlantic cod fillet.', 'price' => 16.99, 'stock' => 30, 'min' => 1, 'max' => 4, 'category' => 'fishmonger'],
        ['name' => 'Squid', 'desc' => 'Fresh whole squid.', 'price' => 14.99, 'stock' => 25, 'min' => 1, 'max' => 4, 'category' => 'fishmonger'],
        ['name' => 'Crab', 'desc' => 'Whole blue crab.', 'price' => 21.99, 'stock' => 20, 'min' => 1, 'max' => 3, 'category' => 'fishmonger'],
        ['name' => 'Oysters', 'desc' => 'Fresh Pacific oysters.', 'price' => 19.99, 'stock' => 35, 'min' => 1, 'max' => 4, 'category' => 'fishmonger'],
        ['name' => 'Scallops', 'desc' => 'Fresh sea scallops.', 'price' => 23.99, 'stock' => 25, 'min' => 1, 'max' => 3, 'category' => 'fishmonger']
    ];

    // Products for Coastal Catch (second shop of trader 3)
    $coastal_products = [
        ['name' => 'Lobster', 'desc' => 'Live Maine lobster.', 'price' => 29.99, 'stock' => 20, 'min' => 1, 'max' => 2, 'category' => 'fishmonger'],
        ['name' => 'Halibut', 'desc' => 'Fresh Pacific halibut.', 'price' => 21.99, 'stock' => 25, 'min' => 1, 'max' => 3, 'category' => 'fishmonger'],
        ['name' => 'Shrimp', 'desc' => 'Wild-caught Gulf shrimp.', 'price' => 17.99, 'stock' => 35, 'min' => 1, 'max' => 4, 'category' => 'fishmonger'],
        ['name' => 'Clams', 'desc' => 'Fresh littleneck clams.', 'price' => 13.99, 'stock' => 40, 'min' => 1, 'max' => 5, 'category' => 'fishmonger'],
        ['name' => 'Swordfish', 'desc' => 'Fresh swordfish steak.', 'price' => 23.99, 'stock' => 20, 'min' => 1, 'max' => 3, 'category' => 'fishmonger'],
        ['name' => 'Octopus', 'desc' => 'Fresh Mediterranean octopus.', 'price' => 19.99, 'stock' => 25, 'min' => 1, 'max' => 3, 'category' => 'fishmonger'],
        ['name' => 'Sea Urchin', 'desc' => 'Fresh uni (sea urchin).', 'price' => 25.99, 'stock' => 15, 'min' => 1, 'max' => 2, 'category' => 'fishmonger'],
        ['name' => 'Mackerel', 'desc' => 'Fresh Atlantic mackerel.', 'price' => 15.99, 'stock' => 30, 'min' => 1, 'max' => 4, 'category' => 'fishmonger'],
        ['name' => 'Anchovies', 'desc' => 'Fresh Spanish anchovies.', 'price' => 12.99, 'stock' => 35, 'min' => 1, 'max' => 5, 'category' => 'fishmonger'],
        ['name' => 'Sea Trout', 'desc' => 'Fresh sea trout fillet.', 'price' => 18.99, 'stock' => 25, 'min' => 1, 'max' => 3, 'category' => 'fishmonger']
    ];

    // Function to insert products for a shop
    function insertProducts($conn, $products, $shop_id, $user_id) {
        $product_sql = "INSERT INTO product (
            product_name, description, price, stock, min_order, max_order, 
            product_image, added_date, updated_date, product_status, 
            discount_percentage, shop_id, user_id, product_category_name
        ) VALUES (
            :name, :product_desc, :price, :stock, :min_order, :max_order,
            :product_image, SYSDATE, SYSDATE, 'active', 0, :shop_id, :user_id, :category
        )";

        foreach ($products as $i => $product) {
            $stmt = oci_parse($conn, $product_sql);
            
            oci_bind_by_name($stmt, ':name', $product['name']);
            oci_bind_by_name($stmt, ':product_desc', $product['desc']);
            oci_bind_by_name($stmt, ':price', $product['price']);
            oci_bind_by_name($stmt, ':stock', $product['stock']);
            oci_bind_by_name($stmt, ':min_order', $product['min']);
            oci_bind_by_name($stmt, ':max_order', $product['max']);
            oci_bind_by_name($stmt, ':category', $product['category']);
            oci_bind_by_name($stmt, ':shop_id', $shop_id);
            oci_bind_by_name($stmt, ':user_id', $user_id);

            // Use a default image from PROD_BLOBS
            $base64 = PROD_BLOBS[$i % count(PROD_BLOBS)];
            if (str_starts_with($base64, 'data:')) {
                $base64 = explode(',', $base64, 2)[1];
            }
            $binary_data = base64_decode($base64);
            $lob = oci_new_descriptor($conn, OCI_D_LOB);
            oci_bind_by_name($stmt, ':product_image', $lob, -1, OCI_B_BLOB);
            $lob->writeTemporary($binary_data, OCI_TEMP_BLOB);

            if (oci_execute($stmt)) {
                echo "Inserted product: {$product['name']} for shop $shop_id<br>";
            }
            
            $lob->free();
            oci_free_statement($stmt);
        }
    }

    // Insert products for each shop using the retrieved shop IDs
    insertProducts($conn, $gourmet_products, $shop_ids[0], 4); // Gourmet Delights
    insertProducts($conn, $artisan_products, $shop_ids[1], 4); // Artisan Foods
    insertProducts($conn, $ocean_products, $shop_ids[2], 5);   // Ocean Fresh
    insertProducts($conn, $coastal_products, $shop_ids[3], 5); // Coastal Catch

    // Insert into cart
    executeSQL($conn, "INSERT INTO cart (cart_id, user_id) VALUES (1, 1)");

    // Insert into cart_product
    executeSQL($conn, "INSERT INTO cart_product (cart_id, product_id, quantity) VALUES (1, 1, 1)");
    executeSQL($conn, "INSERT INTO cart_product (cart_id, product_id, quantity) VALUES (1, 2, 2)");

    // Insert into wishlist
    executeSQL($conn, "INSERT INTO wishlist (wishlist_id, no_of_items, user_id) VALUES (1, 1, 1)");
    executeSQL($conn, "INSERT INTO wishlist_product (wishlist_id, product_id, added_date) VALUES (1, 2, SYSDATE)");
    // Insert into reviews
    executeSQL($conn, "INSERT INTO reviews (review_id, user_id, product_id, review_rating, review_description, review_date) VALUES (1, 1, 1, 5, 'Great product!', SYSDATE)");
    executeSQL($conn, "INSERT INTO reviews (review_id, user_id, product_id, review_rating, review_description, review_date) VALUES (2, 1, 2, 4, 'Interesting read', SYSDATE)");

    // Insert into orders
    executeSQL($conn, "INSERT INTO orders (order_id, user_id, cart_id, order_date, total_item_count, total_amount, order_status) VALUES (1, 1, 1, TO_TIMESTAMP('2024-01-15', 'YYYY-MM-DD'), 2, 1240, 'Completed')");

    // Insert into payment
    executeSQL($conn, "INSERT INTO payment (payment_id, order_id, user_id, amount, payment_method, payment_status, payment_date) VALUES (1, 1, 1, 1240, 'Credit Card', 'Completed', SYSDATE)");

    // Insert into collection_slot
    executeSQL($conn, "INSERT INTO collection_slot (collection_slot_id, slot_date, slot_day, slot_time, total_order) VALUES (1, TO_DATE('2024-01-16', 'YYYY-MM-DD'), 'Monday', TO_TIMESTAMP('2024-01-16 10:00:00', 'YYYY-MM-DD HH24:MI:SS'), 1)");

    // Insert into coupon
    executeSQL($conn, "INSERT INTO coupon (coupon_id, coupon_code, coupon_start_date, coupon_end_date, coupon_description, coupon_discount_percent) VALUES (1, 'WINTER10', TO_DATE('2024-01-01', 'YYYY-MM-DD'), TO_DATE('2024-02-01', 'YYYY-MM-DD'), 'Winter Sale', 10)");

    // Insert into report
    executeSQL($conn, "INSERT INTO report (report_id, user_id, report_description, report_date) VALUES (1, 1, 'Issue with checkout', SYSDATE)");

    // Insert into product_report
    executeSQL($conn, "INSERT INTO product_report (product_id, report_id) VALUES (2, 1)");

    oci_close($conn);
} else {
    echo "Could not connect to database.<br>";
}
