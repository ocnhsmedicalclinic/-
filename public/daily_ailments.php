<?php
require_once "../config/db.php";
requireLogin();

// Input Validation & Security (Prevent Injection)
$filterType = isset($_GET['filter']) ? $_GET['filter'] : 'week';
$customFrom = isset($_GET['from']) ? $_GET['from'] : '';
$customTo = isset($_GET['to']) ? $_GET['to'] : '';

// 1. Whitelist Validation for Filter Type
$allowedFilters = ['today', 'week', 'month', 'custom'];
if (!in_array($filterType, $allowedFilters)) {
    $filterType = 'week'; // Default to safe value
}

// 2. Validate Date Format (YYYY-MM-DD)
function validateDate($date, $format = 'Y-m-d')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

if ($filterType === 'custom') {
    // If dates are invalid, fallback to empty or today to prevent processing bad data
    if (!validateDate($customFrom))
        $customFrom = '';
    if (!validateDate($customTo))
        $customTo = '';
}

// 3. Escape Inputs (Extra layer of security, though strictly not used in current static SQL)
$customFrom = $conn->real_escape_string($customFrom);
$customTo = $conn->real_escape_string($customTo);

// Calculate date range
$today = date('Y-m-d');
switch ($filterType) {
    case 'today':
        $dateFrom = $today;
        $dateTo = $today;
        break;
    case 'week':
        $dateFrom = date('Y-m-d', strtotime('-7 days'));
        $dateTo = $today;
        break;
    case 'month':
        $dateFrom = date('Y-m-01');
        $dateTo = $today;
        break;
    case 'custom':
        // Ensure we have valid dates before using them
        if (empty($customFrom) || empty($customTo)) {
            // Fallback if custom dates are missing/invalid
            $dateFrom = date('Y-m-d', strtotime('-7 days'));
            $dateTo = $today;
        } else {
            $dateFrom = $customFrom;
            $dateTo = $customTo;
        }
        break;
    default:
        $dateFrom = date('Y-m-d', strtotime('-7 days'));
        $dateTo = $today;
}

// Initialize Census-style Data Structures
$censusData = [];
$detailData = [];
$uniqueComplaints = []; // For pie chart

// Define Grades and Colors (Same as Census)
$gradeKeys = ['7', '8', '9', '10', '11', '12', 'Staff', 'Others'];
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

// Initialize Dates in Range
$period = new DatePeriod(
    new DateTime($dateFrom),
    new DateInterval('P1D'),
    (new DateTime($dateTo))->modify('+1 day')
);

foreach ($period as $dt) {
    $dStr = $dt->format('Y-m-d');
    // Structure: [Date][Complaint][Grade][Sex]
    $censusData[$dStr] = []; // Will hold complaints as keys
}

$ailmentTotals = [];
$totalCases = 0;

$persons = [];
$sql_stud = "SELECT name, gender, treatment_logs_json, 'student' as type FROM students WHERE is_archived = 0 AND treatment_logs_json IS NOT NULL AND treatment_logs_json != '[]'";
$res_stud = $conn->query($sql_stud);
while ($row = $res_stud->fetch_assoc())
    $persons[] = $row;

$sql_emp = "SELECT name, gender, treatment_logs_json, 'employee' as type FROM employees WHERE is_archived = 0 AND treatment_logs_json IS NOT NULL AND treatment_logs_json != '[]'";
$res_emp = $conn->query($sql_emp);
while ($row = $res_emp->fetch_assoc())
    $persons[] = $row;

$sql_others = "SELECT name, gender, treatment_logs_json, 'others' as type FROM others WHERE is_archived = 0 AND treatment_logs_json IS NOT NULL AND treatment_logs_json != '[]'";
$res_others = $conn->query($sql_others);
while ($row = $res_others->fetch_assoc())
    $persons[] = $row;

