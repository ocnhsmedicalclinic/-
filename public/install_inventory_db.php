<?php
// install_inventory_db.php
// Run this file once to set up the Inventory System tables in the database.

require_once '../config/db.php';

echo "<h2>Setting up Inventory System Database...</h2>";

// 1. Create 'inventory_items' table
$sql_items = "CREATE TABLE IF NOT EXISTS `inventory_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `category` ENUM('Medicine', 'Medical Supply', 'Equipment') NOT NULL,
    `description` TEXT,
    `quantity` INT(11) NOT NULL DEFAULT 0,
    `unit` VARCHAR(50) NOT NULL DEFAULT 'pcs',
    `expiry_date` DATE DEFAULT NULL,
    `reorder_level` INT(11) NOT NULL DEFAULT 10,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql_items) === TRUE) {
    echo "✅ Table 'inventory_items' created successfully.<br>";
} else {
    echo "❌ Error creating 'inventory_items': " . $conn->error . "<br>";
}

// 2. Create 'inventory_transactions' table
$sql_transactions = "CREATE TABLE IF NOT EXISTS `inventory_transactions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `item_id` INT(11) NOT NULL,
    `type` ENUM('Stock In', 'Stock Out', 'Adjustment', 'Dispensed') NOT NULL,
    `quantity` INT(11) NOT NULL,
    `transaction_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `remarks` TEXT,
    `user_id` INT(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `item_id` (`item_id`),
    CONSTRAINT `fk_inventory_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql_transactions) === TRUE) {
    echo "✅ Table 'inventory_transactions' created successfully.<br>";
} else {
    echo "❌ Error creating 'inventory_transactions': " . $conn->error . "<br>";
}

// 3. Insert Initial Dummy Data (Only if empty)
$check_empty = $conn->query("SELECT COUNT(*) as count FROM inventory_items");
$row = $check_empty->fetch_assoc();

if ($row['count'] == 0) {
    $sql_insert = "INSERT INTO `inventory_items` (`name`, `category`, `description`, `quantity`, `unit`, `expiry_date`, `reorder_level`) VALUES
    ('Paracetamol 500mg', 'Medicine', 'For fever and pain relief', 100, 'tablet', '2025-12-31', 20),
    ('Amoxicillin 500mg', 'Medicine', 'Antibiotic', 50, 'capsule', '2024-10-15', 10),
    ('Mefenamic Acid', 'Medicine', 'Pain reliever', 80, 'capsule', '2025-05-20', 15),
    ('Face Mask', 'Medical Supply', 'Disposable surgical mask', 200, 'pcs', NULL, 50),
    ('Alcohol 70%', 'Medical Supply', 'Disinfectant', 10, 'bottle', '2026-01-01', 5),
    ('Stethoscope', 'Equipment', 'Medical listening device', 2, 'unit', NULL, 1),
    ('Blood Pressure Monitor', 'Equipment', 'Digital BP Monitor', 3, 'unit', NULL, 1);";

    if ($conn->query($sql_insert) === TRUE) {
        echo "✅ Initial dummy data inserted successfully.<br>";
    } else {
        echo "❌ Error inserting dummy data: " . $conn->error . "<br>";
    }
} else {
    echo "ℹ️ Table 'inventory_items' is not empty. Skipping dummy data.<br>";
}

echo "<br><h3>🎉 Setup Complete! You can now use the Inventory System.</h3>";
echo "<a href='inventory.php'>Go to Inventory Dashboard</a>"; // We will create this next
?>