<?php
require_once "../../config/db.php";
requireLogin();

header('Content-Type: application/json');

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 5;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_clauses = ["is_archived = 0"];
$params = [];
$types = "";

if (!empty($search)) {
    $where_search = "(name LIKE ? OR lrn LIKE ? OR address LIKE ? OR curriculum LIKE ? OR gender LIKE ? OR birth_date LIKE ? OR birthplace LIKE ? OR religion LIKE ? OR guardian LIKE ? OR contact LIKE ?)";
    $where_clauses[] = $where_search;
    $searchTerm = "%$search%";
    for ($i = 0; $i < 10; $i++) {
        $params[] = $searchTerm;
    }
    $types .= str_repeat("s", 10);
}

// Sorting Logic
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';
if (!in_array($order, ['ASC', 'DESC']))
    $order = 'ASC';

$allowed_sort = ['name', 'lrn', 'curriculum', 'address', 'gender', 'birth_date', 'birthplace', 'religion', 'guardian', 'contact'];
if (!in_array($sort, $allowed_sort))
    $sort = 'name';

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Count Query
$count_query = "SELECT COUNT(*) as total FROM students $where_sql";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// If count_only is requested, return early
if (isset($_GET['count_only'])) {
    echo json_encode([
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total_records
        ]
    ]);
    exit();
}

// Data Query with Sorting
$sql = "SELECT * FROM students $where_sql ORDER BY $sort $order LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$students = [];

while ($row = $result->fetch_assoc()) {
    // Add computed age
    $row['age'] = '-';
    if ($row['birth_date']) {
        $birth = new DateTime($row['birth_date']);
        $today = new DateTime('today');
        $row['age'] = $birth->diff($today)->y;
    }
    // Format birth date
    $row['birth_date_formatted'] = $row['birth_date'] ? date('m/d/Y', strtotime($row['birth_date'])) : '-';

    // Sanitize for JSON safety (although json_encode handles most)
    // We already have raw data, but let's make sure it's clean for the frontend if needed
    // Actually json_encode is fine with UTF-8

    $students[] = $row;
}

echo json_encode([
    'data' => $students,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $total_pages,
        'total_records' => $total_records
    ]
]);
?>