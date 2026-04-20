<?php
require_once '../config/db.php';

// Add JSON columns to employees if they don't exist
$cols = ['treatment_logs_json', 'health_exam_json'];
foreach ($cols as $col) {
    $res = $conn->query("SHOW COLUMNS FROM employees LIKE '$col'");
    if ($res->num_rows == 0) {
        $conn->query("ALTER TABLE employees ADD $col LONGTEXT NULL");
        echo "Added $col to employees table.\n";
    }
}

// Create employee_files table
$sql = "CREATE TABLE IF NOT EXISTS employee_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(255) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql)) {
    echo "Table employee_files ensured.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
?>