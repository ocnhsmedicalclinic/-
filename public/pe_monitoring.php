<?php
require_once "../config/db.php";
requireLogin();

// DEFAULT DATE: Current Year & Month with Validation
$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : 'all';

// Validate Year
if ($selectedYear < 2000 || $selectedYear > 2100)
    $selectedYear = (int) date('Y');

// Validate Month ('all' or 1-12)
if ($selectedMonth !== 'all') {
    $selectedMonth = (int) $selectedMonth;
    if ($selectedMonth < 1 || $selectedMonth > 12) {
        $selectedMonth = 'all';
    }
}

// Grades to monitor
$gradeLevels = ['7', '8', '9', '10', '11', '12'];

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

/**
 * CONFIGURATION: Define the exact rows and their behavior
 * We use a single array to define the order.
 * type: 'legend' (uses legend mapping), 'simple' (simple count), 'checkbox' (bool count), 'bmi_calc' (average bmi)
 * field: the slug in health_exam_json
 * legend_items: (optional) if 'legend', subset of items to show
 */

$monitoringConfig = [

    [
        'label' => 'BMI/Weight for Age',
        'type' => 'legend',
        'field' => 'bmi_weight',
        'items' => [
            'a' => 'Normal Weight',
            'b' => 'Wasted',
            'c' => 'Severely Wasted',
            'd' => 'Overweight',
            'e' => 'Obese',
        ]
    ],
    [
        'label' => 'BMI/Height for Age',
        'type' => 'legend',
        'field' => 'bmi_height',
        'items' => [
            'f' => 'Normal Height',
            'g' => 'Stunted',
            'h' => 'Severely Stunted',
            'i' => 'Tall'
        ]
    ],
    [
        'label' => 'Snellen',
        'type' => 'simple',
        'field' => 'snellen'
    ],
    [
        'label' => 'Eye chart (Near)',
        'type' => 'simple',
        'field' => 'eye_chart_near'
    ],
    [
        'label' => 'Ishihara chart',
        'type' => 'simple',
        'field' => 'ishihara_chart'
    ],
    [
        'label' => 'Auditory screening (Tuning Fork)',
        'type' => 'simple',
        'field' => 'auditory'
    ],
    [
        'label' => 'Skin/Scalp',
        'type' => 'legend',
        'field' => 'skin_scalp',
        'items' => [
            'a' => 'Normal',
            'b' => 'Pediculosis',
            'c' => 'Tinea Flava',
            'd' => 'Ringworm',
            'e' => 'Eczema/Rash',
            'f' => 'Impetigo/Boil',
            'g' => 'Dandruff',
            'h' => 'Bruises/ Hematoma',
            'i' => 'Acne/Pimple'
        ]
    ],
    [
        'label' => 'Eyes/Ears/Nose',
        'type' => 'legend',
        'field' => 'eyes_ears_nose',
        'items' => [
            'a' => 'Normal',
            'b' => 'Stye',
            'c' => 'Conjunctivitis',
            'd' => 'Squinting',
            'e' => 'Pale conjunctivae',
            'f' => 'Ear discharged',
            'g' => 'Impacted cerumen',
            'h' => 'Deformed nose',
            'i' => 'Ear perforation',
            'j' => 'Ear tag'
        ]
    ],
    [
        'label' => 'Mouth/Neck/Throat',
        'type' => 'legend',
        'field' => 'mouth_neck_throat',
        'items' => [
            'a' => 'Normal',
            'b' => 'Enlarged Tonsils',
            'c' => 'Enlarged Thyroid Gland',
            'd' => 'Cleft Palate Harelip',
            'e' => 'With Lymphadenopathy'
        ]
    ],
    [
        'label' => 'Lungs/Heart',
        'type' => 'legend',
        'field' => 'lungs_heart',
        'items' => [
            'a' => 'Normal Breath Sounds',
            'b' => 'Normal Heart Sounds',
            'c' => 'Rales',
            'd' => 'Wheezes',
            'e' => 'Murmur',
            'f' => 'Deformed Chest',
            'g' => 'Irregular Heart Rate'
        ]
    ],
    [
        'label' => 'Abdomen/Genitalia',
        'type' => 'legend',
        'field' => 'abdomen_genitalia',
        'items' => [
            'a' => 'Normal Abdomen',
            'b' => 'Normal Genitalia',
            'c' => 'Mass',
            'd' => 'Hemorrhoid',
            'e' => 'Hernia',
            'f' => 'Tenderness',
            'g' => 'Bowel sounds'
        ]
    ],
    [
        'label' => 'Spine/Extremities',
        'type' => 'legend',
        'field' => 'spine_extremities',
        'items' => [
            'a' => 'Normal Spine',
            'b' => 'Normal Upper Extremities',
            'c' => 'Normal Lower Extremities',
            'd' => 'Scoliosis',
            'e' => 'Lordosis',
            'f' => 'Kyphosis',
            'g' => 'Bowlegs/ Knock Knees',
            'h' => 'Flat Foot',
            'i' => 'Club foot',
            'j' => 'Polio'
        ]
    ],
    [
        'label' => 'Iron-Folic Acid Supplementation (V o X)',
        'type' => 'checkbox',
        'field' => 'iron-folic_acid_supplementation__v_o_x_'
    ],
    [
        'label' => 'Deworming (V o X)',
        'type' => 'checkbox',
        'field' => 'deworming__v_o_x_'
    ],
    [
        'label' => 'Immunization (specify)',
        'type' => 'simple', // treated as simple value presence
        'field' => 'immunization__specify_'
    ],
    [
        'label' => 'SBFP Beneficiary (V o X)',
        'type' => 'checkbox',
        'field' => 'sbfp_beneficiary__v_o_x_'
    ],
    [
        'label' => '4Ps Beneficiary (V o X)',
        'type' => 'checkbox',
        'field' => '4ps_beneficiary__v_o_x_'
    ],
    [
        'label' => 'Menarche',
        'type' => 'simple',
        'field' => 'menarche'
    ],
    [
        'label' => 'Others, Specify',
        'type' => 'simple',
        'field' => 'others__specify'
    ]
];

