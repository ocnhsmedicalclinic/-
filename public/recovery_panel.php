<?php
session_start();
require_once '../config/recovery.php';

// Check if in recovery mode
if (!isRecoveryMode()) {
    header("Location: recovery_index.php");
    exit();
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: recovery_index.php");
    exit();
}

$message = "";
$error = "";

// Handle database restore
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['backup_file'])) {
    $file_tmp = $_FILES['backup_file']['tmp_name'];
    $file_name = $_FILES['backup_file']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if (!in_array($file_ext, ['sql', 'zip'])) {
        $error = "Invalid file format. Please upload a .sql or .zip file.";
    } else {
        try {
            // First, try to connect to MySQL server without selecting a database
            $temp_conn = @new mysqli('localhost', 'root', '');

            if ($temp_conn->connect_error) {
                throw new Exception("Cannot connect to MySQL server. Please make sure MySQL is running in XAMPP: " . $temp_conn->connect_error);
            }

            // Check if database exists
            $db_check = $temp_conn->query("SHOW DATABASES LIKE 'clinic_db'");

            if ($db_check->num_rows == 0) {
                // Database doesn't exist, create it
                if (!$temp_conn->query("CREATE DATABASE clinic_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
                    throw new Exception("Failed to create database: " . $temp_conn->error);
                }
            }

            // Select the database
            if (!$temp_conn->select_db('clinic_db')) {
                throw new Exception("Failed to select database: " . $temp_conn->error);
            }

            $conn = $temp_conn;
            $conn->set_charset("utf8mb4");

            if ($file_ext === 'sql') {
                restoreFromSql($file_tmp, $conn);
                $message = "Database restored successfully from SQL file! You can now exit recovery mode.";
            } else {
                // ZIP RESTORE
                if (!class_exists('ZipArchive')) {
                    throw new Exception("ZipArchive extension is not enabled on this server. Cannot restore ZIP files.");
                }

                $zip = new ZipArchive;
                if ($zip->open($file_tmp) === TRUE) {
                    $extractPath = sys_get_temp_dir() . '/recovery_restore_' . uniqid();
                    if (!mkdir($extractPath)) {
                        throw new Exception("Failed to create temporary extraction directory.");
                    }
                    $zip->extractTo($extractPath);
                    $zip->close();

                    // 1. Restore Database
                    $sqlFile = $extractPath . '/database.sql';
                    if (file_exists($sqlFile)) {
                        restoreFromSql($sqlFile, $conn);
                    }

                    // 2. Restore System Files
                    $targetRoot = realpath(__DIR__ . '/..');
                    if ($targetRoot) {
                        $files = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS),
                            RecursiveIteratorIterator::SELF_FIRST
                        );

                        foreach ($files as $item) {
                            $relativePath = substr($item->getRealPath(), strlen($extractPath) + 1);

                            // Skip the database.sql file as it's already processed
                            if ($relativePath === 'database.sql')
                                continue;

                            $destPath = $targetRoot . DIRECTORY_SEPARATOR . $relativePath;

                            if ($item->isDir()) {
                                if (!file_exists($destPath)) {
                                    mkdir($destPath, 0777, true);
                                }
                            } else {
                                // Ensure parent directory exists
                                $parentDir = dirname($destPath);
                                if (!file_exists($parentDir)) {
                                    mkdir($parentDir, 0777, true);
                                }
                                // Use @ to suppress errors in case a file is locked/in-use
                                @copy($item->getRealPath(), $destPath);
                            }
                        }
                    }

                    // Clean up temp
                    $it = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CHILD_FIRST
                    );
                    foreach ($it as $file) {
                        if ($file->isDir())
                            rmdir($file->getRealPath());
                        else
                            unlink($file->getRealPath());
                    }
                    rmdir($extractPath);

                    $message = "Full system restored successfully from ZIP file! Database and uploaded files have been recovered.";
                } else {
                    throw new Exception("Failed to open ZIP backup file.");
                }
            }
            $conn->close();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

function restoreFromSql($filePath, $conn)
{
    $lines = file($filePath);
    if (!$lines)
        throw new Exception("Failed to read SQL file.");

    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    $conn->query("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");

    $query = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if (substr($line, 0, 2) == '--' || $line == '' || substr($line, 0, 2) == '/*')
            continue;
        if (stripos($line, 'USE ') === 0 || stripos($line, 'USE`') === 0)
            continue;
        if (stripos($line, 'CREATE DATABASE') !== false || stripos($line, 'DROP DATABASE') !== false)
            continue;

        $query .= $line . "\n";
        if (substr($line, -1, 1) == ';') {
            if (!$conn->query($query)) {
                // We continue on errors but could log them if needed
            }
            $query = '';
        }
    }
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Recovery Panel for OCNHS Medical Clinic - Secure database restoration with premium glassmorphism design.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <title>Recovery Panel - OCNHS Medical Clinic</title>
    <link rel="icon" type="image/x-icon" href="assets/img/ocnhs_logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #ff6b6b;
            --secondary: #28a745;
            --bg-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            --card-bg: rgba(255, 255, 255, 0.15);
            --card-blur: 10px;
            --text-color: #fff;
            --alert-bg: rgba(255, 193, 7, 0.15);
            --alert-border: #ffca28;
            --heading-color: #333;
            --info-card-bg: rgba(255, 255, 255, 0.3);
            --info-label: #666;
            --info-value: #333;
            --file-bg: #e7f3ff;
            --file-hover: #d0e8ff;
            --file-text: #333;
            --instruct-bg: #e7f3ff;
            --instruct-title: #0d5aa7;
            --instruct-text: #333;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --primary: #ff8a80;
                --secondary: #66bb6a;
                --bg-gradient: linear-gradient(135deg, #0d1b2a 0%, #1b263b 100%);
                --card-bg: rgba(30, 30, 30, 0.4);
                --text-color: #e0e0e0;
                --alert-bg: rgba(255, 152, 0, 0.2);
                --alert-border: #ffb74d;
                --heading-color: #e0e0e0;
                --info-card-bg: rgba(0, 0, 0, 0.3);
                --info-label: #aaa;
                --info-value: #fff;
                --file-bg: rgba(33, 150, 243, 0.1);
                --file-hover: rgba(33, 150, 243, 0.2);
                --file-text: #e0e0e0;
                --instruct-bg: rgba(255, 255, 255, 0.05);
                --instruct-title: #64b5f6;
                --instruct-text: #ccc;
            }
        }

        /* Alert styling */
        .alert {
            background: var(--alert-bg);
            border-left: 5px solid var(--alert-border);
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert i {
            font-size: 1.2rem;
        }

        .alert strong {
            color: var(--primary);
        }

        /* Ensure alert-warning uses same styling */
        .alert-warning {
            background: var(--alert-bg);
            border-left-color: var(--alert-border);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background: var(--bg-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
        }

        .recovery-header {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            padding: 20px 40px;
            border-radius: 15px;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .recovery-header h1 {
            color: var(--primary);
            font-size: 1.8rem;
        }

        .recovery-header .badge {
            background: var(--primary);
            color: #fff;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .recovery-header .logout-btn {
            background: #dc3545;
            color: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: transform .2s, background .2s;
        }

        .recovery-header .logout-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .container {
            max-width: 900px;
            width: 100%;
        }

        .panel {
            background: var(--card-bg);
            backdrop-filter: blur(var(--card-blur));
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            margin-bottom: 20px;
            animation: fadeIn 0.6s ease-out;
        }

        .panel h2 {
            color: var(--heading-color);
            margin-bottom: 20px;
            font-size: 1.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .info-card {
            background: var(--info-card-bg);
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #00ACB1;
            transition: transform .2s, box-shadow .2s;
        }

        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .info-card label {
            display: block;
            font-size: 0.85rem;
            color: var(--info-label);
            margin-bottom: 5px;
        }

        .info-card strong {
            font-size: 1.1rem;
            color: var(--info-value);
        }

        .file-input-wrapper {
            margin: 20px 0;
        }

        .file-input-wrapper input[type="file"] {
            display: none;
        }

        .file-label {
            display: inline-block;
            padding: 12px 25px;
            background: var(--file-bg);
            color: var(--file-text);
            border: 2px dashed #2196F3;
            border-radius: 10px;
            cursor: pointer;
            transition: all .3s;
            text-align: center;
            width: 100%;
        }

        .file-label:hover {
            background: var(--file-hover);
            border-color: #0d5aa7;
        }

        .file-label i {
            margin-right: 8px;
            color: #2196F3;
        }

        .btn-restore {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #fff;
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform .2s, box-shadow .2s;
            width: 100%;
            text-transform: uppercase;
        }

        .btn-restore:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
        }

        .btn-restore:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        .instructions {
            background: var(--instruct-bg);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .instructions h3 {
            color: var(--instruct-title);
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .instructions ol {
            margin-left: 20px;
        }

        .instructions li {
            margin-bottom: 8px;
            color: var(--instruct-text);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width:600px) {
            .recovery-header {
                flex-direction: column;
                gap: 10px;
            }

            .recovery-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
    <?php include 'assets/inc/console_suppress.php'; ?>
</head>

<body>
    <div class="container">
        <div class="recovery-header">
            <h1>
                <i class="fa-solid fa-shield-halved"></i>
                Emergency Recovery Panel
            </h1>
            <div class="user-info">
                <span class="badge">
                    <i class="fa-solid fa-user-shield"></i> RECOVERY MODE
                </span>
                <a href="?action=logout" class="logout-btn">
                    <i class="fa-solid fa-right-from-bracket"></i> Exit
                </a>
            </div>
        </div>

        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <div>
                <strong>Emergency Mode Active!</strong> You are using the recovery account. Regular database operations
                may not work if the database is corrupted.
            </div>
        </div>

        <div class="panel">
            <h2>
                <i class="fa-solid fa-database"></i>
                Database Recovery
            </h2>

            <div class="info-grid">
                <div class="info-card">
                    <label>Recovery Status</label>
                    <strong style="color: #ff6b6b;">ACTIVE</strong>
                </div>
                <div class="info-card">
                    <label>Access Level</label>
                    <strong>SUPERADMIN</strong>
                </div>
                <div class="info-card">
                    <label>Session User</label>
                    <strong>
                        <?= htmlspecialchars($_SESSION['username']) ?>
                    </strong>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" id="restoreForm">
                <div class="file-input-wrapper">
                    <input type="file" name="backup_file" accept=".sql,.zip" required id="backupFile"
                        onchange="updateFileName()">
                    <label for="backupFile" class="file-label" id="fileLabel">
                        <i class="fa-solid fa-file-zipper"></i>
                        <span>Choose ZIP or SQL Backup File</span>
                    </label>
                </div>

                <button type="submit" class="btn-restore">
                    <i class="fa-solid fa-rotate-left"></i> Restore Database
                </button>
            </form>

            <div class="instructions">
                <h3><i class="fa-solid fa-circle-info"></i> Recovery Instructions</h3>
                <ol>
                    <li>Select a valid <strong>.zip (Full Backup)</strong> or <strong>.sql (Database Only)</strong>
                        backup file.</li>
                    <li><strong>Full Backup (.zip)</strong> will recover both the database and all uploaded patient
                        files.</li>
                    <li>Click "Restore Database" to begin the recovery process.</li>
                    <li>Wait for the restoration to complete (do not close this page).</li>
                    <li>If successful, exit recovery mode and login normally.</li>
                </ol>
            </div>
        </div>
    </div>

    <script>
        // Show SweetAlert for success or error messages
        <?php if ($message): ?>
            Swal.fire({
                icon: 'success',
                title: 'Database Restored!',
                html: '<?= addslashes($message) ?>',
                confirmButtonColor: '#28a745',
                confirmButtonText: 'Exit Recovery Mode',
                showCancelButton: true,
                cancelButtonText: 'Stay Here',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?action=logout';
                }
            });
        <?php endif; ?>

        <?php if ($error): ?>
            Swal.fire({
                icon: 'error',
                title: 'Restoration Failed',
                html: '<?= addslashes($error) ?>',
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Try Again'
            });
        <?php endif; ?>

        function updateFileName() {
            const input = document.getElementById('backupFile');
            const label = document.getElementById('fileLabel');
            if (input.files.length > 0) {
                label.innerHTML = '<i class="fa-solid fa-check"></i> ' + input.files[0].name;
                label.style.background = '#d4edda';
                label.style.borderColor = '#28a745';
            }
        }
    </script>
</body>

</html>