<?php
require_once "../config/db.php";
requireLogin();

$selectedGrade = isset($_GET['grade']) ? $_GET['grade'] : 'all';

// DepEd BMI Classification for ages 5-19 (simplified thresholds)
function classifyBMI($bmi, $age, $gender)
{
    if ($bmi <= 0 || $age <= 0)
        return 'No Data';

    // Simplified DepEd-aligned thresholds
    if ($age >= 5 && $age <= 19) {
        if ($bmi < 14.0)
            return 'Severely Wasted';
        if ($bmi < 16.0)
            return 'Wasted';
        if ($bmi < 23.0)
            return 'Normal';
        if ($bmi < 27.0)
            return 'Overweight';
        return 'Obese';
    }

    // Adult (20+)
    if ($bmi < 16.0)
        return 'Severely Wasted';
    if ($bmi < 18.5)
        return 'Wasted';
    if ($bmi < 25.0)
        return 'Normal';
    if ($bmi < 30.0)
        return 'Overweight';
    return 'Obese';
}

function getStatusColor($status)
{
    $colors = [
        'Severely Wasted' => '#e74c3c',
        'Wasted' => '#f39c12',
        'Normal' => '#2ecc71',
        'Overweight' => '#e67e22',
        'Obese' => '#c0392b',
        'No Data' => '#bdc3c7'
    ];
    return $colors[$status] ?? '#bdc3c7';
}

// Parse height and weight from health_exam_json (Grade 7-12 fields)
function parseMetric($value)
{
    if (empty($value))
        return 0;
    // Remove units like 'cm', 'klg', 'kg', 'm'
    $num = preg_replace('/[^0-9.]/', '', $value);
    return floatval($num);
}

$filterMonth = isset($_GET['filter_month']) ? $_GET['filter_month'] : 'all';
$filterYear = isset($_GET['filter_year']) ? $_GET['filter_year'] : date('Y');

$dateCondition = "";
if ($filterYear != '') {
    $dateCondition .= " AND YEAR(created_at) = '" . $conn->real_escape_string($filterYear) . "'";
    if ($filterMonth != 'all') {
        $dateCondition .= " AND MONTH(created_at) = '" . $conn->real_escape_string($filterMonth) . "'";
    }
}

// Collect student data
$students = [];
$statusCounts = ['Severely Wasted' => 0, 'Wasted' => 0, 'Normal' => 0, 'Overweight' => 0, 'Obese' => 0, 'No Data' => 0];
$gradeStatusCounts = [];

$sql = "SELECT id, name, gender, birth_date, health_exam_json, curriculum FROM students WHERE is_archived = 0 $dateCondition";
$res = $conn->query($sql);

while ($row = $res->fetch_assoc()) {
    $health = json_decode($row['health_exam_json'] ?? '{}', true);

    // Try to find the latest height/weight from grade 7-12
    $height = 0;
    $weight = 0;
    $gradeFound = '';

    for ($g = 12; $g >= 7; $g--) {
        $h = parseMetric($health["height_$g"] ?? '');
        $w = parseMetric($health["weight_$g"] ?? '');
        if ($h > 0 && $w > 0) {
            $height = $h;
            $weight = $w;
            $gradeFound = "Grade $g";
            break;
        }
    }

    // Calculate Age
    $age = 0;
    if ($row['birth_date']) {
        $birth = new DateTime($row['birth_date']);
        $today = new DateTime('today');
        $age = $birth->diff($today)->y;
    }

    // Calculate BMI (height in cm → m)
    $bmi = 0;
    if ($height > 0 && $weight > 0) {
        $heightM = $height > 3 ? $height / 100 : $height; // If > 3, assume cm
        if ($heightM > 0) {
            $bmi = round($weight / ($heightM * $heightM), 1);
        }
    }

    $status = classifyBMI($bmi, $age, $row['gender']);
    $statusCounts[$status]++;

    // Track by grade
    if ($gradeFound) {
        if (!isset($gradeStatusCounts[$gradeFound])) {
            $gradeStatusCounts[$gradeFound] = ['Severely Wasted' => 0, 'Wasted' => 0, 'Normal' => 0, 'Overweight' => 0, 'Obese' => 0, 'No Data' => 0];
        }
        $gradeStatusCounts[$gradeFound][$status]++;
    }

    $studentData = [
        'id' => $row['id'],
        'name' => $row['name'],
        'gender' => $row['gender'],
        'age' => $age,
        'grade' => $gradeFound,
        'height' => $height,
        'weight' => $weight,
        'bmi' => $bmi,
        'status' => $status
    ];

    if ($selectedGrade == 'all' || $gradeFound == $selectedGrade) {
        $students[] = $studentData;
    }
}

