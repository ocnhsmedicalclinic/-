<?php
require_once "../../config/db.php";
requireLogin();

header('Content-Type: application/json');

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_clauses = ["is_archived = 0"];
$params = [];
$types = "";

try {
    // Disable error display to prevent HTML injection in JSON
    ini_set('display_errors', 0);
    error_reporting(E_ALL);

    if (!empty($search)) {
        $where_search = "(name LIKE ? OR position LIKE ? OR designation LIKE ? OR school_district_division LIKE ? OR entry_date LIKE ?)";
        $where_clauses[] = $where_search;
        $searchTerm = "%$search%";
        for ($i = 0; $i < 5; $i++) {
            $params[] = $searchTerm;
        }
        $types .= str_repeat("s", 5);
    }

    $where_sql = "WHERE " . implode(" AND ", $where_clauses);

    // Sorting Logic
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
    $order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';
    if (!in_array($order, ['ASC', 'DESC']))
        $order = 'ASC';

    $allowed_sort = ['name', 'employee_no', 'birth_date', 'gender', 'civil_status', 'first_year_in_service', 'school_district_division', 'position', 'designation'];
    if (!in_array($sort, $allowed_sort))
        $sort = 'name';

    // Count Query
    $count_query = "SELECT COUNT(*) as total FROM employees $where_sql";
    $stmt = $conn->prepare($count_query);
    if (!$stmt)
        throw new Exception("Prepare failed: " . $conn->error);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total_result = $stmt->get_result();
    $total_row = $total_result->fetch_assoc();
    $total_records = $total_row['total'];
    $total_pages = ceil($total_records / $limit);

    // Data Query with Sorting
    $sql = "SELECT * FROM employees $where_sql ORDER BY $sort $order LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($sql);
    if (!$stmt)
        throw new Exception("Prepare failed: " . $conn->error);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $employees = [];

    // Helper functions (duplicated from employees.php logic)
    if (!function_exists('calculateAge')) {
        function calculateAge($birthDate)
        {
            if (empty($birthDate) || $birthDate == '0000-00-00')
                return '-';
            try {
                $birthDate = new DateTime($birthDate);
                $today = new DateTime('today');
                return $birthDate->diff($today)->y;
            } catch (Exception $e) {
                return '-';
            }
        }
    }

    if (!function_exists('calculateServiceYears')) {
        function calculateServiceYears($firstYear)
        {
            if (empty($firstYear))
                return '-';
            $currentYear = date("Y");
            return $currentYear - $firstYear;
        }
    }

    while ($row = $result->fetch_assoc()) {
        $row['entry_date_formatted'] = ($row['entry_date'] && $row['entry_date'] != '0000-00-00') ? date('m/d/Y', strtotime($row['entry_date'])) : '-';
        $row['birth_date_formatted'] = ($row['birth_date'] && $row['birth_date'] != '0000-00-00') ? date('m/d/Y', strtotime($row['birth_date'])) : '-';

        $row['age'] = calculateAge($row['birth_date']);
        $row['service_years'] = calculateServiceYears($row['first_year_in_service']);

        // Ensure nulls are handled for JSON
        foreach ($row as $key => $value) {
            if ($value === null)
                $row[$key] = '';
        }

        $employees[] = $row;
    }

    echo json_encode([
        'data' => $employees,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total_records
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>