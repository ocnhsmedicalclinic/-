<?php
require_once "../config/db.php";
requireLogin();

$uploadDir = "uploads/medical_records/";

// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// I-check kung may ID sa URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    $patientType = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'student';

    if ($patientType === 'employee') {
        $filesTable = "employee_files";
        $idField = "employee_id";
        $backLink = "employees.php";
        $stmt = $conn->prepare("SELECT name FROM employees WHERE id = ?");
    } else {
        $type = 'student'; // Force default student if not employee
        $filesTable = "student_files";
        $idField = "student_id";
        $backLink = "student.php";
        $stmt = $conn->prepare("SELECT name FROM students WHERE id = ?");
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $person = $result->fetch_assoc();
    } else {
        // Fallback check: maybe it's the other type?
        $otherType = ($patientType === 'employee') ? 'student' : 'employee';
        $otherTable = ($patientType === 'employee') ? 'students' : 'employees';
        $stmt2 = $conn->prepare("SELECT name FROM $otherTable WHERE id = ?");
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        $res2 = $stmt2->get_result();

        if ($res2->num_rows > 0) {
            // Found under different type, redirect to correct type
            header("Location: medical_records.php?id=$id&type=$otherType" . (isset($_GET['delete_id']) ? "&delete_id=" . $_GET['delete_id'] : ""));
            exit;
        }

        die("<div style='text-align:center; padding:50px; font-family:Arial;'><h1>❌ Error</h1><p>Patient record not found for ID: $id ($patientType).</p><a href='$backLink' style='padding:10px 20px; background:" . ($patientType === 'employee' ? '#795548' : '#00ACB1') . "; color:white; text-decoration:none; border-radius:5px;'>Go Back</a></div>");
    }
} else {
    die("<div style='text-align:center; padding:50px; font-family:Arial;'><h1>⚠️ Error</h1><p>No valid Patient ID provided.</p><a href='student.php' style='padding:10px 20px; background:#00ACB1; color:white; text-decoration:none; border-radius:5px;'>Go Back</a></div>");
}

$message = "";
$error = "";

// Dynamic color based on type
$primaryColor = ($patientType === 'employee') ? '#795548' : '#00ACB1';

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $fileName = basename($file['name']);
    $targetFilePath = $uploadDir . time() . "_" . $fileName;
    $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
    $fileSize = $file['size'];

    // Check file size (5MB limit)
    if ($file['size'] < 5000000) {
        if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
            $stmt = $conn->prepare("INSERT INTO $filesTable ($idField, filename, filepath, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssi", $id, $fileName, $targetFilePath, $fileType, $fileSize);

            if ($stmt->execute()) {
                $message = "File uploaded successfully.";
            } else {
                $error = "Database error: " . $conn->error;
            }
        } else {
            $error = "Sorry, there was an error uploading your file.";
        }
    } else {
        $error = "File is too large. Max 5MB.";
    }
}

// Handle File Delete
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);

    // Get file info first
    $stmt = $conn->prepare("SELECT * FROM $filesTable WHERE id = ? AND $idField = ?");
    $stmt->bind_param("ii", $deleteId, $id);
    $stmt->execute();
    $fileRes = $stmt->get_result();

    if ($fileRes->num_rows > 0) {
        $fileData = $fileRes->fetch_assoc();
        $filePath = $fileData['filepath'];

        // Delete from DB
        $stmt = $conn->prepare("DELETE FROM $filesTable WHERE id = ?");
        $stmt->bind_param("i", $deleteId);
        if ($stmt->execute()) {
            // Delete from disk
            $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . $filePath;
            if (file_exists($absolutePath)) {
                unlink($absolutePath);
            }
            $_SESSION['success_message'] = "File deleted successfully.";
            header("Location: medical_records.php?id=$id&type=$patientType");
            exit;
        }
    }
}

// Check session message
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

include "index_layout.php";
?>

