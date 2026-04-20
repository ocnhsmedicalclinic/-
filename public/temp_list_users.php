<?php
require_once "../config/db.php";

$result = $conn->query("SELECT id, username, role FROM users");
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Username: " . $row['username'] . " | Role: " . $row['role'] . "\n";
}
?>