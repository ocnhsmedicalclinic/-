<?php
require_once "../../config/db.php";
requireLogin();

header('Content-Type: application/json');

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 5;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Sorting Logic
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';
if (!in_array($order, ['ASC', 'DESC']))
    $order = 'ASC';

$allowed_sort = ['name', 'birth_date', 'sdo', 'gender', 'address', 'remarks'];
if (!in_array($sort, $allowed_sort))
    $sort = 'name';

$where_clauses = ["is_archived = 0"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_search = "(name LIKE ? OR sdo LIKE ? OR address LIKE ?)";
    $where_clauses[] = $where_search;
    $searchTerm = "%$search%";
    for ($i = 0; $i < 3; $i++) {
        $params[] = $searchTerm;
    }
    $types .= str_repeat("s", 3);
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Count Query
$count_query = "SELECT COUNT(*) as total FROM others $where_sql";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// Data Query with Sorting
$sql = "SELECT * FROM others $where_sql ORDER BY $sort $order LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$others = [];

while ($row = $result->fetch_assoc()) {
    $row['age_computed'] = '-';
    if (!empty($row['birth_date'])) {
        $birthDate = new DateTime($row['birth_date']);
        $today = new DateTime('today');
        $row['age_computed'] = $birthDate->diff($today)->y;
    } else {
        $row['age_computed'] = $row['age'] ?: '-';
    }

    $others[] = $row;
}

echo json_encode([
    'data' => $others,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_records' => $total_records
    ]
]);
?>
