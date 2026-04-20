<?php
require_once 'config/db.php';
$res = $conn->query("SHOW COLUMNS FROM students");
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "<br>\n";
}
?>