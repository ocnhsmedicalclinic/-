<?php
$conn = new mysqli('localhost', 'root', '', 'clinic_db');
$res = $conn->query("DESCRIBE student_files");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Null'] . "\n";
}
echo "\n====\n";
$res2 = $conn->query("DESCRIBE employee_files");
while ($row = $res2->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Null'] . "\n";
}
