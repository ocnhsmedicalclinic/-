<?php
ob_start();
require_once "../config/db.php";
requireLogin();

$type = isset($_GET['type']) ? $_GET['type'] : 'student';
$is_archived = isset($_GET['archived']) ? 1 : 0;
$table = 'students';

if ($type == 'drug_log') {
    $startDate = $_GET['start'] ?? date('Y-m-01');
    $endDate = $_GET['end'] ?? date('Y-m-t');
    $searchMed = $_GET['search'] ?? '';
    $title = "Medicine Dispensing Registry (Drug Log)";

    function extractLogs($res, $pType, $pIdentKey, $startDate, $endDate, $searchMed)
    {
        $data = [];
        while ($row = $res->fetch_assoc()) {
            $logs = json_decode($row['treatment_logs_json'] ?? '[]', true);
            foreach ($logs as $log) {
                $d = $log['date'] ?? '';
                if ($d >= $startDate && $d <= $endDate) {
                    $slots = [
                        ['p' => $log['plan'] ?? ($log['treatment'] ?? ''), 'q' => $log['quantity'] ?? 1, 'a' => $log['attended'] ?? 'Staff'],
                        ['p' => $log['plan2'] ?? '', 'q' => $log['quantity2'] ?? 1, 'a' => $log['attended2'] ?? 'Staff'],
                        ['p' => $log['plan3'] ?? '', 'q' => $log['quantity3'] ?? 1, 'a' => $log['attended3'] ?? 'Staff']
                    ];
                    foreach ($slots as $s) {
                        $med = trim($s['p']);
                        if ($med) {
                            if ($searchMed && stripos($med, $searchMed) === false && stripos($row['name'], $searchMed) === false)
                                continue;
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
} else {
    if ($type == 'employee')
        $table = 'employees';
    elseif ($type == 'inventory')
        $table = 'inventory_items';
    elseif ($type == 'others')
        $table = 'others';
    $title = ($is_archived ? "Archived " : "") . ucfirst($type) . " Records Report";
    $result = $conn->query("SELECT * FROM $table WHERE is_archived = $is_archived ORDER BY name ASC");
}

// Columns determination
$cols = 6;
if ($type == 'inventory')
    $cols = 7;
elseif ($type == 'student')
    $cols = 8;
elseif ($type == 'others')
    $cols = 7;
elseif ($type == 'employee')
    $cols = 7;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= $title ?></title>

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .report-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #00ACB1;
            padding-bottom: 10px;
        }

        .report-header img {
            height: 60px;
            margin-bottom: 5px;
        }

        .report-header h2 {
            margin: 0;
            color: #00ACB1;
            text-transform: uppercase;
        }

        .report-header p {
            margin: 5px 0 0;
            font-size: 14px;
            font-weight: bold;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }

        th,
        td {
            border: 1px solid #edf2f7;
            padding: 12px 10px;
            text-align: center;
            font-size: 11px;
        }

        th {
            background: linear-gradient(135deg, #00ACB1 0%, #00d4aa 100%) !important;
            color: white !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        tr:nth-child(even) {
            background-color: #fcfdfe;
        }

        .no-print {
            padding: 10px;
            background: #f5f5f5;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        button {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-print {
            background: #00ACB1;
            color: white;
        }

        .btn-back {
            background: #666;
            color: white;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                margin: 0;
                padding: 0;
            }

            .report-table thead {
                display: table-header-group;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php include 'assets/inc/console_suppress.php'; ?>
</head>

<body>
    <div class="no-print">
        <button class="btn-back" onclick="window.history.back()">
            <i class="fa-solid fa-arrow-left"></i> Back
        </button>
        <button class="btn-print" onclick="window.print()">
            <i class="fa-solid fa-print"></i> Print / Save as PDF
        </button>
    </div>

    <table class="report-table">
        <thead>
            <tr>
                <td colspan="<?= $cols ?>" style="border: none !important;">
                    <div class="report-header">
                        <img src="assets/img/ocnhs_logo.png" alt="Logo" onerror="this.src='assets/img/logo.png'">
                        <h2>Olongapo City National High School</h2>
                        <p>Medical Clinic Records Management System</p>
                        <div style="margin-top: 10px; font-weight: bold; color: #333; font-size: 14px;">
                            <?= $title ?>
                        </div>
                        <div style="font-size: 11px; color: #777;">
                            Date Generated: <?= date('M d, Y h:i A') ?>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <?php if ($type == 'drug_log'): ?>
                    <th>Date</th>
                    <th>Patient Name</th>
                    <th>Category</th>
                    <th>Medicine Dispensed</th>
                    <th>Qty</th>
                    <th>Attended By</th>
                <?php elseif ($type == 'inventory'): ?>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Stock</th>
                    <th>Unit</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                <?php else: ?>
                    <th>Name</th>
                    <?php if ($type == 'student'): ?>
                        <th>LRN</th>
                        <th>Curriculum</th>
                        <th>Age</th>
                    <?php elseif ($type == 'others'): ?>
                        <th>Age</th>
                        <th>SDO</th>
                    <?php else: ?>
                        <th>Position</th>
                        <th>Designation</th>
                        <th>Age</th>
                    <?php endif; ?>
                    <th>Gender</th>
                    <th>Birth Date</th>
                    <?php if ($type == 'student'): ?>
                        <th>Contact</th>
                    <?php elseif ($type == 'others'): ?>
                        <th>Address</th>
                    <?php endif; ?>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($type == 'drug_log'): ?>
                <?php foreach ($drugLogs as $row): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($row['date'])) ?></td>
                        <td><strong><?= htmlspecialchars($row['name']) ?></strong><br><small><?= $row['id'] ?></small></td>
                        <td><?= $row['type'] ?></td>
                        <td style="color: #00ACB1; font-weight: bold;"><?= htmlspecialchars($row['med']) ?></td>
                        <td><?= $row['qty'] ?></td>
                        <td><?= htmlspecialchars($row['att']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php elseif ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <?php if ($type == 'inventory'): ?>
                            <td style="font-weight: bold;"><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td><?= htmlspecialchars($row['quantity']) ?></td>
                            <td><?= htmlspecialchars($row['unit']) ?></td>
                            <td><?= $row['expiry_date'] ? date('M d, Y', strtotime($row['expiry_date'])) : '-' ?></td>
                            <td>
                                <?php
                                if ($row['quantity'] == 0)
                                    echo "Out of Stock";
                                elseif ($row['expiry_date'] && new DateTime($row['expiry_date']) < new DateTime('today'))
                                    echo "Expired";
                                elseif ($row['quantity'] <= ($row['reorder_level'] ?? 10))
                                    echo "Low Stock";
                                else
                                    echo "Available";
                                ?>
                            </td>
                        <?php else: ?>
                            <td style="font-weight: bold;"><?= htmlspecialchars($row['name']) ?></td>
                            <?php if ($type == 'student'): ?>
                                <td><?= htmlspecialchars($row['lrn']) ?></td>
                                <td><?= htmlspecialchars($row['curriculum']) ?></td>
                                <td>
                                    <?php
                                    if ($row['birth_date']) {
                                        $birthDate = new DateTime($row['birth_date']);
                                        echo $birthDate->diff(new DateTime('today'))->y;
                                    } else
                                        echo '-';
                                    ?>
                                </td>
                            <?php elseif ($type == 'others'): ?>
                                <td><?php if ($row['birth_date'])
                                    echo (new DateTime($row['birth_date']))->diff(new DateTime('today'))->y;
                                else
                                    echo $row['age'] ?: '-'; ?>
                                </td>
                                <td><?= htmlspecialchars($row['sdo']) ?></td>
                            <?php else: ?>
                                <td><?= htmlspecialchars($row['position']) ?></td>
                                <td><?= htmlspecialchars($row['designation']) ?></td>
                                <td><?php if ($row['birth_date'])
                                    echo (new DateTime($row['birth_date']))->diff(new DateTime('today'))->y;
                                else
                                    echo '-'; ?>
                                </td>
                            <?php endif; ?>
                            <td><?= htmlspecialchars($row['gender']) ?></td>
                            <td><?= $row['birth_date'] ? date('m/d/Y', strtotime($row['birth_date'])) : '-' ?></td>
                            <?php if ($type == 'student'): ?>
                                <td><?= htmlspecialchars($row['contact']) ?></td>
                            <?php elseif ($type == 'others'): ?>
                                <td><?= htmlspecialchars($row['address']) ?></td>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?= $cols ?>" style="text-align: center; padding: 50px;">No records found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>

</html>