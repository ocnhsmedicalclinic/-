<?php
require_once "../../config/db.php";
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}

$primaryId = intval($_POST['primary_id'] ?? 0);
$duplicateId = intval($_POST['duplicate_id'] ?? 0);
$type = $_POST['type'] ?? 'student'; // 'student', 'employee', or 'other'

if ($primaryId <= 0 || $duplicateId <= 0 || $primaryId === $duplicateId) {
    die(json_encode(['success' => false, 'message' => 'Invalid patient IDs']));
}

$table = 'students';
$filesTable = 'student_files';
$idField = 'student_id';

if ($type === 'employee') {
    $table = 'employees';
    $filesTable = 'employee_files';
    $idField = 'employee_id';
} elseif ($type === 'other') {
    $table = 'others';
    $filesTable = 'other_files';
    $idField = 'other_id';
}

// Fetch records
$primary = $conn->query("SELECT * FROM $table WHERE id = $primaryId")->fetch_assoc();
$duplicate = $conn->query("SELECT * FROM $table WHERE id = $duplicateId")->fetch_assoc();

if (!$primary || !$duplicate) {
    die(json_encode(['success' => false, 'message' => 'One or both records not found']));
}

// 1. Merge treatment_logs_json
$primaryLogs = json_decode($primary['treatment_logs_json'] ?? '[]', true);
if (!is_array($primaryLogs)) $primaryLogs = [];
$duplicateLogs = json_decode($duplicate['treatment_logs_json'] ?? '[]', true);
if (!is_array($duplicateLogs)) $duplicateLogs = [];

$mergedLogs = array_merge($primaryLogs, $duplicateLogs);
// Sort merged logs by date
usort($mergedLogs, function ($a, $b) {
    return strtotime($b['date'] ?? '') - strtotime($a['date'] ?? '');
});
$mergedLogsJson = mysqli_real_escape_string($conn, json_encode($mergedLogs));

// 2. Merge health_exam_json
$primaryHealth = json_decode($primary['health_exam_json'] ?? '{}', true);
if (!is_array($primaryHealth)) $primaryHealth = [];
$duplicateHealth = json_decode($duplicate['health_exam_json'] ?? '{}', true);
if (!is_array($duplicateHealth)) $duplicateHealth = [];

$mergedHealth = $primaryHealth;
foreach ($duplicateHealth as $key => $val) {
    if (!empty($val) && empty($mergedHealth[$key])) {
        $mergedHealth[$key] = $val;
    }
}
$mergedHealthJson = mysqli_real_escape_string($conn, json_encode($mergedHealth));

// 3. Merge consent_data_json (Mostly for students)
$mergedConsentJson = $primary['consent_data_json'] ?? '';
if ($type === 'student') {
    $primaryConsent = json_decode($primary['consent_data_json'] ?? '{}', true);
    if (!is_array($primaryConsent)) $primaryConsent = [];
    $duplicateConsent = json_decode($duplicate['consent_data_json'] ?? '{}', true);
    if (!is_array($duplicateConsent)) $duplicateConsent = [];

    $mergedConsent = $primaryConsent;
    foreach ($duplicateConsent as $key => $val) {
        if (!empty($val) && empty($mergedConsent[$key])) {
            $mergedConsent[$key] = $val;
        }
    }
    $mergedConsentJson = mysqli_real_escape_string($conn, json_encode($mergedConsent));
}

// 4. Merge Standard Fields
$fieldsToMerge = [
    'student' => ['lrn', 'curriculum', 'address', 'gender', 'birth_date', 'birthplace', 'religion', 'guardian', 'contact', 'status'],
    'employee' => ['employee_no', 'entry_date', 'birth_date', 'gender', 'civil_status', 'school_district_division', 'position', 'designation', 'first_year_in_service', 'salary_grade', 'address', 'birthplace', 'religion'],
    'other' => ['sdo', 'birth_date', 'age', 'gender', 'address', 'remarks', 'contact_no']
];

$updates = [];
if (isset($fieldsToMerge[$type])) {
    foreach ($fieldsToMerge[$type] as $field) {
        // Only update if primary is empty but duplicate has data
        if (isset($primary[$field]) && empty($primary[$field]) && !empty($duplicate[$field])) {
            $val = mysqli_real_escape_string($conn, $duplicate[$field]);
            $updates[] = "$field = '$val'";
        }
    }
}

// 5. Consent Files (For students)
if ($type === 'student') {
    if (empty($primary['consent_front_file']) && !empty($duplicate['consent_front_file'])) {
        $updates[] = "consent_front_file = '" . mysqli_real_escape_string($conn, $duplicate['consent_front_file']) . "'";
    }
    if (empty($primary['consent_back_file']) && !empty($duplicate['consent_back_file'])) {
        $updates[] = "consent_back_file = '" . mysqli_real_escape_string($conn, $duplicate['consent_back_file']) . "'";
    }
}

// 6. Update Primary Record
$allUpdates = array_merge([
    "treatment_logs_json = '$mergedLogsJson'",
    "health_exam_json = '$mergedHealthJson'",
    "consent_data_json = '$mergedConsentJson'"
], $updates);

$updateSql = "UPDATE $table SET " . implode(", ", $allUpdates) . " WHERE id = $primaryId";

if ($conn->query($updateSql)) {
    // 7. Move Files
    $conn->query("UPDATE $filesTable SET $idField = $primaryId WHERE $idField = $duplicateId");

    // 8. Update Treatment Records
    if ($type === 'student') {
        $conn->query("UPDATE treatment_records SET student_id = $primaryId WHERE student_id = $duplicateId");
    } elseif ($type === 'employee') {
        $conn->query("UPDATE treatment_records SET employee_id = $primaryId WHERE employee_id = $duplicateId");
    } elseif ($type === 'other') {
        $conn->query("UPDATE treatment_records SET other_id = $primaryId WHERE other_id = $duplicateId");
    }

    // 9. Delete Duplicate
    $conn->query("DELETE FROM $table WHERE id = $duplicateId");

    echo json_encode(['success' => true, 'message' => 'Records merged successfully!', 'primary_name' => $primary['name']]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update primary record: ' . $conn->error]);
}
