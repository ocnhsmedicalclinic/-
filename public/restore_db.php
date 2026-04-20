<?php
require_once "../config/db.php";
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['backup_file'])) {

    $file_tmp = $_FILES['backup_file']['tmp_name'];
    $file_name = $_FILES['backup_file']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if ($file_ext != 'sql') {
        $_SESSION['error_message'] = "Invalid file format. Please upload a .sql file.";
        header("Location: backup.php");
        exit;
    }

    // Read the file properly
    $lines = file($file_tmp);
    if (!$lines) {
        $_SESSION['error_message'] = "Failed to read the backup file.";
        header("Location: backup.php");
        exit;
    }

    // Disable FK checks and error reporting for cleaner restore flow
    $conn->query("SET FOREIGN_KEY_CHECKS=0");

    $query = '';
    $success_count = 0;
    $error_count = 0;

    foreach ($lines as $line) {
        // Skip comments and empty lines
        if (substr(trim($line), 0, 2) == '--' || trim($line) == '') {
            continue;
        }

        $query .= $line;

        // If line ends with semicolon, execute query
        if (substr(trim($line), -1, 1) == ';') {
            if (!$conn->query($query)) {
                $error_count++;
                // Optionally log specific query errors: error_log($conn->error);
            } else {
                $success_count++;
            }
            $query = '';
        }
    }

    $conn->query("SET FOREIGN_KEY_CHECKS=1");

    logSecurityEvent('DATABASE_RESTORE', "User {$_SESSION['username']} restored database from file: $file_name");

    if ($error_count == 0) {
        $_SESSION['success_message'] = "Database restored successfully.";
    } elseif ($success_count > 0) {
        $_SESSION['success_message'] = "Database restored with warnings ($error_count errors).";
    } else {
        $_SESSION['error_message'] = "Failed to restore database.";
    }

} else {
    $_SESSION['error_message'] = "No file uploaded.";
}

header("Location: backup.php");
exit;
?>