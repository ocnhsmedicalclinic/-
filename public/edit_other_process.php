<?php
require_once "../config/db.php";
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $name = $_POST['name'] ?? '';
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $age = !empty($_POST['age']) ? (int) $_POST['age'] : null;
    $sdo = $_POST['sdo'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $address = $_POST['address'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    $stmt = $conn->prepare("UPDATE others SET name=?, birth_date=?, age=?, sdo=?, gender=?, address=?, remarks=? WHERE id=?");
    $stmt->bind_param("ssissssi", $name, $birth_date, $age, $sdo, $gender, $address, $remarks, $id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Record updated successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to update record.";
    }
}

header("Location: others.php");
exit;
?>