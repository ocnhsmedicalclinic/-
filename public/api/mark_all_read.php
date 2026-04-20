<?php
require_once "../../config/db.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$is_admin_role = (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')) ? 1 : 0;

$response = ['success' => false];

if (isset($conn)) {
    // 1. Mark all DB notifications as read
    $stmtMarkAll = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND (user_id = ? OR (user_id IS NULL AND ? = 1))");
    if ($stmtMarkAll) {
        $stmtMarkAll->bind_param("ii", $current_user_id, $is_admin_role);
        $stmtMarkAll->execute();
        $stmtMarkAll->close();
    }

    // 2. Mark all inventory notifications as read (cookie)
    $cookie_name = 'read_inv_notifs_' . $_SESSION['user_id'];
    $read_notifs = isset($_COOKIE[$cookie_name]) ? json_decode($_COOKIE[$cookie_name], true) : [];
    if (!is_array($read_notifs)) {
        $read_notifs = [];
    }

    $invLowRes = $conn->query("SELECT id FROM inventory_items WHERE quantity <= 10 AND quantity > 0");
    if ($invLowRes) {
        while ($r = $invLowRes->fetch_assoc()) {
            $nid = 'low_' . $r['id'];
            if (!in_array($nid, $read_notifs)) {
                $read_notifs[] = $nid;
            }
        }
    }

    $invExpRes = $conn->query("SELECT id FROM inventory_items WHERE expiry_date IS NOT NULL AND expiry_date <= CURDATE()");
    if ($invExpRes) {
        while ($r = $invExpRes->fetch_assoc()) {
            $nid = 'exp_' . $r['id'];
            if (!in_array($nid, $read_notifs)) {
                $read_notifs[] = $nid;
            }
        }
    }

    setcookie($cookie_name, json_encode($read_notifs), time() + (86400 * 30), "/");

    $response['success'] = true;
    $response['message'] = "All notifications marked as read.";
}

header('Content-Type: application/json');
echo json_encode($response);
?>