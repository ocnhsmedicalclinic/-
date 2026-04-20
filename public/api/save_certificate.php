<?php
require_once "../../config/db.php";
requireLogin();

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Fallback to $_POST if request came from offline sync queue (FormData)
if (!$data && !empty($_POST)) {
    $data = $_POST;
}

file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . "\nInput length: " . strlen($input) . "\n", FILE_APPEND);

if (!$data || !isset($data['patient_id']) || !isset($data['patient_type']) || !isset($data['image_data']) || !isset($data['title'])) {
    file_put_contents('debug_log.txt', "Failed logic check.\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Invalid data provided.', 'debug_len' => strlen($input), 'json_error' => json_last_error_msg()]);
    exit;
}

$patientId = intval($data['patient_id']);
$patientType = strtolower($data['patient_type']);
$imageData = $data['image_data'];
$title = preg_replace('/[^A-Za-z0-9 _-]/', '', $data['title']);

// Create directory if not exists
$uploadDir = "../uploads/medical_records/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Convert base64 to image
$parts = explode(',', $imageData);
if (count($parts) !== 2) {
    echo json_encode(['success' => false, 'message' => 'Invalid image format.']);
    exit;
}
$base64Image = $parts[1];
$binaryImage = base64_decode($base64Image);

$filename = time() . "_" . str_replace(" ", "_", $title) . ".jpg";
$targetFilePath = $uploadDir . $filename;

if (file_put_contents($targetFilePath, $binaryImage) !== false) {
    $dbPath = "uploads/medical_records/" . $filename;
    $fileSize = filesize($targetFilePath);

    if ($patientType === 'employee') {
        $filesTable = "employee_files";
        $idField = "employee_id";
    } else {
        $filesTable = "student_files";
        $idField = "student_id";
    }

    $stmt = $conn->prepare("INSERT INTO $filesTable ($idField, filename, filepath, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
    $fileType = 'jpg';
    $stmt->bind_param("isssi", $patientId, $title, $dbPath, $fileType, $fileSize);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Certificate saved to medical records.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to write file.']);
}
?>