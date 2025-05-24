<?php

include 'connect.php';

$conn = getDBConnection();

// Function to safely execute SQL with error handling
function executeSQL($conn, $sql) {
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $error = oci_error($conn);
        echo "SQL Parse Error: " . $error['message'] . "<br>";
        return false;
    }
    
    $result = oci_execute($stmt);
    if (!$result) {
        $error = oci_error($stmt);
        // Only show error if it's not "table/view does not exist" or "trigger does not exist"
        if ($error['code'] != 942 && $error['code'] != 4080) {
            echo "SQL Execute Error: " . $error['message'] . "<br>";
        }
    }
    
    oci_free_statement($stmt);
    return $result;
}

// Check if trigger exists before dropping
$check_trigger_sql = "SELECT COUNT(*) FROM USER_TRIGGERS WHERE TRIGGER_NAME = 'MY_USER_ID_TRG'";
$stmt = oci_parse($conn, $check_trigger_sql);
oci_execute($stmt);
$trigger_exists = oci_fetch_array($stmt, OCI_NUM)[0];
oci_free_statement($stmt);

if ($trigger_exists) {
    $sql = "DROP TRIGGER MY_USER_ID_TRG";
    executeSQL($conn, $sql);
}

// Drop table and sequence
$sql = "DROP TABLE MY_USERS";
executeSQL($conn, $sql);

$sql = "DROP SEQUENCE MY_USER_ID_SEQ";
executeSQL($conn, $sql);

// Create table (removed duplicate PRIMARY KEY)
$sql = "CREATE TABLE MY_USERS (
    MY_USER_ID NUMBER, 
    USERNAME VARCHAR2(50) NOT NULL, 
    PASSWORD VARCHAR2(200) NOT NULL, 
    ROLE VARCHAR2(20) NOT NULL, 
    CREATED_AT DATE DEFAULT SYSDATE, 
    CHECK (role IN ('admin', 'trader')) ENABLE, 
    PRIMARY KEY (MY_USER_ID)
    USING INDEX  ENABLE, 
    UNIQUE (USERNAME)
    USING INDEX  ENABLE
   )";

if (executeSQL($conn, $sql)) {
    echo "Table MY_USERS created successfully<br>";
}

// Create sequence
$sql = "CREATE SEQUENCE MY_USER_ID_SEQ START WITH 1 INCREMENT BY 1";
if (executeSQL($conn, $sql)) {
    echo "Sequence MY_USER_ID_SEQ created successfully<br>";
}

// Create trigger
$sql = "CREATE OR REPLACE TRIGGER MY_USER_ID_TRG
BEFORE INSERT ON MY_USERS
FOR EACH ROW
BEGIN
    SELECT MY_USER_ID_SEQ.NEXTVAL INTO :NEW.MY_USER_ID FROM DUAL;
END;";

if (executeSQL($conn, $sql)) {
    echo "Trigger MY_USER_ID_TRG created successfully<br>";
}

// Insert sample users (admin and trader)
$insert_admin = "INSERT INTO MY_USERS (USERNAME, PASSWORD, ROLE) VALUES ('admin_user', 'admin123', 'admin')";
$insert_trader = "INSERT INTO MY_USERS (USERNAME, PASSWORD, ROLE) VALUES ('trader_user', 'trader123', 'trader')";

$stmt_admin = oci_parse($conn, $insert_admin);
oci_execute($stmt_admin);
oci_free_statement($stmt_admin);

$stmt_trader = oci_parse($conn, $insert_trader);
oci_execute($stmt_trader);
oci_free_statement($stmt_trader);
oci_close($conn);


