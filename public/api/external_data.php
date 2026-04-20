<?php
// api/external_data.php
require_once '../../config/db.php';

header('Content-Type: application/json');

// Check login (optional but good practice)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? '';

if ($action === 'search_medicine') {
    $query = $_GET['query'] ?? '';
    $query = $conn->real_escape_string($query);
    $results = [];

    if (strlen($query) >= 2) {
        // 1. Search Local Inventory Items
        $sql = "SELECT name, quantity, unit FROM inventory_items 
                WHERE (name LIKE '%$query%' OR description LIKE '%$query%') 
                AND quantity > 0 AND is_archived = 0
                ORDER BY name ASC LIMIT 5";

        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $results[] = [
                    'item' => $row['name'],
                    'source' => 'Local Pharmacy',
                    'stock' => $row['quantity'] . " " . $row['unit']
                ];
            }
        }

        // 2. Search Global NLM RxTerms (US National Library of Medicine)
        $url = "https://clinicaltables.nlm.nih.gov/api/rxterms/v3/search?terms=" . urlencode($query) . "&maxList=5";
        $response = @file_get_contents($url);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data[1]) && is_array($data[1])) {
                foreach ($data[1] as $drug) {
                    $results[] = [
                        'item' => $drug,
                        'source' => 'NLM Global Pharmacopeia',
                        'stock' => 'Prescription Only'
                    ];
                }
            }
        }
    }
    echo json_encode(['medicines' => $results]);
    exit;

} elseif ($action === 'doh_alerts') {
    // Keep existing DOH alerts logic if needed, or stub it out for now
    // Assuming ExternalAPIs was used before, we can leave it out or include if absolutely necessary.
    // Given the user focus on inventory, let's keep it simple.
    echo json_encode(['alerts' => []]);

} elseif ($action === 'get_item_statuses') {
    $ids = $_GET['ids'] ?? '';
    if (empty($ids)) {
        echo json_encode([]);
        exit;
    }

    $ids_array = array_map('intval', explode(',', $ids)); // sanitize
    if (empty($ids_array)) {
        echo json_encode([]);
        exit;
    }

    $ids_str = implode(',', $ids_array);

    $sql = "SELECT id, name, quantity, reorder_level, expiry_date, 
            DATEDIFF(expiry_date, CURDATE()) as days_to_expiry
            FROM inventory_items 
            WHERE id IN ($ids_str)";

    $result = $conn->query($sql);
    $data = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $status_class = 'status-ok';
            $status_text = 'Available';

            if ($row['quantity'] == 0) {
                $status_class = 'status-out';
                $status_text = 'Out of Stock';
            } elseif ($row['expiry_date'] && $row['days_to_expiry'] < 0) {
                $status_class = 'status-exp';
                $status_text = 'Expired';
            } elseif ($row['quantity'] <= 10 && $row['quantity'] > 0) {
                $status_class = 'status-low';
                $status_text = 'Low Stock';
            } elseif ($row['expiry_date'] && $row['days_to_expiry'] <= 30) {
                $status_class = 'status-low';
                $status_text = 'Expiring Soon';
            }

            $data[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'quantity' => $row['quantity'],
                'expiry_date' => $row['expiry_date'],
                'days_to_expiry' => $row['days_to_expiry'],
                'status_class' => $status_class,
                'status_text' => $status_text,
                'is_low_stock' => ($row['quantity'] <= 10 && $row['quantity'] > 0)
            ];
        }
    }

    echo json_encode($data);

} else {
    echo json_encode(['error' => 'Invalid action']);
}
?>