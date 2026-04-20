<?php
// Protect this page - require authentication
ob_start();
require_once "../config/db.php";
requireAdmin();

$activePage = "backup";
$pageTitle = "Backup & Recovery";

// Handle Backup Action
if (isset($_GET['action']) && $_GET['action'] == 'backup') {
  // Use memory limit fix for large backups
  ini_set('memory_limit', '-1');
  set_time_limit(0);

  // Check if ZipArchive is available
  if (!class_exists('ZipArchive')) {
    // FALLBACK: SQL ONLY BACKUP
    $filename = "clinic_backup_" . date("Y-m-d_H-i-s") . ".sql";

    // Generate SQL (Shared Logic)
    $tables = array();
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
      $tables[] = $row[0];
    }

    $return = "-- Medical Clinic System Backup (SQL Only - Zip Extension Missing)\n";
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

    // Log this backup
    require_once '../config/backup_reminder.php';
    logBackup($conn, $_SESSION['username']);
    logSecurityEvent('DATABASE_BACKUP', 'User ' . $_SESSION['username'] . ' created a SQL database backup (Zip fallback).');
    $_SESSION['backup_created'] = true;

    // Download SQL file
    if (ob_get_length())
      ob_clean();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($return));
    echo $return;
    exit;
  }

  // BACKUP TYPE (Default: essential for smaller size)
  $backupType = isset($_GET['type']) ? $_GET['type'] : 'essential';
  $zipFilename = ($backupType == 'essential' ? "clinic_data_backup_" : "clinic_full_backup_") . date("Y-m-d_H-i-s") . ".zip";
  $zipPath = sys_get_temp_dir() . '/' . $zipFilename;

  $zip = new ZipArchive();
  if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die("Failed to create ZIP archive.");
  }

  // 1. Generate SQL Dump
  $tables = array();
  $result = $conn->query("SHOW TABLES");
  while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
  }

  $return = "-- Medical Clinic System Backup (" . strtoupper($backupType) . ")\n";
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
          if ($row[$j] === null) {
            $return .= 'NULL';
          } else {
            $row[$j] = addslashes($row[$j]);
            $row[$j] = str_replace("\n", "\\n", $row[$j]);
            $return .= '"' . $row[$j] . '"';
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

  // Add SQL to ZIP (At the root)
  $zip->addFromString('database.sql', $return);

  // 2. Add System Files
  $projectRoot = realpath(__DIR__ . '/..');
  $uploadDir = realpath(__DIR__ . '/uploads');

  if ($backupType == 'essential') {
    // ESSENTIAL MODE: Only Database + Uploads + Archives
    // We already added Database. Now add Uploads and Archives.

    // Add Uploads (Map to 'uploads/' in ZIP)
    if ($uploadDir && file_exists($uploadDir)) {
      $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadDir, RecursiveDirectoryIterator::SKIP_DOTS),
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

    // Add Archives
    $archivesDir = $projectRoot . DIRECTORY_SEPARATOR . 'archives';
    if (file_exists($archivesDir)) {
      $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($archivesDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
      );
      foreach ($files as $name => $file) {
        if (!$file->isDir()) {
          $filePath = $file->getRealPath();
          $relativePath = 'archives/' . substr($filePath, strlen($archivesDir) + 1);
          $zip->addFile($filePath, $relativePath);
        }
      }
    }

  } else {
    // FULL MODE: All System Files with strict exclusions
    if ($projectRoot && file_exists($projectRoot)) {
      $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
      );

      $skipDirectories = ['.git', 'node_modules', 'backups', 'logs', 'tmp', 'temp', '__pycache__', '.vscode', '.idea'];

      foreach ($files as $name => $file) {
        if (!$file->isDir()) {
          $filePath = $file->getRealPath();
          $relativePath = substr($filePath, strlen($projectRoot) + 1);

          // Check if file is in a skipped directory
          $shouldSkip = false;
          foreach ($skipDirectories as $dir) {
            if (strpos($relativePath, $dir . DIRECTORY_SEPARATOR) === 0 || strpos($relativePath, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR) !== false) {
              $shouldSkip = true;
              break;
            }
          }

          // Skip existing backups/dumps at root to avoid recursion
          if ($shouldSkip || basename($filePath) === 'database.sql' || str_ends_with($filePath, '.zip') || str_ends_with($filePath, '.sql')) {
            if (basename($filePath) !== 'database.sql' || $relativePath !== 'database.sql') {
              continue;
            }
          }

          $zip->addFile($filePath, $relativePath);
        }
      }
    }
  }

  $zip->close();

  // Log this backup
  require_once '../config/backup_reminder.php';
  logBackup($conn, $_SESSION['username']);
  logSecurityEvent('DATABASE_BACKUP', 'User ' . $_SESSION['username'] . ' created a full system backup (DB + Files).');
  $_SESSION['backup_created'] = true;

  // Download ZIP
  if (file_exists($zipPath)) {
    if (ob_get_length())
      ob_clean();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);
    unlink($zipPath); // Delete temp file
    exit;
  } else {
    die("Error: Backup file creation failed.");
  }
}

