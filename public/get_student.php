<?php
require_once "../config/db.php";
requireLogin();

$id = intval($_GET['id']);
$result = $conn->query("SELECT * FROM students WHERE id=$id");
echo json_encode($result->fetch_assoc());
