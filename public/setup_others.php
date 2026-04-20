<?php
require_once "../config/db.php";

$sql = "CREATE TABLE IF NOT EXISTS others (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    birth_date DATE DEFAULT NULL,
    age INT DEFAULT NULL,
    sdo VARCHAR(255) DEFAULT '',
    gender VARCHAR(50) DEFAULT '',
    address TEXT DEFAULT '',
    treatment_logs_json LONGTEXT DEFAULT '[]',
    is_archived TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'others' created successfully.";
} else {
    echo "Error creating table: " . $conn->error;
}
?>