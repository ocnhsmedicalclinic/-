<?php
require_once '../config/db.php';
requireAdmin();

$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);
$type = $_POST['type'] ?? 'student'; // Default type from form

// Smart Lookup: Prioritize the type sent by the form to avoid ID collisions
$realTable = '';
$realType = '';

// Determine priority based on form input
if ($type === 'employee') {
    $priorityCheck = ['employees', 'employee'];
    $secondaryCheck = ['students', 'student'];
} elseif ($type === 'inventory') {
    $priorityCheck = ['inventory_items', 'inventory'];
    $secondaryCheck = []; // No fallback for inventory
} elseif ($type === 'others') {
    $priorityCheck = ['others', 'others'];
    $secondaryCheck = [];
} else {
    $priorityCheck = ['students', 'student'];
    $secondaryCheck = ['employees', 'employee'];
}

// 1. Check Priority Table
$checkPrimary = $conn->query("SELECT id FROM {$priorityCheck[0]} WHERE id = $id");
if ($checkPrimary && $checkPrimary->num_rows > 0) {
    $realTable = $priorityCheck[0];
    $realType = $priorityCheck[1];
} else {
    // 2. Fallback to Secondary Table (in case of type mismatch or error)
    if (!empty($secondaryCheck)) {
        $checkSecondary = $conn->query("SELECT id FROM {$secondaryCheck[0]} WHERE id = $id");
        if ($checkSecondary && $checkSecondary->num_rows > 0) {
            $realTable = $secondaryCheck[0];
            $realType = $secondaryCheck[1];
        }
    }
}

// If record not found in either table
if (empty($realTable)) {
    $_SESSION['error_message'] = "Record with ID $id not found in database.";
    header("Location: archive_list.php?type=$type");
    exit;
}

// Use the correct table
$table = $realTable;
// Update type only if we fell back to the other table
$type = $realType;

switch ($action) {
    case 'restore':
        $stmt = $conn->prepare("UPDATE $table SET is_archived = 0, archived_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            if (function_exists('logSecurityEvent')) {
                logSecurityEvent(strtoupper($type) . '_RESTORED', ucfirst($type) . " ID: $id restored by Admin");
            }
            $_SESSION['success_message'] = ucfirst($type) . " record restored successfully!";
        } else {
            $_SESSION['error_message'] = "Database Error during restore: " . $stmt->error;
        }
        $stmt->close();
        break;

    case 'delete':
        $conn->begin_transaction();
        try {
            // 1. Handle Files and Related Data
            if ($table === 'students' || $table === 'employees') {
                $filesTable = ($table === 'students') ? 'student_files' : 'employee_files';
                $idField = ($table === 'students') ? 'student_id' : 'employee_id';

                // Get and delete medical record files from disk
                $stmtFiles = $conn->prepare("SELECT filepath FROM $filesTable WHERE $idField = ?");
                $stmtFiles->bind_param("i", $id);
                $stmtFiles->execute();
                $resFiles = $stmtFiles->get_result();
                while ($f = $resFiles->fetch_assoc()) {
                    if (!empty($f['filepath'])) {
                        $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . $f['filepath'];
                        if (file_exists($absolutePath)) {
                            unlink($absolutePath);
                        }
                    }
                }
                $stmtFiles->close();

                // Delete records from files table
                $conn->query("DELETE FROM $filesTable WHERE $idField = $id");

                // 2. Handle Consent Files (Students only)
                if ($table === 'students') {
                    $stmtConsent = $conn->prepare("SELECT consent_front_file, consent_back_file FROM students WHERE id = ?");
                    $stmtConsent->bind_param("i", $id);
                    $stmtConsent->execute();
                    $resConsent = $stmtConsent->get_result();
                    if ($c = $resConsent->fetch_assoc()) {
                        $consentUploadDir = __DIR__ . "/uploads/consent/";
                        if (!empty($c['consent_front_file']) && file_exists($consentUploadDir . $c['consent_front_file'])) {
                            unlink($consentUploadDir . $c['consent_front_file']);
                        }
                        if (!empty($c['consent_back_file']) && file_exists($consentUploadDir . $c['consent_back_file'])) {
                            unlink($consentUploadDir . $c['consent_back_file']);
                        }
                    }
                    $stmtConsent->close();
                }
            }

            // 3. Delete the Main Record
            $stmtDelete = $conn->prepare("DELETE FROM $table WHERE id = ?");
            $stmtDelete->bind_param("i", $id);

            if ($stmtDelete->execute()) {
                $conn->commit();
                if (function_exists('logSecurityEvent')) {
                    logSecurityEvent(strtoupper($type) . '_DELETED', ucfirst($type) . " ID: $id PERMANENTLY DELETED by Admin");
                }
                $_SESSION['success_message'] = ucfirst($type) . " record and all associated data/files permanently deleted.";
            } else {
                throw new Exception($stmtDelete->error);
            }
            $stmtDelete->close();

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "Error during permanent delete: " . $e->getMessage();
        }
        break;

    default:
        $_SESSION['error_message'] = "Invalid action received: $action";
        break;
}

header("Location: archive_list.php?type=$type");
exit;
?>