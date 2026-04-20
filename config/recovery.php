<?php
/**
 * Emergency Recovery Account Configuration
 * This file contains hardcoded emergency admin credentials
 * Use ONLY when database is corrupted or inaccessible
 */

// Emergency Recovery Admin Credentials
define('RECOVERY_USERNAME', 'emergency_admin');
define('RECOVERY_PASSWORD', 'OcnhsRecovery2024!'); // Change this to a strong password

// Recovery mode session key
define('RECOVERY_MODE_KEY', 'recovery_mode_active');

/**
 * Verify recovery credentials
 */
function verifyRecoveryCredentials($username, $password)
{
    return ($username === RECOVERY_USERNAME && $password === RECOVERY_PASSWORD);
}

/**
 * Check if user is in recovery mode
 */
function isRecoveryMode()
{
    return isset($_SESSION[RECOVERY_MODE_KEY]) && $_SESSION[RECOVERY_MODE_KEY] === true;
}

/**
 * Enable recovery mode
 */
function enableRecoveryMode()
{
    $_SESSION[RECOVERY_MODE_KEY] = true;
    $_SESSION['username'] = RECOVERY_USERNAME;
    $_SESSION['role'] = 'superadmin';
    $_SESSION['user_id'] = 0; // Special ID for recovery account
}

/**
 * Disable recovery mode
 */
function disableRecoveryMode()
{
    unset($_SESSION[RECOVERY_MODE_KEY]);
}
?>