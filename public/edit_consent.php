<?php
require_once "../config/db.php";
requireLogin();

// Ensure columns exist
$checkFront = $conn->query("SHOW COLUMNS FROM students LIKE 'consent_front_file'");
if ($checkFront->num_rows == 0) {
    $conn->query("ALTER TABLE students ADD COLUMN consent_front_file VARCHAR(255) NULL");
}
$checkBack = $conn->query("SHOW COLUMNS FROM students LIKE 'consent_back_file'");
if ($checkBack->num_rows == 0) {
    $conn->query("ALTER TABLE students ADD COLUMN consent_back_file VARCHAR(255) NULL");
}

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    $upload_dir = "uploads/consent/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $front_file = '';
    $back_file = '';
    $errors = [];

    // Handle Front File
    if (isset($_FILES['consent_front']) && $_FILES['consent_front']['error'] == 0) {
        $file_ext = strtolower(pathinfo($_FILES['consent_front']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_ext, $allowed)) {
            $new_name = "consent_front_" . $id . "_" . time() . "." . $file_ext;
            $target = $upload_dir . $new_name;

            if (move_uploaded_file($_FILES['consent_front']['tmp_name'], $target)) {
                $front_file = $new_name;
            } else {
                $errors[] = "Failed to upload front file.";
            }
        } else {
            $errors[] = "Invalid front file type. Allowed: PDF, JPG, PNG";
        }
    }

    // Handle Back File
    if (isset($_FILES['consent_back']) && $_FILES['consent_back']['error'] == 0) {
        $file_ext = strtolower(pathinfo($_FILES['consent_back']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_ext, $allowed)) {
            $new_name = "consent_back_" . $id . "_" . time() . "." . $file_ext;
            $target = $upload_dir . $new_name;

            if (move_uploaded_file($_FILES['consent_back']['tmp_name'], $target)) {
                $back_file = $new_name;
            } else {
                $errors[] = "Failed to upload back file.";
            }
        } else {
            $errors[] = "Invalid back file type. Allowed: PDF, JPG, PNG";
        }
    }

    // Update database
    if (empty($errors)) {
        $updates = [];
        if ($front_file)
            $updates[] = "consent_front_file='$front_file'";
        if ($back_file)
            $updates[] = "consent_back_file='$back_file'";

        if (!empty($updates)) {
            $sql = "UPDATE students SET " . implode(", ", $updates) . " WHERE id='$id'";

            if ($conn->query($sql)) {
                // Success page
                echo "
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <title>Upload Successful</title>
                    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'>
                    <style>
                        body { margin: 0; height: 100vh; display: flex; justify-content: center; align-items: center; background-color: rgba(0,0,0,0.5); font-family: 'Arial', sans-serif; backdrop-filter: blur(5px); }
                        .success-card { background: white; padding: 40px 60px; border-radius: 15px; text-align: center; box-shadow: 0 15px 40px rgba(0,0,0,0.2); animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); max-width: 400px; width: 90%; }
                        .icon-box { width: 80px; height: 80px; background: #e0f2f1; color: #00ACB1; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 40px; }
                        h2 { margin: 0 0 10px; color: #333; }
                        @keyframes popIn { from { opacity: 0; transform: scale(0.8); } to { opacity: 1; transform: scale(1); } }
                    </style>
                </head>
                <body>
                    <div class='success-card'>
                        <div class='icon-box'><i class='fa-solid fa-check'></i></div>
                        <h2>Upload Successful!</h2>
                        <p>Consent files have been uploaded.</p>
                    </div>
                    <script>
                        setTimeout(() => { window.location.href = 'view_consent.php?id=$id'; }, 1500);
                    </script>
                </body>
                </html>";
                exit;
            } else {
                $errors[] = "Database error: " . $conn->error;
            }
        }
    }
}

