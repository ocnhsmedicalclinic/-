<?php
/**
 * Security Configuration
 * Comprehensive security measures for the Clinic System
 */

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

/**
 * Advanced Local Network Health Check
 * Ensures the requester is coming from a local/trusted source.
 */
function isLocalNetwork($ip)
{
    if ($ip === '127.0.0.1' || $ip === '::1')
        return true;

    // Check private IP ranges (LAN)
    $ip_long = ip2long($ip);
    if ($ip_long === false)
        return false;

    return (
        ($ip_long >= ip2long('10.0.0.0') && $ip_long <= ip2long('10.255.255.255')) ||
        ($ip_long >= ip2long('172.16.0.0') && $ip_long <= ip2long('172.31.255.255')) ||
        ($ip_long >= ip2long('192.168.0.0') && $ip_long <= ip2long('192.168.255.255'))
    );
}

$client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && !isLocalNetwork($client_ip)) {
    logSecurityEvent('EXTERNAL_ACCESS_BLOCKED', "IP: $client_ip tried to access local system");
    die("<h1>Access Denied</h1><p>This system is for LOCAL use only. Connections from outside the local network are blocked for security.</p>");
}

/**
 * Session Fingerprinting
 * Prevent session hijacking by tying session to user profile info.
 */
if (isset($_SESSION['user_id'])) {
    $fingerprint = md5($_SERVER['HTTP_USER_AGENT'] . ($_SERVER['REMOTE_ADDR'] ?? ''));
    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $fingerprint;
    } elseif ($_SESSION['fingerprint'] !== $fingerprint) {
        logSecurityEvent('SESSION_HIJACK_DETECTED', "Fingerprint mismatch for User ID: " . $_SESSION['user_id']);
        session_unset();
        session_destroy();
        header("Location: login.php?error=secure_logout");
        exit();
    }
}

// Inactivity Timeout removed as requested

// Regenerate session ID periodically to prevent session fixation
// Regenerate session ID periodically to prevent session fixation
// SKIP for AJAX requests to prevent race conditions with background polling
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$is_ajax) {
    if (!isset($_SESSION['last_regeneration'])) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

/**
 * Single Session Check
 * If user is logged in, verify their session token matches the database.
 * This prevents multiple concurrent logins for the same account.
 */
if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
    // We need database connection here. 
    // Since security.php is required by db.php, and db.php creates $conn AFTER requiring security.php,
    // we can't use $conn here immediately if this runs before $conn is created.
    // However, security.php functions are called later.
    // The code block at the top of security.php runs on include.

    // BETTER APPROACH: Define a function to validate session and call it in requireLogin() 
    // or run it if $conn is available (which it isn't yet at line 3 of db.php).
    // So we will add a function validateSession($conn) and call it in db.php or rely on requireLogin.
}

/**
 * Validate Session Token
 */
function validateSession($conn)
{
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        return false;
    }

    $stmt = $conn->prepare("SELECT session_token FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if ($user['session_token'] !== $_SESSION['session_token']) {
            // Token mismatch - logged in elsewhere
            return false;
        }
        return true;
    }

    // User not found
    return false;
}


/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Require login - redirect to login page if not authenticated
 */
function requireLogin()
{
    if (!isLoggedIn() || !isset($_SESSION['session_token'])) {
        header("Location: index.php?reason=auth_required");
        exit();
    }
}

/**
 * Require Admin - redirect to restricted page if not admin
 */
function requireAdmin()
{
    requireLogin();
    if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
        $_SESSION['error_message'] = "Access Denied: You do not have permission to view this page.";
        header("Location: index.php");
        exit();
    }
}

