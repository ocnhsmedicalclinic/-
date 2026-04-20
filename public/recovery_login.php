<?php
session_start();
require_once '../config/recovery.php';

// Check if redirected due to database error
$is_db_error = isset($_GET['reason']) && $_GET['reason'] == 'db_error';

// Redirect if already logged in normally (but NOT if we have a database error)
if (!$is_db_error && isset($_SESSION['user_id']) && !isRecoveryMode()) {
    header("Location: index.php");
    exit();
}

$error = "";
$db_error_message = "";

// ---------------------------------------------------------
// SECURITY CHECK: PREVENT ACCESS IF DATABASE IS WORKING
// ---------------------------------------------------------
// ALWAYS check connection. Even if URL says ?reason=db_error, we verify it.
// If DB is up, this page is FORBIDDEN.
try {
    $test_conn = new mysqli('localhost', 'root', '', 'clinic_db');
    if (!$test_conn->connect_error) {
        // DATABASE IS WORKING! This page is inaccessible.
        $test_conn->close();
        header("Location: index.php");
        exit();
    }
} catch (Exception $e) {
    // Database is down or missing — allow recovery page to load
}

// Get database error message if present
if ($is_db_error) {
    $db_error_message = isset($_SESSION['db_error']) ? $_SESSION['db_error'] : "Database connection failed";
    unset($_SESSION['db_error']); // Clear after reading
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (verifyRecoveryCredentials($username, $password)) {
        enableRecoveryMode();
        header("Location: recovery_panel.php");
        exit();
    } else {
        $error = "Invalid recovery credentials. This is for emergency access only.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Recovery Access - OCNHS Medical Clinic</title>
    <link rel="icon" type="image/x-icon" href="assets/img/ocnhs_logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .recovery-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .recovery-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .recovery-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
            color: white;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .recovery-header h1 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .recovery-header p {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .warning-box i {
            color: #ffc107;
            font-size: 1.2rem;
            margin-top: 2px;
        }

        .warning-box p {
            margin: 0;
            color: #856404;
            font-size: 0.85rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #ff6b6b;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border-left: 4px solid #dc3545;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .error-message i {
            font-size: 1.2rem;
            margin-top: 2px;
        }

        .btn-recovery {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-recovery:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 107, 107, 0.3);
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .back-link a:hover {
            color: #333;
        }

        .back-link i {
            margin-right: 5px;
        }

        .credentials-info {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-top: 20px;
            border-radius: 8px;
        }

        .credentials-info p {
            margin: 5px 0;
            color: #0d5aa7;
            font-size: 0.85rem;
        }

        .credentials-info strong {
            color: #084298;
        }
    </style>
    <?php include 'assets/inc/console_suppress.php'; ?>
</head>

<body>
    <div class="recovery-container">
        <div class="recovery-header">
            <div class="recovery-icon">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h1>Emergency Recovery</h1>
            <p>Database recovery access for system administrators</p>
        </div>

        <?php if ($db_error_message): ?>
            <div class="error-message" style="background: #dc3545; color: white; border-left: 4px solid #9b1c1c;">
                <i class="fa-solid fa-database"></i>
                <div>
                    <strong>Database Error Detected!</strong><br>
                    <small><?= htmlspecialchars($db_error_message) ?></small><br>
                    <small style="margin-top: 5px; display: block;">The system has been automatically redirected to recovery
                        mode. Please restore your database backup.</small>
                </div>
            </div>
        <?php endif; ?>

        <div class="warning-box">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <p><strong>Warning:</strong> This is for emergency database recovery only. Use regular login for normal
                access.</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Recovery Username</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-user-shield"></i>
                    <input type="text" name="username" required autocomplete="off">
                </div>
            </div>

            <div class="form-group">
                <label>Recovery Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-key"></i>
                    <input type="password" name="password" required>
                </div>
            </div>

            <button type="submit" class="btn-recovery">
                <i class="fa-solid fa-unlock"></i> Access Recovery Mode
            </button>
        </form>

        <div class="credentials-info">
            <p><strong>Default Credentials:</strong></p>
            <p>Username: <strong>emergency_admin</strong></p>
            <p>Password: <strong>OcnhsRecovery2024!</strong></p>
            <p style="margin-top: 10px; color: #721c24;">⚠️ Change these in <code>config/recovery.php</code></p>
        </div>

        <div class="back-link">
            <a href="index.php">
                <i class="fa-solid fa-arrow-left"></i> Back to Normal Login
            </a>
        </div>
    </div>
</body>

</html>