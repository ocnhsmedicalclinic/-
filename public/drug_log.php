<?php
require_once "../config/db.php";
requireLogin();

$filterMonth = $_GET['month'] ?? date('Y-m');
$startDate = $filterMonth . '-01';
$endDate = date("Y-m-t", strtotime($startDate));
$searchMed = $_GET['medicine'] ?? '';

// Helper to extract med logs from JSON more robustly
function extractMedicationLogs($rows, $pName, $pType, $pIdentifier, $startDate, $endDate, $searchMed)
{
    $results = [];
    foreach ($rows as $log) {
        $logDate = $log['date'] ?? '';
        if (empty($logDate))
            continue;

        // Month/Year check
        if ($logDate < $startDate || $logDate > $endDate)
            continue;

        // Check columns Plan 1, 2, 3
        $slots = [
            ['p' => $log['plan'] ?? ($log['treatment'] ?? ''), 'q' => $log['quantity'] ?? 1, 'a' => $log['attended'] ?? 'Staff'],
            ['p' => $log['plan2'] ?? '', 'q' => $log['quantity2'] ?? 1, 'a' => $log['attended2'] ?? 'Staff'],
            ['p' => $log['plan3'] ?? '', 'q' => $log['quantity3'] ?? 1, 'a' => $log['attended3'] ?? 'Staff']
        ];

        foreach ($slots as $slot) {
            $med = trim($slot['p']);
            $qty = intval($slot['q']);
            if (!empty($med)) {
                // Search filter (Matches patient name, ID, or medicine)
                if (!empty($searchMed)) {
                    $searchTerms = [
                        $med,
                        $pName,
                        $pIdentifier,
                        $log['assessment'] ?? '',
                        $slot['a'] // Attended by
                    ];
                    $match = false;
                    foreach ($searchTerms as $term) {
                        if (stripos($term, $searchMed) !== false) {
                            $match = true;
                            break;
                        }
                    }
                    if (!$match)
                        continue;
                }

                $results[] = [
                    'date' => $logDate,
                    'name' => $pName,
                    'type' => $pType,
                    'identifier' => $pIdentifier,
                    'medicine' => $med,
                    'quantity' => $qty,
                    'attended' => $slot['a'],
                    'assessment' => $log['assessment'] ?? 'N/A'
                ];
            }
        }
    }
    return $results;
}

$allMedLogs = [];
$res = $conn->query("SELECT name, lrn, treatment_logs_json FROM students WHERE is_archived = 0");
while ($row = $res->fetch_assoc()) {
    $logs = json_decode($row['treatment_logs_json'] ?? '[]', true);
    $allMedLogs = array_merge($allMedLogs, extractMedicationLogs($logs, $row['name'], 'Student', $row['lrn'], $startDate, $endDate, $searchMed));
}
$res = $conn->query("SELECT name, employee_no, treatment_logs_json FROM employees WHERE is_archived = 0");
while ($row = $res->fetch_assoc()) {
    $logs = json_decode($row['treatment_logs_json'] ?? '[]', true);
    $allMedLogs = array_merge($allMedLogs, extractMedicationLogs($logs, $row['name'], 'Employee', $row['employee_no'], $startDate, $endDate, $searchMed));
}
$res = $conn->query("SELECT name, treatment_logs_json FROM others WHERE is_archived = 0");
while ($row = $res->fetch_assoc()) {
    $logs = json_decode($row['treatment_logs_json'] ?? '[]', true);
    $allMedLogs = array_merge($allMedLogs, extractMedicationLogs($logs, $row['name'], 'Other', 'N/A', $startDate, $endDate, $searchMed));
}

// 4. Include manual inventory transactions (Stock Out, Dispensed)
$transQuery = "SELECT t.*, i.name as med_name, i.category 
              FROM inventory_transactions t 
              JOIN inventory_items i ON t.item_id = i.id 
              WHERE (t.type = 'Stock Out' OR t.type = 'Dispensed')
              AND t.transaction_date >= '$startDate 00:00:00' 
              AND t.transaction_date <= '$endDate 23:59:59'";

if ($searchMed) {
    $transQuery .= " AND (i.name LIKE '%$searchMed%' OR t.remarks LIKE '%$searchMed%')";
}

$transRes = $conn->query($transQuery);
while ($t = $transRes->fetch_assoc()) {
    // Only add if not already captured (Manual transactions usually have distinct remarks)
    // To avoid double counting with automated treatment logs, we identify them by remarks
    if (strpos($t['remarks'], 'Used in treatment for') !== false)
        continue;

    $allMedLogs[] = [
        'date' => date('Y-m-d', strtotime($t['transaction_date'])),
        'name' => 'Inventory System',
        'type' => 'Manual deduction',
        'identifier' => 'N/A',
        'medicine' => $t['med_name'],
        'quantity' => $t['quantity'],
        'attended' => 'System/Admin',
        'assessment' => $t['remarks'] ?: $t['type']
    ];
}

