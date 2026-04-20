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

// If already in recovery mode, redirect to panel
if (isRecoveryMode()) {
    header('Location: recovery_panel.php');
    exit();
}

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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recovery Access – OCNHS Medical Clinic</title>
    <link rel="icon" type="image/x-icon" href="assets/img/ocnhs_logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 450px;
            width: 100%;
        }

        h1 {
            color: #ff6b6b;
            font-size: 2rem;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-weight: 700;
        }

        h1 i {
            font-size: 2.2rem;
        }

        .info {
            color: #444;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .login-btn {
            background: #28a745;
            color: #fff;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all .2s;
        }

        .login-btn:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
        }
    </style>
    <?php include 'assets/inc/console_suppress.php'; ?>
</head>

<body>
    <div class="card">
        <h1><i class="fa-solid fa-shield-halved"></i> Emergency Recovery</h1>
        <p class="info">Enter the emergency recovery credentials<br>to access the system.</p>
        <button class="login-btn" onclick="location.href='recovery_login.php'">Proceed to Login</button>
    </div>
    <script>
        // If a message is passed via query string (e.g., after logout), show a friendly alert
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg')) {
            Swal.fire({
                icon: 'info',
                title: 'Notice',
                text: decodeURIComponent(urlParams.get('msg')),
                confirmButtonColor: '#3085d6'
            });
        }
    </script>
</body>

</html>