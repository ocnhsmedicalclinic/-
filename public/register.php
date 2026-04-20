<?php
// IMPORTANT: This must be the FIRST line - no output before this
require_once '../config/db.php';

// Include PHPMailer
require '../lib/PHPMailer/PHPMailer.php';
require '../lib/PHPMailer/SMTP.php';
require '../lib/PHPMailer/Exception.php';
require_once '../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

// Check if already logged in
if (isLoggedIn()) {
    header("Location: index.php");
    exit();
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Check if any user exists
// Limit removed to allow multiple registrations
$registrationDisabled = false;

// Handle registration POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($registrationDisabled) {
        $error = "Registration is disabled. Only one user account is allowed.";
    } elseif (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid security token. Please try again.";
        logSecurityEvent('REGISTER_CSRF_FAILED', $_POST['username'] ?? 'unknown');
    } else {
        // Sanitize inputs
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $role = sanitizeInput($_POST['role'] ?? 'medical_staff');

        // Validate inputs
        if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
            $error = "All fields are required.";
        } elseif (strlen($username) < 3) {
            $error = "Username must be at least 3 characters.";
        } elseif (!validateEmail($email)) {
            $error = "Invalid email address.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match.";
        } else {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "Username already exists.";
                $stmt->close();
            } else {
                $stmt->close();

                // Check if email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $error = "Email already exists.";
                    $stmt->close();
                } else {
                    $stmt->close();

                    // Hash password
                    $hashedPassword = hashPassword($password);

                    // Insert new user
                    // Since this is the FIRST user (checked above), we might want to make them Active automatically if the logic implies "Only one user". 
                    // However, usually first user is admin. The code below sets is_active=0. 
                    // If the user wants "only one user to OPEN account", maybe they mean only one user can exist. 
                    // If this is the ONLY user, maybe auto-activate?
                    // For now, I'll stick to existing logic but just prevent >1.

                    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, is_active) VALUES (?, ?, ?, ?, 0)");
                    $stmt->bind_param("ssss", $username, $hashedPassword, $email, $role);

                    if ($stmt->execute()) {
                        logSecurityEvent('USER_REGISTERED', "Username: $username, Email: $email, Role: $role");

                        // Create Dashboard Notification
                        $notifMsg = "New user registered: $username (" . ucfirst($role) . ")";
                        $notifLink = "users.php";
                        $stmtNotify = $conn->prepare("INSERT INTO notifications (type, message, link) VALUES ('registration', ?, ?)");
                        $stmtNotify->bind_param("ss", $notifMsg, $notifLink);
                        $stmtNotify->execute();

                        // Notify Admins
                        $adminRs = $conn->query("SELECT email FROM users WHERE role = 'admin'");
                        if ($adminRs && $adminRs->num_rows > 0) {
                            $mail = new PHPMailer(true);
                            try {
                                $mail->isSMTP();
                                $mail->Host = SMTP_HOST;
                                $mail->SMTPAuth = true;
                                $mail->Username = SMTP_USERNAME;
                                $mail->Password = SMTP_PASSWORD;
                                // Localhost SSL bypass
                                $mail->SMTPOptions = array(
                                    'ssl' => array(
                                        'verify_peer' => false,
                                        'verify_peer_name' => false,
                                        'allow_self_signed' => true
                                    )
                                );
                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                $mail->Port = SMTP_PORT;
                                $mail->setFrom(SMTP_FROM_EMAIL, 'OCNHS Clinic System');

                                $mail->Subject = "ACTION REQUIRED: New User Registration - $username";
                                $mail->Body = "
                                    <h3>New Registration Pending Approval</h3>
                                    <p>A new user has registered and needs approval:</p>
                                    <ul>
                                        <li><strong>Username:</strong> $username</li>
                                        <li><strong>Email:</strong> $email</li>
                                        <li><strong>Role:</strong> $role</li>
                                    </ul>
                                    <p>Please login to the admin dashboard to approve or reject this user.</p>
                                ";
                                $mail->isHTML(true);

                                while ($admin = $adminRs->fetch_assoc()) {
                                    $mail->addAddress($admin['email']);
                                    $mail->send();
                                    $mail->clearAddresses();
                                }
                            } catch (Exception $e) {
                                error_log("Admin Notification Error: " . $mail->ErrorInfo);
                            }
                        }

                        $success = "Registration successful! Please wait for the email confirmation if your account is approved.";
                        // Clear form
                        $_POST = array();
                    } else {
                        $error = "Registration failed. Please try again.";
                        logSecurityEvent('REGISTER_FAILED', "Error: " . $stmt->error);
                    }

                    $stmt->close();
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
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Manrope:wght@400;500;600;700&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Override background for register page only */
        body {
            background: url("assets/img/background.png") center/cover no-repeat fixed !important;
        }

        body::before {
            background: rgba(0, 0, 0, 0.05) !important;
            opacity: 1 !important;
        }

        .disclaimer {
            font-size: 10px;
            color: #666;
            text-align: center;
            margin-top: 10px;
            line-height: 1.4;
        }
    </style>
    <?php include 'assets/inc/console_suppress.php'; ?>
</head>

