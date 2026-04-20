<?php
// api/inventory_operations.php
require_once '../../config/db.php';

header('Content-Type: application/json');

// Check login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_REQUEST['action'] ?? '';

// 1. GET Requests (Fetch Data)
if ($method === 'GET') {

    if ($action === 'get_inventory') {
        // Fetch Active Inventory Items
        $search = $_GET['search'] ?? '';

        $sql = "SELECT *, DATEDIFF(expiry_date, CURDATE()) as days_to_expiry FROM inventory_items WHERE is_archived = 0";

        if (!empty($search)) {
            $search = $conn->real_escape_string($search);
            if ($search === 'low_stock') {
                $sql .= " AND quantity <= reorder_level";
            } elseif ($search === 'out_of_stock') {
                $sql .= " AND quantity = 0";
            } elseif ($search === 'expired') {
                $sql .= " AND expiry_date IS NOT NULL AND expiry_date <= CURDATE()";
            } else {
                $sql .= " AND (name LIKE '%$search%' OR description LIKE '%$search%')";
            }
        }

        $sql .= " ORDER BY name ASC";
        $result = $conn->query($sql);

        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                // Formatting Logic
                $status_class = 'status-ok';
                $status_text = 'Available';

                if ($row['quantity'] == 0) {
                    $status_class = 'status-out';
                    $status_text = 'Out of Stock';
                } elseif ($row['expiry_date'] && $row['days_to_expiry'] < 0) {
                    $status_class = 'status-exp';
                    $status_text = 'Expired';
                } elseif ($row['quantity'] <= $row['reorder_level']) {
                    $status_class = 'status-low';
                    $status_text = 'Low Stock';
                } elseif ($row['expiry_date'] && $row['days_to_expiry'] <= 30) {
                    $status_class = 'status-low';
                    $status_text = 'Expiring Soon';
                }

                $row['status_class'] = $status_class;
                $row['status_text'] = $status_text;
                $data[] = $row;
            }
        }
        echo json_encode(['data' => $data]);
        exit;
    }

    if ($action === 'get_archived_inventory') {
        // Fetch Archived Inventory Items
        $sql = "SELECT * FROM inventory_items WHERE is_archived = 1 ORDER BY archived_at DESC";
        $result = $conn->query($sql);

        $data = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        echo json_encode(['data' => $data]);
        exit;
    }

}

// 2. POST Requests (Actions)
if ($method === 'POST') {
    $id = intval($_POST['id'] ?? 0);

    if ($action === 'archive') {
        $stmt = $conn->prepare("UPDATE inventory_items SET is_archived = 1, archived_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Item archived successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
        exit;
    }

    if ($action === 'restore') {
        $stmt = $conn->prepare("UPDATE inventory_items SET is_archived = 0, archived_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Item restored successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
        exit;
    }

    if ($action === 'delete_permanent') {
        $stmt = $conn->prepare("DELETE FROM inventory_items WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Item permanently deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
        exit;
    }
}
?>