// Handle Restore Action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['backup_file'])) {
  $file_tmp = $_FILES['backup_file']['tmp_name'];
  $file_ext = strtolower(pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION));

  if ($file_ext === 'sql') {
    // Legacy SQL Restore
    $lines = file($file_tmp);
    if (!$lines) {
      $_SESSION['error_message'] = "Failed to read the backup file.";
    } else {
      $conn->query("SET FOREIGN_KEY_CHECKS=0");
      $query = '';
      $error_count = 0;
      foreach ($lines as $line) {
        if (substr(trim($line), 0, 2) == '--' || trim($line) == '')
          continue;
        $query .= $line;
        if (substr(trim($line), -1, 1) == ';') {
          try {
            if (!$conn->query($query))
              $error_count++;
          } catch (Exception $e) {
            $error_count++;
          }
          $query = '';
        }
      }
      $conn->query("SET FOREIGN_KEY_CHECKS=1");
      if ($error_count > 0) {
        $_SESSION['error_message'] = "Database restored with $error_count errors.";
      } else {
        logSecurityEvent('DATABASE_RESTORE', 'User ' . $_SESSION['username'] . ' restored a DB backup (SQL).');
        $_SESSION['success_message'] = "Database restored successfully!";
      }
    }
  } elseif ($file_ext === 'zip') {
    // Full ZIP Restore
    $zip = new ZipArchive;
    if ($zip->open($file_tmp) === TRUE) {
      $extractPath = sys_get_temp_dir() . '/restore_' . uniqid();
      mkdir($extractPath);
      $zip->extractTo($extractPath);
      $zip->close();

      // 1. Restore Database
      $sqlFile = $extractPath . '/database.sql';
      if (file_exists($sqlFile)) {
        $lines = file($sqlFile);
        $conn->query("SET FOREIGN_KEY_CHECKS=0");
        $query = '';
        $error_count = 0;
        foreach ($lines as $line) {
          if (substr(trim($line), 0, 2) == '--' || trim($line) == '')
            continue;
          $query .= $line;
          if (substr(trim($line), -1, 1) == ';') {
            try {
              if (!$conn->query($query))
                $error_count++;
            } catch (Exception $e) {
              $error_count++;
            }
            $query = '';
          }
        }
        $conn->query("SET FOREIGN_KEY_CHECKS=1");
      }

      // 2. Restore Uploads (Check multiple possible paths in the ZIP)
      $uploadedSource = $extractPath . '/uploads'; // Path in 'essential' backup
      if (!file_exists($uploadedSource)) {
        $uploadedSource = $extractPath . '/public/uploads'; // Path in 'full' backup
      }
      $targetDir = realpath(__DIR__ . '/uploads');

      if (file_exists($uploadedSource) && $targetDir) {
        // Simple Copy Logic - Overwrite existing
        $files = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($uploadedSource, RecursiveDirectoryIterator::SKIP_DOTS),
          RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $item) {
          // Path relative to source 'uploads'
          $subPath = $files->getSubPathName();
          $destPath = $targetDir . DIRECTORY_SEPARATOR . $subPath;

          if ($item->isDir()) {
            if (!file_exists($destPath)) {
              mkdir($destPath, 0755, true);
            }
          } else {
            // Ensure parent directory exists
            $parentDir = dirname($destPath);
            if (!file_exists($parentDir)) {
              mkdir($parentDir, 0755, true);
            }
            copy($item->getRealPath(), $destPath);
          }
        }
      }

      // Clean up temp
      // recursivel delete extractPath
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

      if (isset($error_count) && $error_count > 0) {
        $_SESSION['error_message'] = "System restored with database errors ($error_count).";
      } else {
        logSecurityEvent('FULL_RESTORE', 'User ' . $_SESSION['username'] . ' restored a full system backup (ZIP).');
        $_SESSION['success_message'] = "Full system restored successfully!";
      }

    } else {
      $_SESSION['error_message'] = "Failed to open ZIP file.";
    }
  } else {
    $_SESSION['error_message'] = "Invalid file format. Please upload .sql or .zip";
  }

  header("Location: backup.php");
  exit;
}

include "index_layout.php";
?>

