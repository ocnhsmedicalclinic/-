<?php
require_once "../config/db.php";
requireLogin();

// ============ DATA COLLECTION ============
$currentYear = date('Y');
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : $currentYear;

// 1. TOP AILMENTS
// 3. INVENTORY STATS
$invStatus = ['Good' => 0, 'Low Stock' => 0, 'Out of Stock' => 0, 'Expired' => 0];
$invCategories = [];

$invSql = "SELECT *, DATEDIFF(expiry_date, CURDATE()) as days_to_expiry FROM inventory_items WHERE is_archived = 0";
$invRes = $conn->query($invSql);

if ($invRes) {
    while ($row = $invRes->fetch_assoc()) {
        // Categories
        $cat = !empty($row['category']) ? $row['category'] : 'Uncategorized';
        $invCategories[$cat] = ($invCategories[$cat] ?? 0) + 1;

        // Status
        if ($row['quantity'] == 0) {
            $invStatus['Out of Stock']++;
        } elseif (!empty($row['expiry_date']) && $row['days_to_expiry'] < 0) {
            $invStatus['Expired']++;
        } elseif ($row['quantity'] <= 10 && $row['quantity'] > 0) {
            $invStatus['Low Stock']++;
        } else {
            $invStatus['Good']++;
        }
    }
}

// 4. TOP AILMENTS (Existing Logic...)
$ailmentCounts = [];
$monthlyVisits = array_fill(1, 12, 0);
$gradeCounts = [];

// Also collect recent week ailments for daily report
$weekAilments = [];
$todayVisits = 0;
$today = date('Y-m-d');
$weekFrom = date('Y-m-d', strtotime('-7 days'));
$weekTo = $today;

$sql = "SELECT id, name, lrn as identifier, gender, birth_date, treatment_logs_json, health_exam_json, 'student' as ptype FROM students WHERE is_archived = 0 
        UNION ALL 
        SELECT id, name, employee_no as identifier, gender, birth_date, treatment_logs_json, '' as health_exam_json, 'employee' as ptype FROM employees WHERE is_archived = 0
        UNION ALL
        SELECT id, name, '' as identifier, gender, birth_date, treatment_logs_json, '' as health_exam_json, 'others' as ptype FROM others WHERE is_archived = 0";
$res = $conn->query($sql);

