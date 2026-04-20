<?php
require_once '../config/db.php';
requireLogin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = "All fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (verifyPassword($currentPassword, $user['password'])) {
            // Update password
            $hashedPassword = hashPassword($newPassword);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashedPassword, $_SESSION['user_id']);

            if ($stmt->execute()) {
                $success = "Password updated successfully!";
                logSecurityEvent('PASSWORD_CHANGED', "User ID: " . $_SESSION['user_id']);
            } else {
                $error = "Failed to update password.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
        $stmt->close();
    }
}

// Fetch recent security alerts
$securityAlerts = [];
if (isset($_SESSION['user_id'])) {
    $stmtAlerts = $conn->prepare("SELECT message, created_at, is_read, ip_address, user_agent FROM notifications WHERE user_id = ? AND type = 'security' ORDER BY created_at DESC LIMIT 5");
    if ($stmtAlerts) {
        $stmtAlerts->bind_param("i", $_SESSION['user_id']);
        $stmtAlerts->execute();
        $resAlerts = $stmtAlerts->get_result();
        while ($row = $resAlerts->fetch_assoc()) {
            $securityAlerts[] = $row;
        }
        $stmtAlerts->close();
    }
}

// Fetch login history
$loginHistory = [];
if (isset($_SESSION['user_id'])) {
    $stmtLogin = $conn->prepare("SELECT ip_address, user_agent, login_at, status, location FROM login_history WHERE user_id = ? ORDER BY login_at DESC LIMIT 5");
    if ($stmtLogin) {
        $stmtLogin->bind_param("i", $_SESSION['user_id']);
        $stmtLogin->execute();
        $resLogin = $stmtLogin->get_result();
        while ($row = $resLogin->fetch_assoc()) {
            $loginHistory[] = $row;
        }
        $stmtLogin->close();
    }
}

include "index_layout.php";
?>