// Initialize Data Arrays
$monitoringData = [];
$detailData = [];
// For Categories (Legend Types), we track Category Totals
$categoryCounts = [];
$categoryDetails = [];

foreach ($monitoringConfig as $config) {
    if ($config['type'] === 'legend') {
        // Initialize Row for each Item
        foreach ($config['items'] as $code => $title) {
            $rowName = "{$config['label']}: $code. $title";
            $monitoringData[$rowName] = [];
            $detailData[$rowName] = [];
            foreach ($gradeLevels as $g) {
                $monitoringData[$rowName][$g] = ['Male' => 0, 'Female' => 0];
                $detailData[$rowName][$g] = ['Male' => [], 'Female' => []];
            }
        }
        // Initialize Category Totals
        foreach ($gradeLevels as $g) {
            $categoryCounts[$config['label']][$g] = ['Male' => 0, 'Female' => 0];
            $categoryDetails[$config['label']][$g] = ['Male' => [], 'Female' => []];
        }

    } else {
        // Simple/Checkbox rows
        $rowName = $config['label'];
        $monitoringData[$rowName] = [];
        $detailData[$rowName] = [];
        foreach ($gradeLevels as $g) {
            $monitoringData[$rowName][$g] = ['Male' => 0, 'Female' => 0];
            $detailData[$rowName][$g] = ['Male' => [], 'Female' => []];
        }
    }
}


// TOTAL POPULATION per Grade
$population = [];
foreach ($gradeLevels as $g)
    $population[$g] = ['Male' => 0, 'Female' => 0];

// Calculate Population (same logic)
$res_pop = $conn->query("SELECT gender, health_exam_json FROM students WHERE is_archived = 0");
while ($row = $res_pop->fetch_assoc()) {
    $gender = (ucfirst(strtolower($row['gender'] ?? '')) == 'Male') ? 'Male' : 'Female';
    $health_values = json_decode($row['health_exam_json'] ?? '{}', true);
    foreach ($gradeLevels as $g) {
        if (!empty($health_values['date_' . $g])) {
            $date = strtotime($health_values['date_' . $g]);
            $y = date('Y', $date);
            $m = date('m', $date);

            if ($y == $selectedYear && ($selectedMonth == 'all' || $m == $selectedMonth)) {
                $population[$g][$gender]++;
            }
        }
    }
}