<div class="container-fluid" style="padding: 20px;">
    <div class="panel form"
        style="max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">

        <!-- Header -->
        <div
            style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-bottom: 2px solid <?= $primaryColor ?>; padding-bottom: 15px; margin-bottom: 20px;">
            <div>
                <h2 style="margin: 0; color: #333; font-family: 'Cinzel', serif;">Medical Records</h2>
                <div style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                    <h4 style="margin: 0; color: #666; text-transform: uppercase;">
                        <?= htmlspecialchars($person['name']) ?></h4>
                    <span
                        style="background: <?= $primaryColor ?>; color: white; padding: 4px 15px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <?= ($patientType === 'employee') ? 'Employee' : 'Student' ?>
                    </span>
                </div>
            </div>
            <a href="<?= $backLink ?>?view_id=<?= $id ?>" class="btn"
                style="background-color: <?= $primaryColor ?>; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; white-space: nowrap; flex-shrink: 0; font-weight: bold; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); transition: 0.3s;">
                <i class="fa-solid fa-arrow-left"></i> Back to Records
            </a>
        </div>

        <?php if ($message): ?>
            <div
                style="padding: 12px; background: #d4edda; border-left: 4px solid #28a745; color: #155724; border-radius: 6px; margin-bottom: 20px;">
                <i class="fa-solid fa-check-circle"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div
                style="padding: 12px; background: #fee; border-left: 4px solid #f44; color: #c33; border-radius: 6px; margin-bottom: 20px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Upload Form -->
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
            <h3 style="margin-top: 0; color: <?= $primaryColor ?>;"><i class="fa-solid fa-cloud-arrow-up"></i> Upload
                New File</h3>
            <form action="" method="post" enctype="multipart/form-data">
                <div style="margin-bottom: 5px;">
                    <label for="fileUpload"
                        style="display: block; font-weight: bold; color: #555; margin-bottom: 5px;">Select
                        File</label>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="file" name="file" id="fileUpload" required
                            style="flex-grow: 1; padding: 8px; border: 1px solid #ddd; border-radius: 5px; background: white; min-width: 200px;">
                        <button type="submit" class="btn"
                            style="background-color: <?= $primaryColor ?>; color: white; border: none; padding: 9px 20px; border-radius: 5px; cursor: pointer; white-space: nowrap; flex-grow: 1;">
                            <i class="fa-solid fa-upload"></i> Upload
                        </button>
                    </div>
                    <small style="color: #777; display: block; margin-top: 5px;">All file types allowed (Max
                        5MB)</small>
                </div>
            </form>
        </div>

        <!-- Files List -->
        <h3 style="color: #333;"><i class="fa-solid fa-folder-open"></i> Uploaded Documents</h3>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: <?= $primaryColor ?>; color: white;">
                        <th style="padding: 12px; text-align: left; border-radius: 6px 0 0 6px;">Filename</th>
                        <th style="padding: 12px; text-align: center;">Type</th>
                        <th style="padding: 12px; text-align: center;">Size</th>
                        <th style="padding: 12px; text-align: center;">Date Uploaded</th>
                        <th style="padding: 12px; text-align: center; border-radius: 0 6px 6px 0;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $files = $conn->query("SELECT * FROM $filesTable WHERE $idField = '$id' ORDER BY uploaded_at DESC");

                    if ($files->num_rows > 0):
                        while ($file = $files->fetch_assoc()):
                            $fileIcon = 'fa-file';
                            $fType = $file['file_type'];
                            if (in_array($fType, ['jpg', 'jpeg', 'png', 'gif']))
                                $fileIcon = 'fa-file-image';
                            elseif ($fType == 'pdf')
                                $fileIcon = 'fa-file-pdf';
                            elseif (in_array($fType, ['doc', 'docx']))
                                $fileIcon = 'fa-file-word';
                            ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;">
                                    <i class="fa-solid <?= $fileIcon ?>" style="color: #777; margin-right: 8px;"></i>
                                    <a href="<?= htmlspecialchars($file['filepath']) ?>" target="_blank"
                                        style="color: #333; text-decoration: none; font-weight: 500;">
                                        <?= htmlspecialchars($file['filename']) ?>
                                    </a>
                                </td>
                                <td
                                    style="padding: 12px; text-align: center; color: #666; text-transform: uppercase; font-size: 12px;">
                                    <?= $fType ?>
                                </td>
                                <td style="padding: 12px; text-align: center; color: #666; font-size: 12px;">
                                    <?= round($file['file_size'] / 1024, 1) ?> KB
                                </td>
                                <td style="padding: 12px; text-align: center; color: #666; font-size: 12px;">
                                    <?= date('M d, Y h:i A', strtotime($file['uploaded_at'])) ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <a href="<?= htmlspecialchars($file['filepath']) ?>" target="_blank" class="btn-action view"
                                        title="View in Browser"
                                        style="display: inline-block; padding: 5px 10px; background: #e3f2fd; color: #1976d2; border-radius: 4px; margin-right: 5px;">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="<?= htmlspecialchars($file['filepath']) ?>"
                                        download="<?= htmlspecialchars($file['filename']) ?>" class="btn-action download"
                                        title="Download File"
                                        style="display: inline-block; padding: 5px 10px; background: #e8f5e9; color: #2e7d32; border-radius: 4px; margin-right: 5px;">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                    <a href="?id=<?= $id ?>&type=<?= $type ?>&delete_id=<?= $file['id'] ?>"
                                        onclick="confirmDelete(event, this.href)" class="btn-action delete" title="Delete"
                                        style="display: inline-block; padding: 5px 10px; background: #ffebee; color: #c62828; border-radius: 4px;">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php
                        endwhile;
                    else:
                        ?>
                        <tr>
                            <td colspan="5" style="padding: 30px; text-align: center; color: #999;">
                                <i class="fa-solid fa-folder-open"
                                    style="font-size: 40px; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                No files uploaded yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script>
    function confirmDelete(event, url) {
        event.preventDefault(); // Stop default navigation

        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url; // Proceed with deletion
            }
        })
    }
</script>

<?php $conn->close(); ?>