<body>
    <main class="scene">
        <section class="card">
            <span class="divider" aria-hidden="true"></span>

            <div class="panel brand">
                <div class="brand-mark" aria-label="CNHS Medical Clinic">
                    <img src="assets/img/LOGO.png" alt="CNHS Logo">
                </div>
            </div>

            <div class="panel form">
                <div>
                    <h1 class="title">Register</h1>
                    <p class="subtitle">Enter your details to register in to your account</p>
                </div>



                <?php if ($registrationDisabled): ?>
                    <div style="text-align: center; padding: 20px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" style="width: 64px; height: 64px; color: #dc3545; margin-bottom: 20px;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <h2 style="color: #333; margin-bottom: 10px;">Registration Closed</h2>
                        <p style="color: #666; margin-bottom: 20px;">
                            This system is restricted to a single user account, and an account already exists.
                        </p>
                        <p style="color: #666;">
                            Please contact the administrator if you believe this is an error.
                        </p>
                    </div>
                <?php else: ?>

                    <form action="" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                        <div>
                            <label for="username">Username</label>
                            <div class="field">
                                <input id="username" name="username" type="text" required autofocus maxlength="50"
                                    value="<?= isset($_POST['username']) ? escapeOutput($_POST['username']) : '' ?>" />
                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                    <circle cx="12" cy="7" r="4" />
                                </svg>
                            </div>
                        </div>

                        <div>
                            <label for="email">Email</label>
                            <div class="field">
                                <input id="email" name="email" type="email" required maxlength="100"
                                    value="<?= isset($_POST['email']) ? escapeOutput($_POST['email']) : '' ?>" />
                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                                    <polyline points="22,6 12,13 2,6" />
                                </svg>
                            </div>
                        </div>

                        <div>
                            <label for="password">Password</label>
                            <div class="field">
                                <input id="password" name="password" type="password" required maxlength="100" />
                                <i class="fa-solid fa-eye icon" id="togglePassword"
                                    style="cursor: pointer; display: flex; align-items: center; justify-content: center;"></i>
                            </div>
                        </div>

                        <div>
                            <label for="confirm_password">Confirm Password</label>
                            <div class="field">
                                <input id="confirm_password" name="confirm_password" type="password" required
                                    maxlength="100" />
                                <i class="fa-solid fa-eye icon" id="toggleConfirmPassword"
                                    style="cursor: pointer; display: flex; align-items: center; justify-content: center;"></i>
                            </div>
                        </div>

                        <div>
                            <label for="role">Role</label>
                            <div class="field">
                                <select id="role" name="role" required
                                    style="width: 100%; padding: 12px 42px 12px 12px; border-radius: 8px; border: 1px solid rgba(10, 173, 175, 0.7); outline: none; font-size: 14px; background: #ffffff; appearance: none;">
                                    <option value="medical_staff">Medical Staff</option>
                                    <option value="nurse">Nurse</option>
                                    <option value="doctor">Doctor</option>
                                </select>
                                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                                </svg>
                            </div>

                            <!-- SweetAlert2 CDN -->
                            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

                            <?php if ($error): ?>
                                <script>
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Registration Error',
                                        text: '<?= escapeOutput($error) ?>',
                                        confirmButtonColor: '#00ACB1'
                                    });
                                </script>
                            <?php endif; ?>

                            <?php if ($success): ?>
                                <script>
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Registration Successful!',
                                        text: '<?= escapeOutput($success) ?>',
                                        confirmButtonColor: '#00ACB1'
                                    }).then((result) => {
                                        // Optional: Redirect to login or stay
                                        window.location.href = "index.php";
                                    });
                                </script>
                            <?php endif; ?>
                        </div>

                        <p class="disclaimer">
                            By clicking Register, You may receive EMAIL Notifications from us and will for approval of Super
                            admin to open your account.
                        </p>

                        <button class="btn" type="submit" style="width: 100%;">Register</button>
                    </form>
                <?php endif; ?>

                <p class="signup">Already have an account? <a href="index.php">Back to LOGIN</a></p>
            </div>
        </section>
    </main>
    </main>



    <script>
        document.querySelector('form').addEventListener('submit', function (e) {
            e.preventDefault();

            // Get values
            const username = document.getElementById('username').value;
            const roleSelect = document.getElementById('role');
            const roleText = roleSelect.options[roleSelect.selectedIndex].text;

            Swal.fire({
                title: 'Confirm Registration',
                html: `You are about to create a new account for<br>
                       <strong style="color: #00ACB1; font-size: 18px;">${username}</strong><br>
                       with the role of <strong>${roleText}</strong>.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#00ACB1',
                cancelButtonColor: '#777',
                confirmButtonText: 'Confirm & Register'
            }).then((result) => {
                if (result.isConfirmed) {
                    HTMLFormElement.prototype.submit.call(e.target);
                }
            });
        });

        // Password Toggle Logic
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // toggle the icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        const toggleConfirmPassword = document.querySelector('#toggleConfirmPassword');
        const confirmPassword = document.querySelector('#confirm_password');

        toggleConfirmPassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPassword.setAttribute('type', type);
            // toggle the icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>

</html>
