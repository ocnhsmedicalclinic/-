<?php
require_once "../config/db.php";
requireAdmin(); // Enforce Admin Access for Archiving

if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // Secure against SQLi

    // Check if student exists
    $check = $conn->query("SELECT name FROM students WHERE id = $id");
    if ($check->num_rows > 0) {
        $student = $check->fetch_assoc();
        $name = $student['name'];

        // Perform Archive: Set is_archived = 1 and archived_at = NOW()
        $stmt = $conn->prepare("UPDATE students SET is_archived = 1, archived_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            logSecurityEvent('STUDENT_ARCHIVE', "User {$_SESSION['username']} archived student: $name (ID: $id)");
            $_SESSION['success_message'] = "Student $name has been archived successfully.";
        } else {
            $_SESSION['error_message'] = "Error archiving student: " . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Student not found.";
    }
}

header("Location: student.php");
exit;
?>
