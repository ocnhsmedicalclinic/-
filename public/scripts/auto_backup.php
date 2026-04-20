<?php
// Set CLI environment
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

// Define root path (clinic-system root)
define('ROOT_PATH', dirname(__DIR__, 2));

// Load environment variables
if (file_exists(ROOT_PATH . '/.env')) {
    $env = parse_ini_file(ROOT_PATH . '/.env');
    $db_host = $env['DB_HOST'];
    $db_user = $env['DB_USER'];
    $db_pass = $env['DB_PASS'];
    $db_name = $env['DB_NAME'];
} else {
    // Fallback to config values if .env missing
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'clinic_db';
}

// Connect to Database
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set backup directory
$backupDir = ROOT_PATH . '/public/backups/';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
    // Secure it
    file_put_contents($backupDir . '.htaccess', "Deny from all\n<FilesMatch \"\.(php|php5|php4|php3|phtml|pl|py|jsp|asp|htm|html|shtml|sh|cgi)$\">\n    Deny from all\n</FilesMatch>");
}

// Generate Filename (Timestamped for history)
$timestamp = date("Y-m-d_H-i-s");
$zipFilename = "clinic_system_auto_backup_" . $timestamp . ".zip";
$zipPath = $backupDir . $zipFilename;

echo "Starting automated backup...\n";

// --- BACKUP LOGIC START ---

// Check if ZipArchive is available
if (!class_exists('ZipArchive')) {
    echo "Error: ZipArchive class not found. Please enable php_zip extension.\n";
    // Fallback? Just SQL
    $sqlFilename = "clinic_system_auto_backup_" . $timestamp . ".sql";
    $sqlPath = $backupDir . $sqlFilename;

    // Generate SQL
    $sqlDump = generateSQLDump($conn);
    file_put_contents($sqlPath, $sqlDump);
    echo "Backup saved as SQL only: $sqlFilename\n";

} else {
    // Create ZIP
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        die("Failed to create ZIP archive at $zipPath\n");
    }

    // Add SQL Dump
    echo "Dumping database...\n";
    $sqlDump = generateSQLDump($conn);
    $zip->addFromString('database.sql', $sqlDump);

    // Add Key Config Files (Optional - be careful with secrets)
    // $zip->addFile(ROOT_PATH . '/config/db.php', 'config/db.php');

    // Add Uploaded Files
    echo "Adding uploaded files...\n";
    $uploadDir = ROOT_PATH . '/public/uploads';
    if (file_exists($uploadDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadDir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = 'uploads/' . substr($filePath, strlen($uploadDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    $zip->close();
    echo "Backup created successfully: $zipFilename\n";
}

// --- CLEANUP LOGIC ---
// Keep last 7 days of backups
echo "Pruning old backups...\n";
$backups = glob($backupDir . "clinic_system_auto_backup_*.{zip,sql}", GLOB_BRACE);
$now = time();
$days = 7; // Retention period
$seconds = $days * 24 * 60 * 60;

foreach ($backups as $file) {
    if (is_file($file)) {
        if ($now - filemtime($file) >= $seconds) {
            unlink($file);
            echo "Deleted old backup: " . basename($file) . "\n";
        }
    }
}

echo "Backup process completed.\n";

// --- HELPER FUNCTION: SQL DUMP ---
function generateSQLDump($conn)
{
    $tables = array();
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    $return = "-- Medical Clinic System Automated Backup\n";
    $return .= "-- Generated: " . date("Y-m-d H:i:s") . "\n";
    $return .= "-- Host: " . $conn->host_info . "\n\n";
    $return .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $return .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

    foreach ($tables as $table) {
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

    return $return;
}
?>