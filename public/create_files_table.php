<?php
require_once '../config/db.php';

echo "<h2>Creating Student Files Table...</h2>";

$sql = "CREATE TABLE IF NOT EXISTS student_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(255) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "Table 'student_files' created successfully (or already exists).";
} else {
    echo "Error creating table: " . $conn->error;
}
?>