ksort($gradeStatusCounts);

// Calculate totals excluding No Data
$totalWithData = $statusCounts['Severely Wasted'] + $statusCounts['Wasted'] + $statusCounts['Normal'] + $statusCounts['Overweight'] + $statusCounts['Obese'];

// Pagination Logic
$limit = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

$totalStudents = count($students);
$totalPages = ceil($totalStudents / $limit);
$offset = ($page - 1) * $limit;

$paginatedStudents = array_slice($students, $offset, $limit);

include "index_layout.php";
?>

<style>
    .nutri-container {
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .nutri-header {
        text-align: center;
        margin-bottom: 20px;
    }

    .nutri-header h2 {
        font-family: 'Cinzel', serif;
        color: #333;
        margin: 0 0 5px;
    }

    .status-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 25px;
    }

    .status-card {
        background: white;
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06);
        border-top: 4px solid;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }

    .status-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
    }

    .status-card .s-value {
        font-size: 1.8rem;
        font-weight: 800;
    }

    .status-card .s-label {
        font-size: 0.78rem;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 3px;
    }

    .chart-grid2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 25px;
    }

    .chart-box2 {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
    }

    .chart-box2 h3 {
        margin: 0 0 15px;
        font-size: 1rem;
        color: #333;
    }

    .chart-box2 h3 i {
        color: #00ACB1;
    }

    .grade-filter {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .grade-filter a {
        padding: 6px 16px;
        border-radius: 20px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.8rem;
        border: 2px solid #00ACB1;
        color: #00ACB1;
        transition: all 0.3s;
    }

    .grade-filter a.active,
    .grade-filter a:hover {
        background: #00ACB1;
        color: white;
    }

    .nutri-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
    }

    .nutri-table thead th {
        background: #00ACB1;
        color: white;
        padding: 12px 15px;
        text-align: center;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .nutri-table tbody td {
        padding: 10px 15px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.88rem;
        text-align: center;
    }

    .nutri-table tbody tr:hover {
        background: #f8fafa;
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        color: white;
        display: inline-block;
    }

    @media (max-width: 768px) {
        .chart-grid2 {
            grid-template-columns: 1fr;
        }

        .status-cards {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<?php $reportDate = isset($_GET['report_date']) ? $_GET['report_date'] : date('Y-m-d'); ?>
<div class="nutri-container">
    <div class="nutri-header">
        <h2><i class="fa-solid fa-weight-scale" style="color: #00ACB1;"></i> Nutritional Status Report</h2>
        <form method="GET" id="dateForm"
            style="margin-bottom: 5px; display: inline-flex; align-items: center; gap: 8px; justify-content: center; flex-wrap: wrap;">
            <?php if (isset($_GET['grade'])): ?>
                <input type="hidden" name="grade" value="<?= htmlspecialchars($_GET['grade']) ?>">
            <?php endif; ?>
            <span style="color: #666; font-weight: bold;">Filter:</span>
            <select name="filter_month" onchange="document.getElementById('dateForm').submit();"
                style="border: 1px solid #ccc; padding: 4px 8px; border-radius: 4px; font-weight: bold; color: #333; font-size: 0.95rem; cursor: pointer; outline: none;">
                <option value="all" <?= $filterMonth == 'all' ? 'selected' : '' ?>>All Months</option>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $filterMonth == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                <?php endfor; ?>
            </select>
            <input type="number" name="filter_year" value="<?= htmlspecialchars($filterYear) ?>" min="2000" max="2099"
                onchange="document.getElementById('dateForm').submit();"
                style="border: 1px solid #ccc; padding: 4px 8px; border-radius: 4px; font-weight: bold; color: #333; font-size: 0.95rem; cursor: pointer; outline: none; width: 80px;">
            <button type="button" onclick="window.print()" class="no-print"
                style="background: #333; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 5px; margin-left: 5px;">
                <i class="fa-solid fa-print"></i> Print Report
            </button>
        </form>

        <style>
            @media print {

                nav,
                header,
                .grade-filter,
                .pagination-container,
                .no-print,
                #dateForm {
                    display: none !important;
                }

                .nutri-header p,
                .status-cards,
                .nutri-table,
                .chart-grid2 {
                    display: none !important;
                }

                .print-report-content {
                    display: block !important;
                }

                .nutri-container {
                    padding: 0;
                    margin: 0;
                    max-width: 100%;
                }
            }
        </style>
        <p style="color: #888;">BMI-based classification per DepEd standards</p>
    </div>

    <div class="status-cards no-print">
        <?php foreach (['Severely Wasted', 'Wasted', 'Normal', 'Overweight', 'Obese'] as $s):
            $pct = $totalWithData > 0 ? round($statusCounts[$s] / $totalWithData * 100, 1) : 0;
            ?>
            <div class="status-card" style="border-top-color: <?= getStatusColor($s) ?>;" onclick="viewStatus('<?= $s ?>')">
                <div class="s-value" style="color: <?= getStatusColor($s) ?>;">
                    <?= $statusCounts[$s] ?>
                </div>
                <div class="s-label">
                    <?= $s ?> (<?= $pct ?>%)
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="chart-grid2 no-print">
        <div class="chart-box2">
            <h3><i class="fa-solid fa-chart-pie"></i> Overall Distribution</h3>
            <canvas id="nutriPie" style="max-height: 280px;"></canvas>
        </div>
        <div class="chart-box2">
            <h3><i class="fa-solid fa-chart-bar"></i> By Grade Level</h3>
            <canvas id="gradeBar" style="max-height: 280px;"></canvas>
        </div>
    </div>

    <div class="grade-filter no-print">
        <a href="?grade=all" class="<?= $selectedGrade == 'all' ? 'active' : '' ?>">All Grades</a>
        <?php for ($g = 7; $g <= 12; $g++): ?>
            <a href="?grade=Grade+<?= $g ?>" class="<?= $selectedGrade == "Grade $g" ? 'active' : '' ?>">Grade
                <?= $g ?>
            </a>
        <?php endfor; ?>
    </div>

    <div class="no-print" style="overflow-x: auto; width: 100%; border-radius: 12px; box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);">
        <table class="nutri-table" style="box-shadow: none;">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Gender</th>
                    <th>Age</th>
                    <th>Grade</th>
                    <th>Height (cm)</th>
                    <th>Weight (kg)</th>
                    <th>BMI</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($paginatedStudents)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #999;">No student data found for
                            this filter.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($paginatedStudents as $s): ?>
                        <tr>
                            <td style="font-weight: 600;">
                                <?= htmlspecialchars($s['name']) ?>
                            </td>
                            <td>
                                <?= $s['gender'] ?>
                            </td>
                            <td>
                                <?= $s['age'] ?: '-' ?>
                            </td>
                            <td>
                                <?= $s['grade'] ?: '-' ?>
                            </td>
                            <td>
                                <?= $s['height'] > 0 ? $s['height'] : '-' ?>
                            </td>
                            <td>
                                <?= $s['weight'] > 0 ? $s['weight'] : '-' ?>
                            </td>
                            <td style="font-weight: 700;">
                                <?= $s['bmi'] > 0 ? $s['bmi'] : '-' ?>
                            </td>
                            <td><span class="status-badge" style="background: <?= getStatusColor($s['status']) ?>;">
                                    <?= $s['status'] ?>
                                </span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination-container no-print"
            style="display: flex; justify-content: flex-end; align-items: center; margin-top: 15px; gap: 10px;">
            <?php
            $qp = $_GET; // preserve query params
            $qp['page'] = $page - 1;
            $prevUrl = '?' . http_build_query($qp);
            $qp['page'] = $page + 1;
            $nextUrl = '?' . http_build_query($qp);
            ?>
            <a href="<?= $page > 1 ? $prevUrl : '#' ?>" class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>" <?= $page <= 1 ? 'onclick="return false;"' : '' ?>
                style="padding: 6px 12px; border-radius: 4px; background: <?= $page <= 1 ? '#eee' : '#00ACB1' ?>; color: <?= $page <= 1 ? '#999' : 'white' ?>; text-decoration: none;"><i
                    class="fa-solid fa-chevron-left"></i></a>
            <span class="pagination-info" style="font-weight: bold; color: #555; font-size: 0.9rem;">Page <?= $page ?> of
                <?= $totalPages ?></span>
            <a href="<?= $page < $totalPages ? $nextUrl : '#' ?>"
                class="pagination-btn <?= $page >= $totalPages ? 'disabled' : '' ?>" <?= $page >= $totalPages ? 'onclick="return false;"' : '' ?>
                style="padding: 6px 12px; border-radius: 4px; background: <?= $page >= $totalPages ? '#eee' : '#00ACB1' ?>; color: <?= $page >= $totalPages ? '#999' : 'white' ?>; text-decoration: none;"><i
                    class="fa-solid fa-chevron-right"></i></a>
        </div>
    <?php endif; ?>

    <!-- FULL PRINT REPORT (Hidden on Screen) -->
    <div class="print-report-content" style="display: none;">
        <h2 style="text-align: center; color: #00ACB1; margin-bottom: 20px;">Nutritional Status Summary Report</h2>
        <p style="text-align: center; font-weight: bold; margin-bottom: 30px;">
            Filter: <?= $filterMonth != 'all' ? date('F', mktime(0, 0, 0, $filterMonth, 1)) . ' ' : '' ?><?= $filterYear ?>
            <?= $selectedGrade != 'all' ? ' | ' . $selectedGrade : '' ?>
        </p>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
            <thead>
                <tr style="background: #f2f2f2;">
                    <th style="border: 1px solid #333; padding: 10px; text-align: left;">BMI Category</th>
                    <th style="border: 1px solid #333; padding: 10px; text-align: center;">Count</th>
                    <th style="border: 1px solid #333; padding: 10px; text-align: center;">Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (['Severely Wasted', 'Wasted', 'Normal', 'Overweight', 'Obese'] as $s):
                    $pct = $totalWithData > 0 ? round($statusCounts[$s] / $totalWithData * 100, 1) : 0;
                    ?>
                    <tr>
                        <td style="border: 1px solid #333; padding: 10px; font-weight: bold;">
                            <?= $s ?>
                        </td>
                        <td style="border: 1px solid #333; padding: 10px; text-align: center;">
                            <?= $statusCounts[$s] ?>
                        </td>
                        <td style="border: 1px solid #333; padding: 10px; text-align: center;">
                            <?= $pct ?>%
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background: #eee; font-weight: bold;">
                    <td style="border: 1px solid #333; padding: 10px;">TOTAL (with BMI data)</td>
                    <td style="border: 1px solid #333; padding: 10px; text-align: center;">
                        <?= $totalWithData ?>
                    </td>
                    <td style="border: 1px solid #333; padding: 10px; text-align: center;">100%</td>
                </tr>
            </tbody>
        </table>

        <?php foreach (['Severely Wasted', 'Wasted', 'Normal', 'Overweight', 'Obese'] as $category):
            $filtered = array_filter($students, function ($s) use ($category) {
                return $s['status'] === $category;
            });
            if (empty($filtered)) continue;
            ?>
            <div style="page-break-inside: avoid; margin-top: 30px;">
                <h3 style="color: <?= getStatusColor($category) ?>; border-bottom: 2px solid <?= getStatusColor($category) ?>; padding-bottom: 5px;">
                    <?= $category ?> List (<?= count($filtered) ?>)
                </h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background: #f9f9f9;">
                            <th style="border: 1px solid #999; padding: 6px;">Name</th>
                            <th style="border: 1px solid #999; padding: 6px;">Gender</th>
                            <th style="border: 1px solid #999; padding: 6px;">Age</th>
                            <th style="border: 1px solid #999; padding: 6px;">Grade</th>
                            <th style="border: 1px solid #999; padding: 6px;">BMI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered as $s): ?>
                            <tr>
                                <td style="border: 1px solid #999; padding: 6px;"><?= htmlspecialchars($s['name']) ?></td>
                                <td style="border: 1px solid #999; padding: 6px; text-align: center;"><?= $s['gender'] ?></td>
                                <td style="border: 1px solid #999; padding: 6px; text-align: center;"><?= $s['age'] ?: '-' ?></td>
                                <td style="border: 1px solid #999; padding: 6px; text-align: center;"><?= $s['grade'] ?: '-' ?></td>
                                <td style="border: 1px solid #999; padding: 6px; text-align: center; font-weight: bold;"><?= $s['bmi'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Category Students Modal -->
<div id="statusModal" class="modal-overlay"
    style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding: 10px;">
    <div class="modal-card view-modal"
        style="background:#fff; width:100%; max-width:90%; max-height:85vh; display:flex; flex-direction:column; border-radius:15px; position:relative; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
        <button onclick="document.getElementById('statusModal').style.display='none'"
            style="position:absolute; right:15px; top:15px; background:none; border:none; font-size:24px; cursor:pointer; color:#666; z-index:10;">&times;</button>
        <div style="padding: 25px 25px 15px 25px; display: flex; align-items: center; justify-content: space-between;">
            <button onclick="printNutriModal()"
                style="padding: 5px 12px; background: #00ACB1; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-family: sans-serif; white-space: nowrap;"><i
                    class="fa-solid fa-print"></i> Print</button>
            <h2 id="modalStatusTitle"
                style="color:#00ACB1; margin:0; font-family:'Cinzel', serif; text-align:center; padding-right: 70px; flex-grow: 1;">
                Category Students
            </h2>
        </div>
        <div id="modalPrintArea" style="overflow-x:auto; overflow-y:auto; padding: 0 25px 25px 25px; flex-grow:1;">
            <table class="nutri-table" style="width:100%; min-width:600px;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Grade</th>
                        <th>Height (cm)</th>
                        <th>Weight (kg)</th>
                        <th>BMI</th>
                    </tr>
                </thead>
                <tbody id="modalStatusBody">
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const allStudentsData = <?= json_encode($students, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    const statusColors = { 'Severely Wasted': '#e74c3c', 'Wasted': '#f39c12', 'Normal': '#2ecc71', 'Overweight': '#e67e22', 'Obese': '#c0392b' };

    function viewStatus(status) {
        const filtered = allStudentsData.filter(s => s.status === status);
        const tbody = document.getElementById('modalStatusBody');

        document.getElementById('modalStatusTitle').innerText = status + ' Students (' + filtered.length + ')';
        document.getElementById('modalStatusTitle').style.color = statusColors[status] || '#00ACB1';

        tbody.innerHTML = '';
        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 20px; color: #999;">No students in this category.</td></tr>';
        } else {
            filtered.forEach(s => {
                tbody.innerHTML += `
                    <tr>
                        <td style="font-weight:600;">${s.name}</td>
                        <td>${s.gender}</td>
                        <td>${s.age || '-'}</td>
                        <td>${s.grade || '-'}</td>
                        <td>${s.height > 0 ? s.height : '-'}</td>
                        <td>${s.weight > 0 ? s.weight : '-'}</td>
                        <td style="font-weight:700;">${s.bmi > 0 ? s.bmi : '-'}</td>
                    </tr>
                `;
            });
        }

        document.getElementById('statusModal').style.display = 'flex';
    }

    function printNutriModal() {
        var printContents = document.getElementById('modalPrintArea').innerHTML;
        var modalTitle = document.getElementById('modalStatusTitle').innerText;
        var printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Nutritional Status Report</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; padding: 20px; }');
        printWindow.document.write('h2 { color: #00ACB1; text-align: center; margin-bottom: 20px; }');
        printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; text-align: left; }');
        printWindow.document.write('th, td { border: 1px solid #000; padding: 8px; }');
        printWindow.document.write('th { background-color: #f2f2f2; font-weight: bold; }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        var rMonth = document.querySelector('select[name="filter_month"]');
        var rYear = document.querySelector('input[name="filter_year"]');
        var dateString = rYear.value;
        if (rMonth.value !== 'all') {
            dateString = rMonth.options[rMonth.selectedIndex].text + " " + rYear.value;
        } else {
            dateString = "Year " + rYear.value;
        }
        printWindow.document.write('<h2>' + modalTitle + '</h2>');
        printWindow.document.write('<p style="text-align:center; font-weight:bold; margin-top:-15px;">Filter: ' + dateString + '</p>');
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
    const labels = Object.keys(statusColors);
    const colors = Object.values(statusColors);
    const counts = <?= json_encode(array_values(array_intersect_key($statusCounts, array_flip(['Severely Wasted', 'Wasted', 'Normal', 'Overweight', 'Obese'])))) ?>;

    new Chart(document.getElementById('nutriPie'), {
        type: 'doughnut',
        data: { labels, datasets: [{ data: counts, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 12,
                        usePointStyle: true,
                        font: { size: 11, weight: 'bold' },
                        generateLabels: function (chart) {
                            const data = chart.data;
                            return data.labels.map(function (label, i) {
                                return {
                                    text: label + ' (' + data.datasets[0].data[i] + ')',
                                    fillStyle: data.datasets[0].backgroundColor[i],
                                    strokeStyle: data.datasets[0].backgroundColor[i],
                                    pointStyle: 'circle',
                                    hidden: false,
                                    index: i
                                };
                            });
                        }
                    }
                }
            }
        }
    });

    // Grade Level Stacked Bar
    const gradeLabels = <?= json_encode(array_keys($gradeStatusCounts)) ?>;
    const gradeData = <?= json_encode($gradeStatusCounts) ?>;
    const datasets = labels.map(status => ({
        label: status,
        data: gradeLabels.map(g => gradeData[g]?.[status] || 0),
        backgroundColor: statusColors[status],
        borderRadius: 3
    }));

    new Chart(document.getElementById('gradeBar'), {
        type: 'bar',
        data: { labels: gradeLabels, datasets },
        options: {
            responsive: true,
            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 10,
                        usePointStyle: true,
                        font: { size: 11, weight: 'bold' },
                        generateLabels: function (chart) {
                            return chart.data.datasets.map(function (ds, i) {
                                const total = ds.data.reduce((a, b) => a + b, 0);
                                return {
                                    text: ds.label + ' (' + total + ')',
                                    fillStyle: ds.backgroundColor,
                                    strokeStyle: ds.backgroundColor,
                                    pointStyle: 'circle',
                                    hidden: false,
                                    datasetIndex: i
                                };
                            });
                        }
                    }
                }
            }
        }
    });
</script>
</body>

</html>