// FETCH STUDENTS AND PROCESS DATA
$res_stud = $conn->query("SELECT name, gender, health_exam_json FROM students WHERE is_archived = 0");
while ($person = $res_stud->fetch_assoc()) {
    $gender = (ucfirst(strtolower($person['gender'] ?? '')) == 'Male') ? 'Male' : 'Female';
    $health_values = json_decode($person['health_exam_json'] ?? '{}', true);
    if (empty($health_values))
        continue;

    foreach ($gradeLevels as $g) {
        // 1. Check Main Date of Examination Validity
        $mainDateValid = false;
        if (!empty($health_values['date_' . $g])) {
            $date = strtotime($health_values['date_' . $g]);
            $y = date('Y', $date);
            $m = date('m', $date);
            if ($y == $selectedYear && ($selectedMonth == 'all' || $m == $selectedMonth)) {
                $mainDateValid = true;
            }
        }

        // 2. Process Each Config Item
        foreach ($monitoringConfig as $config) {
            $field_slug = $config['field'] . '_' . $g;
            $date_slug = $config['field'] . '_date_' . $g; // Assumes consistent naming for date fields

            $shouldCount = false; // Default for non-legend types
            $specificDateVal = $health_values[$date_slug] ?? '';

            // A. Validation Logic
            // Check Specific Date first (prioritize item-specific dates)
            if (!empty($specificDateVal)) {
                $d = strtotime($specificDateVal);
                $y = date('Y', $d);
                $m = date('m', $d);
                if ($y == $selectedYear && ($selectedMonth == 'all' || $m == $selectedMonth)) {
                    // Valid specific date found -> implies record exists
                    $shouldCount = true;
                }
            }
            // Fallback to Main Date if no specific date matched (or existed)
            elseif ($mainDateValid) {
                // If main date is valid, we check if the VALUE is present
                if ($config['type'] === 'legend') {
                    // Legend types are handled purely by value content here
                    $val = strtolower(trim($health_values[$field_slug] ?? ''));
                    if ($val !== '') {
                        $codes_in_val = preg_split('/[,\s]+/', $val);
                        $hasMatch = false;
                        foreach ($config['items'] as $code => $title) {
                            // Check for Code match OR exact Title match
                            $isCodeMatch = in_array(strtolower($code), $codes_in_val);
                            $isTitleMatch = ($val === strtolower($title));

                            if ($isCodeMatch || $isTitleMatch) {
                                $rowName = "{$config['label']}: $code. $title";
                                $monitoringData[$rowName][$g][$gender]++;
                                $detailData[$rowName][$g][$gender][] = $person['name'];
                                $hasMatch = true;
                            }
                        }
                        if ($hasMatch) {
                            $categoryCounts[$config['label']][$g][$gender]++;
                            $categoryDetails[$config['label']][$g][$gender][] = $person['name'];
                        }
                    }
                    // Legend counting is self-contained, so we continue
                    continue;
                } elseif ($config['type'] === 'checkbox' || $config['type'] === 'simple') {
                    // For checkbox/simple, if main date is valid, we need a value
                    if (!empty($health_values[$field_slug])) {
                        $shouldCount = true;
                    }
                }
            }

            // B. Execution Logic (for Simple/Checkbox)
            if ($shouldCount) {
                $rowName = $config['label'];
                $monitoringData[$rowName][$g][$gender]++;
                $detailData[$rowName][$g][$gender][] = $person['name'];
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
        --bg-btn: #333;
        --text-btn: #fff;

        /* Row highlights */
        --bg-row-found: #f9f9f9;
    }

    /* FORCE LIGHT MODE ONLY - Ignore global Dark Mode */
    body.dark-mode {
        background: #f5f7fa !important;
    }

    body.dark-mode .census-container,
    body.dark-mode .census-table,
    body.dark-mode .filters {
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
        --text-secondary: #555 !important;
        --bg-container: #ffffff !important;
        --bg-header-row: #00ACB1 !important;
        --text-header-row: #ffffff !important;
        --border-color: #ccc !important;

        --bg-summary-header: #ffffff !important;
        --text-summary-header: #333 !important;
        --bg-total-row: #e0e0e0 !important;
        --bg-subtotal: #eee !important;
        --bg-row-found: #ffffff !important;
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

    /* Modal Dark Mode for PE Monitoring */
    body.dark-mode #peModal .modal-content {
        background: #ffffff !important;
        color: #333 !important;
    }

    body.dark-mode #peModal table {
        background: #1e1e1e !important;
    }

    body.dark-mode #peModal td {
        border-bottom-color: #333 !important;
        color: #e0e0e0 !important;
    }

    body.dark-mode #peModal .modal-header h3 {
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
        font-size: 11px;
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

    /* Print Styles */
    @media print {
        body.dark-mode {
            background: white !important;
            color: black !important;
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
            width: 25% !important;
            text-align: center !important;
        }

        .census-table th:last-child,
        .census-table td:last-child {
            width: 8% !important;
        }

        .census-table td:not(:first-child):not(:last-child),
        .census-table th:not(:first-child):not(:last-child):not([colspan]) {
            width: 4.18% !important;
        }

        .census-table thead th {
            background: linear-gradient(to bottom, #00ACB1, #9FF0D7) !important;
            background-color: #00ACB1 !important;
            color: white !important;
            font-weight: bold !important;
            font-size: 8pt !important;
        }

        .census-table thead th[colspan] {
            width: 8.36% !important;
            text-align: center !important;
            font-size: 7pt !important;
        }

        .report-header {
            display: block !important;
            text-align: center !important;
            margin-bottom: 20px !important;
        }

        .report-header h1 {
            color: #00ACB1 !important;
            font-size: 16pt !important;
            margin: 0 !important;
        }

        @page {
            size: portrait;
            margin: 5mm;
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

    .modal-close:hover {
        color: var(--text-primary);
    }

    .student-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .student-list li {
        padding: 8px;
        border-bottom: 1px solid var(--border-color);
    }

    .student-list li:last-child {
        border-bottom: none;
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
        .filters,
        .modal-overlay,
        .btn-submit {
            display: none !important;
        }

        .report-header {
            display: block !important;
            text-align: center !important;
            margin-bottom: 30px !important;
        }

        .report-header h1 {
            color: #000 !important;
            font-size: 24px !important;
            margin: 0 !important;
            text-align: center !important;
        }

        /* Ensure table fits */
        .census-container {
            box-shadow: none;
            padding: 0;
            overflow: visible;
            max-height: none;
        }

        .census-table th,
        .census-table td {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<div class="census-container">

    <div class="report-header"
        style="display: flex; flex-wrap: wrap; gap: 15px; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <?php
        $monthDisplay = ($selectedMonth == 'all') ? '' : date('F', mktime(0, 0, 0, $selectedMonth, 1)) . ' ';
        ?>
        <h1 style="color: #00ACB1; margin: 0; font-size: 1.5rem;">Physical Exam Monitoring -
            <?= $monthDisplay . $selectedYear ?>
        </h1>
        <div class="report-nav" style="margin-bottom: 0; flex-wrap: wrap; gap: 8px;">
            <a href="census.php">Monthly Census</a>
            <a href="daily_ailments.php">Daily Ailments</a>
            <a href="pe_monitoring.php" class="active">Physical Exam</a>
        </div>
    </div>

    <!-- Navigation / Filter -->
    <!-- Navigation / Filter -->
    <form class="filters" method="GET">
        <label><strong>Month:</strong></label>
        <select name="month" style="padding: 5px;" onchange="this.form.submit()">
            <option value="all" <?= $selectedMonth == 'all' ? 'selected' : '' ?>>All Months</option>
            <?php
            for ($m = 1; $m <= 12; $m++) {
                $monthName = date('F', mktime(0, 0, 0, $m, 1));
                $sel = ($selectedMonth == $m) ? 'selected' : '';
                echo "<option value='$m' $sel>$monthName</option>";
            }
            ?>
        </select>
        <label><strong>Year:</strong></label>
        <select name="year" style="padding: 5px;" onchange="this.form.submit()">
            <?php for ($y = 2099; $y >= 2000; $y--): ?>
                <option value="<?= $y ?>" <?= $selectedYear == $y ? 'selected' : '' ?>>
                    <?= $y ?>
                </option>
            <?php endfor; ?>
        </select>

        <button type="button" onclick="window.print()" class="btn-submit"
            style="background: var(--bg-btn); color: var(--text-btn);"><i class="fa-solid fa-print"></i> Print</button>
    </form>

    <div id="printArea" style="overflow-x: auto; width: 100%;">
        <table class="census-table">
            <thead>
                <tr>
                    <th rowspan="2" style="background: var(--bg-header-row); color: var(--text-header-row);">Health Indicator</th>
                    <?php foreach ($gradeLevels as $g): ?>
                            <th colspan="2" style="background: <?= $gradeColors[$g] ?? 'var(--bg-others)' ?>; color: var(--text-primary);">Grade <?= $g ?></th>
                    <?php endforeach; ?>
                    <th rowspan="2" style="background: var(--bg-header-row); color: var(--text-header-row);">Grand Total</th>
                </tr>
                <tr>
                    <?php foreach ($gradeLevels as $g): ?>
                            <th style="background: <?= $gradeColors[$g] ?? 'var(--bg-others)' ?>; color: var(--text-primary);">M</th>
                            <th style="background: <?= $gradeColors[$g] ?? 'var(--bg-others)' ?>; color: var(--text-primary);">F</th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <!-- TOTAL POPULATION -->


                <!-- DYNAMIC ROWS BASED ON CONFIG -->
                <?php
                foreach ($monitoringConfig as $config) {
                    if ($config['type'] === 'legend') {
                        // SHOW CATEGORY ROW with Totals
                        $catLabel = $config['label'];
                        $catGrandTotal = 0;
                        foreach ($gradeLevels as $g) {
                            $catGrandTotal += ($categoryCounts[$catLabel][$g]['Male'] + $categoryCounts[$catLabel][$g]['Female']);
                        }
                        ?>
                                <tr style="background: var(--bg-row-found); font-weight: bold; color: var(--text-primary);">
                                    <td style="text-align: left; padding-left: 10px;"><?= $catLabel ?> (Found)</td>
                                    <?php foreach ($gradeLevels as $g):
                                        $mVal = $categoryCounts[$catLabel][$g]['Male'];
                                        $mNames = $categoryDetails[$catLabel][$g]['Male'];
                                        $mAttr = ($mVal > 0) ? 'class="clickable-cell" onclick="showDetails(this)" data-details=\'' . htmlspecialchars(json_encode($mNames), ENT_QUOTES, 'UTF-8') . '\'' : '';

                                        $fVal = $categoryCounts[$catLabel][$g]['Female'];
                                        $fNames = $categoryDetails[$catLabel][$g]['Female'];
                                        $fAttr = ($fVal > 0) ? 'class="clickable-cell" onclick="showDetails(this)" data-details=\'' . htmlspecialchars(json_encode($fNames), ENT_QUOTES, 'UTF-8') . '\'' : '';
                                        ?>
                                            <td <?= $mAttr ?> style="background: <?= $gradeColors[$g] ?>;"><?= $mVal ?: '-' ?></td>
                                            <td <?= $fAttr ?> style="background: <?= $gradeColors[$g] ?>;"><?= $fVal ?: '-' ?></td>
                                    <?php endforeach; ?>
                                        <td style="background: var(--bg-subtotal); color: var(--text-primary);"><?= $catGrandTotal ?: '0' ?></td>
                                    </tr>

                                <!-- SHOW BREAKDOWN ROWS -->
                                 <?php
                                 foreach ($config['items'] as $code => $title) {
                                     $rowName = "{$config['label']}: $code. $title";
                                     $rowTotal = 0;
                                     ?>
                                         <tr>
                                             <td style="text-align: left; padding-left: 20px; font-size: 11px;">
                                                 <?= "$code. $title" ?>
                                             </td>
                                             <?php foreach ($gradeLevels as $g):
                                                 $mVal = $monitoringData[$rowName][$g]['Male'];
                                                 $mNames = $detailData[$rowName][$g]['Male'];
                                                 $mAttr = ($mVal > 0) ? 'class="clickable-cell" onclick="showDetails(this)" data-details=\'' . htmlspecialchars(json_encode($mNames), ENT_QUOTES, 'UTF-8') . '\'' : '';

                                                 $fVal = $monitoringData[$rowName][$g]['Female'];
                                                 $fNames = $detailData[$rowName][$g]['Female'];
                                                 $fAttr = ($fVal > 0) ? 'class="clickable-cell" onclick="showDetails(this)" data-details=\'' . htmlspecialchars(json_encode($fNames), ENT_QUOTES, 'UTF-8') . '\'' : '';

                                                 $rowTotal += ($mVal + $fVal);
                                                 ?>
                                                     <td <?= $mAttr ?> style="background: <?= $gradeColors[$g] ?>; font-weight: <?= $mVal > 0 ? 'bold' : 'normal' ?>;"><?= $mVal ?: '-' ?></td>
                                                     <td <?= $fAttr ?> style="background: <?= $gradeColors[$g] ?>; font-weight: <?= $fVal > 0 ? 'bold' : 'normal' ?>;"><?= $fVal ?: '-' ?></td>
                                             <?php endforeach; ?>
                                             <td style="background: var(--bg-subtotal); color: var(--text-primary); font-weight: bold;"><?= $rowTotal ?: '-' ?></td>
                                         </tr>
                                         <?php
                                 }

                    } else {
                        // SIMPLE / CHECKBOX ROW
                        $rowName = $config['label'];
                        $rowTotal = 0;
                        ?>
                                <tr>
                                    <td style="text-align: left; padding-left: 10px;"><?= $rowName ?></td>
                                    <?php foreach ($gradeLevels as $g):
                                        $mVal = $monitoringData[$rowName][$g]['Male'];
                                        $mNames = $detailData[$rowName][$g]['Male'];
                                        $mAttr = ($mVal > 0) ? 'class="clickable-cell" onclick="showDetails(this)" data-details=\'' . htmlspecialchars(json_encode($mNames), ENT_QUOTES, 'UTF-8') . '\'' : '';

                                        $fVal = $monitoringData[$rowName][$g]['Female'];
                                        $fNames = $detailData[$rowName][$g]['Female'];
                                        $fAttr = ($fVal > 0) ? 'class="clickable-cell" onclick="showDetails(this)" data-details=\'' . htmlspecialchars(json_encode($fNames), ENT_QUOTES, 'UTF-8') . '\'' : '';

                                        $rowTotal += ($mVal + $fVal);
                                        ?>
                                            <td <?= $mAttr ?> style="background: <?= $gradeColors[$g] ?>; font-weight: <?= $mVal > 0 ? 'bold' : 'normal' ?>;"><?= $mVal ?: '-' ?></td>
                                            <td <?= $fAttr ?> style="background: <?= $gradeColors[$g] ?>; font-weight: <?= $fVal > 0 ? 'bold' : 'normal' ?>;"><?= $fVal ?: '-' ?></td>
                                    <?php endforeach; ?>
                                    <td style="background: var(--bg-subtotal); color: var(--text-primary); font-weight: bold;"><?= $rowTotal ?: '-' ?></td>
                                </tr>
                                <?php
                    }
                }
                ?>
                <!-- TOTAL POPULATION MOVED TO BOTTOM -->
                <tr style="background-color: var(--bg-total-row); color: var(--text-primary); font-weight: bold; border-top: 3px solid var(--border-color);">
                    <td style="text-align: left; padding-left: 10px;">Total Population Examined</td>
                    <?php
                    $grandTotal = 0;
                    foreach ($gradeLevels as $g):
                        $mVal = $population[$g]['Male'];
                        $fVal = $population[$g]['Female'];
                        $grandTotal += ($mVal + $fVal);
                        ?>
                            <td><?= $mVal ?></td>
                            <td><?= $fVal ?></td>
                    <?php endforeach; ?>
                    <td style="background-color: var(--bg-7); color: var(--text-primary);"><?= $grandTotal ?></td>
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
                <button onclick="printModal()" style="padding: 5px 12px; background: #00ACB1; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px; font-weight: bold;"><i class="fa-solid fa-print"></i> Print</button>
                <span class="modal-close" onclick="closeModal()">&times;</span>
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
            const names = JSON.parse(dataStr);
            const modalBody = document.getElementById('modalBody');
            let html = '<table class="modal-table"><thead><tr><th>Name</th></tr></thead><tbody>';
            if (names.length === 0) {
                html += '<tr><td style="text-align:center;">No students found.</td></tr>';
            } else {
                names.forEach(name => {
                    html += `<tr><td style="text-transform: uppercase;"><strong>${name}</strong></td></tr>`;
                });
            }
            html += '</tbody></table>';
            modalBody.innerHTML = html;
            document.getElementById('censusModal').style.display = 'flex';
        } catch (e) {
            console.error("Error parsing details", e);
        }
    }
    
    function closeModal() { document.getElementById('censusModal').style.display = 'none'; }
    window.onclick = function (event) { if (event.target == document.getElementById('censusModal')) closeModal(); }

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
        printWindow.document.write('<h2>Patient List (Physical Exam Monitoring)</h2>');
        printWindow.document.write(printContents);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.focus();
        setTimeout(function() {
            printWindow.print();
            printWindow.close();
        }, 250);
    }
</script>