usort($allMedLogs, fn($a, $b) => strcmp($b['date'], $a['date']));

// Pagination Logic for Array
$limit = 10;
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;
$total_items = count($allMedLogs);
$total_pages = ceil($total_items / $limit);
$offset = ($page - 1) * $limit;
$paginatedLogs = array_slice($allMedLogs, $offset, $limit);

// Calc Stats
$stats = ['total' => 0, 'students' => 0, 'employees' => 0, 'others' => 0, 'meds' => []];
foreach ($allMedLogs as $log) {
    if (strtolower($log['type']) === 'student')
        $stats['students'] += $log['quantity'];
    elseif (strtolower($log['type']) === 'employee')
        $stats['employees'] += $log['quantity'];
    else
        $stats['others'] += $log['quantity'];
    $stats['total'] += $log['quantity'];
    $stats['meds'][$log['medicine']] = ($stats['meds'][$log['medicine']] ?? 0) + $log['quantity'];
}
arsort($stats['meds']);
$top3 = array_slice($stats['meds'], 0, 3, true);

include "index_layout.php";
?>

<div class="registry-container">
    <div class="registry-hero">
        <div class="hero-left">
            <h1>Drug Log Registry</h1>
            <p>Audited records of all medications issued for <?= date('F Y', strtotime($startDate)) ?>.</p>
        </div>
        <div class="hero-right">
            <div class="dropdown-wrapper">
                <button class="btn-premium" onclick="toggleDropdown(event)">
                    <i class="fa-solid fa-file-export"></i> DOWNLOAD RECORDS <i class="fa-solid fa-caret-down"></i>
                </button>
                <div class="dropdown-content-premium">
                    <a
                        href="export_pdf.php?type=drug_log&start=<?= $startDate ?>&end=<?= $endDate ?>&search=<?= urlencode($searchMed) ?>"><i
                            class="fa-solid fa-file-pdf"></i> PDF</a>
                    <a
                        href="export_xlsx.php?type=drug_log&start=<?= $startDate ?>&end=<?= $endDate ?>&search=<?= urlencode($searchMed) ?>"><i
                            class="fa-solid fa-file-excel"></i> Excel</a>
                </div>
            </div>
        </div>
    </div>

    <div class="stats-board">
        <div class="stat-card-neo" style="--clr: #00ACB1;">
            <div class="stat-icon-wrap"><i class="fa-solid fa-capsules"></i></div>
            <div class="stat-text">
                <h3><?= number_format($stats['total']) ?></h3>
                <p>Total Items</p>
            </div>
        </div>
        <div class="stat-card-neo" style="--clr: #3498db;">
            <div class="stat-icon-wrap"><i class="fa-solid fa-crown"></i></div>
            <div class="stat-text">
                <h3><?= !empty($top3) ? array_key_first($top3) . ' (' . reset($top3) . ')' : 'N/A' ?></h3>
                <p>Most Dispensed</p>
            </div>
        </div>
        <div class="stat-card-neo" style="--clr: #9b59b6;">
            <div class="stat-icon-wrap"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="stat-text">
                <h3><?= count($allMedLogs) ?> Events</h3>
                <p>Activity Logged</p>
            </div>
        </div>
    </div>

    <div class="registry-main-card">
        <div class="filter-hub">
            <form method="GET" id="registryFilterForm">
                <div class="hub-grid" style="display: flex; gap: 20px;">
                    <div class="hub-group"><label>Period</label><input type="month" name="month"
                            value="<?= $filterMonth ?>" onchange="this.form.submit()" class="hub-input"></div>
                    <div class="hub-group" style="flex:1;"><label>Search</label><input type="text" name="medicine"
                            id="regSearch" value="<?= htmlspecialchars($searchMed) ?>"
                            placeholder="Search patient or medicine..." class="hub-input" style="width:100%;"></div>
                </div>
            </form>
        </div>

        <div class="table-frame">
            <table class="registry-table" style="width: 100%; border-collapse: separate; border-spacing: 0 10px;">
                <thead>
                    <tr
                        style="text-align: left; background: linear-gradient(135deg, #00ACB1 0%, #00d4aa 100%); color: white; border-radius: 12px;">
                        <th
                            style="padding: 20px 15px; border-radius: 12px 0 0 12px; font-weight: 800; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px;">
                            Date</th>
                        <th
                            style="padding: 20px 15px; font-weight: 800; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px;">
                            Name</th>
                        <th
                            style="padding: 20px 15px; font-weight: 800; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px;">
                            Category</th>
                        <th
                            style="padding: 20px 15px; font-weight: 800; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px;">
                            Medicine</th>
                        <th style="padding: 20px 15px; font-weight: 800; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px;"
                            class="text-center">Qty</th>
                        <th
                            style="padding: 20px 15px; border-radius: 0 12px 12px 0; font-weight: 800; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px;">
                            Attended By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginatedLogs as $log): ?>
                        <tr>
                            <td class="date-col">
                                <div class="date-blob"
                                    style="background: #f8fafb; padding: 10px; border-radius: 12px; text-align: center;">
                                    <div style="font-weight: 800; color: #00ACB1;"><?= date('d', strtotime($log['date'])) ?>
                                    </div>
                                    <div style="font-size: 0.7rem; color: #999;"><?= date('M', strtotime($log['date'])) ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 700;"><?= htmlspecialchars($log['name']) ?></div>
                                <div style="font-size: 0.75rem; color: #999;"><?= $log['identifier'] ?></div>
                            </td>
                            <td><span class="tag tag-<?= strtolower($log['type']) ?>"
                                    style="background: #f0f0f0; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700;"><?= $log['type'] ?></span>
                            </td>
                            <td style="font-weight: 700; color: #00ACB1;"><?= htmlspecialchars($log['medicine']) ?></td>
                            <td class="text-center"><strong><?= $log['quantity'] ?></strong></td>
                            <td><i class="fa fa-user-check" style="color: #00ACB1;"></i>
                                <?= htmlspecialchars($log['attended']) ?></td>
                        </tr>
                    <?php endforeach;
                    if (empty($paginatedLogs))
                        echo "<tr><td colspan='6' style='text-align:center; padding:50px;'>No records found.</td></tr>"; ?>
                </tbody>
            </table>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container" style="padding: 20px 0; display: flex; justify-content: center; gap: 10px;">
                <?php 
                $queryString = $_GET;
                unset($queryString['p']);
                $baseUri = 'drug_log.php?' . http_build_query($queryString) . '&p=';
                ?>
                
                <?php if ($page > 1): ?>
                    <a href="<?= $baseUri . ($page - 1) ?>" class="page-btn-neo"><i class="fa fa-chevron-left"></i> Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="<?= $baseUri . $i ?>" class="page-btn-neo <?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="<?= $baseUri . ($page + 1) ?>" class="page-btn-neo">Next <i class="fa fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap');

    .registry-container {
        padding: 30px;
        font-family: 'Outfit', sans-serif;
        background: #fbfbfc;
        min-height: 100vh;
    }

    .registry-container {
        padding: 30px;
        font-family: 'Outfit', sans-serif;
        background: #fbfbfc;
        min-height: 100vh;
        background-image: radial-gradient(#00ACB1 0.5px, transparent 0.5px);
        background-size: 30px 30px;
        background-attachment: fixed;
    }

    .registry-hero {
        background: linear-gradient(135deg, #00767a 0%, #00ACB1 100%);
        border-radius: 35px;
        padding: 60px 70px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: white;
        margin-bottom: 40px;
        box-shadow: 0 20px 50px rgba(0, 172, 177, 0.25);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .hero-left h1 {
        margin: 0;
        font-size: 2.8rem;
        font-weight: 800;
        letter-spacing: -1px;
    }

    .btn-premium {
        background: white;
        border: none;
        padding: 12px 28px;
        border-radius: 50px;
        color: #00767a;
        font-weight: 800;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .btn-premium:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        filter: brightness(1.05);
    }

    .stats-board {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card-neo {
        background: white;
        padding: 20px 25px;
        border-radius: 20px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
        display: flex;
        align-items: center;
        gap: 15px;
        border: 2px solid #f8f9fa;
        transition: all 0.3s ease;
    }

    .stat-icon-wrap {
        width: 50px;
        height: 50px;
        background: var(--clr);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.4rem;
    }

    .stat-text h3 {
        margin: 0;
        font-size: 1.4rem;
        font-weight: 800;
        color: #2d3436;
    }

    .stat-text p {
        margin: 0;
        font-size: 0.75rem;
        color: #7f8c8d;
        text-transform: uppercase;
        font-weight: 700;
    }

    .dl-table thead th {
        text-align: left;
        padding: 22px 18px;
        font-size: 0.95rem;
        font-weight: 800;
        color: white;
        text-transform: uppercase;
        letter-spacing: 1px;
        background: #2d3436;
        /* High Contrast Dark Header */
        border-bottom: 3px solid #00ACB1;
    }

    .dl-table tbody td {
        padding: 22px 18px;
        border-bottom: 1px solid #f1f2f6;
        vertical-align: middle;
        font-size: 1.15rem;
        color: #1a1a1a;
        font-weight: 600;
        font-family: 'Outfit', sans-serif;
    }

    .dl-med-txt {
        font-weight: 800;
        color: #008185;
        font-size: 1.2rem;
    }

    .dl-date-cell {
        display: flex;
        flex-direction: column;
        align-items: center;
        border-right: 2px solid #f8f9fa;
        padding-right: 20px !important;
    }

    .dl-day {
        font-size: 1.6rem;
        font-weight: 800;
        color: #00ACB1;
        line-height: 1;
    }

    .dl-mo {
        font-size: 1rem;
        font-weight: 700;
        color: #a2a2a2;
        text-transform: uppercase;
    }

    .dl-pinfo strong {
        display: block;
        color: #2d3436;
        font-size: 1.2rem;
        font-weight: 800;
        margin-bottom: 3px;
    }

    .dl-pinfo small {
        color: #b2bec3;
        font-size: 0.95rem;
        font-weight: 600;
        font-family: 'JetBrains Mono', monospace;
    }

    .dl-med-txt {
        font-weight: 800;
        color: #00ACB1;
        font-size: 1.1rem;
    }

    .dl-assessment {
        font-size: 1rem;
        color: #636e72;
        line-height: 1.5;
        max-width: 350px;
    }

    .registry-table tbody tr {
        transition: all 0.3s ease;
    }

    .registry-table tbody tr:hover td {
        background: rgba(0, 172, 177, 0.02);
    }

    .registry-table tbody tr:hover .date-blob {
        background: #00ACB1 !important;
        transform: scale(1.1);
    }

    .registry-table tbody tr:hover .date-blob div {
        color: white !important;
    }

    .registry-main-card {
        background: white;
        padding: 35px;
        border-radius: 35px;
        box-shadow: 0 15px 50px rgba(0, 0, 0, 0.04);
    }

    .hub-input {
        border: 2px solid #edf1f2;
        padding: 12px 20px;
        border-radius: 12px;
        outline: none;
        transition: 0.3s;
    }

    .hub-input:focus {
        border-color: #00ACB1;
    }

    .registry-table tbody td {
        background: white;
        padding: 15px 20px;
        border-top: 1px solid #f8f9fa;
        border-bottom: 1px solid #f8f9fa;
    }

    .dropdown-wrapper {
        position: relative;
    }

    .dropdown-content-premium {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        background: white;
        min-width: 180px;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        z-index: 100;
        margin-top: 10px;
    }

    .dropdown-content-premium a {
        display: block;
        padding: 12px 20px;
        text-decoration: none;
        color: #333;
        font-weight: 700;
        border-bottom: 1px solid #f8f9fa;
    }

    /* Dark Mode */
    body.dark-mode .registry-container {
        background: #18191a;
    }

    body.dark-mode .registry-main-card,
    body.dark-mode .stat-card-neo {
        background: #242526;
        border-color: #333;
        color: white;
    }

    body.dark-mode .hub-input {
        background: #333;
        color: white;
        border-color: #444;
    }

    body.dark-mode .registry-table tbody td {
        background: #242526;
        border-color: #333;
    }

    /* Pagination Neo Style */
    .page-btn-neo {
        background: white;
        padding: 10px 18px;
        border-radius: 12px;
        color: #2d3436;
        text-decoration: none;
        font-weight: 800;
        font-size: 0.9rem;
        box-shadow: 0 4px 10px rgba(0,0,0,0.03);
        border: 2px solid #f0f0f0;
        transition: 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .page-btn-neo:hover {
        border-color: #00ACB1;
        color: #00ACB1;
        transform: translateY(-2px);
    }

    .page-btn-neo.active {
        background: #00ACB1;
        color: white;
        border-color: #00ACB1;
        box-shadow: 0 5px 15px rgba(0, 172, 177, 0.3);
    }
</style>

<script>
    function toggleDropdown(e) { e.stopPropagation(); const d = document.querySelector('.dropdown-content-premium'); d.style.display = d.style.display === 'block' ? 'none' : 'block'; }
    window.onclick = () => { if (document.querySelector('.dropdown-content-premium')) document.querySelector('.dropdown-content-premium').style.display = 'none'; }
    let sTimer; document.getElementById('regSearch').addEventListener('input', () => { clearTimeout(sTimer); sTimer = setTimeout(() => document.getElementById('registryFilterForm').submit(), 800); });
</script>