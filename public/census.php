<?php
require_once "../config/db.php";
requireLogin();

// DEFAULT DATE: Current Month/Year with Validation
$selectedMonth = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('m');
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

// Validate Range
if ($selectedMonth < 1 || $selectedMonth > 12)
    $selectedMonth = (int) date('m');
if ($selectedYear < 2000 || $selectedYear > 2100)
    $selectedYear = (int) date('Y');

// Generate Dates for the selected month
$numDays = date('t', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
$dates = [];
for ($d = 1; $d <= $numDays; $d++) {
    $dates[] = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $d);
}
// Genders: Male, Female
$censusData = [];
// Initialize with zeros for all dates
$detailData = [];
foreach ($dates as $date) {
    $censusData[$date] = [
        '7' => ['Male' => 0, 'Female' => 0],
        '8' => ['Male' => 0, 'Female' => 0],
        '9' => ['Male' => 0, 'Female' => 0],
        '10' => ['Male' => 0, 'Female' => 0],
        '11' => ['Male' => 0, 'Female' => 0],
        '12' => ['Male' => 0, 'Female' => 0],
        'Staff' => ['Male' => 0, 'Female' => 0], // Added Staff
        'Others' => ['Male' => 0, 'Female' => 0],
        'Total' => 0
    ];
    // Initialize detail structure
    $detailData[$date] = [
        '7' => ['Male' => [], 'Female' => []],
        '8' => ['Male' => [], 'Female' => []],
        '9' => ['Male' => [], 'Female' => []],
        '10' => ['Male' => [], 'Female' => []],
        '11' => ['Male' => [], 'Female' => []],
        '12' => ['Male' => [], 'Female' => []],
        'Staff' => ['Male' => [], 'Female' => []], // Added Staff
        'Others' => ['Male' => [], 'Female' => []]
    ];
}

// Initialize Intervention Summaries
$interventionSummary = [];
$interventionDetailData = [];


// FETCH ALL PERSONS (Students + Employees)
$persons = [];
$res_stud = $conn->query("SELECT id, name, gender, treatment_logs_json, 'student' as type FROM students WHERE is_archived = 0");
while ($r = $res_stud->fetch_assoc())
    $persons[] = $r;

$res_emp = $conn->query("SELECT id, name, gender, treatment_logs_json, 'employee' as type FROM employees WHERE is_archived = 0");
while ($r = $res_emp->fetch_assoc())
    $persons[] = $r;

$res_others = $conn->query("SELECT id, name, gender, treatment_logs_json, 'others' as type FROM others WHERE is_archived = 0");
while ($r = $res_others->fetch_assoc())
    $persons[] = $r;

