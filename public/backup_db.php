<?php
require_once "../config/db.php";
requireAdmin();

// Configuration
$filename = "clinic_backup_" . date("Y-m-d_H-i-s") . ".sql";
$host = "localhost"; // Assuming localhost from previous context, ideally should come from config but db.php has the connection
// We can use the existing $conn object

// Get all tables
$tables = array();
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$return = "-- Medical Clinic System Backup\n";
$return .= "-- Generated: " . date("Y-m-d H:i:s") . "\n";
$return .= "-- Host: " . $conn->host_info . "\n\n";

$return .= "SET FOREIGN_KEY_CHECKS=0;\n";
$return .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

foreach ($tables as $table) {
    if ($table == 'activity_logs' && isset($_POST['exclude_logs'])) {
        continue; // Option to exclude logs if we ever want to add it
    }

    $result = $conn->query("SELECT * FROM $table");
    $num_fields = $result->field_count;

    $return .= "-- Table structure for table `$table`\n";
    $return .= "DROP TABLE IF EXISTS `$table`;\n";

    $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch_row();
    $return .= "\n\n" . $row2[1] . ";\n\n";

    $return .= "-- Dumping data for table `$table`\n";

    for ($i = 0; $i < $num_fields; $i++) {
        while ($row = $result->fetch_row()) {
            $return .= "INSERT INTO `$table` VALUES(";
            for ($j = 0; $j < $num_fields; $j++) {
                $row[$j] = addslashes($row[$j]);
                $row[$j] = str_replace("\n", "\\n", $row[$j]);
                if (isset($row[$j])) {
                    $return .= '"' . $row[$j] . '"';
                } else {
                    $return .= '""';
                }
                if ($j < ($num_fields - 1)) {
                    $return .= ',';
                }
            }
            $return .= ");\n";
        }
    }
    $return .= "\n\n";
}

$return .= "SET FOREIGN_KEY_CHECKS=1;\n";

// Log the activity
logSecurityEvent('DATABASE_BACKUP', 'User ' . $_SESSION['username'] . ' created a database backup.');

// Log for Reminder Reset
require_once '../config/backup_reminder.php';
logBackup($conn, $_SESSION['username']);

// Force download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($return));
echo $return;
exit;
?>