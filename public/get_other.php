<?php
require_once "../config/db.php";
header('Content-Type: application/json');

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);

    $stmt = $conn->prepare("SELECT id, name, birth_date, age, sdo, gender, address, remarks FROM others WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request.']);
}
?>