<style>
    :root {
        --bg-card: #fff;
        --text-primary: #333;
        --text-secondary: #777;
        --text-label: #444;
        --input-bg: #fff;
        --input-border: #E2E8F0;
        --input-focus-border: #00ACB1;
        --btn-cancel-bg: #F1F5F9;
        --btn-cancel-text: #333;
        --btn-cancel-border: #E2E8F0;
        --shadow-color: rgba(0, 0, 0, 0.05);
    }

    body.dark-mode {
        --bg-card: #272727;
        --text-primary: #e0e0e0;
        --text-secondary: #aaa;
        --text-label: #ccc;
        --input-bg: #333;
        --input-border: #555;
        --btn-cancel-bg: #444;
        --btn-cancel-text: #ddd;
        --btn-cancel-border: #555;
        --shadow-color: rgba(0, 0, 0, 0.2);
    }

    /* Password Page Specific Styles */
    .password-container {
        max-width: 600px;
        margin: 50px auto;
        padding: 20px 20px;
        border-radius: 15px;
    }

    .password-header-card {
        background: var(--bg-card);
        border-radius: 15px;
        padding: 25px 35px;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 4px 15px var(--shadow-color);
        margin-bottom: 30px;
    }

    .password-icon {
        background: #00ACB1;
        color: white;
        width: 60px;
        height: 60px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }

    .password-title h2 {
        margin: 0;
        color: var(--text-primary);
        font-size: 24px;
        font-weight: 700;
    }

    .password-title p {
        margin: 5px 0 0;
        color: var(--text-secondary);
        font-size: 14px;
    }

    .password-form-card {
        background: var(--bg-card);
        border-radius: 15px;
        padding: 40px;
        box-shadow: 0 4px 15px var(--shadow-color);
    }

    .password-input-group {
        margin-bottom: 25px;
    }

    .password-input-group label {
        display: block;
        font-weight: 600;
        color: var(--text-label);
        margin-bottom: 10px;
        font-size: 15px;
    }

    .password-input-group label span {
        color: #ff5c5c;
    }

    .password-wrapper {
        position: relative;
    }

    .password-wrapper input {
        width: 100%;
        padding: 15px 50px 15px 20px;
        border: 1px solid var(--input-border);
        border-radius: 12px;
        background-color: var(--input-bg);
        color: var(--text-primary);
        font-size: 15px;
        outline: none;
        transition: all 0.3s ease;
    }

    .password-wrapper input:focus {
        border-color: var(--input-focus-border);
        box-shadow: 0 0 0 4px rgba(0, 172, 177, 0.1);
    }

    .toggle-password {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #00ACB1;
        font-size: 18px;
        transition: 0.2s;
    }

    .toggle-password:hover {
        color: #007D81;
    }

    .password-input-group small {
        display: block;
        margin-top: 8px;
        color: var(--text-secondary);
        font-size: 12px;
        line-height: 1.4;
    }

    .password-actions {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-top: 40px;
    }

    .btn-save-password {
        background: linear-gradient(135deg, #00ACB1 0%, #00d4aa 100%);
        color: white;
        padding: 14px 40px;
        border-radius: 12px;
        border: none;
        font-weight: 700;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 172, 177, 0.3);
    }

    .btn-save-password:hover {
        background: linear-gradient(135deg, #008e91 0%, #00b89a 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 172, 177, 0.4);
    }

    .btn-cancel-password {
        background: var(--btn-cancel-bg);
        color: var(--btn-cancel-text);
        padding: 14px 40px;
        border-radius: 12px;
        border: 1px solid var(--btn-cancel-border);
        font-weight: 700;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-cancel-password:hover {
        background: var(--input-border);
    }

    body.dark-mode .btn-cancel-password:hover {
        background: #555;
    }

    .alert {
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-error {
        background: #fee;
        border-left: 4px solid #ff5c5c;
        color: #c33;
    }

    .alert-success {
        background: #efe;
        border-left: 4px solid #2ecc71;
        color: #2a6;
    }

    /* Login History Styles */
    .login-history-card {
        background: var(--bg-card);
        border-radius: 15px;
        padding: 30px 35px;
        box-shadow: 0 4px 15px var(--shadow-color);
        margin-top: 30px;
    }

    .login-history-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }

    .login-history-header .lh-icon {
        background: linear-gradient(135deg, #00ACB1, #00d4aa);
        color: white;
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .login-history-header h3 {
        margin: 0;
        color: var(--text-primary);
        font-size: 18px;
        font-weight: 700;
    }

    .login-history-header p {
        margin: 2px 0 0;
        color: var(--text-secondary);
        font-size: 12px;
    }

    .lh-list {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .lh-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 16px;
        border-bottom: 1px solid var(--input-border);
        transition: background 0.2s;
        border-radius: 8px;
    }

    .lh-item:last-child {
        border-bottom: none;
    }

    .lh-item:hover {
        background: rgba(0, 172, 177, 0.04);
    }

    .lh-device-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }

    .lh-device-icon.desktop {
        background: rgba(0, 172, 177, 0.1);
        color: #00ACB1;
    }

    .lh-device-icon.mobile {
        background: rgba(155, 89, 182, 0.1);
        color: #9b59b6;
    }

    .lh-device-icon.tablet {
        background: rgba(243, 156, 18, 0.1);
        color: #f39c12;
    }

    .lh-info {
        flex: 1;
        min-width: 0;
    }

    .lh-info .lh-browser {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 14px;
        margin: 0 0 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .lh-info .lh-ip {
        font-size: 12px;
        color: var(--text-secondary);
        margin: 0;
    }

    .lh-time {
        text-align: right;
        flex-shrink: 0;
    }

    .lh-time .lh-date {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-primary);
        display: block;
    }

    .lh-time .lh-hour {
        font-size: 11px;
        color: var(--text-secondary);
    }

    .lh-current-badge {
        background: #e0fdf4;
        color: #00ACB1;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 10px;
        margin-left: 6px;
    }

    body.dark-mode .lh-current-badge {
        background: rgba(0, 172, 177, 0.2);
    }

    .lh-empty {
        text-align: center;
        padding: 30px;
        color: var(--text-secondary);
        font-size: 14px;
    }

    .lh-empty i {
        font-size: 40px;
        color: #00ACB1;
        display: block;
        margin-bottom: 10px;
        opacity: 0.5;
    }
</style>

<div class="password-container">
    <div class="password-header-card">
        <div class="password-icon">
            <i class="fa-solid fa-lock"></i>
        </div>
        <div class="password-title">
            <h2>Password</h2>
            <p>Update your personal password</p>
        </div>
    </div>

    <div class="password-form-card">
        <?php if ($error): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: '<?= escapeOutput($error) ?>',
                        confirmButtonColor: '#d33'
                    });
                });
            </script>
        <?php endif; ?>

        <?php if ($success): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: '<?= escapeOutput($success) ?>',
                        confirmButtonColor: '#00ACB1'
                    });
                });
            </script>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="password-input-group">
                <label for="currentPassword">Current Password <span>*</span></label>
                <div class="password-wrapper">
                    <input type="password" id="currentPassword" name="current_password" required
                        autocomplete="current-password">
                    <i class="fa-solid fa-eye toggle-password"
                        onclick="togglePasswordVisibility('currentPassword', this)"></i>
                </div>
            </div>

            <div class="password-input-group">
                <label for="newPassword">New Password <span>*</span></label>
                <div class="password-wrapper">
                    <input type="password" id="newPassword" name="new_password" required autocomplete="new-password">
                    <i class="fa-solid fa-eye toggle-password"
                        onclick="togglePasswordVisibility('newPassword', this)"></i>
                </div>
                <small>Password should include a mix of uppercase letters, lowercase letters, numbers, and special
                    symbols (e.g., @, #, $, %).</small>
            </div>

            <div class="password-input-group">
                <label for="confirmPassword">Confirm Password <span>*</span></label>
                <div class="password-wrapper">
                    <input type="password" id="confirmPassword" name="confirm_password" required
                        autocomplete="new-password">
                    <i class="fa-solid fa-eye toggle-password"
                        onclick="togglePasswordVisibility('confirmPassword', this)"></i>
                </div>
                <small>Please enter the same password again.</small>
            </div>

            <div class="password-actions">
                <button type="submit" class="btn-save-password">Save Password</button>
                <button type="button" class="btn-cancel-password"
                    onclick="window.location.href='student.php'">Cancel</button>
            </div>
        </form>
    </div>

    <!-- Security Alerts Section -->
    <?php if (!empty($securityAlerts)): ?>
        <div class="login-history-card">
            <div class="login-history-header">
                <div class="lh-icon" style="background: linear-gradient(135deg, #ff5c5c, #e74c3c);">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <h3>Recent Security Alerts</h3>
                    <p>Suspicious activity detected on your account</p>
                </div>
            </div>

            <div class="lh-list">
                <?php foreach ($securityAlerts as $alert):
                    // Parse device info from user_agent
                    $alertUa = $alert['user_agent'] ?? '';
                    $alertBrowser = 'Unknown Browser';
                    if (preg_match('/Edg\//i', $alertUa))
                        $alertBrowser = 'Microsoft Edge';
                    elseif (preg_match('/OPR|Opera/i', $alertUa))
                        $alertBrowser = 'Opera';
                    elseif (preg_match('/Chrome/i', $alertUa))
                        $alertBrowser = 'Google Chrome';
                    elseif (preg_match('/Firefox/i', $alertUa))
                        $alertBrowser = 'Mozilla Firefox';
                    elseif (preg_match('/Safari/i', $alertUa))
                        $alertBrowser = 'Safari';
                    elseif (preg_match('/MSIE|Trident/i', $alertUa))
                        $alertBrowser = 'Internet Explorer';

                    $alertOs = '';
                    if (preg_match('/Windows/i', $alertUa))
                        $alertOs = 'Windows';
                    elseif (preg_match('/Mac/i', $alertUa))
                        $alertOs = 'macOS';
                    elseif (preg_match('/Linux/i', $alertUa))
                        $alertOs = 'Linux';
                    elseif (preg_match('/Android/i', $alertUa))
                        $alertOs = 'Android';
                    elseif (preg_match('/iPhone|iPad/i', $alertUa))
                        $alertOs = 'iOS';

                    $alertIsMobile = preg_match('/Mobile|Android|iPhone|iPod/i', $alertUa);
                    $alertIsTablet = preg_match('/Tablet|iPad/i', $alertUa);
                    $alertDeviceType = $alertIsTablet ? 'tablet' : ($alertIsMobile ? 'mobile' : 'desktop');
                    $alertDeviceIcon = $alertIsTablet ? 'fa-tablet-screen-button' : ($alertIsMobile ? 'fa-mobile-screen' : 'fa-desktop');
                    $alertDevice = $alertBrowser . ($alertOs ? ' · ' . $alertOs : '');
                    $alertDate = date('M d, Y', strtotime($alert['created_at']));
                    $alertTime = date('h:i A', strtotime($alert['created_at']));
                    ?>
                    <div class="lh-item">
                        <div class="lh-device-icon" style="background: rgba(255, 92, 92, 0.1); color: #ff5c5c;">
                            <i class="fa-solid <?= $alertDeviceIcon ?>"></i>
                        </div>
                        <div class="lh-info">
                            <p class="lh-browser">
                                <?php if (!empty($alertUa)): ?>
                                    <?= htmlspecialchars($alertDevice) ?>
                                <?php else: ?>
                                    Unknown Device
                                <?php endif; ?>
                                <?php if (!$alert['is_read']): ?>
                                    <span
                                        style="background: #ff5c5c; color: white; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px; margin-left: 6px;">NEW</span>
                                <?php endif; ?>
                            </p>
                            <p class="lh-ip">
                                <i class="fa-solid fa-globe" style="margin-right: 4px;"></i>
                                <?= !empty($alert['ip_address']) ? htmlspecialchars($alert['ip_address']) : 'Unknown IP' ?>
                                <span style="margin-left: 8px; color: #ff5c5c; font-weight: 600;">
                                    <i class="fa-solid fa-triangle-exclamation" style="margin-right: 2px;"></i>
                                    Failed Login Attempt
                                </span>
                            </p>
                        </div>
                        <div class="lh-time">
                            <span class="lh-date"><?= $alertDate ?></span>
                            <span class="lh-hour"><?= $alertTime ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Login History Section -->
    <div class="login-history-card">
        <div class="login-history-header">
            <div class="lh-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div>
                <h3>Login History</h3>
                <p>Recent account access activity</p>
            </div>
        </div>

        <?php if (!empty($loginHistory)): ?>
            <div class="lh-list">
                <?php foreach ($loginHistory as $idx => $login):
                    $ua = $login['user_agent'] ?? '';
                    $isFailed = (($login['status'] ?? 'success') === 'failed');
                    // Detect device type
                    $isMobile = preg_match('/Mobile|Android|iPhone|iPod/i', $ua);
                    $isTablet = preg_match('/Tablet|iPad/i', $ua);
                    $deviceType = $isTablet ? 'tablet' : ($isMobile ? 'mobile' : 'desktop');
                    $deviceIcon = $isTablet ? 'fa-tablet-screen-button' : ($isMobile ? 'fa-mobile-screen' : 'fa-desktop');

                    // Detect browser
                    $browser = 'Unknown Browser';
                    if (preg_match('/Edg\//i', $ua))
                        $browser = 'Microsoft Edge';
                    elseif (preg_match('/OPR|Opera/i', $ua))
                        $browser = 'Opera';
                    elseif (preg_match('/Chrome/i', $ua))
                        $browser = 'Google Chrome';
                    elseif (preg_match('/Firefox/i', $ua))
                        $browser = 'Mozilla Firefox';
                    elseif (preg_match('/Safari/i', $ua))
                        $browser = 'Safari';
                    elseif (preg_match('/MSIE|Trident/i', $ua))
                        $browser = 'Internet Explorer';

                    // Detect OS
                    $os = '';
                    if (preg_match('/Windows/i', $ua))
                        $os = 'Windows';
                    elseif (preg_match('/Mac/i', $ua))
                        $os = 'macOS';
                    elseif (preg_match('/Linux/i', $ua))
                        $os = 'Linux';
                    elseif (preg_match('/Android/i', $ua))
                        $os = 'Android';
                    elseif (preg_match('/iPhone|iPad/i', $ua))
                        $os = 'iOS';

                    $browserOs = $browser . ($os ? ' · ' . $os : '');
                    $isCurrent = ($idx === 0 && !$isFailed);
                    $loginDate = date('M d, Y', strtotime($login['login_at']));
                    $loginTime = date('h:i A', strtotime($login['login_at']));
                    $loginLoc = $login['location'] ?? '';
                    ?>
                    <div class="lh-item">
                        <?php if ($isFailed): ?>
                            <div class="lh-device-icon" style="background: rgba(255, 92, 92, 0.1); color: #ff5c5c;">
                                <i class="fa-solid <?= $deviceIcon ?>"></i>
                            </div>
                        <?php else: ?>
                            <div class="lh-device-icon <?= $deviceType ?>">
                                <i class="fa-solid <?= $deviceIcon ?>"></i>
                            </div>
                        <?php endif; ?>
                        <div class="lh-info">
                            <p class="lh-browser">
                                <?= htmlspecialchars($browserOs) ?>
                                <?php if ($isFailed): ?>
                                    <span
                                        style="background: #ff5c5c; color: white; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px; margin-left: 6px;">FAILED</span>
                                <?php elseif ($isCurrent): ?>
                                    <span class="lh-current-badge">LATEST</span>
                                <?php endif; ?>
                            </p>
                            <p class="lh-ip">
                                <i class="fa-solid fa-globe" style="margin-right: 4px;"></i>
                                <?= htmlspecialchars($login['ip_address']) ?>
                                <?php if (!empty($loginLoc)): ?>
                                    <span style="margin-left: 6px;"><i class="fa-solid fa-location-dot"
                                            style="margin-right: 3px;"></i><?= htmlspecialchars($loginLoc) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="lh-time">
                            <span class="lh-date"><?= $loginDate ?></span>
                            <span class="lh-hour"><?= $loginTime ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="lh-empty">
                <i class="fa-solid fa-shield-check"></i>
                <p>No login history available yet.<br>Your login sessions will appear here.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function togglePasswordVisibility(inputId, icon) {
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