foreach ($persons as $person) {
    $logs = json_decode($person['treatment_logs_json'] ?? '[]', true);
    $gender = ucfirst(strtolower($person['gender']));
    $type = $person['type'];

    if (!is_array($logs))
        continue;

    foreach ($logs as $log) {
        if (empty($log['date']))
            continue;

        // Parse log date
        $logDate = date('Y-m-d', strtotime($log['date']));

        // Check if date is in selected month
        if (strpos($logDate, "$selectedYear-" . sprintf('%02d', $selectedMonth)) === 0) {

            // Get Grade
            // Get Grade / Category
            if ($type == 'employee') {
                $grade = 'Staff';
            } elseif ($type == 'others') {
                $grade = 'Others';
            } else {
                $grade = isset($log['grade']) ? $log['grade'] : 'Others';
                // Normalize Grade
                if (!in_array($grade, ['7', '8', '9', '10', '11', '12'])) {
                    $grade = 'Others';
                }
            }


            // Normalize Gender
            $sexKey = ($gender == 'Male' || $gender == 'M') ? 'Male' : 'Female';

            // Increment Census
            if (isset($censusData[$logDate])) {
                $censusData[$logDate][$grade][$sexKey]++;
                $censusData[$logDate]['Total']++;

                // Add to Details
                $assessment = trim($log['assessment'] ?? '');
                if (empty($assessment)) {
                    $assessment = $log['complaint'] ?? ($log['subjective_complaint'] ?? '');
                    if (!empty($log['objective_complaint'])) {
                        $assessment .= ($assessment ? ' / ' : '') . $log['objective_complaint'];
                    }
                    if (!$assessment)
                        $assessment = $log['chief_complaint'] ?? 'N/A';
                }

                $complaint = $assessment;
                $detailData[$logDate][$grade][$sexKey][] = [
                    'name' => $person['name'],
                    'complaint' => $complaint // We keep the key as 'complaint' for the view but the content is the assessment
                ];

            }

            // Aggregate Interventions
            $interventions = [
                'int_pharm' => 'Pharmaceutical Drugs',
                'int_trad' => 'Traditional/Alt Healthcare',
                'int_mo' => 'Referred to Medical Officer',
                'int_dentist' => 'Referred to Dentist',
                'int_agency' => 'Referred to Other Agencies',
                'int_rhu' => 'Referred to RHU',
                'int_hosp' => 'Referred to Hospital',
                'int_teacher' => 'Teachers for Follow-up'
            ];

            foreach ($interventions as $key => $label) {
                if (isset($log[$key]) && $log[$key] == '1') {
                    if (!isset($interventionSummary[$label])) {
                        $interventionSummary[$label] = [
                            '7' => ['Male' => 0, 'Female' => 0],
                            '8' => ['Male' => 0, 'Female' => 0],
                            '9' => ['Male' => 0, 'Female' => 0],
                            '10' => ['Male' => 0, 'Female' => 0],
                            '11' => ['Male' => 0, 'Female' => 0],
                            '12' => ['Male' => 0, 'Female' => 0],
                            'Staff' => ['Male' => 0, 'Female' => 0],
                            'Others' => ['Male' => 0, 'Female' => 0],
                            'Total' => 0
                        ];
                        // Init detail structure for this label if needed
                        $interventionDetailData[$label] = [
                            '7' => ['Male' => [], 'Female' => []],
                            '8' => ['Male' => [], 'Female' => []],
                            '9' => ['Male' => [], 'Female' => []],
                            '10' => ['Male' => [], 'Female' => []],
                            '11' => ['Male' => [], 'Female' => []],
                            '12' => ['Male' => [], 'Female' => []],
                            'Staff' => ['Male' => [], 'Female' => []],
                            'Others' => ['Male' => [], 'Female' => []]
                        ];
                    }

                    $interventionSummary[$label][$grade][$sexKey]++;
                    $interventionSummary[$label]['Total']++;

                    // Add Intervention Detail
                    $comp = $log['complaint'] ?? ($log['subjective_complaint'] ?? '');
                    if (!empty($log['objective_complaint'])) {
                        $comp .= ($comp ? ' / ' : '') . $log['objective_complaint'];
                    }
                    if (!$comp)
                        $comp = $log['chief_complaint'] ?? 'N/A';

                    $complaint = $comp;
                    $interventionDetailData[$label][$grade][$sexKey][] = [
                        'name' => $person['name'],
                        'complaint' => $complaint . " (" . date('M d', strtotime($logDate)) . ")"
                    ];
                }
            }
        }
    }
}

include "index_layout.php";
?>