<?php if (isset($_SESSION['success_message'])): ?>
  <div class="alert-toast success">
    <i class="fa-solid fa-check-circle"></i>
    <span><?= htmlspecialchars($_SESSION['success_message']) ?></span>
  </div>
  <?php unset($_SESSION['success_message']); ?>
  <script>
    setTimeout(() => {
      const toast = document.querySelector('.alert-toast');
      toast.style.animation = 'slideInRight 0.3s ease reverse';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  </script>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
  <div class="alert-toast error">
    <i class="fa-solid fa-exclamation-circle"></i>
    <span><?= htmlspecialchars($_SESSION['error_message']) ?></span>
  </div>
  <?php unset($_SESSION['error_message']); ?>
  <script>
    setTimeout(() => {
      const toast = document.querySelector('.alert-toast');
      toast.style.animation = 'slideInRight 0.3s ease reverse';
      setTimeout(() => toast.remove(), 300);
    }, 3000);
  </script>
<?php endif; ?>

<div class="backup-header" style="text-align: center; margin-top: 40px;">
  <h1 style="color: #333; font-size: 2.5rem; font-weight: 800; margin-bottom: 10px;">Backup & Recovery</h1>
  <p style="color: #666; font-size: 1.1rem;">Manage and secure your clinic's database and files</p>
</div>

<div class="backup-container">

  <?php if (!class_exists('ZipArchive')): ?>
    <div class="backup-card warning"
      style="border-left: 5px solid #ffa000; grid-column: span 2; display: flex; align-items: center; gap: 20px; background: #fffbe6; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
      <div class="icon-wrapper"
        style="background: rgba(255, 160, 0, 0.1); color: #ffa000; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
        <i class="fa-solid fa-triangle-exclamation"></i>
      </div>
      <div class="card-content">
        <h3 style="color: #856404; margin-bottom: 5px;">Zip Extension Missing!</h3>
        <p style="color: #856404; font-size: 0.95rem; margin: 0;">
          The <strong>ZipArchive</strong> PHP extension is not enabled on your server.
          You can only download <strong>Database (.sql)</strong> backups.
          To enable full backups (including images/files), please enable <code>extension=zip</code> in your
          <code>php.ini</code> and restart Apache.
        </p>
      </div>
    </div>
  <?php endif; ?>

  <!-- BACKUP OPTIONS -->
  <?php if (class_exists('ZipArchive')): ?>
    <!-- ESSENTIAL BACKUP (RECOMMENDED) -->
    <div class="backup-card highlight">
      <div class="icon-wrapper backup-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
        <i class="fa-solid fa-box-archive"></i>
      </div>
      <div class="card-content">
        <div class="card-badge"
          style="background: #28a745; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; display: inline-block; margin-bottom: 8px;">
          RECOMMENDED</div>
        <h3>Smallest Storage (Data Only)</h3>
        <p>
          Backs up only your <strong>Database</strong>, <strong>Uploaded Files</strong>, and <strong>Archives</strong>.
          Fastest and takes up very little storage space. Perfect for daily backups.
        </p>
        <a href="backup.php?action=backup&type=essential" class="btn green text-center"
          style="width: 300px; margin-top: 15px; text-decoration: none;">
          <i class="fa-solid fa-download"></i> Download Data Backup
        </a>
      </div>
    </div>

    <!-- FULL BACKUP -->
    <div class="backup-card">
      <div class="icon-wrapper backup-icon" style="background: rgba(0, 123, 255, 0.1); color: #007bff;">
        <i class="fa-solid fa-file-zipper"></i>
      </div>
      <div class="card-content">
        <h3>Full System Backup</h3>
        <p>
          Complete backup of <strong>Everything</strong> (Source Code + Database + Uploads).
          Provides full insurance but results in a <strong>much larger file size</strong>.
        </p>
        <a href="backup.php?action=backup&type=full" class="btn blue text-center"
          style="width: 300px; margin-top: 15px; text-decoration: none;">
          <i class="fa-solid fa-download"></i> Download Full Backup
        </a>
      </div>
    </div>
  <?php else: ?>
    <!-- DATABASE ONLY (FALLBACK) -->
    <div class="backup-card">
      <div class="icon-wrapper backup-icon">
        <i class="fa-solid fa-database"></i>
      </div>
      <div class="card-content">
        <h3>Database Backup</h3>
        <p>
          Download a backup of your <strong>database tables and data</strong> (.sql file).
          Note: System files and images are not included because ZipArchive is missing.
        </p>
        <a href="backup.php?action=backup" class="btn green text-center" style="width: 300px; margin-top: 15px;">
          <i class="fa-solid fa-download"></i> Download Database (.sql)
        </a>
      </div>
    </div>
  <?php endif; ?>

  <!-- RESTORE -->
  <div class="backup-card danger">
    <div class="icon-wrapper restore-icon">
      <i class="fa-solid fa-cloud-arrow-up"></i>
    </div>
    <div class="card-content">
      <h3>Restore System</h3>
      <p>
        Restore from a backup file (.zip or .sql).
        <strong>Warning: This will overwrite existing data.</strong>
      </p>

      <form action="backup.php" method="POST" enctype="multipart/form-data">
        <div class="file-input-wrapper">
          <input type="file" name="backup_file" accept=".sql,.zip" required id="backupFile" class="file-input">
          <label for="backupFile" class="file-label">
            <i class="fa-solid fa-file-code"></i> Choose .zip or .sql file
          </label>
        </div>
        <button type="submit" class="btn red w-100">
          <i class="fa-solid fa-rotate-left"></i> Restore Data
        </button>
      </form>
    </div>
  </div>

</div>

<script src="assets/js/app.js?v=<?= time() ?>"></script>
</body>

</html>