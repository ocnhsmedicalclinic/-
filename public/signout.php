<?php
require_once '../config/db.php';

// Prevent direct GET access (Typing in URL)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    die("
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Error 403 - Forbidden</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f4f6f8; }
            h1 { color: #dc3545; font-size: 48px; }
            p { font-size: 18px; color: #333; }
            a { color: #00ACB1; text-decoration: none; font-weight: bold; }
        </style>
    </head>
    <body>
        <h1>403 Forbidden</h1>
        <p>Direct access to this page is restricted.</p>
        <p>Please logout securely via the <a href='index.php'>Dashboard</a>.</p>
    </body>
    </html>
    ");
}

// Log logout event
if (isLoggedIn()) {
    logSecurityEvent('LOGOUT', 'User: ' . $_SESSION['username']);
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login
header("Location: index.php?logged_out=1");
exit();
?>