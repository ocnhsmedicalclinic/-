<?php
require_once "../../config/db.php";
requireLogin();

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'student';
$table = 'students';
$idField = 'lrn';

if ($type === 'employee') {
    $table = 'employees';
    $idField = 'employee_no';
} elseif ($type === 'other') {
    $table = 'others';
    $idField = 'sdo';
}

// Find records that have same name AND same birth_date / age
$ageField = ($type === 'other') ? "COALESCE(birth_date, age, '')" : "birth_date";

$sql = "SELECT name, $ageField as dedupe_age, COUNT(*) as count 
        FROM $table 
        WHERE is_archived = 0 
        GROUP BY name, dedupe_age 
        HAVING count > 1";

$res = $conn->query($sql);
$suggestions = [];

while ($row = $res->fetch_assoc()) {
    $name = mysqli_real_escape_string($conn, $row['name']);
    $ageVal = mysqli_real_escape_string($conn, $row['dedupe_age']);
    
    // Determine the WHERE clause for dedupe_age
    $whereAge = ($type === 'other') ? "(birth_date = '$ageVal' OR age = '$ageVal')" : "birth_date = '$ageVal'";
    if ($ageVal === '') {
        $whereAge = ($type === 'other') ? "(birth_date IS NULL AND (age IS NULL OR age = ''))" : "birth_date IS NULL";
    }

    $details = $conn->query("SELECT id, name, $idField as identifier, created_at, birth_date, age FROM $table WHERE name = '$name' AND $whereAge AND is_archived = 0 ORDER BY created_at DESC");

    $records = [];
    while ($p = $details->fetch_assoc()) {
        $records[] = $p;
    }

    $suggestions[] = [
        'name' => $row['name'],
        'age' => $row['dedupe_age'],
        'count' => $row['count'],
        'records' => $records
    ];
}

echo json_encode($suggestions);
