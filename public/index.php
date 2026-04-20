<?php
// IMPORTANT: This must be the FIRST line - no output before this
require_once '../config/db.php';

// PHPMailer Includes
require '../lib/PHPMailer/PHPMailer.php';
require '../lib/PHPMailer/SMTP.php';
require '../lib/PHPMailer/Exception.php';
require_once '../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';
$activeView = 'login'; // Default

if (isLoggedIn()) {
    header("Location: student.php");
    exit();
}

$csrfToken = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        // ... LOGIN LOGIC (Exact copy from previous) ...
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $lockoutIdentifier = !empty($username) ? $username : null;

        // Use DB-based rate limiting for persistence across devices
        $isLockedOut = false;
        $dbWait = 0;
        try {
            $stmt = $conn->prepare("SELECT failed_login_attempts, lockout_until FROM users WHERE username = ?");
            if ($stmt) {
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($res->num_rows === 1) {
                    $uData = $res->fetch_assoc();
                    if ($uData['lockout_until']) {
                        if (strtotime($uData['lockout_until']) > time()) {
                            $isLockedOut = true;
                            $dbWait = strtotime($uData['lockout_until']) - time();
                        } else {
                            // Lockout expired, strictly reset counter so user has fresh attempts
                            $conn->query("UPDATE users SET failed_login_attempts = 0, lockout_until = NULL WHERE username = '" . $conn->real_escape_string($username) . "'");
                            // Reset local variable too if we fetched it (not used later but good practice)
                        }
                    }
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            // Ignore DB errors (missing columns)
        }

        // Also check IP-based session limit (for non-existent users or extra security)
        $limitStatus = checkRateLimit('login', 5, 30, $lockoutIdentifier);

        if ($isLockedOut || $limitStatus['allowed'] === false) {
            $wait = $isLockedOut ? $dbWait : $limitStatus['wait'];
            // ... existing lockout error logic ...

            $error = "Too many login attempts. Please try again in $wait seconds.";
            $logId = $lockoutIdentifier ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            logSecurityEvent('LOGIN_RATE_LIMIT_HIT', $logId);

            // Notify user via Email (if exists and not already notified in this session)
            if (!isset($_SESSION['security_alert_sent_' . $username])) {
                $checkUser = $conn->prepare("SELECT id, email FROM users WHERE username = ? LIMIT 1");
                $checkUser->bind_param("s", $username);
                $checkUser->execute();
                $checkResult = $checkUser->get_result();

                if ($checkResult->num_rows === 1) {
                    $uRow = $checkResult->fetch_assoc();
                    $uEmail = $uRow['email'];
                    $uId = $uRow['id'];

                    $mail = new PHPMailer(true);
                    try {
                        // ... (omitted mail config lines for brevity in thought, but must include in replace_file_content)
                        // Actually I can just target the query lines and the valid user block to minimize change.
                        // But I need $uId for the notification insert later.

                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USERNAME;
                        $mail->Password = SMTP_PASSWORD;
                        $mail->SMTPOptions = array(
                            'ssl' => array(
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            )
                        );
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = SMTP_PORT;
                        $mail->setFrom(SMTP_FROM_EMAIL, 'OCNHS Clinic Security');
                        $mail->addAddress($uEmail);

                        $mail->isHTML(true);
                        $mail->Subject = 'Security Alert: Multiple Failed Login Attempts';
                        $mail->Body = "
                            <h3>Security Alert</h3>
                            <p>Hello $username,</p>
                            <p>We detected <strong>5 consecutive failed login attempts</strong> to your account.</p>
                            <p><strong>IP Address:</strong> " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "</p>
                            <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
                            <p>If this was you, please wait before trying again. If you did not attempt to login, please secure your account immediately or contact the administrator.</p>
                        ";
                        $mail->send();
                        $_SESSION['security_alert_sent_' . $username] = true;

                        // Create System Notification (For the Victim User)
                        $sysNotifMsg = "Security Alert: 5 failed login attempts detected on your account.";
                        $alertIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                        $alertAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                        // Link to change password for safety
                        $stmtSys = $conn->prepare("INSERT INTO notifications (user_id, type, message, link, ip_address, user_agent) VALUES (?, 'security', ?, 'change_password.php', ?, ?)");
                        $stmtSys->bind_param("isss", $uId, $sysNotifMsg, $alertIp, $alertAgent);
                        $stmtSys->execute();
                        $stmtSys->close();

                        // Optional: Create Notification for Admins as well (Global)
                        $adminMsg = "Security Alert: User '$username' hit login limit.";
                        $stmtAdmin = $conn->prepare("INSERT INTO notifications (type, message, link, ip_address, user_agent) VALUES ('security', ?, 'users.php', ?, ?)");
                        $stmtAdmin->bind_param("sss", $adminMsg, $alertIp, $alertAgent);
                        $stmtAdmin->execute();
                        $stmtAdmin->close();

                    } catch (Exception $e) {
                        // Fail silently
                    }
                }
                $checkUser->close();
            }
        } else {
            if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
                $error = "Invalid security token.";
            } else {
                if (empty($username) || empty($password)) {
                    $error = "Please enter both username and password.";
                } else {
                    $stmt = $conn->prepare("SELECT id, username, password, role, is_active FROM users WHERE username = ? LIMIT 1");
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows === 1) {
                        $user = $result->fetch_assoc();
                        if (verifyPassword($password, $user['password'])) {
                            if ($user['is_active'] == 0) {
                                $error = "Account pending approval.";
                            } else {
                                // Reset failed attempts on success
                                $conn->query("UPDATE users SET failed_login_attempts = 0, lockout_until = NULL WHERE id = " . $user['id']);

                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['username'] = $user['username'];
                                $_SESSION['role'] = $user['role'];
                                $sessionToken = bin2hex(random_bytes(32));
                                $_SESSION['session_token'] = $sessionToken;
                                $updateStmt = $conn->prepare("UPDATE users SET session_token = ? WHERE id = ?");
                                $updateStmt->bind_param("si", $sessionToken, $user['id']);
                                $updateStmt->execute();
                                $updateStmt->close(); // Close explicitly

                                if ($lockoutIdentifier)
                                    unset($_SESSION['rate_limit']['login_' . $lockoutIdentifier]);
                                else
                                    unset($_SESSION['rate_limit']['login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')]);

                                logSecurityEvent('LOGIN_SUCCESS', "User: $username");

                                // Record login history
                                $loginIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                                $loginAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                                $loginStatus = 'success';

                                // Resolve location from IP
                                $loginLocation = 'Local Network';
                                if (!in_array($loginIp, ['::1', '127.0.0.1', 'unknown']) && !preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $loginIp)) {
                                    $geoData = @file_get_contents("http://ip-api.com/json/{$loginIp}?fields=city,regionName,country");
                                    if ($geoData) {
                                        $geo = json_decode($geoData, true);
                                        if (!empty($geo['city'])) {
                                            $loginLocation = $geo['city'] . ', ' . ($geo['regionName'] ?? '') . ', ' . ($geo['country'] ?? '');
                                        }
                                    }
                                }

                                $stmtHistory = $conn->prepare("INSERT INTO login_history (user_id, username, ip_address, user_agent, status, location) VALUES (?, ?, ?, ?, ?, ?)");
                                $stmtHistory->bind_param("isssss", $user['id'], $username, $loginIp, $loginAgent, $loginStatus, $loginLocation);
                                $stmtHistory->execute();
                                $stmtHistory->close();

                                $_SESSION['login_welcome'] = true; // Set flag for welcome alert
                                header("Location: student.php");
                                exit();
                            }
                        } else {
                            $error = "Invalid credentials.";

                            // Increment failures and lock if needed (persistent across devices)
                            try {
                                $stmtFail = $conn->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1, lockout_until = CASE WHEN failed_login_attempts + 1 >= 5 THEN DATE_ADD(NOW(), INTERVAL 30 SECOND) ELSE NULL END WHERE id = ?");
                                if ($stmtFail) {
                                    $stmtFail->bind_param("i", $user['id']);
                                    $stmtFail->execute();
                                    $stmtFail->close();
                                }
                            } catch (Exception $e) {
                            }

                            logSecurityEvent('LOGIN_FAILED_PASSWORD', "User: $username");

                            // Record failed login attempt
                            $failIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                            $failAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                            $failStatus = 'failed';

                            // Resolve location from IP
                            $failLocation = 'Local Network';
                            if (!in_array($failIp, ['::1', '127.0.0.1', 'unknown']) && !preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $failIp)) {
                                $geoData = @file_get_contents("http://ip-api.com/json/{$failIp}?fields=city,regionName,country");
                                if ($geoData) {
                                    $geo = json_decode($geoData, true);
                                    if (!empty($geo['city'])) {
                                        $failLocation = $geo['city'] . ', ' . ($geo['regionName'] ?? '') . ', ' . ($geo['country'] ?? '');
                                    }
                                }
                            }

                            $stmtFailHistory = $conn->prepare("INSERT INTO login_history (user_id, username, ip_address, user_agent, status, location) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmtFailHistory->bind_param("isssss", $user['id'], $username, $failIp, $failAgent, $failStatus, $failLocation);
                            $stmtFailHistory->execute();
                            $stmtFailHistory->close();
                        }
                    } else {
                        $error = "Invalid credentials.";
                        logSecurityEvent('LOGIN_FAILED_USER', "User: $username");
                    }
                    $stmt->close();
                }
            }
        }

    } elseif ($action === 'register') {
        $activeView = 'register';
        // ... REGISTER LOGIC ...
        $registrationDisabled = false;

        if ($registrationDisabled) {
            $error = "Registration disabled.";
        } elseif (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            $error = "Invalid security token.";
        } else {
            $username = sanitizeInput($_POST['username'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $role = sanitizeInput($_POST['role'] ?? 'medical_staff');

            if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
                $error = "All fields required.";
            } elseif (strlen($username) < 3) {
                $error = "Username too short.";
            } elseif (!validateEmail($email)) {
                $error = "Invalid email.";
            } elseif (strlen($password) < 6) {
                $error = "Password too short.";
            } elseif ($password !== $confirmPassword) {
                $error = "Passwords do not match.";
            } else {
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error = "Username taken.";
                } else {
                    $stmt->close();
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $error = "Email taken.";
                    } else {
                        $stmt->close();
                        $hashedPassword = hashPassword($password);
                        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, is_active) VALUES (?, ?, ?, ?, 0)");
                        $stmt->bind_param("ssss", $username, $hashedPassword, $email, $role);

                        if ($stmt->execute()) {
                            // Notifications
                            $notifMsg = "New user: $username";
                            $stmtNotify = $conn->prepare("INSERT INTO notifications (type, message, link) VALUES ('registration', ?, 'users.php')");
                            $stmtNotify->bind_param("s", $notifMsg);
                            $stmtNotify->execute();
                            $stmtNotify->close(); // Close

                            $success = "Please wait for an email notification regarding your account approval or rejection by the Administrator.";
                            $activeView = 'login';
                        } else {
                            $error = "Registration failed.";
                        }
                        $stmt->close(); // Close
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>OCNHS Medical Clinic RMS</title>
    <link rel="icon" type="image/x-icon" href="assets/img/ocnhs_logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Mobile Logo - Hidden by default */
        .mobile-logo {
            display: none;
            max-width: 120px;
            margin-bottom: 10px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: url("assets/img/background.png") center/cover no-repeat fixed;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            font-family: 'Manrope', sans-serif;
            height: 100vh;
            margin: 0;
            -webkit-user-select: none;
            /* Safari */
            -ms-user-select: none;
            /* IE 10 and IE 11 */
            user-select: none;
            /* Standard syntax */
        }

        /* Allow selection in inputs */
        input,
        textarea,
        [contenteditable] {
            -webkit-user-select: text;
            -moz-user-select: text;
            -ms-user-select: text;
            user-select: text;
        }

        @media (max-width: 900px) {
            body {
                overflow-y: auto;
                height: auto;
                min-height: 100vh;
                padding: 20px 0;
                overflow-x: hidden;
                /* Prevent horizontal scroll causing space on right */
            }
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.05);
            z-index: -1;
        }

        h1 {
            font-weight: bold;
            margin: 0;
            color: #333;
        }

        p {
            font-size: 14px;
            font-weight: 400;
            line-height: 20px;
            letter-spacing: 0.5px;
            margin: 20px 0 30px;
        }

        span {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }

        a {
            color: #333;
            font-size: 14px;
            text-decoration: none;
            margin: 15px 0;
            transition: 0.3s;
        }

        a:hover {
            color: #00ACB1;
        }

        button {
            border-radius: 20px;
            border: 1px solid #00ACB1;
            background-color: #00ACB1;
            color: #FFFFFF;
            font-size: 12px;
            font-weight: bold;
            padding: 12px 45px;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: transform 80ms ease-in;
            cursor: pointer;
            margin-top: 10px;
        }

        button:active {
            transform: scale(0.95);
        }

        button:focus {
            outline: none;
        }

        button.ghost {
            background-color: transparent;
            border-color: #FFFFFF;
        }

        form {
            background-color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 0 50px;
            height: 100%;
            text-align: center;
        }

        input,
        select {
            background-color: #eee;
            border: none;
            padding: 12px 15px;
            margin: 8px 0;
            width: 100%;
            border-radius: 5px;
            outline: none;
        }

        input:focus,
        select:focus {
            box-shadow: 0 0 0 2px rgba(0, 172, 177, 0.2);
        }

        .container {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 14px 28px rgba(0, 0, 0, 0.25),
                0 10px 10px rgba(0, 0, 0, 0.22);
            position: relative;
            overflow: hidden;
            width: 850px;
            /* Slightly wider for our fields */
            max-width: 100%;
            min-height: 550px;
            /* Taller for register fields */
        }

        .form-container {
            position: absolute;
            top: 0;
            height: 100%;
            transition: all 0.6s ease-in-out;
        }

        .sign-in-container {
            left: 0;
            width: 50%;
            z-index: 2;
        }

        .container.right-panel-active .sign-in-container {
            transform: translateX(100%);
        }

        .sign-up-container {
            left: 0;
            width: 50%;
            opacity: 0;
            z-index: 1;
        }

        .container.right-panel-active .sign-up-container {
            transform: translateX(100%);
            opacity: 1;
            z-index: 5;
            animation: show 0.6s;
        }

        @keyframes show {

            0%,
            49.99% {
                opacity: 0;
                z-index: 1;
            }

            50%,
            100% {
                opacity: 1;
                z-index: 5;
            }
        }

        .overlay-container {
            position: absolute;
            top: 0;
            left: 50%;
            width: 50%;
            height: 100%;
            overflow: hidden;
            transition: transform 0.6s ease-in-out;
            z-index: 100;
        }

        .container.right-panel-active .overlay-container {
            transform: translateX(-100%);
        }

        .overlay {
            background: #00ACB1;
            background: -webkit-linear-gradient(to right, #00ACB1, #48c9b0);
            background: linear-gradient(to right, #00ACB1, #48c9b0);
            background-repeat: no-repeat;
            background-size: cover;
            background-position: 0 0;
            color: #FFFFFF;
            position: relative;
            left: -100%;
            height: 100%;
            width: 200%;
            transform: translateX(0);
            transition: transform 0.6s ease-in-out;
        }

        .container.right-panel-active .overlay {
            transform: translateX(50%);
        }

        .overlay-panel {
            position: absolute;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 0 40px;
            text-align: center;
            top: 0;
            height: 100%;
            width: 50%;
            transform: translateX(0);
            transition: transform 0.6s ease-in-out;
        }

        .overlay-panel h1 {
            color: #FFFFFF;
        }

        .overlay-left {
            transform: translateX(-20%);
        }

        .container.right-panel-active .overlay-left {
            transform: translateX(0);
        }

        .overlay-right {
            right: 0;
            transform: translateX(0);
        }

        .container.right-panel-active .overlay-right {
            transform: translateX(20%);
        }

        .alert-box {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-size: 12px;
            text-align: left;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #e0fdf4;
            color: #006064;
            border: 1px solid #00ACB1;
        }

        @media (max-width: 900px) {

            /* Mobile/Tablet Card Style with Up/Down Animation */
            .container {
                width: 90%;
                max-width: 400px;
                min-height: 750px;
                height: 750px;
                /* Fixed height for absolute positioning to work */
                border-radius: 10px;
                box-shadow: 0 14px 28px rgba(0, 0, 0, 0.25), 0 10px 10px rgba(0, 0, 0, 0.22);
                position: relative;
                margin: auto;
                overflow: hidden;
            }

            form {
                padding: 30px 20px;
                padding-bottom: 50px;
                height: 100%;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .mobile-logo {
                display: block;
                s margin: 0 auto 40px;
                max-width: 250px;
            }

            h1 {
                font-size: 24px;
                margin-bottom: 5px;
            }

            p,
            span {
                font-size: 14px;
                margin-bottom: 20px;
            }

            input,
            select {
                font-size: 16px;
                padding: 14px 15px;
            }

            /* Absolute positioning for sliding */
            .form-container {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                transition: transform 0.6s ease-in-out, opacity 0.6s ease-in-out;
            }

            /* Sign In - Default Visible */
            .sign-in-container {
                z-index: 2;
                transform: translateY(0);
                opacity: 1;
            }

            /* Sign Up - Default Hidden (Bottom) */
            .sign-up-container {
                z-index: 1;
                transform: translateY(100%);
                opacity: 0;
                animation: none;
            }

            .overlay-container {
                display: none;
            }

            /* Active State (Register) - Slide Up/Down */
            .container.right-panel-active .sign-in-container {
                transform: translateY(-100%);
                opacity: 0;
            }

            .container.right-panel-active .sign-up-container {
                transform: translateY(0);
                opacity: 1;
                animation: none;
            }

            /* Mobile Switch Links */
            .mobile-switch {
                display: block;
                margin-top: 15px;
                color: #00ACB1;
                cursor: pointer;
                text-decoration: underline;
                font-size: 12px;
            }
        }

        }

        @media (min-width: 901px) {
            .mobile-switch {
                display: none;
            }
        }
    </style>
    <?php include 'assets/inc/console_suppress.php'; ?>
</head>

<body>

    <div class="container <?= $activeView === 'register' ? 'right-panel-active' : '' ?>" id="container">

        <!-- REGISTER FORM (Left/Hidden) -->
        <div class="form-container sign-up-container">
            <form action="" method="post">
                <img src="assets/img/LOGO.png" alt="Logo" class="mobile-logo">
                <h1 style="color:#00ACB1;">Create Account</h1>
                <span>Use your email for registration</span>

                <input type="hidden" name="action" value="register">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <?php if ($error && $activeView === 'register'): ?>
                    <div class="alert-box alert-error"><?= $error ?></div>
                <?php endif; ?>

                <input type="text" name="username" placeholder="Username" required autocomplete="username"
                    aria-label="Username"
                    value="<?= ($activeView === 'register' && isset($_POST['username'])) ? htmlspecialchars($_POST['username']) : '' ?>" />
                <input type="email" name="email" placeholder="Email" required autocomplete="email" aria-label="Email"
                    value="<?= ($activeView === 'register' && isset($_POST['email'])) ? htmlspecialchars($_POST['email']) : '' ?>" />
                <div style="position:relative; width:100%; margin: 8px 0;">
                    <input type="password" name="password" id="regPassword" placeholder="Password" required
                        autocomplete="new-password" aria-label="Password" style="margin:0; padding-right:40px;" />
                    <i class="fa fa-eye" onclick="togglePassword(this, 'regPassword')"
                        style="position:absolute; right:15px; top:50%; transform:translateY(-50%); cursor:pointer; color:#888;"></i>
                </div>
                <div style="position:relative; width:100%; margin: 8px 0;">
                    <input type="password" name="confirm_password" id="regConfirm" placeholder="Confirm Password"
                        required autocomplete="new-password" aria-label="Confirm Password"
                        style="margin:0; padding-right:40px;" />
                    <i class="fa fa-eye" onclick="togglePassword(this, 'regConfirm')"
                        style="position:absolute; right:15px; top:50%; transform:translateY(-50%); cursor:pointer; color:#888;"></i>
                </div>
                <select name="role" required autocomplete="off" aria-label="Role">
                    <option value="medical_staff">Medical Staff</option>
                    <option value="nurse">Nurse</option>
                    <option value="doctor">Doctor</option>
                </select>

                <button type="submit">Sign Up</button>
                <a class="mobile-switch" id="mobileSignIn">Already have an account? Sign In</a>
            </form>
        </div>

        <!-- LOGIN FORM (Right/Visible) -->
        <div class="form-container sign-in-container">
            <form action="" method="post">
                <img src="assets/img/LOGO.png" alt="Logo" class="mobile-logo">
                <h1 style="color:#00ACB1;">Sign in</h1>
                <span>Use your account</span>

                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <?php if ($error && $activeView === 'login'): ?>
                    <div class="alert-box alert-error"><?= $error ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['reason']) && $_GET['reason'] === 'concurrent_login'): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Multiple Logins Detected',
                                text: 'Your account was logged in from another device, and your session here has been ended for security.',
                                confirmButtonColor: '#00ACB1'
                            });
                        });
                    </script>
                <?php endif; ?>

                <?php if ($success): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            Swal.fire({
                                icon: 'success',
                                title: 'Registration Successful!',
                                text: '<?= htmlspecialchars($success) ?>',
                                confirmButtonColor: '#00ACB1'
                            });
                        });
                    </script>
                <?php endif; ?>

                <input type="text" name="username" placeholder="Username" required autocomplete="username"
                    aria-label="Username"
                    value="<?= ($activeView === 'login' && isset($_POST['username'])) ? htmlspecialchars($_POST['username']) : '' ?>" />
                <div style="position:relative; width:100%; margin: 8px 0;">
                    <input type="password" name="password" id="loginPassword" placeholder="Password" required
                        autocomplete="current-password" aria-label="Password" style="margin:0; padding-right:40px;" />
                    <i class="fa fa-eye" onclick="togglePassword(this, 'loginPassword')"
                        style="position:absolute; right:15px; top:50%; transform:translateY(-50%); cursor:pointer; color:#888;"></i>
                </div>
                <a href="forgot_password.php">Forgot your password?</a>
                <button type="submit">Sign In</button>
                <a class="mobile-switch" id="mobileSignUp">Don't have an account? Sign Up</a>
            </form>
        </div>

        <!-- OVERLAY -->
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <h1>Welcome Back!</h1>
                    <p>To keep connected with us please login with your personal info</p>
                    <button class="ghost" id="signIn">Sign In</button>
                </div>
                <div class="overlay-panel overlay-right">
                    <img src="assets/img/LOGO.png" alt="OCNHS Logo"
                        style="max-width: 180px; margin-bottom: 20px; filter: drop-shadow(0 0 25px rgba(255,255,255,0.9)) drop-shadow(0 0 10px rgba(255,255,255,0.7));">
                    <p>Enter your personal details to create an account and start managing health records</p>
                    <button class="ghost" id="signUp">Sign Up</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const signUpButton = document.getElementById('signUp');
        const signInButton = document.getElementById('signIn');
        const container = document.getElementById('container');
        const mobileSignUp = document.getElementById('mobileSignUp');
        const mobileSignIn = document.getElementById('mobileSignIn');

        if (signUpButton) {
            signUpButton.addEventListener('click', () => {
                container.classList.add("right-panel-active");
            });
        }

        if (signInButton) {
            signInButton.addEventListener('click', () => {
                container.classList.remove("right-panel-active");
            });
        }

        // Mobile handlers
        if (mobileSignUp) {
            mobileSignUp.addEventListener('click', () => {
                container.classList.add("right-panel-active");
                // For mobile CSS to react, we might need JS manipulation if pure CSS isn't enough
                // But my CSS uses .right-panel-active for mobile display toggling too.
            });
        }

        if (mobileSignIn) {
            mobileSignIn.addEventListener('click', () => {
                container.classList.remove("right-panel-active");
            });
        }

        // Toggle Password Function
        function togglePassword(icon, inputId) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        // Reason-based Alerts
        const urlParams = new URLSearchParams(window.location.search);
        const reason = urlParams.get('reason');

        if (reason === 'auth_required') {
            Swal.fire({
                icon: 'warning',
                title: 'Security Notice',
                text: 'Authorized token is missing or expired. Please login to continue.',
                confirmButtonColor: '#00ACB1',
                timer: 4000
            });
        } else if (reason === 'concurrent_login') {
            Swal.fire({
                icon: 'info',
                title: 'Session Ended',
                text: 'You have been logged out because your account was accessed from another device.',
                confirmButtonColor: '#00ACB1'
            });
        }
    </script>

</body>

</html>