// Fetch student data
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $result = $conn->query("SELECT * FROM students WHERE id = '$id'");

    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
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
    <title>Upload Consent -
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
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            padding: 40px;
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
        }

        .student-info strong {
            color: rgba(255, 255, 255, 0.9);
        }

        .upload-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .upload-section {
            /* margin-bottom: 30px; */
        }

        .upload-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .upload-box {
            border: 2px dashed #00ACB1;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            background: #f0f9ff;
            cursor: pointer;
            transition: 0.3s;
            display: block;
        }

        .upload-box:hover {
            background: #e0f2f7;
            border-color: #008e91;
            transform: translateY(-2px);
        }

        .upload-box i {
            font-size: 48px;
            color: #00ACB1;
            margin-bottom: 15px;
            display: block;
        }

        .upload-box p {
            margin: 5px 0;
            color: #666;
        }

        .upload-box input[type="file"] {
            display: none;
        }

        .file-selected {
            background: #d4edda;
            border: 1px solid #28a745;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            color: #155724;
            font-size: 13px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
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
            gap: 10px;
            transition: 0.3s;
            font-size: 14px;
            min-width: 200px;
            /* Ensure equal size */
            justify-content: center;
        }

        .btn-submit {
            background: #00ACB1;
        }

        .btn-submit:hover {
            background: #008e91;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .btn-back {
            background: #6c757d;
        }

        .btn-back:hover {
            background: #5a6268;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            .upload-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .upload-section h3 {
                font-size: 15px;
            }

            .upload-box {
                padding: 30px 15px;
            }

            .upload-box i {
                font-size: 40px;
            }

            .action-buttons {
                flex-direction: row;
                justify-content: space-between;
                gap: 15px;
            }

            .btn {
                width: 48%;
                justify-content: center;
                padding: 12px 10px;
                font-size: 13px;
            }
        }

        @media (max-width: 576px) {
            .action-buttons {
                flex-direction: column-reverse;
                gap: 10px;
            }

            .btn {
                width: 100%;
                font-size: 14px;
            }

            .header h1 {
                font-size: 20px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 15px;
            }

            .student-info {
                padding: 15px;
            }

            .student-info p {
                font-size: 14px;
            }

            .upload-box p {
                font-size: 13px;
            }
        }
    </style>
    <?php include 'assets/inc/console_suppress.php'; ?>
</head>

<body>


    <div class="container">
        <div class="student-info">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <p style="margin: 0; font-size: 16px;"><strong>Student Name:</strong>
                        <?= htmlspecialchars($student['name']) ?></p>
                    <p style="margin: 5px 0 0 0; font-size: 14px; color: #666;"><strong>LRN:</strong>
                        <?= htmlspecialchars($student['lrn']) ?></p>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <strong>Errors:</strong>
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="edit_consent.php?id=<?= $id ?>" method="post" enctype="multipart/form-data">
            <div class="upload-grid">
                <div class="upload-section">
                    <h3><i class="fa-solid fa-file-pdf"></i> Front Page</h3>
                    <label for="consent_front" class="upload-box">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <p><strong>Click to upload Front Page</strong></p>
                        <p style="font-size: 11px; color: #999;">Supported: PDF, JPG, PNG (Max 10MB)</p>
                        <input type="file" id="consent_front" name="consent_front" accept=".pdf,.jpg,.jpeg,.png"
                            onchange="showFileName(this, 'front-preview')">
                    </label>
                    <div id="front-preview"></div>
                </div>

                <div class="upload-section">
                    <h3><i class="fa-solid fa-file-pdf"></i> Back Page</h3>
                    <label for="consent_back" class="upload-box">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <p><strong>Click to upload Back Page</strong></p>
                        <p style="font-size: 11px; color: #999;">Supported: PDF, JPG, PNG (Max 10MB)</p>
                        <input type="file" id="consent_back" name="consent_back" accept=".pdf,.jpg,.jpeg,.png"
                            onchange="showFileName(this, 'back-preview')">
                    </label>
                    <div id="back-preview"></div>
                </div>
            </div>

            <div class="action-buttons">
                <a href="view_consent.php?id=<?= $id ?>" class="btn btn-back">
                    <i class="fa-solid fa-arrow-left"></i> Cancel
                </a>

                <button type="submit" class="btn btn-submit">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload Documents
                </button>
            </div>
        </form>
    </div>

    <script>
        function showFileName(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                preview.innerHTML = `<div class="file-selected"><i class="fa-solid fa-check-circle"></i> ${input.files[0].name} selected</div>`;
            }
        }
    </script>

</body>

</html>