foreach ($persons as $row) {
    $logs = json_decode($row['treatment_logs_json'], true);
    if (!is_array($logs))
        continue;

    $gender = ucfirst(strtolower($row['gender']));
    $sexKey = ($gender == 'Male' || $gender == 'M') ? 'Male' : 'Female';
    $type = $row['type'];

    foreach ($logs as $log) {
        $comp = trim($log['assessment'] ?? '');

        if (empty($log['date']) || empty($comp))
            continue;

        $logDate = date('Y-m-d', strtotime($log['date']));

        if ($logDate >= $dateFrom && $logDate <= $dateTo) {
            // Determine Grade
            if ($type == 'employee') {
                $grade = 'Staff';
            } elseif ($type == 'others') {
                $grade = 'Others';
            } else {
                $grade = isset($log['grade']) ? $log['grade'] : 'Others';
                if (!in_array($grade, $gradeKeys))
                    $grade = 'Others';
            }

            $complaint = ucfirst(strtolower(trim($comp)));

            // Populate Matrix
            if (!isset($censusData[$logDate][$complaint])) {
                $censusData[$logDate][$complaint] = [];
                foreach ($gradeKeys as $g) {
                    $censusData[$logDate][$complaint][$g] = ['Male' => 0, 'Female' => 0];
                }
                $censusData[$logDate][$complaint]['Total'] = 0;
            }

            $censusData[$logDate][$complaint][$grade][$sexKey]++;
            $censusData[$logDate][$complaint]['Total']++;

            // Details
            if (!isset($detailData[$logDate][$complaint])) {
                $detailData[$logDate][$complaint] = [];
                foreach ($gradeKeys as $g) {
                    $detailData[$logDate][$complaint][$g] = ['Male' => [], 'Female' => []];
                }
            }
            $detailData[$logDate][$complaint][$grade][$sexKey][] = [
                'name' => $row['name'],
                'complaint' => $complaint
            ];

            // Aggregate for Charts
            $ailmentTotals[$complaint] = ($ailmentTotals[$complaint] ?? 0) + 1;
            $totalCases++;
        }
    }
}

arsort($ailmentTotals);

include "index_layout.php";
?>