/**
 * Generate CSRF Token
 */
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCSRFToken($token)
{
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize input data
 */
function sanitizeInput($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email
 */
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Sanitize for SQL (use with prepared statements)
 */
function sanitizeSQL($conn, $data)
{
    return mysqli_real_escape_string($conn, $data);
}

/**
 * Hash password securely
 */
function hashPassword($password)
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}

/**
 * Log security events
 */
function logSecurityEvent($event, $details = '')
{
    $logDir = dirname(__DIR__) . '/logs';
    $logFile = $logDir . '/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user = $_SESSION['username'] ?? 'guest';

    $logEntry = "[$timestamp] [$ip] [$user] $event - $details\n";

    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // LOG ROTATION LOGIC (Max 10MB)
    if (file_exists($logFile) && filesize($logFile) > 10485760) { // 10MB in bytes
        $backupName = '../logs/security_' . date('Y-m-d_H-i-s') . '.log.bak';
        rename($logFile, $backupName);

        // Optional: Retention Policy - Keep only last 5 backups
        $backups = glob('../logs/security_*.log.bak');
        if (count($backups) > 5) {
            // Sort by modification time (oldest first)
            usort($backups, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            // Delete oldest files until we have 5 left
            while (count($backups) > 5) {
                $oldest = array_shift($backups);
                if (file_exists($oldest)) {
                    unlink($oldest);
                }
            }
        }

        // Log the rotation event in the NEW file
        file_put_contents($logFile, "[$timestamp] SYSTEM: Log file rotated. Previous log archived.\n");
    }

    file_put_contents($logFile, $logEntry, FILE_APPEND);

    // AUTO-TRIM LOGS (Keep only latest 500 lines) - Automated enforcement
    $currentLogs = file($logFile);
    if (count($currentLogs) > 500) {
        $trimmedLogs = array_slice($currentLogs, -500);
        file_put_contents($logFile, implode("", $trimmedLogs));
    }
}

/**
 * Prevent XSS attacks
 */
function escapeOutput($data)
{
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Rate limiting - prevent brute force attacks
 */
function checkRateLimit($action, $maxAttempts = 5, $baseTimeWindow = 30, $identifier = null)
{
    if ($identifier) {
        $key = $action . '_' . $identifier;
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // Use session-based key for specific user tracking if available? No, IP is safer for login brute force.
        $key = $action . '_' . $ip;
    }

    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = [
            'attempts' => 0,
            'first_attempt' => time(),
            'lockout_time' => 0
        ];
    }

    $data = &$_SESSION['rate_limit'][$key];

    // Check if currently locked out
    if (time() < $data['lockout_time']) {
        return [
            'allowed' => false,
            'wait' => $data['lockout_time'] - time()
        ];
    }

    // Check if previously locked out and time passed? Reset if long time no see
    // If last attempt was cleaner than 2*window? 
    // Simplified logic: If attempts >= max, check if window passed.

    // Increment attempts
    $data['attempts']++;

    // If attempts exceed max, calculate lockout
    if ($data['attempts'] > $maxAttempts) {
        // Calculate Lockout Duration
        // First lockout: baseTimeWindow (e.g. 30s)
        // Second lockout: base * 2 (60s)
        // Third lockout: base * 4 (120s)
        // Formula: base * 2^(attempts - max - 1)

        $excess = $data['attempts'] - $maxAttempts;
        $multiplier = pow(2, $excess - 1);
        $lockoutDuration = $baseTimeWindow * $multiplier;

        $data['lockout_time'] = time() + $lockoutDuration;

        logSecurityEvent('RATE_LIMIT_LOCKOUT', "Action: $action, Duration: $lockoutDuration, Attempts: " . $data['attempts']);

        return [
            'allowed' => false,
            'wait' => $lockoutDuration
        ];
    }

    // Not locked out yet
    return ['allowed' => true];
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'], $maxSize = 5242880)
{
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }

    // Check file size (5MB default)
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large'];
    }

    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type'];
    }

    return ['success' => true];
}

/**
 * Prevent SQL Injection - Use prepared statements
 */
function executeQuery($conn, $query, $params = [], $types = '')
{
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        logSecurityEvent('SQL_PREPARE_ERROR', $conn->error);
        return false;
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $result = $stmt->execute();

    if (!$result) {
        logSecurityEvent('SQL_EXECUTE_ERROR', $stmt->error);
        $stmt->close();
        return false;
    }

    return $stmt;
}

/**
 * Check for suspicious activity
 */
function detectSuspiciousActivity($input)
{
    $suspiciousPatterns = [
        '/<script/i',           // XSS
        '/javascript:/i',       // XSS
        '/on\w+\s*=/i',        // Event handlers
        '/UNION.*SELECT/i',     // SQL Injection
        '/DROP.*TABLE/i',       // SQL Injection
        '/DELETE.*FROM/i',      // SQL Injection
        '/INSERT.*INTO/i',      // SQL Injection
        '/../',                 // Directory traversal
        '/\.\.\\//',           // Directory traversal
    ];

    foreach ($suspiciousPatterns as $pattern) {
        if (preg_match($pattern, $input)) {
            logSecurityEvent('SUSPICIOUS_INPUT', "Pattern: $pattern, Input: " . substr($input, 0, 100));
            return true;
        }
    }

    return false;
}

/**
 * Secure headers
 */
function setSecurityHeaders()
{
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    // Uncomment if using HTTPS
    // header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

// Set security headers
setSecurityHeaders();

/**
 * Clean all POST data
 */
function cleanPOSTData()
{
    $cleaned = [];
    foreach ($_POST as $key => $value) {
        if (is_array($value)) {
            $cleaned[$key] = array_map('sanitizeInput', $value);
        } else {
            $cleaned[$key] = sanitizeInput($value);
        }
    }
    return $cleaned;
}

/**
 * Clean all GET data
 */
function cleanGETData()
{
    $cleaned = [];
    foreach ($_GET as $key => $value) {
        if (is_array($value)) {
            $cleaned[$key] = array_map('sanitizeInput', $value);
        } else {
            $cleaned[$key] = sanitizeInput($value);
        }
    }
    return $cleaned;
}

