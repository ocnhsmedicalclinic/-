<?php
ob_start();
require_once "../config/db.php";
requireLogin();

$type = isset($_GET['type']) ? $_GET['type'] : 'student';
$is_archived = isset($_GET['archived']) ? 1 : 0;
$filename = ($is_archived ? "archived_" : "") . $type . "_records_" . date('Y-m-d') . ".xls";

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');

if ($type == 'drug_log') {
    $startDate = $_GET['start'] ?? date('Y-m-01');
    $endDate = $_GET['end'] ?? date('Y-m-t');
    $searchMed = $_GET['search'] ?? '';

    function extractLogs($res, $pType, $pIdentKey, $startDate, $endDate, $searchMed) {
        $data = [];
        while($row = $res->fetch_assoc()) {
            $logs = json_decode($row['treatment_logs_json'] ?? '[]', true);
            foreach($logs as $log) {
                $d = $log['date'] ?? '';
                if ($d >= $startDate && $d <= $endDate) {
                    $slots = [
                        ['p' => $log['plan'] ?? ($log['treatment'] ?? ''), 'q' => $log['quantity'] ?? 1, 'a' => $log['attended'] ?? 'Staff'],
                        ['p' => $log['plan2'] ?? '', 'q' => $log['quantity2'] ?? 1, 'a' => $log['attended2'] ?? 'Staff'],
                        ['p' => $log['plan3'] ?? '', 'q' => $log['quantity3'] ?? 1, 'a' => $log['attended3'] ?? 'Staff']
                    ];
                    foreach($slots as $s) {
                        $med = trim($s['p']);
                        if ($med) {
                            if ($searchMed && stripos($med, $searchMed) === false && stripos($row['name'], $searchMed) === false) continue;
                            $data[] = ['date' => $d, 'name' => $row['name'], 'id' => $row[$pIdentKey] ?? 'N/A', 'type' => $pType, 'med' => $med, 'qty' => $s['q'], 'att' => $s['a']];
                        }
                    }
                }
            }
        }
        return $data;
    }

    $drugLogs = [];
    $drugLogs = array_merge($drugLogs, extractLogs($conn->query("SELECT name, lrn, treatment_logs_json FROM students WHERE is_archived=0"), 'Student', 'lrn', $startDate, $endDate, $searchMed));
    $drugLogs = array_merge($drugLogs, extractLogs($conn->query("SELECT name, employee_no, treatment_logs_json FROM employees WHERE is_archived=0"), 'Employee', 'employee_no', $startDate, $endDate, $searchMed));
    $drugLogs = array_merge($drugLogs, extractLogs($conn->query("SELECT name, treatment_logs_json FROM others WHERE is_archived=0"), 'Other', 'name', $startDate, $endDate, $searchMed));
    usort($drugLogs, fn($a, $b) => strcmp($b['date'], $a['date']));

    echo "Date\tPatient Name\tID\tCategory\tMedicine\tQty\tAttended By\n";
    foreach($drugLogs as $row) {
        echo $row['date'] . "\t" . $row['name'] . "\t" . $row['id'] . "\t" . $row['type'] . "\t" . $row['med'] . "\t" . $row['qty'] . "\t" . $row['att'] . "\n";
    }
} else {
    // Original Logic
    $table = 'students';
    if ($type == 'employee') $table = 'employees';
    elseif ($type == 'inventory') $table = 'inventory_items';
    elseif ($type == 'others') $table = 'others';

    if ($type == 'student') {
        echo "Name\tLRN\tCurriculum\tAge\tAddress\tGender\tBirth Date\tBirthplace\tReligion\tGuardian\tContact\n";
    } elseif ($type == 'others') {
        echo "Name\tAge\tSDO\tGender\tAddress\tRemarks\tBirth Date\n";
    } elseif ($type == 'inventory') {
        echo "Item Name\tCategory\tDescription\tStock Level\tUnit\tExpiry Date\tStatus\n";
    } else {
        echo "Name\tPosition\tDesignation\tAge\tGender\tBirth Date\tCivil Status\n";
    }

    $result = $conn->query("SELECT * FROM $table WHERE is_archived = $is_archived ORDER BY name ASC");
    while ($row = $result->fetch_assoc()) {
        if ($type == 'student') {
            $age = '-';
            if ($row['birth_date']) {
                $birth = new DateTime($row['birth_date']);
                $age = $birth->diff(new DateTime('today'))->y;
            }
            echo $row['name'] . "\t" . $row['lrn'] . "\t" . $row['curriculum'] . "\t" . $age . "\t" . $row['address'] . "\t" . $row['gender'] . "\t" . $row['birth_date'] . "\t" . $row['birthplace'] . "\t" . $row['religion'] . "\t" . $row['guardian'] . "\t" . $row['contact'] . "\n";
        } elseif ($type == 'inventory') {
            $status = "Available";
            if ($row['quantity'] == 0) $status = "Out of Stock";
            elseif ($row['expiry_date'] && new DateTime($row['expiry_date']) < new DateTime('today')) $status = "Expired";
            elseif ($row['quantity'] <= ($row['reorder_level'] ?? 10)) $status = "Low Stock";

            echo $row['name'] . "\t" . $row['category'] . "\t" . $row['description'] . "\t" . $row['quantity'] . "\t" . $row['unit'] . "\t" . $row['expiry_date'] . "\t" . $status . "\n";
        } elseif ($type == 'others') {
            $age = '-';
            if ($row['birth_date']) {
                $age = (new DateTime($row['birth_date']))->diff(new DateTime('today'))->y;
            } else { $age = $row['age'] ?: '-'; }
            echo $row['name'] . "\t" . $age . "\t" . $row['sdo'] . "\t" . $row['gender'] . "\t" . $row['address'] . "\t" . $row['remarks'] . "\t" . $row['birth_date'] . "\n";
        } else {
            $age = '-';
            if ($row['birth_date']) { $age = (new DateTime($row['birth_date']))->diff(new DateTime('today'))->y; }
            echo $row['name'] . "\t" . $row['position'] . "\t" . $row['designation'] . "\t" . $age . "\t" . $row['gender'] . "\t" . $row['birth_date'] . "\t" . $row['civil_status'] . "\n";
        }
    }
}
exit;