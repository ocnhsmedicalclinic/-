<?php
require_once "../../config/db.php";
requireLogin();

header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : (isset($_GET['query']) ? trim($_GET['query']) : '');
$typeFilter = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';

if (empty($q)) {
    echo json_encode([]);
    exit;
}

$like = "%" . $q . "%";
$response = [];

// Search Students
if (empty($typeFilter) || $typeFilter === 'student') {
    $stmt1 = $conn->prepare("SELECT id, name, lrn, gender, birth_date, address, curriculum, 'Student' as type FROM students WHERE (name LIKE ? OR lrn LIKE ? OR address LIKE ?) AND is_archived = 0 LIMIT 10");
    $stmt1->bind_param("sss", $like, $like, $like);
    $stmt1->execute();
    $res1 = $stmt1->get_result();
    while ($row = $res1->fetch_assoc()) {
        $age = '';
        if (!empty($row['birth_date'])) {
            $age = (new DateTime($row['birth_date']))->diff(new DateTime('today'))->y;
        }
        $row['age'] = $age;
        $response[] = $row;
    }
    $stmt1->close();
}

// Search Employees
if (empty($typeFilter) || $typeFilter === 'employee') {
    $stmt2 = $conn->prepare("SELECT id, name, employee_no, gender, birth_date, address, position, 'Employee' as type FROM employees WHERE (name LIKE ? OR employee_no LIKE ? OR address LIKE ?) AND is_archived = 0 LIMIT 10");
    $stmt2->bind_param("sss", $like, $like, $like);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) {
        $age = '';
        if (!empty($row['birth_date'])) {
            $age = (new DateTime($row['birth_date']))->diff(new DateTime('today'))->y;
        }
        $row['age'] = $age;
        $response[] = $row;
    }
    $stmt2->close();
}

// Search Others
if (empty($typeFilter) || $typeFilter === 'other') {
    $stmt3 = $conn->prepare("SELECT id, name, sdo, gender, birth_date, address, remarks, 'Other' as type FROM others WHERE (name LIKE ? OR sdo LIKE ? OR address LIKE ? OR remarks LIKE ?) AND is_archived = 0 LIMIT 10");
    $stmt3->bind_param("ssss", $like, $like, $like, $like);
    $stmt3->execute();
    $res3 = $stmt3->get_result();
    while ($row = $res3->fetch_assoc()) {
        $age = '';
        if (!empty($row['birth_date'])) {
            $age = (new DateTime($row['birth_date']))->diff(new DateTime('today'))->y;
        }
        $row['age'] = $age;
        $response[] = $row;
    }
    $stmt3->close();
}

echo json_encode($response);
?>