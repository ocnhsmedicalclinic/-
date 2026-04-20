<?php
require_once "../config/db.php";
requireLogin();

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);

    // Check if record exists
    $stmt = $conn->prepare("SELECT id FROM others WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $update = $conn->prepare("UPDATE others SET is_archived = 1, archived_at = NOW() WHERE id = ?");
        $update->bind_param("i", $id);

        if ($update->execute()) {
            $_SESSION['success_message'] = "Record has been moved to archive.";
        } else {
            $_SESSION['error_message'] = "Failed to archive record.";
        }
    } else {
        $_SESSION['error_message'] = "Record not found.";
    }
}

header("Location: others.php");
exit;
?>