<?php
require_once '../config/db.php';

$error = '';
$success = '';

$token = $_GET['token'] ?? '';
$isTokenValid = false;

// Verify Token
if ($token) {
    if (strlen($token) === 64) { // Assuming SHA256 length or similar, bin2hex(32) is 64 chars
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > ? LIMIT 1");
        $stmt->bind_param("ss", $token, $now);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $isTokenValid = true;
            $row = $result->fetch_assoc();
            $email = $row['email'];
        } else {
            $error = "This password reset token is invalid or has expired.";
        }
    } else {
        $error = "Invalid token format.";
    }
} else {
    $error = "No reset token provided.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? ''; // Re-fetch from hidden field

    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "Please fill in all fields.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Find User by Email (re-verify token too for security)
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > ? LIMIT 1");
        $stmt->bind_param("ss", $token, $now);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $resetRow = $result->fetch_assoc();
            $email = $resetRow['email'];

            // Update User Password
            $hashedPassword = hashPassword($newPassword);
            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $updateStmt->bind_param("ss", $hashedPassword, $email);

            if ($updateStmt->execute()) {
                // Delete the token
                $delStmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                $delStmt->bind_param("s", $email);
                $delStmt->execute();

                $success = "Your password has been reset successfully! <a href='index.php'>Login now</a>";
                $isTokenValid = false; // Hide form
            } else {
                $error = "Failed to update password. Please try again.";
            }
        } else {
            $error = "Invalid or expired token.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - OCNHS Medical Clinic</title>
    <link rel="icon" type="image/x-icon" href="assets/img/ocnhs_logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: url("assets/img/background.png") center/cover no-repeat fixed !important;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            z-index: -1;
        }

        .auth-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        .brand-logo {
            width: 80px;
            margin-bottom: 20px;
        }

        .auth-title {
            color: #00ACB1;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .auth-subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: 0.3s;
        }

        .form-control:focus {
            border-color: #00ACB1;
            box-shadow: 0 0 0 3px rgba(0, 172, 177, 0.1);
        }

        .btn-submit {
            background: #00ACB1;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            width: 100%;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            font-size: 16px;
        }

        .btn-submit:hover {
            background: #008e91;
            transform: translateY(-2px);
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: left;
        }

        .alert-error {
            background: #fee;
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            cursor: pointer;
        }
    </style>
    <?php include 'assets/inc/console_suppress.php'; ?>
</head>

<body>

    <div class="auth-card">
        <img src="assets/img/LOGO.png" alt="Logo" class="brand-logo">
        <h1 class="auth-title">Reset Password</h1>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <a href="forgot_password.php" class="btn-submit"
                style="display:inline-block; text-decoration:none; margin-top:10px;">Try Again</a>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if ($isTokenValid): ?>
            <p class="auth-subtitle">Create a new secure password for your account.</p>
            <form method="POST" action="">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="password-toggle">
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                        <i class="fa-solid fa-eye" onclick="togglePass('new_password', this)"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-toggle">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <i class="fa-solid fa-eye" onclick="togglePass('confirm_password', this)"></i>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function togglePass(inputId, icon) {
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
    </script>

</body>

</html>