<style>
    :root {
        /* Grade Backgrounds */
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
        /* Main container bg */
        --bg-header-row: #00ACB1;
        --text-header-row: #fff;
        --border-color: #ccc;

        /* Special Rows */
        --bg-total-row: #e0e0e0;
        --bg-subtotal: #eee;

        /* Filters */
        --bg-input: #fff;
        --text-input: #333;
        --border-input: #ddd;
        --bg-btn: #333;
        --text-btn: #fff;

        /* Ailment specific */
        --bg-rank: #f8f9fa;
        --bg-rank-hover: #e8f8f8;
        --bg-tag: #e8f8f8;
        --text-tag: #333;

        /* AI Widget Light */
        --ai-widget-bg: linear-gradient(135deg, #e0f7fa 0%, #ffffff 100%);
        --ai-widget-border: #00ACB1;
        --ai-widget-title: #00838f;
        --ai-widget-text: #006064;
    }

    /* Dark Mode Overrides */
    /* FORCE LIGHT MODE ONLY - Ignore global Dark Mode */
    body.dark-mode {
        background: #f5f7fa !important;
    }

    body.dark-mode .ailments-container,
    body.dark-mode .date-section,
    body.dark-mode .summary-box,
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
        --bg-rank: #f8f9fa !important;
        --bg-rank-hover: #e8f8f8 !important;
        --bg-tag: #e8f8f8 !important;
        --text-tag: #333 !important;
        --ai-widget-bg: linear-gradient(135deg, #e0f7fa 0%, #ffffff 100%) !important;
        --ai-widget-border: #00ACB1 !important;
        --ai-widget-title: #00838f !important;
        --ai-widget-text: #006064 !important;
    }

    /* Force Light Mode Badges in AI Widget */
    body.dark-mode .ai-badge {
        background: #ccc !important;
        color: #555 !important;
    }

    body.dark-mode .ai-badge-high {
        background: #fff3f3 !important;
        color: #c62828 !important;
    }

    body.dark-mode .ai-badge-mid {
        background: #fff9c4 !important;
        color: #856404 !important;
    }

    body.dark-mode .ai-badge-low {
        background: #e8f5e9 !important;
        color: #2e7d32 !important;
    }

    body.dark-mode .ai-badge-mod {
        background: #fff3e0 !important;
        color: #ef6c00 !important;
    }

    /* Modal Dark Mode for Daily Ailments */
    body.dark-mode #detailModal .modal-content {
        background: #1e1e1e !important;
        color: #e0e0e0 !important;
    }

    body.dark-mode #detailModal table {
        background: #1e1e1e !important;
    }

    body.dark-mode #detailModal td {
        border-bottom-color: #333 !important;
        color: #e0e0e0 !important;
    }

    body.dark-mode #detailModal .modal-header h3 {
        color: #4dd0e1 !important;
    }

    .ai-widget {
        background: var(--ai-widget-bg);
        border-left: 5px solid var(--ai-widget-border);
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: none;
    }

    .ai-widget-title {
        margin: 0;
        color: var(--ai-widget-title);
        font-size: 1.1em;
    }

    .ai-widget-text {
        margin: 5px 0 0;
        color: var(--ai-widget-text);
        font-size: 0.9em;
    }

    .ai-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 0.85em;
        background: #ccc;
        color: #555;
    }

    /* Dark Mode Badges */
    body.dark-mode .ai-badge {
        background: #444;
        color: #ccc;
    }

    body.dark-mode .ai-badge-high {
        background: #4a2e2e;
        color: #f5a5a5;
    }

    body.dark-mode .ai-badge-mid {
        background: #4a482e;
        color: #fff9c4;
    }

    body.dark-mode .ai-badge-low {
        background: #2e4a2e;
        color: #a3d9a5;
    }

    .ai-badge-mid {
        background: #fff9c4;
        color: #856404;
    }

    .ai-badge-low {
        background: #e8f5e9;
        color: #2e7d32;
    }

    body.dark-mode .ai-badge-high {
        background: #4a1c1c;
        color: #ff8a80;
    }

    .ai-widget-high {
        border-left-color: #c62828 !important;
    }

    body.dark-mode .ai-widget-high {
        border-left-color: #ff8a80 !important;
    }

    .ai-badge-mod {
        background: #fff3e0;
        color: #ef6c00;
    }

    body.dark-mode .ai-badge-mod {
        background: #4a2e1c;
        color: #ffb74d;
    }

    .ai-widget-mod {
        border-left-color: #ef6c00 !important;
    }

    body.dark-mode .ai-widget-mod {
        border-left-color: #ffb74d !important;
    }

    .ai-badge-low {
        background: #e0f2f1;
        color: #00695c;
    }

    body.dark-mode .ai-badge-low {
        background: #1c3633;
        color: #4db6ac;
    }

    .ai-widget-low {
        border-left-color: #00ACB1 !important;
    }

    body.dark-mode .ai-widget-low {
        border-left-color: #008e93 !important;
    }

    .ailments-container {
        padding: 20px;
        /* max-width and margin removed to allow full width */
    }

    /* Navigation Tabs */
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

    .filter-bar {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }

    .filter-bar a {
        padding: 8px 20px;
        border-radius: 20px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.85rem;
        border: 2px solid #00ACB1;
        color: #00ACB1;
        transition: all 0.3s;
    }

    .filter-bar a.active,
    .filter-bar a:hover {
        background: #00ACB1;
        color: white;
    }

    .custom-form {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text-primary);
    }

    .custom-form input[type="date"] {
        padding: 6px 10px;
        border: 2px solid var(--border-input);
        background: var(--bg-input);
        color: var(--text-input);
        border-radius: 6px;
        font-size: 0.85rem;
    }

    .custom-form button {
        padding: 8px 16px;
        background: #00ACB1;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 25px;
    }

    .summary-box {
        background: var(--bg-container);
        color: var(--text-primary);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
    }

    .summary-box h3 {
        margin: 0 0 15px;
        font-size: 1rem;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .summary-box h3 i {
        color: #00ACB1;
    }

    .ailment-rank {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 15px;
        border-radius: 8px;
        margin-bottom: 6px;
        background: var(--bg-rank);
        transition: background 0.2s;
    }

    .ailment-rank:hover {
        background: var(--bg-rank-hover);
    }

    .ailment-rank .rank-num {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #00ACB1;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.8rem;
        margin-right: 12px;
    }

    .ailment-rank .rank-name {
        flex: 1;
        font-weight: 600;
        color: var(--text-primary);
    }

    .ailment-rank .rank-count {
        font-weight: 800;
        color: #00ACB1;
        font-size: 1.1rem;
    }

    .date-section {
        background: var(--bg-container);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
        margin-bottom: 15px;
        color: var(--text-primary);
    }

    .date-section h4 {
        margin: 0 0 12px;
        color: #00ACB1;
        font-size: 0.95rem;
        border-bottom: 2px solid var(--border-input);
        padding-bottom: 8px;
    }

    .complaint-tag {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 14px;
        background: var(--bg-tag);
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-tag);
        margin: 3px 5px 3px 0;
    }

    .complaint-tag .count-badge {
        background: #00ACB1;
        color: white;
        border-radius: 50%;
        width: 22px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 800;
    }

    .alert-box {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
        border-left: 5px solid #f39c12;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        color: #856404;
        /* Keep reliable contrast for alert */
    }

    .alert-box i {
        font-size: 1.5rem;
        color: #f39c12;
    }

    @media (max-width: 768px) {
        .summary-grid {
            grid-template-columns: 1fr;
        }
    }

    .daily-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        background: var(--bg-container);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        color: var(--text-primary);
    }

    .daily-table th,
    .daily-table td {
        border: 1px solid var(--border-color);
        padding: 12px;
        text-align: center;
    }

    .daily-table th {
        background: var(--bg-header-row);
        color: var(--text-header-row);
    }

    .daily-table tr:hover {
        background: var(--bg-subtotal);
        /* Subtle hover */
    }

    /* Print Styles */
    @media print {
        body.dark-mode {
            background: white !important;
            color: black !important;
        }

        body,
        body.dark-mode {
            --bg-container: white !important;
            --text-primary: black !important;
            --border-color: #000 !important;
            --bg-header-row: #00ACB1 !important;
            --text-header-row: white !important;
            --bg-total-row: #eee !important;
            --bg-subtotal: #f9f9f9 !important;
        }

        @page {
            margin: 1cm;
            size: auto;
        }

        .report-nav,
        .sidebar,
        .filter-bar,
        .no-print,
        .summary-grid,
        .alert-box,
        h3,
        p {
            display: none !important;
        }

        .report-header {
            display: block !important;
            text-align: center;
            margin-bottom: 10px;
        }

        .report-header h1 {
            display: block !important;
            color: #00ACB1 !important;
            -webkit-print-color-adjust: exact;
            font-size: 24px;
            margin: 0;
        }

        .ailments-container {
            padding: 0;
            margin: 0;
            max-width: 100%;
            box-shadow: none;
            background: white !important;
            color: black !important;
        }

        .daily-table {
            box-shadow: none !important;
            border: 1px solid #000;
            width: 100%;
            margin-top: 0;
        }

        .daily-table th,
        .daily-table td {
            border: 1px solid #000;
            color: black !important;
            text-decoration: none !important;
            padding: 8px !important;
        }

        .daily-table th {
            background-color: #00ACB1 !important;
            color: white !important;
        }
    }

    /* Remove link styling from numbers */
    .clickable-cell {
        text-decoration: none !important;
        color: black !important;
        cursor: default !important;
        pointer-events: none;
    }

    /* Force light theme vars for print */
    :root {
        --bg-7: #dcedc8 !important;
        --bg-8: #ffcdd2 !important;
        --bg-9: #bbdefb !important;
    }

    .daily-table th {
        background-color: #eee !important;
        -webkit-print-color-adjust: exact;
    }
    }
