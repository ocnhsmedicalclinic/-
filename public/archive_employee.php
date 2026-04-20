<?php
require_once "../config/db.php";
requireLogin();

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    $sql = "UPDATE employees SET is_archived = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Employee record moved to archive.";
    } else {
        $_SESSION['error_message'] = "Error archiving record: " . $conn->error;
    }
}

header("Location: employees.php");
exit();
?>