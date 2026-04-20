<?php
require_once '../../config/db.php';
require_once '../../lib/ClinicAI.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$ai = new ClinicAI($conn);

if ($action === 'analyze_image') {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'No image uploaded']);
        exit;
    }

    $mode = $_POST['mode'] ?? 'rash';
    $tmpName = $_FILES['image']['tmp_name'];

    // Create a permanent upload for record keeping
    $uploadDir = '../../uploads/vision/';
    if (!is_dir($uploadDir))
        mkdir($uploadDir, 0777, true);

    $filename = uniqid('img_') . '.jpg';
    $destination = $uploadDir . $filename;

    if (move_uploaded_file($tmpName, $destination)) {
        // Run AI Analysis
        $absPath = realpath($destination);
        $result = $ai->runVisionAnalysis($absPath, $mode);

        $result['image_url'] = 'uploads/vision/' . $filename;
        echo json_encode($result);
    } else {
        echo json_encode(['error' => 'Failed to save image']);
    }
} else {
    echo json_encode(['error' => 'Invalid action']);
}