</style>

<div class="ailments-container">
    <style>
        @media print {
            .report-header {
                display: block !important;
                text-align: center;
            }

            .report-header h1 {
                margin: 0 auto;
            }
        }
    </style>

    <div class="report-header"
        style="display: flex; flex-wrap: wrap; gap: 15px; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="color: #00ACB1; margin: 0; font-size: 1.5rem;">Daily Ailments Report -
                <?= date('F Y', strtotime($dateFrom)) ?>
            </h1>
            <p style="margin: 5px 0 0; color: var(--text-secondary);">Track common complaints to spot potential
                outbreaks</p>
        </div>

        <div class="report-nav" style="margin-bottom: 0; flex-wrap: wrap; gap: 8px;">
            <a href="census.php">Monthly Census</a>
            <a href="daily_ailments.php" class="active">Daily Ailments</a>
            <a href="pe_monitoring.php">Physical Exam</a>
        </div>
    </div>

    <!-- AI Outbreak Analysis Widget -->
    <div id="aiOutbreakWidget" class="ai-widget">
        <div style="display: flex; align-items: center; gap: 15px;">
            <div
                style="background: #00ACB1; color: white; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                <i class="fa-solid fa-robot"></i>
            </div>
            <div>
                <h3 class="ai-widget-title">AI Outbreak Analysis</h3>
                <p id="aiRiskText" class="ai-widget-text">Analyzing trends...</p>
            </div>
            <div style="margin-left: auto; text-align: right;">
                <span id="aiRiskBadge" class="ai-badge">ANALYZING</span>
            </div>
        </div>
    </div>



    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Fetch AI Risk
            fetch('api/ai_suggestions.php?action=outbreak_risk')
                .then(response => response.json())
                .then(data => {
                    const widget = document.getElementById('aiOutbreakWidget');
                    const riskText = document.getElementById('aiRiskText');
                    const riskBadge = document.getElementById('aiRiskBadge');

                    if (data.risk_level) {
                        widget.style.display = 'block';
                        riskText.innerText = data.message;
                        riskBadge.innerText = data.risk_level.toUpperCase() + ' RISK';

                        // Styling for Risk Level
                        widget.className = 'ai-widget';
                        riskBadge.className = 'ai-badge';

                        if (data.risk_level === 'High') {
                            widget.classList.add('ai-widget-high');
                            riskBadge.classList.add('ai-badge-high');
                        } else if (data.risk_level === 'Moderate') {
                            widget.classList.add('ai-widget-mod');
                            riskBadge.classList.add('ai-badge-mod');
                        } else {
                            widget.classList.add('ai-widget-low');
                            riskBadge.classList.add('ai-badge-low');
                        }

                        // Add AI Details if available (Python)
                        if (data.analysis_details && data.analysis_details.length > 0) {
                            const detailsDiv = document.createElement('div');
                            detailsDiv.style.marginTop = '10px';
                            detailsDiv.style.fontSize = '0.85em';
                            detailsDiv.style.color = 'var(--text-secondary)';

                            let html = '<strong style="color:#00ACB1"><i class="fa-brands fa-python"></i> AI Forecast:</strong><br>';
                            data.analysis_details.slice(0, 3).forEach(item => {
                                // Only show if slope is positive or significant
                                if (item.slope > 0.1) {
                                    html += `Scan: <b>${item.name}</b> (Trend: +${item.slope.toFixed(2)} cases/day)<br>`;
                                }
                            });
                            detailsDiv.innerHTML = html;
                            // Append to the text container (second child of widget flex)
                            widget.children[0].children[1].appendChild(detailsDiv);
                        } else if (data.analysis_type) {
                            // Fallback Indicator
                            const fallbackLabel = document.createElement('div');
                            fallbackLabel.innerHTML = '<small style="color:#999; font-style:italic;">Basic Statistical Analysis</small>';
                            widget.children[0].children[1].appendChild(fallbackLabel);
                        }
                    }
                })
                .catch(err => console.error('AI Error:', err));
        });
    </script>

    <div class="filter-bar">
        <a href="?filter=today" class="<?= $filterType == 'today' ? 'active' : '' ?>">Today</a>
        <a href="?filter=week" class="<?= $filterType == 'week' ? 'active' : '' ?>">This Week</a>
        <a href="?filter=month" class="<?= $filterType == 'month' ? 'active' : '' ?>">This Month</a>
        <form class="custom-form" method="GET">
            <input type="hidden" name="filter" value="custom">
            <input type="date" name="from" value="<?= htmlspecialchars($customFrom) ?>" required>
            <span>to</span>
            <input type="date" name="to" value="<?= htmlspecialchars($customTo) ?>" required>
            <button type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
        </form>
        <button type="button" onclick="window.print()"
            style="padding: 8px 16px; background: var(--bg-btn); color: var(--text-btn); border: none; border-radius: 6px; cursor: pointer; font-weight: 600;"><i
                class="fa-solid fa-print"></i> Print</button>
    </div>

    <?php if ($totalCases > 0): ?>
        <?php
        $alertAilment = null;
        foreach ($ailmentTotals as $name => $count) {
            if ($count >= 10) {
                $alertAilment = ['name' => $name, 'count' => $count];
                break;
            }
        }
        ?>
        <?php if ($alertAilment): ?>
            <div class="alert-box">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div><strong>Outbreak Alert:</strong> <em><?= htmlspecialchars($alertAilment['name']) ?></em> has
                    <strong><?= $alertAilment['count'] ?></strong> cases in this period.
                </div>
            </div>
        <?php endif; ?>

        <div class="summary-grid">
            <div class="summary-box">
                <h3><i class="fa-solid fa-ranking-star"></i> Ailment Ranking (<?= $totalCases ?> total cases)</h3>
                <?php $rank = 1;
                foreach ($ailmentTotals as $name => $count): ?>
                    <div class="ailment-rank">
                        <div class="rank-num"><?= $rank ?></div>
                        <span class="rank-name"><?= htmlspecialchars($name) ?></span>
                        <span class="rank-count"><?= $count ?></span>
                    </div>
                    <?php $rank++;
                    if ($rank > 10)
                        break;
                endforeach; ?>
            </div>
            <div class="summary-box">
                <h3><i class="fa-solid fa-chart-pie"></i> Distribution</h3>
                <canvas id="ailmentPie" style="max-height: 320px;"></canvas>
            </div>
        </div>

        <h3 style="color: var(--text-primary); margin-bottom: 15px; margin-top: 30px;"><i class="fa-solid fa-list"
                style="color: #00ACB1;"></i>
            Detailed Breakdown</h3>

        <style>
            .census-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 11px;
                text-align: center;
                background: var(--bg-container);
                color: var(--text-primary);
            }

            .census-table th,
            .census-table td {
                border: 1px solid var(--border-color);
                padding: 6px;
            }

            .clickable-cell {
                cursor: pointer;
                text-decoration: underline;
                color: #00ACB1;
            }

            .modal-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                justify-content: center;
                align-items: center;
                z-index: 1000;
            }

            .modal-content {
                background: var(--bg-container);
                color: var(--text-primary);
                padding: 20px;
                border-radius: 8px;
                width: 400px;
                max-width: 90%;
            }

            .detail-list {
                list-style: none;
                padding: 0;
            }

            .detail-item {
                border-bottom: 1px solid var(--border-color);
                padding: 8px 0;
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
        </style>

        <div style="overflow-x: auto; width: 100%;">
            <table class="census-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="background: var(--bg-header-row); color: var(--text-header-row);">Date</th>
                        <th rowspan="2" style="background: var(--bg-header-row); color: var(--text-header-row);">Assessment
                        </th>
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
                    <?php foreach ($censusData as $date => $complaints):
                        if (empty($complaints))
                            continue;
                        $rowSpan = count($complaints);
                        $first = true;
                        // Provide a set of all dates if "Today" is empty? No, respect filter.
                
                        foreach ($complaints as $complaintName => $counts):
                            ?>
                            <tr>
                                <?php if ($first): ?>
                                    <td rowspan="<?= $rowSpan ?>"
                                        style="white-space: nowrap; font-weight:bold; color:var(--text-secondary); vertical-align:middle; background:var(--bg-container);">
                                        <?= date('M d, Y', strtotime($date)) ?>
                                    </td>
                                <?php endif; ?>

                                <td style="text-align:left; font-weight:bold; color:var(--text-primary);">
                                    <?= htmlspecialchars($complaintName) ?>
                                </td>

                                <?php foreach ($gradeKeys as $g):
                                    $mCount = $counts[$g]['Male'];
                                    $fCount = $counts[$g]['Female'];

                                    $mAttr = $mCount > 0 ? 'class="clickable-cell" onclick="showDetails(this)"' : '';
                                    $mData = $mCount > 0 ? "data-details='" . htmlspecialchars(json_encode($detailData[$date][$complaintName][$g]['Male'] ?? []), ENT_QUOTES, 'UTF-8') . "'" : '';

                                    $fAttr = $fCount > 0 ? 'class="clickable-cell" onclick="showDetails(this)"' : '';
                                    $fData = $fCount > 0 ? "data-details='" . htmlspecialchars(json_encode($detailData[$date][$complaintName][$g]['Female'] ?? []), ENT_QUOTES, 'UTF-8') . "'" : '';
                                    ?>
                                    <td <?= $mAttr ?>                 <?= $mData ?> style="background: <?= $gradeColors[$g] ?>"><?= $mCount ?: '' ?></td>
                                    <td <?= $fAttr ?>                 <?= $fData ?> style="background: <?= $gradeColors[$g] ?>"><?= $fCount ?: '' ?></td>
                                <?php endforeach; ?>
                                <td style="font-weight:bold; background:var(--bg-subtotal); color: var(--text-primary);">
                                    <?= $counts['Total'] ?: '' ?>
                                </td>
                            </tr>
                            <?php $first = false; endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Modal for Details -->
        <div id="detailModal" class="modal-overlay">
            <div class="modal-content" style="max-width: 600px; width: 95%;">
                <div
                    style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                    <h3 style="margin:0; color:#00ACB1;">List of Patients</h3>
                    <div>
                        <button onclick="printModal()"
                            style="padding: 5px 12px; background: #00ACB1; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px; font-weight: bold;"><i
                                class="fa-solid fa-print"></i> Print</button>
                        <span onclick="document.getElementById('detailModal').style.display='none'"
                            style="cursor:pointer; font-size:1.5rem;">&times;</span>
                    </div>
                </div>
                <div id="modalBody" style="max-height: 400px; overflow-y:auto; padding-right: 5px;"></div>
            </div>
        </div>

        <script>
            function showDetails(cell) {
                const dataStr = cell.getAttribute('data-details');
                if (!dataStr) return;
                try {
                    const students = JSON.parse(dataStr);
                    let html = '<table class="modal-table"><thead><tr><th>Name</th><th>Assessment</th></tr></thead><tbody>';
                    if (students.length === 0) {
                        html += '<tr><td colspan="2" style="text-align:center;">No records found.</td></tr>';
                    } else {
                        students.forEach(s => {
                            html += `<tr><td style="text-transform: uppercase;"><strong>${s.name}</strong></td><td><span style="color:var(--text-secondary);">${s.complaint}</span></td></tr>`;
                        });
                    }
                    html += '</tbody></table>';
                    document.getElementById('modalBody').innerHTML = html;
                    document.getElementById('detailModal').style.display = 'flex';
                } catch (e) { console.error(e); }
            }
            window.onclick = function (e) {
                if (e.target == document.getElementById('detailModal')) {
                    document.getElementById('detailModal').style.display = 'none';
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
                printWindow.document.write('<h2>Patient List (Daily Ailments)</h2>');
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

    <?php else: ?>
        <div style="text-align: center; padding: 60px 20px; color: #999;">
            <i class="fa-solid fa-face-smile"
                style="font-size: 3rem; color: #00ACB1; display: block; margin-bottom: 10px;"></i>
            <h3>No ailment cases found for this period.</h3>
            <p>That's great news!</p>
        </div>
    <?php endif; ?>
</div>

<?php if ($totalCases > 0): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        new Chart(document.getElementById('ailmentPie'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys(array_slice($ailmentTotals, 0, 8, true))) ?>,
                datasets: [{ data: <?= json_encode(array_values(array_slice($ailmentTotals, 0, 8, true))) ?>, backgroundColor: ['#00ACB1', '#f39c12', '#e74c3c', '#3498db', '#2ecc71', '#9b59b6', '#e67e22', '#1abc9c'], borderWidth: 2, borderColor: '#fff' }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true } } } }
        });
    </script>
<?php endif; ?>
</body>

</html>