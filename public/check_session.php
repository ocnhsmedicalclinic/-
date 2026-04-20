<?php
// Set headers for JSON response
header('Content-Type: application/json');
require_once '../config/db.php';

// If validateSession returns false, it means session is invalid
// Note: validateSession is defined in security.php which is included by db.php
// However, db.php ALREADY calls validateSession and destroys session if invalid.
// So if we reach here and session is empty, it means logged out.

$response = [
    'valid' => true
];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
    $response['valid'] = false;
    $response['reason'] = 'inactivity';
} else {
    // db.php handles the check on include. 
    // If we are here, and session still exists, it means db.php didn't destroy it.
    // BUT db.php logic might have already run and destroyed it if invalid.

    // Let's double check manually just to be sure, although db.php does it.
    // If db.php destroyed it, $_SESSION['user_id'] would be unset.

    // So if strictly isset($_SESSION['user_id']) is true here, it is valid.
    // UNLESS db.php redirection logic kicked in?
    // db.php redirects if invalid. We need to prevent redirect for this AJAX script if possible, 
    // OR handle the redirect response in JS.
    // But db.php detects AJAX (HTTP_X_REQUESTED_WITH) and avoids redirect? 
    // I added that logic in db.php in previous step! 

    // Let's verify db.php logic again.
}

echo json_encode($response);
?>