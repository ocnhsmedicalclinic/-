<?php
// Set Timezone
date_default_timezone_set('Asia/Manila');

// Enhanced database connection with security measures
require_once __DIR__ . '/security.php';

// Database credentials - IMPORTANT: Move to environment variables in production
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'clinic_db');

// Function to check if we're already on a recovery page
function isRecoveryPage()
{
    $script = basename($_SERVER['SCRIPT_NAME']);
    return in_array($script, ['recovery_login.php', 'recovery_panel.php']);
}

// Create connection with error handling
try {
    // Attempt database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check for connection errors
    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }

    // Set charset to UTF-8 to prevent SQL injection via encoding
    $conn->set_charset("utf8mb4");

    // Single Session Enforcement
    // Check if user is logged in, then validate session
    if (isset($_SESSION['user_id'])) {
        // If checking session fails (invalid token), log them out
        if (!validateSession($conn)) {
            // Log the event
            // Note: logSecurityEvent might use session username before we clear it
            $username = $_SESSION['username'] ?? 'unknown';
            // We can't use logSecurityEvent here easily if it depends on session? Actually it's fine.
            error_log("Session Invalidated for User: $username (Concurrent Login)");

            // Destroy session
            $_SESSION = array();
            session_destroy();

            // Redirect to login with reason
            // Check if request is AJAX or specifically the check_session script
            $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
            $is_check_script = basename($_SERVER['SCRIPT_NAME']) === 'check_session.php';

            if ($is_ajax || $is_check_script) {
                header('Content-Type: application/json');
                echo json_encode(['valid' => false, 'reason' => 'concurrent_login']);
                exit();
            } else {
                header("Location: index.php?reason=concurrent_login");
                exit();
            }
        }
    }

} catch (Exception $e) {
    // Log the error
    error_log("Database Connection Error: " . $e->getMessage());

    // If not already on recovery page, redirect there
    if (!isRecoveryPage()) {
        // Store error message in session for recovery page
        if (!session_id()) {
            session_start();
        }

        // Clear any existing user session to prevent redirect loops
        $_SESSION = array();

        // Store only the error message
        $_SESSION['db_error'] = $e->getMessage();

        // Redirect to recovery login (use relative path to avoid issues)
        $redirect_url = "recovery_login.php?reason=db_error";

        // Check if we're already in the public directory
        $current_dir = basename(dirname($_SERVER['SCRIPT_FILENAME']));
        if ($current_dir !== 'public') {
            $redirect_url = "../public/" . $redirect_url;
        }

        header("Location: " . $redirect_url);
        exit();
    } else {
        // If on recovery page and still can't connect, show specific error
        die("Critical Database Error: Unable to connect to MySQL server. Please check XAMPP/MySQL service.");
    }
}

// Disable error display in production (show generic messages only)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Log successful connection (optional - disable in production for performance)
// logSecurityEvent('DATABASE_CONNECTED', 'User: ' . ($_SESSION['username'] ?? 'guest'));
