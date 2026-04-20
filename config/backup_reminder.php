<?php
// Backup Notification Helper
// This file provides functions to check and display backup reminders

/**
 * Get the last backup date from the database
 */
function getLastBackupDate($conn)
{
    // Check if backup_log table exists, if not create it
    $conn->query("CREATE TABLE IF NOT EXISTS backup_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        backup_date DATETIME NOT NULL,
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Get the absolute latest backup based on date
    $result = $conn->query("SELECT MAX(backup_date) as last_backup FROM backup_log");
    $row = $result->fetch_assoc();
    return $row['last_backup'];
}

/**
 * Check if backup reminder should be shown (Monthly rule)
 * Rule: Show if no backup has been performed in the current calendar month.
 * Persists until a backup is created for the current month.
 */
function shouldShowBackupReminder($conn)
{
    $currentMonth = date('Y-m');

    // Ensure table exists
    $conn->query("CREATE TABLE IF NOT EXISTS backup_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        backup_date DATETIME NOT NULL,
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Check if any backup exists for the current month
    $stmt = $conn->prepare("SELECT id FROM backup_log WHERE backup_date LIKE ? LIMIT 1");
    $search = $currentMonth . '%';
    $stmt->bind_param("s", $search);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        return false; // Backup already performed this month
    }

    // No backup found for this month, show reminder
    return true;
}

/**
 * Log a new backup
 */
function logBackup($conn, $username)
{
    // Ensure table exists
    $conn->query("CREATE TABLE IF NOT EXISTS backup_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        backup_date DATETIME NOT NULL,
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $stmt = $conn->prepare("INSERT INTO backup_log (backup_date, created_by) VALUES (NOW(), ?)");
    $stmt->bind_param("s", $username);
    $stmt->execute();
}
?>