// 2. NUTRITIONAL STATUS
function parseMetric($value)
{
    if (empty($value))
        return 0;
    $num = preg_replace('/[^0-9.]/', '', $value);
    return floatval($num);
}
function classifyBMI($bmi, $age)
{
    if ($bmi <= 0 || $age <= 0)
        return 'No Data';
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
$nutriCounts = ['Severely Wasted' => 0, 'Wasted' => 0, 'Normal' => 0, 'Overweight' => 0, 'Obese' => 0, 'No Data' => 0];
$todayVisitDetails = [];

while ($row = $res->fetch_assoc()) {
    // Treatment logs
    $logs = json_decode($row['treatment_logs_json'] ?? '[]', true);
    if (is_array($logs)) {
        foreach ($logs as $log) {
            $logYear = isset($log['date']) ? date('Y', strtotime($log['date'])) : null;

            if ($logYear == $selectedYear) {
                if (!empty($log['assessment'])) {
                    $c = strtolower(trim($log['assessment']));
                    $ailmentCounts[$c] = ($ailmentCounts[$c] ?? 0) + 1;
                }
                if (!empty($log['date'])) {
                    $monthlyVisits[(int) date('n', strtotime($log['date']))]++;
                    if (date('Y-m-d', strtotime($log['date'])) == $today) {
                        $todayVisits++;
                        
                        // Collect visit details for the modal
                        $assessment = $log['assessment'] ?? '';
                        $treatmentList = [];
                        if (!empty($log['plan'])) $treatmentList[] = $log['plan'] . (!empty($log['quantity']) ? " (".$log['quantity'].")" : "");
                        elseif (!empty($log['treatment'])) $treatmentList[] = $log['treatment'] . (!empty($log['quantity']) ? " (".$log['quantity'].")" : "");
                        
                        if (!empty($log['plan2'])) $treatmentList[] = $log['plan2'] . (!empty($log['quantity2']) ? " (".$log['quantity2'].")" : "");
                        if (!empty($log['plan3'])) $treatmentList[] = $log['plan3'] . (!empty($log['quantity3']) ? " (".$log['quantity3'].")" : "");
                        
                        $fullTreatment = $assessment;
                        if (!empty($treatmentList)) {
                            $fullTreatment .= ($fullTreatment ? " — " : "") . implode(", ", $treatmentList);
                        }

                        $todayVisitDetails[] = [
                            'name' => $row['name'],
                            'identifier' => $row['identifier'] ?: 'N/A',
                            'treatment' => $fullTreatment ?: 'N/A',
                            'type' => $row['ptype']
                        ];
                    }
                }
                if ($row['ptype'] == 'employee') {
                    $g = 'Staff';
                } else {
                    $g = !empty($log['grade']) ? 'Grade ' . $log['grade'] : 'Others';
                }
                $gradeCounts[$g] = ($gradeCounts[$g] ?? 0) + 1;
            }

            // Weekly ailments
            if (!empty($log['date']) && !empty($log['assessment'])) {
                $ld = date('Y-m-d', strtotime($log['date']));
                if ($ld >= $weekFrom && $ld <= $weekTo) {
                    $wc = ucfirst(strtolower(trim($log['assessment'])));
                    $weekAilments[$wc] = ($weekAilments[$wc] ?? 0) + 1;
                }
            }
        }
    }

    // Nutritional
    $health = json_decode($row['health_exam_json'] ?? '{}', true);
    $height = 0;
    $weight = 0;
    for ($g = 12; $g >= 7; $g--) {
        $h = parseMetric($health["height_$g"] ?? '');
        $w = parseMetric($health["weight_$g"] ?? '');
        if ($h > 0 && $w > 0) {
            $height = $h;
            $weight = $w;
            break;
        }
    }
    $age = 0;
    if ($row['birth_date']) {
        $age = (new DateTime($row['birth_date']))->diff(new DateTime('today'))->y;
    }
    $bmi = 0;
    if ($height > 0 && $weight > 0) {
        $hm = $height > 3 ? $height / 100 : $height;
        if ($hm > 0)
            $bmi = round($weight / ($hm * $hm), 1);
    }
    $status = classifyBMI($bmi, $age);
    $nutriCounts[$status]++;
}

arsort($ailmentCounts);
$topAilments = array_slice($ailmentCounts, 0, 8, true);
ksort($gradeCounts);
arsort($weekAilments);

$totalStudents = $conn->query("SELECT COUNT(*) as c FROM students WHERE is_archived = 0")->fetch_assoc()['c'];
$totalEmployees = $conn->query("SELECT COUNT(*) as c FROM employees WHERE is_archived = 0")->fetch_assoc()['c'];
$totalVisitsYear = array_sum($monthlyVisits);
$topComplaint = !empty($topAilments) ? ucfirst(array_key_first($topAilments)) : 'N/A';
$nutriTotal = $nutriCounts['Severely Wasted'] + $nutriCounts['Wasted'] + $nutriCounts['Normal'] + $nutriCounts['Overweight'] + $nutriCounts['Obese'];

$yearsRes = $conn->query("SELECT DISTINCT YEAR(created_at) as yr FROM students ORDER BY yr DESC");
$availableYears = [];
while ($y = $yearsRes->fetch_assoc())
    $availableYears[] = $y['yr'];
if (!in_array($currentYear, $availableYears))
    array_unshift($availableYears, $currentYear);

$nutriColors = ['Severely Wasted' => '#e74c3c', 'Wasted' => '#f39c12', 'Normal' => '#2ecc71', 'Overweight' => '#e67e22', 'Obese' => '#c0392b'];

include "index_layout.php";
?>

<style>
    .db {
        max-width: 1400px;
        margin: 0 auto;
        padding: 15px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .db-head {
        text-align: center;
        margin-bottom: 12px;
    }

    .db-head h2 {
        color: #333;
        margin: 0;
        font-size: 1.2rem;
    }

    .db-head p {
        color: #888;
        margin: 2px 0 0;
        font-size: 0.8rem;
    }

    .yr-f {
        display: flex;
        justify-content: center;
        gap: 6px;
        margin-bottom: 12px;
    }

    .yr-f a {
        padding: 4px 14px;
        border-radius: 15px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.75rem;
        border: 2px solid #00ACB1;
        color: #00ACB1;
        transition: all 0.2s;
    }

    .yr-f a.active,
    .yr-f a:hover {
        background: #00ACB1;
        color: white;
        text-decoration: none;
    }

    .yr-f a:hover {
        background: #00ACB1;
        color: white;
    }

    .stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin-bottom: 15px;
    }

    .st {
        background: white;
        border-radius: 8px;
        padding: 12px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        border-left: 3px solid #00ACB1;
        transition: transform 0.2s;
    }

    .st:hover {
        transform: translateY(-2px);
    }

    #visitsTodayCard {
        cursor: pointer;
        position: relative;
    }

    #visitsTodayCard:hover {
        background: #f8fbfb;
        border-bottom: 2px solid #00ACB1;
    }

    .st .sv {
        font-size: 1.4rem;
        font-weight: 800;
        color: #333;
    }

    .st .sl {
        font-size: 0.7rem;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 2px;
    }

    .st:nth-child(2) {
        border-left-color: #f39c12;
    }

    .st:nth-child(3) {
        border-left-color: #e74c3c;
    }

    .cg {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 15px;
    }

    .cb {
        background: white;
        border-radius: 8px;
        padding: 14px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .cb h3 {
        margin: 0 0 8px;
        font-size: 0.85rem;
        color: #333;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .cb h3 i {
        color: #00ACB1;
        font-size: 0.85rem;
    }

    .cf {
        grid-column: 1 / -1;
    }

    /* Section Divider */
    .sec-div {
        margin: 15px 0 10px;
        padding-bottom: 6px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .sec-div h3 {
        margin: 0;
        font-size: 0.9rem;
        color: #333;
    }

    .sec-div i {
        color: #00ACB1;
    }

    /* Ailment Tags */
    .atags {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }

    .atag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        background: #e8f8f8;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 600;
        color: #333;
    }

    .atag .ac {
        background: #00ACB1;
        color: white;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.6rem;
        font-weight: 800;
    }

    /* Nutri Status Mini Cards */
    .nutri-row {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 5px;
    }

    @media (max-width: 1024px) {
        .db {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .cg {
            grid-template-columns: 1fr;
        }

        .stats {
            grid-template-columns: 1fr 1fr;
        }

        .nutri-row {
            grid-template-columns: default;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
        }

        .db-head {
            flex-direction: column;
            gap: 15px;
        }
    }
</style>

<div class="db">

    <!-- LEFT COLUMN: Main Stats & Charts -->
    <div class="db-left" style="display: flex; flex-direction: column; gap: 15px;">
        <div class="db-head"
            style="text-align: left; display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <div>
                <h2 style="font-size: 1.3rem;"><i class="fa-solid fa-chart-line" style="color: #00ACB1;"></i> Clinic
                    Dashboard</h2>
                <p style="margin: 0; font-size: 0.85rem; color: #777;">Analytics Overview — <?= $selectedYear ?></p>
            </div>
            <div class="yr-f" style="margin: 0;">
                <?php foreach ($availableYears as $yr): ?>
                    <a href="?year=<?= $yr ?>" class="<?= $yr == $selectedYear ? 'active' : '' ?>"><?= $yr ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Public Holiday / Class Suspension Widget -->
        <div id="holidayWidget"
            style="background: #e3f2fd; border-left: 5px solid #2196f3; padding: 15px; border-radius: 8px; display: none; align-items: center; gap: 15px;">
            <i class="fa-solid fa-calendar-day" style="font-size: 24px; color: #1976d2;"></i>
            <div>
                <strong style="color: #1565c0; font-size: 1.1em;" id="holidayName">Holiday</strong>
                <p style="margin: 3px 0 0; color: #555; font-size: 0.9em;" id="holidayDate">Today is a special day.</p>
            </div>
        </div>

        <!-- Population Stats Container -->
        <div class="stats" style="margin: 0 0 10px 0;">
            <div class="st">
                <div class="sv" style="font-size: 1.2rem;"><?= number_format($totalStudents) ?></div>
                <div class="sl" style="font-size: 0.65rem;">Total Students</div>
            </div>
            <div class="st" style="border-left-color: #8e44ad;">
                <div class="sv" style="font-size: 1.2rem;"><?= number_format($totalEmployees) ?></div>
                <div class="sl" style="font-size: 0.65rem;">Total Employees</div>
            </div>
        </div>

        <!-- Clinic Stats Container -->
        <div class="stats" style="margin: 0;">
            <div class="st" id="visitsTodayCard" onclick="openVisitsModal()" style="border-left-color: #f39c12;">
                <div class="sv" style="font-size: 1.2rem;"><?= number_format($todayVisits) ?></div>
                <div class="sl" style="font-size: 0.65rem;">Visits Today</div>
                <div style="font-size: 0.5rem; color: #00ACB1; margin-top: 5px;"><i class="fa-solid fa-circle-info"></i> Click to view</div>
            </div>
            <div class="st" style="border-left-color: #e74c3c;">
                <div class="sv" style="font-size: 1.2rem;"><?= $topComplaint ?></div>
                <div class="sl" style="font-size: 0.65rem;">Top Complaint</div>
            </div>
        </div>

        <div class="cg" style="margin: 0;">
            <div class="cb" style="padding: 10px;">
                <h3 style="font-size: 0.8rem; margin-bottom: 5px;"><i class="fa-solid fa-chart-area"></i> Monthly Visits
                    Trend</h3>
                <canvas id="visitsChart" height="110"></canvas>
            </div>
            <div class="cb" style="padding: 10px;">
                <h3 style="font-size: 0.8rem; margin-bottom: 5px;"><i class="fa-solid fa-users"></i> Visits by Grade
                </h3>
                <canvas id="gradeChart" height="110"></canvas>
            </div>
        </div>

        <div class="cb" style="padding: 10px; flex: 1;">
            <h3 style="font-size: 0.8rem; margin-bottom: 5px;"><i class="fa-solid fa-disease"></i> Top Ailments (Yearly)
            </h3>
            <canvas id="ailmentsChart" height="110"></canvas>
        </div>
    </div>

    <!-- RIGHT COLUMN: Breakdowns & Summary Reports -->
    <div class="db-right" style="display: flex; flex-direction: column; gap: 15px;">

        <!-- Ailments This Week (Compact) -->
        <div class="cb" style="padding: 15px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="font-size: 0.85rem; margin: 0;"><i class="fa-solid fa-virus"></i> Ailments This Week</h3>
                <a href="daily_ailments.php"
                    style="font-size: 0.7rem; color: #00ACB1; text-decoration: none; font-weight: 600;">Full Report
                    →</a>
            </div>
            <!-- Neural AI Alert System -->
            <div id="aiOutbreakAlert" style="display: none; margin-bottom: 12px;"></div>

            <?php if (!empty($weekAilments)): ?>
                <?php
                $alertName = null;
                foreach ($weekAilments as $n => $cnt) {
                    if ($cnt >= 10) {
                        $alertName = $n;
                        break;
                    }
                }
                ?>
                <?php if ($alertName && !isset($_GET['ai_override'])): // Fallback if AI hasn't loaded yet ?>
                    <div id="fallbackAlert"
                        style="background: #fff3cd; border-left: 4px solid #f39c12; padding: 6px 10px; border-radius: 5px; margin-bottom: 8px; font-size: 0.75rem; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-triangle-exclamation" style="color: #f39c12;"></i>
                        <span><strong>Statistical Alert:</strong> <?= htmlspecialchars($alertName) ?> (<?= $weekAilments[$alertName] ?>
                            cases)</span>
                    </div>
                <?php endif; ?>
                <div class="atags" style="gap: 4px;">
                    <?php foreach (array_slice($weekAilments, 0, 8, true) as $name => $count): ?>
                        <span class="atag" style="font-size: 0.7rem; padding: 2px 8px;"><?= htmlspecialchars($name) ?> <span
                                class="ac" style="width: 14px; height: 14px; font-size: 0.55rem;"><?= $count ?></span></span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 10px; color: #999; font-size: 0.75rem;">No cases this week.</div>
            <?php endif; ?>
        </div>

        <!-- Nutritional Status -->
        <div class="cb" style="padding: 15px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="font-size: 0.85rem; margin: 0;"><i class="fa-solid fa-weight-scale"></i> Nutritional Status
                </h3>
                <a href="nutritional_status.php"
                    style="font-size: 0.7rem; color: #00ACB1; text-decoration: none; font-weight: 600;">Full Report
                    →</a>
            </div>

            <div class="nutri-row" style="margin-bottom: 15px; display: flex; justify-content: center; text-align: center;">
                <?php foreach (['Severely Wasted', 'Wasted', 'Normal', 'Overweight', 'Obese'] as $s):
                    $pct = $nutriTotal > 0 ? round($nutriCounts[$s] / $nutriTotal * 100, 1) : 0; ?>
                    <div class="nutri-mini" style="border-top-color: <?= $nutriColors[$s] ?>; padding: 5px; min-width: 0; flex: 1; max-width: 80px;">
                        <div class="nv" style="color: <?= $nutriColors[$s] ?>; font-size: 1.1rem; font-weight: bold;"><?= $nutriCounts[$s] ?>
                        </div>
                        <div class="nl"
                            style="font-size: 0.55rem; font-weight: 600; color: #333;"
                            title="<?= $s ?>"><?= $s ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cg">
                <div><canvas id="nutriPie" height="120"></canvas></div>
                <div><canvas id="nutriBar" height="120"></canvas></div>
            </div>
        </div>

        <!-- Inventory -->
        <div class="cb" style="padding: 15px; flex: 1;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="font-size: 0.85rem; margin: 0;"><i class="fa-solid fa-boxes-stacked"></i> Inventory Overview
                </h3>
                <a href="inventory.php"
                    style="font-size: 0.7rem; color: #00ACB1; text-decoration: none; font-weight: 600;">Manage →</a>
            </div>

            <div class="cg" style="height: 100%; align-items: center;">
                <div>
                    <h4 style="font-size: 0.7rem; color: #666; text-align: center; margin: 0 0 5px 0;">Stock Status</h4>
                    <canvas id="invStatusChart" height="150"></canvas>
                </div>
                <div>
                    <h4 style="font-size: 0.7rem; color: #666; text-align: center; margin: 0 0 5px 0;">Categories</h4>
                    <canvas id="invCatChart" height="150"></canvas>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const tp = ['#00ACB1', '#008e91', '#00c9ce', '#33bfc3', '#66d4d7', '#99e3e5', '#f39c12', '#e74c3c', '#3498db', '#2ecc71'];

    window.ailmentsChart = new Chart(document.getElementById('ailmentsChart'), {
        type: 'bar',
        data: { labels: <?= json_encode(array_map('ucfirst', array_keys($topAilments))) ?>, datasets: [{ data: <?= json_encode(array_values($topAilments)) ?>, backgroundColor: tp, borderRadius: 4, borderSkipped: false }] },
        options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });

    const gradeColors = ['#e74c3c', '#f39c12', '#2ecc71', '#3498db', '#9b59b6', '#e67e22', '#1abc9c', '#d35400', '#c0392b', '#27ae60'];
    const gradeLabels = <?= json_encode(array_keys($gradeCounts)) ?>;
    const gradeData = <?= json_encode(array_values($gradeCounts)) ?>;

    window.gradeChart = new Chart(document.getElementById('gradeChart'), {
        type: 'doughnut',
        data: { labels: gradeLabels, datasets: [{ data: gradeData, backgroundColor: gradeColors.slice(0, gradeLabels.length), borderWidth: 2, borderColor: '#fff' }] },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 10,
                        usePointStyle: true,
                        font: { size: 11, weight: 'bold' },
                        generateLabels: function (chart) {
                            const data = chart.data;
                            return data.labels.map(function (label, i) {
                                return {
                                    text: label + ' (' + data.datasets[0].data[i] + ')',
                                    fillStyle: data.datasets[0].backgroundColor[i],
                                    strokeStyle: data.datasets[0].backgroundColor[i],
                                    fontColor: document.body.classList.contains('dark-mode') ? '#e0e0e0' : '#333', /* ChartJS 2/3 */
                                    color: document.body.classList.contains('dark-mode') ? '#e0e0e0' : '#333', /* ChartJS 4+ */
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

    window.visitsChart = new Chart(document.getElementById('visitsChart'), {
        type: 'line',
        data: { labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], datasets: [{ data: <?= json_encode(array_values($monthlyVisits)) ?>, borderColor: '#00ACB1', backgroundColor: 'rgba(0,172,177,0.1)', fill: true, tension: 0.4, pointBackgroundColor: '#00ACB1', pointRadius: 4, pointHoverRadius: 6, borderWidth: 2 }] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });

    // INVENTORY CHARTS
    const invStatusLabels = <?= json_encode(array_keys($invStatus)) ?>;
    const invStatusData = <?= json_encode(array_values($invStatus)) ?>;
    const invStatusColors = ['#2ecc71', '#f39c12', '#95a5a6', '#e74c3c']; // Good, Low, Out, Expired

    window.invStatusChart = new Chart(document.getElementById('invStatusChart'), {
        type: 'doughnut',
        data: { labels: invStatusLabels, datasets: [{ data: invStatusData, backgroundColor: invStatusColors, borderWidth: 2, borderColor: '#fff' }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { padding: 10, usePointStyle: true, font: { size: 11 } } } } }
    });

    const invCatLabels = <?= json_encode(array_keys($invCategories)) ?>;
    const invCatData = <?= json_encode(array_values($invCategories)) ?>;
    const invCatColors = ['#00ACB1', '#3498db', '#9b59b6', '#f1c40f', '#e67e22'];

    window.invCatChart = new Chart(document.getElementById('invCatChart'), {
        type: 'bar',
        data: { labels: invCatLabels, datasets: [{ data: invCatData, backgroundColor: invCatColors, borderRadius: 4 }] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });

    const nc = <?= json_encode(array_values(array_intersect_key($nutriCounts, array_flip(['Severely Wasted', 'Wasted', 'Normal', 'Overweight', 'Obese'])))) ?>;
    const nl = ['Severely Wasted', 'Wasted', 'Normal', 'Overweight', 'Obese'];
    const ncolors = ['#e74c3c', '#f39c12', '#2ecc71', '#e67e22', '#c0392b'];

    window.nutriPieChart = new Chart(document.getElementById('nutriPie'), {
        type: 'doughnut',
        data: { labels: nl, datasets: [{ data: nc, backgroundColor: ncolors, borderWidth: 2, borderColor: '#fff' }] },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { padding: 15, usePointStyle: true, font: { size: 10 } } } } }
    });

    window.nutriBarChart = new Chart(document.getElementById('nutriBar'), {
        type: 'bar',
        data: { labels: nl, datasets: [{ data: nc, backgroundColor: ncolors, borderRadius: 4 }] },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });
</script>
<script>
    // Fetch Public Holidays (Philippines)
    document.addEventListener('DOMContentLoaded', function () {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        const dateStr = `${year}-${month}-${day}`;

        // Using Nager.Date API (Free, no key required)
        fetch(`https://date.nager.at/api/v3/PublicHolidays/${year}/PH`)
            .then(response => response.json())
            .then(data => {
                const holiday = data.find(h => h.date === dateStr);
                if (holiday) {
                    const widget = document.getElementById('holidayWidget');
                    const nameEl = document.getElementById('holidayName');
                    const dateEl = document.getElementById('holidayDate');

                    widget.style.display = 'flex';
                    nameEl.innerText = holiday.name; // e.g., "Independence Day"
                    dateEl.innerText = `Public Holiday - ${holiday.localName || 'Regular Holiday'}`;

                    // Style adjustments for holiday
                    widget.style.background = '#fff3e0';
                    widget.style.borderLeftColor = '#ff9800';
                    widget.querySelector('i').className = 'fa-solid fa-flag';
                    widget.querySelector('i').style.color = '#e65100';
                    nameEl.style.color = '#e65100';
                } else {
                    // Check for weekends (Suspension simulation)
                    const dayOfWeek = today.getDay(); // 0 = Sun, 6 = Sat
                    if (dayOfWeek === 0 || dayOfWeek === 6) {
                        const widget = document.getElementById('holidayWidget');
                        const nameEl = document.getElementById('holidayName');
                        const dateEl = document.getElementById('holidayDate');

                        widget.style.display = 'flex';
                        nameEl.innerText = "Weekend - No Classes";
                        dateEl.innerText = "Clinic operations may be limited.";

                        widget.style.background = '#f3e5f5';
                        widget.style.borderLeftColor = '#9c27b0';
                        widget.querySelector('i').className = 'fa-solid fa-mug-hot';
                        widget.querySelector('i').style.color = '#4a148c';
                        nameEl.style.color = '#6a1b9a';
                    }
                }
            })
            .catch(err => console.error("Holiday API Error:", err));

        // REAL-TIME DASHBOARD UPDATES
        const selectedYear = <?= $selectedYear ?>;

        function updateCharts() {
            fetch(`api/dashboard_stats.php?year=${selectedYear}`)
                .then(response => response.json())
                .then(data => {
                    // Update Ailments Chart
                    if (window.ailmentsChart) {
                        const ailmentLabels = Object.keys(data.ailments).map(s => s);
                        const ailmentData = Object.values(data.ailments);
                        window.ailmentsChart.data.labels = ailmentLabels;
                        window.ailmentsChart.data.datasets[0].data = ailmentData;
                        window.ailmentsChart.update();
                    }

                    // Update Grade Chart
                    if (window.gradeChart) {
                        const gradeLabels = Object.keys(data.grades);
                        const gradeData = Object.values(data.grades);
                        window.gradeChart.data.labels = gradeLabels;
                        window.gradeChart.data.datasets[0].data = gradeData;
                        window.gradeChart.update();
                    }

                    // Update Visits Chart
                    if (window.visitsChart) {
                        window.visitsChart.data.datasets[0].data = Object.values(data.visits);
                        window.visitsChart.update();
                    }

                    // Update Inventory Status Chart
                    if (window.invStatusChart) {
                        const invStatusData = [
                            data.inventory.status['Good'] || 0,
                            data.inventory.status['Low Stock'] || 0,
                            data.inventory.status['Out of Stock'] || 0,
                            data.inventory.status['Expired'] || 0
                        ];
                        window.invStatusChart.data.datasets[0].data = invStatusData;
                        window.invStatusChart.update();
                    }

                    // Update Inventory Categories Chart
                    if (window.invCatChart) {
                        const invCatLabels = Object.keys(data.inventory.categories);
                        const invCatData = Object.values(data.inventory.categories);
                        window.invCatChart.data.labels = invCatLabels;
                        window.invCatChart.data.datasets[0].data = invCatData;
                        window.invCatChart.update();
                    }

                    // Update Nutri Charts
                    if (window.nutriPieChart && window.nutriBarChart) {
                        const nutriLabels = ['Severely Wasted', 'Wasted', 'Normal', 'Overweight', 'Obese'];
                        const nutriData = nutriLabels.map(l => data.nutri_status[l] || 0);

                        window.nutriPieChart.data.datasets[0].data = nutriData;
                        window.nutriPieChart.update();

                        window.nutriBarChart.data.datasets[0].data = nutriData;
                        window.nutriBarChart.update();
                    }
                })
                .catch(err => console.error("Error fetching dashboard stats:", err));
        }

        // Run every 5 seconds
        setInterval(updateCharts, 5000);

        // Fetch AI Outbreak Risk
        function fetchAiOutbreakRisk() {
            fetch('api/ai_suggestions.php?action=outbreak_risk')
                .then(res => res.json())
                .then(data => {
                    if (data.risk_level && data.risk_level !== 'Low') {
                        const alertBox = document.getElementById('aiOutbreakAlert');
                        const fallback = document.getElementById('fallbackAlert');
                        if (fallback) fallback.style.display = 'none';

                        const color = data.risk_level === 'High' ? '#dc2626' : '#d97706';
                        const bg = data.risk_level === 'High' ? '#fef2f2' : '#fffbeb';
                        const border = data.risk_level === 'High' ? '#fecaca' : '#fef3c7';

                        alertBox.innerHTML = `
                            <div style="background: ${bg}; border: 1px solid ${border}; border-left: 4px solid ${color}; padding: 10px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 8px; color: ${color}; font-weight: 800; font-size: 0.8em; margin-bottom: 4px;">
                                    <i class="fa-solid fa-brain"></i> 
                                    <span>AI NEURAL INSIGHT: ${data.risk_level.toUpperCase()} RISK</span>
                                </div>
                                <div style="font-size: 0.75em; color: #444; line-height: 1.4;">
                                    <strong>${data.message}</strong><br>
                                    <span style="font-style: italic; color: #666;">"${data.rationale_insight}"</span>
                                </div>
                            </div>
                        `;
                        alertBox.style.display = 'block';
                    }
                })
                .catch(console.error);
        }
        fetchAiOutbreakRisk();
    });

    function openVisitsModal() {
        document.getElementById('visitsModal').style.display = 'flex';
    }

    function closeVisitsModal() {
        document.getElementById('visitsModal').style.display = 'none';
    }

    // Close modal on click outside
    window.onclick = function(event) {
        const modal = document.getElementById('visitsModal');
        if (event.target == modal) {
            closeVisitsModal();
        }
    }
</script>

<!-- Visits Today Modal -->
<div id="visitsModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="modal-card" style="background: white; padding: 25px; border-radius: 12px; max-width: 850px; width: 90%; max-height: 85vh; overflow-y: auto; position: relative; box-shadow: 0 15px 35px rgba(0,0,0,0.2); animation: modalSlideUp 0.3s ease-out;">
        <button onclick="closeVisitsModal()" style="position: absolute; top: 15px; right: 20px; border: none; background: none; font-size: 28px; cursor: pointer; color: #999; transition: 0.2s;" onmouseover="this.style.color='#333'" onmouseout="this.style.color='#999'">&times;</button>
        
        <h2 style="color: #00ACB1; margin: 0 0 5px 0; font-family: 'Cinzel', serif; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-users" style="font-size: 1.2rem;"></i> Patients Today
        </h2>
        <p style="margin: 0 0 20px 0; color: #777; font-size: 0.85rem;"><?= date('l, F d, Y') ?></p>

        <div style="overflow-x: auto; border-radius: 8px; border: 1px solid #eee;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead>
                    <tr style="background: #00ACB1; color: white;">
                        <th style="padding: 12px; text-align: left;">Name</th>
                        <th style="padding: 12px; text-align: left;">LRN / ID</th>
                        <th style="padding: 12px; text-align: left;">Type</th>
                        <th style="padding: 12px; text-align: left;">Treatment / Assessment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($todayVisitDetails)): ?>
                        <tr><td colspan="4" style="text-align: center; padding: 40px; color: #999;">
                            <i class="fa-solid fa-calendar-day" style="font-size: 3rem; opacity: 0.1; display: block; margin-bottom: 10px;"></i>
                            No patient visits recorded for today.
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($todayVisitDetails as $v): ?>
                            <tr style="border-bottom: 1px solid #eee; transition: 0.2s;" onmouseover="this.style.background='#f9f9f9'" onmouseout="this.style.background='white'">
                                <td style="padding: 12px; font-weight: bold; color: #333; text-transform: uppercase;"><?= htmlspecialchars($v['name']) ?></td>
                                <td style="padding: 12px; font-family: monospace; color: #555;"><?= htmlspecialchars($v['identifier']) ?></td>
                                <td style="padding: 12px;">
                                    <span style="background: <?= $v['type'] == 'employee' ? '#795548' : '#00ACB1' ?>; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.65rem; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <?= $v['type'] ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; color: #666; font-size: 0.8rem; line-height: 1.4;"><?= htmlspecialchars($v['treatment']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; text-align: right;">
            <button onclick="closeVisitsModal()" style="padding: 10px 25px; background: #f0f0f0; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; color: #666;">Close</button>
        </div>
    </div>
</div>

<style>
    @keyframes modalSlideUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    body.dark-mode .modal-card {
        background: #242526 !important;
        border: 1px solid #3a3b3c;
    }
    
    body.dark-mode .modal-card h2 { color: #00d2d8 !important; }
    body.dark-mode .modal-card p { color: #aaa !important; }
    body.dark-mode table tr { border-bottom-color: #3a3b3c !important; }
    body.dark-mode table tr:hover { background: #3a3b3c !important; }
    body.dark-mode td { color: #e4e6eb !important; }
    body.dark-mode td[style*="color: #666"] { color: #b0b3b8 !important; }
    body.dark-mode .modal-card button[style*="background: #f0f0f0"] { background: #3a3b3c !important; color: #e4e6eb !important; }
</style>
</body>

</html>