<style>
    :root {
        /* Grade Backgrounds - Light Mode */
        --bg-7: #dcedc8;
        --bg-8: #ffcdd2;
        --bg-9: #bbdefb;
        --bg-10: #fff9c4;
        --bg-11: #e1bee7;
        --bg-12: #f8bbd0;
        --bg-staff: #e0e0e0;
        --bg-others: #f5f5f5;

        /* General Colors */
        --text-primary: #333;
        --text-secondary: #555;
        --bg-container: #fff;
        --bg-header-row: #00ACB1;
        --text-header-row: #fff;
        --border-color: #ccc;

        /* Special Rows */
        --bg-summary-header: #333;
        --text-summary-header: #fff;
        --bg-total-row: #e0e0e0;
        --bg-subtotal: #eee;

        /* Filters */
        --bg-input: #fff;
        --text-input: #333;
        --border-input: #ccc;
    }

    /* FORCE LIGHT MODE ONLY - Ignore global Dark Mode */
    body.dark-mode {
        background: #f5f7fa !important;
    }

    body.dark-mode .census-container,
    body.dark-mode .census-table,
    body.dark-mode .filters,
    body.dark-mode .report-nav {
        background: #ffffff !important;
        color: #333 !important;
    }

    body.dark-mode :root,
    body.dark-mode {
        --bg-7: #dcedc8 !important;
        --bg-8: #ffcdd2 !important;
        --bg-9: #bbdefb !important;
        --bg-10: #fff9c4 !important;
        --bg-11: #e1bee7 !important;
        --bg-12: #f8bbd0 !important;
        --bg-staff: #e0e0e0 !important;
        --bg-others: #f5f5f5 !important;
        --text-primary: #333 !important;
        --bg-container: #ffffff !important;
        --bg-header-row: #00ACB1 !important;
        --text-header-row: #ffffff !important;
        --border-color: #ccc !important;
        --bg-total-row: #e0e0e0 !important;
        --bg-subtotal: #eee !important;
    }

    body.dark-mode #censusModal .modal-content {
        background: #ffffff !important;
        color: #333 !important;
    }

    body.dark-mode .census-table td,
    body.dark-mode .census-table th {
        border-color: #ddd !important;
        color: #333 !important;
    }

    body.dark-mode .census-table thead th {
        background: linear-gradient(to bottom, #00ACB1, #9FF0D7) !important;
        background-color: #00ACB1 !important;
        color: #ffffff !important;
    }

    body.dark-mode #censusModal table {
        background: #1e1e1e !important;
    }

    body.dark-mode #censusModal td {
        border-bottom-color: #333 !important;
        color: #e0e0e0 !important;
    }

    body.dark-mode #censusModal .modal-header h3 {
        color: #4dd0e1 !important;
    }

    .census-container {
        padding: 20px;
        background: var(--bg-container);
        color: var(--text-primary);
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        max-height: 75vh;
        overflow-y: auto;
        overflow-x: hidden;
        width: 100%;
        box-sizing: border-box;
    }

    .census-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        text-align: center;
        color: var(--text-primary);
    }

    .census-table th,
    .census-table td {
        border: 1px solid var(--border-color);
        padding: 6px;
    }

    .census-table thead th {
        background: var(--bg-header-row);
        color: var(--text-header-row);
        white-space: nowrap;
        position: sticky;
        box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.4);
    }

    .census-table thead tr:nth-child(1) th {
        top: 0;
        z-index: 20;
        height: 40px;
    }

    .census-table thead tr:nth-child(2) th {
        top: 40px;
        z-index: 10;
        height: 30px;
    }

    .census-table thead tr:nth-child(1) th[rowspan="2"] {
        z-index: 25;
    }

    .filters {
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
        background: transparent;
        padding: 10px 0;
        color: var(--text-primary);
    }

    .filters select {
        padding: 5px;
        background: var(--bg-input);
        color: var(--text-input);
        border: 1px solid var(--border-input);
        border-radius: 4px;
    }

    .btn-submit {
        background: #00ACB1;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
    }

    /* Combined Print Styles for Light & Dark Mode */
    @media print {
        @page {
            size: portrait;
            margin: 5mm 3mm;
            /* Narrow side margins for fit */
        }

        /* Standardize background for both themes */
        body {
            background: white !important;
            color: #000 !important;
        }

        /* Dark mode reset (redundant but safe) */
        body.dark-mode {
            background: white !important;
            color: #000 !important;
        }

        /* Unified Color Variables for Print */
        :root,
        body,
        body.dark-mode {
            --bg-7: #dcedc8 !important;
            --bg-8: #ffcdd2 !important;
            --bg-9: #bbdefb !important;
            --bg-10: #fff9c4 !important;
            --bg-11: #e1bee7 !important;
            --bg-12: #f8bbd0 !important;
            --bg-staff: #e0e0e0 !important;
            --bg-others: #f5f5f5 !important;
            --bg-container: white !important;
            --text-primary: #000 !important;
            --border-color: #333 !important;
            --bg-header-row: #00ACB1 !important;
            --text-header-row: white !important;
            --bg-total-row: #eee !important;
            --bg-subtotal: #f9f9f9 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .filters,
        .report-nav,
        .no-print,
        .sidebar,
        .sidebar-wrapper,
        nav {
            display: none !important;
        }

        .census-container {
            box-shadow: none !important;
            overflow: visible !important;
            max-height: none !important;
            background: white !important;
            color: #000 !important;
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }

        body,
        body.dark-mode {
            background: white !important;
            color: black !important;
            --bg-7: #dcedc8 !important;
            --bg-8: #ffcdd2 !important;
            --bg-9: #bbdefb !important;
            --bg-10: #fff9c4 !important;
            --bg-11: #e1bee7 !important;
            --bg-12: #f8bbd0 !important;
            --bg-staff: #e0e0e0 !important;
            --bg-others: #f5f5f5 !important;
            --bg-total-row: #e0e0e0 !important;
            --bg-subtotal: #eee !important;
        }

        .census-table {
            font-size: 7.5pt !important;
            width: 100% !important;
            border-collapse: collapse !important;
            border: 1.5px solid #000 !important;
            table-layout: fixed !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .census-table thead {
            display: table-header-group !important;
        }

        .census-table th,
        .census-table td {
            padding: 4px 1px !important;
            border: 1px solid #333 !important;
            color: #000 !important;
            text-align: center !important;
            vertical-align: middle !important;
            word-wrap: break-word !important;
        }

        .census-table th:first-child,
        .census-table td:first-child {
            width: 28% !important;
            text-align: center !important;
        }

        .census-table th:last-child,
        .census-table td:last-child {
            width: 8% !important;
        }

        .census-table td:not(:first-child):not(:last-child),
        .census-table th:not(:first-child):not(:last-child):not([colspan]) {
            width: 4% !important;
        }

        .census-table thead th {
            background: linear-gradient(to bottom, #00ACB1, #9FF0D7) !important;
            background-color: #00ACB1 !important;
            color: white !important;
            font-weight: bold !important;
            font-size: 8pt !important;
        }

        .census-table thead th[colspan] {
            width: 8% !important;
            text-align: center !important;
            font-size: 7pt !important;
        }

        .report-header {
            display: block !important;
            text-align: center !important;
            margin-bottom: 10px !important;
        }

        .report-header h1 {
            color: #00ACB1 !important;
            font-size: 14pt !important;
            margin: 0 !important;
        }

        /* Reset for the summary row labels at the bottom */
        .census-table tr:last-child td:first-child {
            font-size: 6pt !important;
            line-height: 1 !important;
            background-color: #00ACB1 !important;
            /* User requested white text, needs dark bg */
            color: white !important;
            white-space: nowrap !important;
            /* One line only */
        }
    }

    .report-nav {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }

    .report-nav a {
        text-decoration: none;
        padding: 10px 20px;
        border-radius: 5px;
        font-weight: bold;
        background: var(--bg-total-row);
        color: var(--text-secondary);
        transition: all 0.2s;
    }

    .report-nav a.active {
        background: #00ACB1;
        color: white;
    }

    .report-nav a:hover:not(.active) {
        background: var(--border-color);
    }

    .clickable-cell {
        cursor: pointer;
        transition: filter 0.2s;
    }

    .clickable-cell:hover {
        filter: brightness(0.9);
        text-decoration: underline;
        font-weight: bold;
    }

    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 2000;
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background: var(--bg-container);
        color: var(--text-primary);
        padding: 20px;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 10px;
    }

    .modal-close {
        cursor: pointer;
        font-size: 20px;
        color: var(--text-secondary);
    }

    .detail-name {
        font-weight: bold;
        color: var(--text-primary);
    }

    .detail-complaint {
        font-size: 0.9em;
        color: var(--text-secondary);
    }

    .modal-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        font-size: 0.9rem;
    }

    .modal-table th,
    .modal-table td {
        border: 1px solid var(--border-color);
        padding: 8px;
        text-align: left;
    }

    .modal-table th {
        background: var(--bg-header-row);
        color: var(--text-header-row);
    }

    @media print {

        .report-nav,
        .modal-overlay {
            display: none !important;
        }

        .clickable-cell {
            cursor: default;
            text-decoration: none !important;
            font-weight: normal;
        }
    }
</style>

<div class="census-container">
    <div class="report-header"
        style="display: flex; flex-wrap: wrap; gap: 15px; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 style="color: #00ACB1; margin: 0; font-size: 1.5rem;">Monthly Census Report -
            <?= date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)) ?>
        </h1>

        <div class="report-nav" style="margin-bottom: 0; flex-wrap: wrap; gap: 8px;">
            <a href="census.php" class="active">Monthly Census</a>
            <a href="daily_ailments.php">Daily Ailments</a>
            <a href="pe_monitoring.php">Physical Exam</a>
        </div>
    </div>

    <form class="filters" method="GET">
        <label><strong>Month:</strong></label>
        <select name="month" onchange="this.form.submit()">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $selectedMonth == $m ? 'selected' : '' ?>>
                    <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                </option>
            <?php endfor; ?>
        </select>

        <label><strong>Year:</strong></label>
        <select name="year" onchange="this.form.submit()">
            <?php for ($y = 2099; $y >= 2000; $y--): ?>
                <option value="<?= $y ?>" <?= $selectedYear == $y ? 'selected' : '' ?>>
                    <?= $y ?>
                </option>
            <?php endfor; ?>
        </select>

        <button type="button" onclick="window.print()" class="btn-submit" style="background: #333;"><i
                class="fa-solid fa-print"></i> Print</button>
    </form>

    <?php
    // Define Grade Background Colors (Pastels based on image)
    // Grades mapped to CSS variables
    $gradeColors = [
        '7' => 'var(--bg-7)',
        '8' => 'var(--bg-8)',
        '9' => 'var(--bg-9)',
        '10' => 'var(--bg-10)',
        '11' => 'var(--bg-11)',
        '12' => 'var(--bg-12)',
        'Staff' => 'var(--bg-staff)',
        'Others' => 'var(--bg-others)'
    ];
    $gradeKeys = ['7', '8', '9', '10', '11', '12', 'Staff', 'Others'];

    ?>
    <div style="overflow-x: auto; width: 100%;">
        <table class="census-table">
            <thead>
                <tr>
                    <th rowspan="2" style="background: var(--bg-header-row); color: var(--text-header-row);">Date</th>
                    <?php foreach ($gradeKeys as $g): ?>
                        <th colspan="2" style="background: <?= $gradeColors[$g] ?>; color: var(--text-primary);">
                            <?= ($g == 'Others' ? 'Others' : ($g == 'Staff' ? 'Staff' : "Grade $g")) ?>
                        </th>

                    <?php endforeach; ?>
                    <th rowspan="2" style="background: var(--bg-header-row); color: var(--text-header-row);">TOTAL</th>
                </tr>
                <tr>
                    <?php foreach ($gradeKeys as $g): ?>
                        <th style="background: <?= $gradeColors[$g] ?>; color: var(--text-primary);">M</th>
                        <th style="background: <?= $gradeColors[$g] ?>; color: var(--text-primary);">F</th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($censusData as $date => $counts): ?>
                    <?php $dayDisplay = date('F d', strtotime($date)); ?>
                    <tr>
                        <td style="white-space: nowrap;"><?= $dayDisplay ?></td>
                        <?php foreach ($gradeKeys as $g):
                            // Male Data
                            $mCount = $counts[$g]['Male'];
                            $mAttr = $mCount > 0 ? 'class="clickable-cell" onclick="showDetails(this)"' : '';
                            $mData = $mCount > 0 ? "data-details='" . htmlspecialchars(json_encode($detailData[$date][$g]['Male']), ENT_QUOTES, 'UTF-8') . "'" : '';

                            // Female Data
                            $fCount = $counts[$g]['Female'];
                            $fAttr = $fCount > 0 ? 'class="clickable-cell" onclick="showDetails(this)"' : '';
                            $fData = $fCount > 0 ? "data-details='" . htmlspecialchars(json_encode($detailData[$date][$g]['Female']), ENT_QUOTES, 'UTF-8') . "'" : '';
                            ?>
                            <td <?= $mAttr ?>         <?= $mData ?> style="background: <?= $gradeColors[$g] ?>"><?= $mCount ?: '0' ?></td>
                            <td <?= $fAttr ?>         <?= $fData ?> style="background: <?= $gradeColors[$g] ?>"><?= $fCount ?: '0' ?></td>
                        <?php endforeach; ?>
                        <td><strong><?= $counts['Total'] ?: '0' ?></strong></td>
                    </tr>
                <?php endforeach; ?>

                <!-- Census Month Total -->
                <tr style="background: var(--bg-total-row); color: var(--text-primary); font-weight: bold;">
                    <td
                        style="white-space: nowrap; background: #00ACB1 !important; color: white !important; text-align: center; font-size: 7.5pt !important;">
                        MONTH TOTAL</td>
                    <?php
                    // Calc vertical totals
                    $grandTotal = 0;
                    foreach ($gradeKeys as $g) {
                        $mTotal = 0;
                        $fTotal = 0;
                        foreach ($censusData as $d => $c) {
                            $mTotal += ($c[$g]['Male'] ?? 0);
                            $fTotal += ($c[$g]['Female'] ?? 0);
                        }
                        echo "<td style='background: {$gradeColors[$g]}'>$mTotal</td><td style='background: {$gradeColors[$g]}'>$fTotal</td>";
                        $grandTotal += ($mTotal + $fTotal);
                    }
                    ?>
                    <td><?= $grandTotal ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Force Interventions to 2nd Page -->
    <div style="page-break-before: always; break-before: page; margin-top: 30px;">
        <div style="overflow-x: auto; width: 100%;">
            <table class="census-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="background: var(--bg-header-row); color: var(--text-header-row);">
                            INTERVENTIONS SUMMARY</th>
                        <?php foreach ($gradeKeys as $g): ?>
                            <th colspan="2" style="background: <?= $gradeColors[$g] ?>; color: var(--text-primary);">
                                <?= ($g == 'Others' ? 'Others' : ($g == 'Staff' ? 'Staff' : "Grade $g")) ?>
                            </th>
                        <?php endforeach; ?>
                        <th rowspan="2" style="background: var(--bg-header-row); color: var(--text-header-row);">TOTAL
                        </th>
                    </tr>
                    <tr>
                        <?php foreach ($gradeKeys as $g): ?>
                            <th style="background: <?= $gradeColors[$g] ?>; color: var(--text-primary);">M</th>
                            <th style="background: <?= $gradeColors[$g] ?>; color: var(--text-primary);">F</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $interventionOrder = [
                        'Pharmaceutical Drugs',
                        'Traditional/Alt Healthcare',
                        'Referred to Medical Officer',
                        'Referred to Dentist',
                        'Referred to Other Agencies',
                        'Referred to RHU',
                        'Referred to Hospital',
                        'Teachers for Follow-up'
                    ];

                    foreach ($interventionOrder as $label):
                        $data = isset($interventionSummary[$label]) ? $interventionSummary[$label] : [
                            '7' => ['Male' => 0, 'Female' => 0],
                            '8' => ['Male' => 0, 'Female' => 0],
                            '9' => ['Male' => 0, 'Female' => 0],
                            '10' => ['Male' => 0, 'Female' => 0],
                            '11' => ['Male' => 0, 'Female' => 0],
                            '12' => ['Male' => 0, 'Female' => 0],
                            'Staff' => ['Male' => 0, 'Female' => 0],
                            'Others' => ['Male' => 0, 'Female' => 0],
                            'Total' => 0
                        ];
                        ?>
                        <tr>
                            <td style="text-align: center; font-weight: bold; font-size: 8pt; white-space: nowrap;">
                                <?= $label ?>
                            </td>
                            <?php foreach ($gradeKeys as $g):
                                $mCount = $data[$g]['Male'];
                                $mAttr = $mCount > 0 ? 'class="clickable-cell" onclick="showDetails(this)"' : '';
                                $mData = ($mCount > 0 && isset($interventionDetailData[$label][$g]['Male'])) ? "data-details='" . htmlspecialchars(json_encode($interventionDetailData[$label][$g]['Male']), ENT_QUOTES, 'UTF-8') . "'" : '';
                                $fCount = $data[$g]['Female'];
                                $fAttr = $fCount > 0 ? 'class="clickable-cell" onclick="showDetails(this)"' : '';
                                $fData = ($fCount > 0 && isset($interventionDetailData[$label][$g]['Female'])) ? "data-details='" . htmlspecialchars(json_encode($interventionDetailData[$label][$g]['Female']), ENT_QUOTES, 'UTF-8') . "'" : '';
                                ?>
                                <td <?= $mAttr ?>         <?= $mData ?> style="background: <?= $gradeColors[$g] ?>"><?= $mCount ?: '0' ?>
                                </td>
                                <td <?= $fAttr ?>         <?= $fData ?> style="background: <?= $gradeColors[$g] ?>"><?= $fCount ?: '0' ?>
                                </td>
                            <?php endforeach; ?>
                            <td style="background: var(--bg-subtotal); color: var(--text-primary); font-weight: bold;">
                                <?= $data['Total'] ?: '0' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Grand Total Row for Interventions (Moved to Bottom) -->
                    <tr
                        style="background: var(--bg-total-row); color: var(--text-primary); font-weight: bold; border-top: 2px solid #000;">
                        <td
                            style="text-align: center; white-space: nowrap; background: #00ACB1 !important; color: white !important; font-size: 7.5pt !important;">
                            INTERVENTIONS SUMMARY (MONTHLY TOTAL)</td>
                        <?php
                        $intGrandTotal = 0;
                        $intGradeTotals = [];
                        foreach ($gradeKeys as $g)
                            $intGradeTotals[$g] = ['Male' => 0, 'Female' => 0];
                        foreach ($interventionSummary as $label => $data) {
                            foreach ($intGradeTotals as $g => &$sexCounts) {
                                $sexCounts['Male'] += $data[$g]['Male'] ?? 0;
                                $sexCounts['Female'] += $data[$g]['Female'] ?? 0;
                            }
                            $intGrandTotal += $data['Total'] ?? 0;
                        }
                        unset($sexCounts);
                        foreach ($gradeKeys as $g) {
                            echo "<td style='background: {$gradeColors[$g]}'>" . ($intGradeTotals[$g]['Male'] ?: '0') . "</td>";
                            echo "<td style='background: {$gradeColors[$g]}'>" . ($intGradeTotals[$g]['Female'] ?: '0') . "</td>";
                        }
                        ?>
                        <td><?= $intGrandTotal ?: '0' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Structure -->
    <div id="censusModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 600px; width: 95%;">
            <div class="modal-header">
                <h3 style="margin:0; color: #00ACB1;">List of Patients</h3>
                <div>
                    <button onclick="printModal()"
                        style="padding: 5px 12px; background: #00ACB1; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px; font-weight: bold;"><i
                            class="fa-solid fa-print"></i> Print</button>
                    <span class="modal-close" onclick="closeModal()">&times;</span>
                </div>
            </div>
            <div id="modalBody" style="max-height: 400px; overflow-y:auto; padding-right: 5px;">
                <!-- List will go here -->
            </div>
        </div>
    </div>

    <script>
        function showDetails(cell) {
            const dataStr = cell.getAttribute('data-details');
            if (!dataStr) return;

            try {
                const students = JSON.parse(dataStr);
                const modalBody = document.getElementById('modalBody');
                let html = '<table class="modal-table"><thead><tr><th>Name</th><th>Complaint</th></tr></thead><tbody>';

                if (students.length === 0) {
                    html += '<tr><td colspan="2" style="text-align:center;">No records found.</td></tr>';
                } else {
                    students.forEach(s => {
                        html += `<tr>
                                <td style="text-transform: uppercase;"><strong>${s.name}</strong></td>
                                <td><span style="color:var(--text-secondary);">${s.complaint}</span></td>
                            </tr>`;
                    });
                }
                html += '</tbody></table>';
                modalBody.innerHTML = html;

                document.getElementById('censusModal').style.display = 'flex';
            } catch (e) {
                console.error("Error parsing details", e);
            }
        }

        function closeModal() {
            document.getElementById('censusModal').style.display = 'none';
        }

        // Close on outside click
        window.onclick = function (event) {
            const modal = document.getElementById('censusModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        function printModal() {
            var printContents = document.getElementById('modalBody').innerHTML;
            var printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Patient List</title>');
            printWindow.document.write('<style>');
            printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; }');
            printWindow.document.write('h2 { color: #00ACB1; text-align: center; }');
            printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
            printWindow.document.write('th, td { border: 1px solid #000; padding: 8px; text-align: left; }');
            printWindow.document.write('th { background-color: #f2f2f2; }');
            printWindow.document.write('</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write('<h2>Patient List (Monthly Census)</h2>');
            printWindow.document.write(printContents);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            setTimeout(function () {
                printWindow.print();
                printWindow.close();
            }, 250);
        }
    </script>

    <script>
        // Add active class to nav if needed
    </script>