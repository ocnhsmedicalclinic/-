<?php
require_once '../config/db.php';

echo "Updating notifications schema...\n";

// Add user_id column
$checkCol = $conn->query("SHOW COLUMNS FROM notifications LIKE 'user_id'");
if ($checkCol->num_rows == 0) {
    // Add user_id nullable. If NULL, it might mean Global/Admin? Or we can use explicit role.
    // simpler: user_id for specific user. NULL for "System/Admin Generic".
    if ($conn->query("ALTER TABLE notifications ADD COLUMN user_id INT DEFAULT NULL AFTER id")) {
        echo "Added 'user_id' column successfully.\n";
        // Add index for performance
        $conn->query("ALTER TABLE notifications ADD INDEX (user_id)");
    } else {
        echo "Error adding 'user_id': " . $conn->error . "\n";
    }
} else {
    echo "'user_id' column already exists.\n";
}

echo "Schema update complete.\n";
?>