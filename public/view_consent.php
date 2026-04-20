<?php
require_once "../config/db.php";
requireLogin();

// Handle Delete Request
if (isset($_GET['delete']) && isset($_GET['id']) && isset($_GET['type'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $type = $_GET['type']; // 'front' or 'back'

    if ($type === 'front') {
        $column = 'consent_front_file';
    } elseif ($type === 'back') {
        $column = 'consent_back_file';
    } else {
        die("Invalid file type");
    }

    // Get current filename to delete physical file
    $result = $conn->query("SELECT $column FROM students WHERE id='$id'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $filename = $row[$column];

        // Delete from database
        $sql = "UPDATE students SET $column=NULL WHERE id='$id'";
        if ($conn->query($sql)) {
            // Delete physical file if it exists
            if (!empty($filename)) {
                $filepath = __DIR__ . "/uploads/consent/" . basename($filename);
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
            }

            // Redirect back with success
            header("Location: view_consent.php?id=$id&deleted=success");
            exit;
        } else {
            header("Location: view_consent.php?id=$id&deleted=error");
            exit;
        }
    }
}

// Check ID
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $result = $conn->query("SELECT * FROM students WHERE id = '$id'");

    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();

        // Get uploaded files
        $consent_front = $student['consent_front_file'] ?? '';
        $consent_back = $student['consent_back_file'] ?? '';
    } else {
        die("<div style='text-align:center; padding:50px;'><h1>❌ Error</h1><p>Student record not found.</p><a href='student.php'>Go Back</a></div>");
    }
} else {
    die("<div style='text-align:center; padding:50px;'><h1>⚠️ Error</h1><p>No Student ID provided.</p><a href='student.php'>Go Back</a></div>");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Consent -
        <?= htmlspecialchars($student['name']) ?>
    </title>
    <link rel="icon" type="image/x-icon" href="assets/img/ocnhs_logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f4f4f4;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #00ACB1;
        }

        .header h1 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }

        .student-info {
            background: linear-gradient(135deg, #00ACB1 0%, #76E1E4 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 172, 177, 0.3);
        }

        .student-info p {
            margin: 5px 0;
            font-size: 14px;
            text-transform: uppercase;
        }

        .student-info strong {
            color: rgba(255, 255, 255, 0.9);
        }

        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .file-card {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            background: #fafafa;
        }

        .file-card h3 {
            margin-top: 0;
            color: #00ACB1;
            font-size: 16px;
        }

        .file-preview {
            min-height: 400px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .file-preview img {
            max-width: 100%;
            max-height: 400px;
        }

        .file-preview iframe {
            width: 100%;
            height: 400px;
            border: none;
        }

        .no-file {
            color: #999;
            font-style: italic;
        }

        .file-info {
            font-size: 12px;
            color: #666;
            margin-top: 10px;
        }

        .action-btns {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            min-width: 200px;
            /* Ensure equal size */
            justify-content: center;
        }

        .btn-back {
            background: #6c757d;
        }

        .btn-edit {
            background: #00ACB1;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .btn-delete {
            background: #dc3545;
            margin-left: 10px;
        }

        .btn-delete:hover {
            background: #c82333;
        }

        .btn-view {
            background: #e3f2fd;
            color: #1976d2;
        }

        .btn-view:hover {
            background: #d0e7ff;
            color: #1565c0;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Custom Confirm Modal */
        .confirm-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(3px);
        }

        .confirm-modal.active {
            display: flex;
        }

        .confirm-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .confirm-icon {
            width: 70px;
            height: 70px;
            background: #fff3cd;
            color: #856404;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 20px;
            line-height: 1;
        }

        .confirm-icon i {
            display: block;
        }

        .confirm-title {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .confirm-message {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .confirm-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .confirm-btn {
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
            transition: 0.3s;
        }

        .confirm-btn-cancel {
            background: #6c757d;
            color: white;
        }

        .confirm-btn-cancel:hover {
            background: #5a6268;
        }

        .confirm-btn-delete {
            background: #dc3545;
            color: white;
        }

        .confirm-btn-delete:hover {
            background: #c82333;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .header h1 {
                font-size: 20px;
            }

            .files-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .file-preview {
                min-height: 300px;
            }

            .file-preview iframe {
                height: 300px;
            }

            .action-btns {
                flex-direction: row;
                justify-content: space-between;
                gap: 10px;
            }

            .btn {
                width: 48%;
                justify-content: center;
                padding: 12px 10px;
                font-size: 13px;
            }

            .confirm-content {
                padding: 20px;
                max-width: 90%;
            }

            .confirm-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }

            .confirm-title {
                font-size: 18px;
            }

            .confirm-buttons {
                flex-direction: column;
                width: 100%;
            }

            .confirm-btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .action-btns {
                flex-direction: column-reverse;
                gap: 10px;
            }

            .btn {
                width: 100%;
                font-size: 14px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 15px;
            }

            .header h1 {
                font-size: 18px;
            }

            .student-info {
                padding: 12px;
            }

            .student-info p {
                font-size: 12px;
            }

            .file-card {
                padding: 15px;
            }

            .file-preview {
                min-height: 250px;
            }

            .file-info {
                font-size: 11px;
            }
        }
    </style>
    <script>
        let deleteCallback = null;

        function confirmDelete(type, id) {
            const typeName = type === 'front' ? 'Front Page' : 'Back Page';
            const modal = document.getElementById('confirmModal');
            const message = document.getElementById('confirmMessage');

            message.innerHTML = `Are you sure you want to delete the <strong>${typeName}</strong> file?<br><span style="font-size: 12px; color: #999;">This action cannot be undone.</span>`;

            deleteCallback = function () {
                window.location.href = `view_consent.php?delete=1&id=${id}&type=${type}`;
            };

            modal.classList.add('active');
        }

        function closeConfirmModal() {
            const modal = document.getElementById('confirmModal');
            modal.classList.remove('active');
            deleteCallback = null;
        }

        function executeDelete() {
            if (deleteCallback) {
                deleteCallback();
            }
        }
    </script>
    <?php include 'assets/inc/console_suppress.php'; ?>
</head>

<body>


    <div class="container">
        <div class="header">
            <h1><i class="fa-solid fa-file-contract"></i> Parent Consent Record</h1>
            <p style="margin: 5px 0 0 0; color: #666;">View uploaded consent documents</p>
        </div>

        <div class="student-info">
            <p><strong>Student Name:</strong>
                <?= htmlspecialchars($student['name']) ?>
            </p>
            <p><strong>LRN:</strong>
                <?= htmlspecialchars($student['lrn']) ?>
            </p>
            <p><strong>Grade/Section:</strong>
                <?= htmlspecialchars($student['curriculum']) ?>
            </p>
        </div>

        <?php if (isset($_GET['deleted'])): ?>
            <?php if ($_GET['deleted'] === 'success'): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-check-circle"></i>
                    <span>File deleted successfully!</span>
                </div>
            <?php else: ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-exclamation-circle"></i>
                    <span>Error deleting file. Please try again.</span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="files-grid">
            <!-- Front Page -->
            <div class="file-card">
                <h3><i class="fa-solid fa-file-pdf"></i> Parent Consent - Front</h3>
                <div class="file-preview">
                    <?php if (!empty($consent_front)):
                        $file_ext = strtolower(pathinfo($consent_front, PATHINFO_EXTENSION));
                        $file_path = "uploads/consent/" . basename($consent_front);

                        if (file_exists($file_path)):
                            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                <img src="<?= $file_path ?>" alt="Consent Front">
                            <?php elseif ($file_ext == 'pdf'): ?>
                                <iframe src="<?= $file_path ?>"></iframe>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="no-file"><i class="fa-solid fa-exclamation-circle"></i> File not found</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="no-file"><i class="fa-solid fa-inbox"></i> No file uploaded</p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($consent_front)): ?>
                    <div class="file-info">
                        <i class="fa-solid fa-file"></i>
                        <?= basename($consent_front) ?>
                        <br>
                        <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                            <a href="<?= $file_path ?>" target="_blank" class="btn btn-view"
                                style="margin-top: 10px; padding: 8px 15px; font-size: 12px; min-width: auto;">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                            <a href="<?= $file_path ?>" download class="btn btn-edit"
                                style="margin-top: 10px; padding: 8px 15px; font-size: 12px; min-width: auto;">
                                <i class="fa-solid fa-download"></i> Download
                            </a>
                            <button onclick="confirmDelete('front', <?= $id ?>)" class="btn btn-delete"
                                style="margin-top: 10px; padding: 8px 15px; font-size: 12px; min-width: auto; margin-left: 0;">
                                <i class="fa-solid fa-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Back Page -->
            <div class="file-card">
                <h3><i class="fa-solid fa-file-pdf"></i> Parent Consent - Back</h3>
                <div class="file-preview">
                    <?php if (!empty($consent_back)):
                        $file_ext = strtolower(pathinfo($consent_back, PATHINFO_EXTENSION));
                        $file_path = "uploads/consent/" . basename($consent_back);

                        if (file_exists($file_path)):
                            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                <img src="<?= $file_path ?>" alt="Consent Back">
                            <?php elseif ($file_ext == 'pdf'): ?>
                                <iframe src="<?= $file_path ?>"></iframe>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="no-file"><i class="fa-solid fa-exclamation-circle"></i> File not found</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="no-file"><i class="fa-solid fa-inbox"></i> No file uploaded</p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($consent_back)): ?>
                    <div class="file-info">
                        <i class="fa-solid fa-file"></i>
                        <?= basename($consent_back) ?>
                        <br>
                        <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
                            <a href="<?= $file_path ?>" target="_blank" class="btn btn-view"
                                style="margin-top: 10px; padding: 8px 15px; font-size: 12px; min-width: auto;">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                            <a href="<?= $file_path ?>" download class="btn btn-edit"
                                style="margin-top: 10px; padding: 8px 15px; font-size: 12px; min-width: auto;">
                                <i class="fa-solid fa-download"></i> Download
                            </a>
                            <button onclick="confirmDelete('back', <?= $id ?>)" class="btn btn-delete"
                                style="margin-top: 10px; padding: 8px 15px; font-size: 12px; min-width: auto; margin-left: 0;">
                                <i class="fa-solid fa-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="action-btns">
            <a href="student.php?view_id=<?= $id ?>" class="btn btn-back">
                <i class="fa-solid fa-arrow-left"></i> Go Back
            </a>
            <a href="edit_consent.php?id=<?= $id ?>" class="btn btn-edit">
                <i class="fa-solid fa-upload"></i> Upload Documents
            </a>
        </div>
    </div>

    <!-- Custom Confirm Modal -->
    <div id="confirmModal" class="confirm-modal" onclick="if(event.target === this) closeConfirmModal()">
        <div class="confirm-content">
            <div class="confirm-icon">
                <i class="fa-solid fa-exclamation-triangle"></i>
            </div>
            <div class="confirm-title">Delete File?</div>
            <div class="confirm-message" id="confirmMessage"></div>
            <div class="confirm-buttons">
                <button class="confirm-btn confirm-btn-cancel" onclick="closeConfirmModal()">
                    <i class="fa-solid fa-times"></i> Cancel
                </button>
                <button class="confirm-btn confirm-btn-delete" onclick="executeDelete()">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>

</body>

</html>