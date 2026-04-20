<?php
require_once "../config/db.php";
requireAdmin();

$activePage = "logs";
$pageTitle = "Activity Logs";
include "index_layout.php";

$logFile = dirname(__DIR__) . '/logs/security.log';
$logs = [];

if (file_exists($logFile)) {
    $fileContent = file($logFile);

    // Auto-delete: Keep only the latest 500 logs (User request)
    if (count($fileContent) > 500) {
        $fileContent = array_slice($fileContent, -500);
        file_put_contents($logFile, implode("", $fileContent));
    }

    $fileContent = array_reverse($fileContent);

    foreach ($fileContent as $line) {
        if (preg_match('/\[(.*?)\] \[(.*?)\] \[(.*?)\] (.*?) - (.*)/', $line, $matches)) {
            $logs[] = [
                'timestamp' => $matches[1],
                'ip' => $matches[2],
                'user' => $matches[3],
                'event' => $matches[4],
                'details' => $matches[5]
            ];
        } else {
            $logs[] = [
                'timestamp' => '-',
                'ip' => '-',
                'user' => '-',
                'event' => 'RAW',
                'details' => $line
            ];
        }
    }

    $limit = isset($_GET['limit']) ? $_GET['limit'] : 25;
    if ($limit !== 'all') {
        $logs = array_slice($logs, 0, (int) $limit);
    }
}
?>

<section class="controls">
    <div class="control-group" style="display: flex; align-items: center; gap: 10px;">
        <label>Show:</label>
        <select id="limit" class="filter-select" onchange="window.location.href='?limit='+this.value">
            <option value="10" <?= ($limit == 10) ? 'selected' : '' ?>>10</option>
            <option value="25" <?= ($limit == 25) ? 'selected' : '' ?>>25</option>
            <option value="50" <?= ($limit == 50) ? 'selected' : '' ?>>50</option>
            <option value="100" <?= ($limit == 100) ? 'selected' : '' ?>>100</option>
            <option value="all" <?= ($limit == 'all') ? 'selected' : '' ?>>All</option>
        </select>
    </div>

    <div class="search-box">
        <input type="text" id="search" placeholder="Search Logs..." onkeyup="searchLogs()">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
    </div>

    <?php if ($_SESSION['role'] === 'superadmin'): ?>
        <form method="POST" onsubmit="return confirm('Clear all logs?');" style="margin-left: auto;">
            <button type="submit" name="clear_logs" class="btn" style="background: #e74c3c; color: white;">
                <i class="fa-solid fa-trash"></i> CLEAR LOGS
            </button>
        </form>
    <?php endif; ?>
</section>

<div class="table-container">
    <table id="logsTable">
        <thead>
            <tr>
                <th style="min-width: 200px;">Date & Time</th>
                <th>User</th>
                <th>Event</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($logs)): ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td style="white-space: nowrap;">
                            <div class="log-time" data-timestamp="<?= htmlspecialchars($log['timestamp']) ?>">
                                <?= htmlspecialchars($log['timestamp']) ?>
                            </div>
                            <div class="relative-time" style="font-size: 10px; color: #888; margin-top: 2px;"></div>
                        </td>
                        <td>
                            <span
                                class="user-badge <?= (strtolower($log['user']) === 'admin' || strtolower($log['user']) === 'superadmin') ? 'admin' : '' ?>">
                                <?= htmlspecialchars(strtoupper($log['user'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="event-tag"><?= htmlspecialchars($log['event']) ?></span>
                        </td>
                        <td>
                            <div style="font-size: 13px; color: #555;"><?= htmlspecialchars($log['details']) ?></div>
                            <small style="color: #999; font-size: 10px;">IP: <?= htmlspecialchars($log['ip']) ?></small>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align:center; padding: 40px; color: #888;">
                        No activity logs found.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
    .user-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: 600;
        font-size: 11px;
        background: #e0f2f1;
        color: #00695c;
    }

    .user-badge.admin {
        background: #e8eaf6;
        color: #283593;
    }

    .event-tag {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 12px;
        background: #f5f5f5;
        border: 1px solid #ddd;
        font-size: 11px;
        font-weight: 600;
        color: #333;
    }

    td {
        vertical-align: middle;
        text-align: center;
    }

    th {
        text-align: center;
    }
</style>

<script>
    function searchLogs() {
        var input = document.getElementById("search");
        var filter = input.value.toUpperCase();
        var table = document.getElementById("logsTable");
        var tr = table.getElementsByTagName("tr");
        for (var i = 1; i < tr.length; i++) {
            var text = tr[i].textContent || tr[i].innerText;
            tr[i].style.display = text.toUpperCase().indexOf(filter) > -1 ? "" : "none";
        }
    }

    function updateRelativeTimes() {
        document.querySelectorAll('.log-time').forEach(el => {
            const timestamp = el.getAttribute('data-timestamp');
            const relativeEl = el.nextElementSibling;
            if (timestamp && timestamp !== '-' && relativeEl) {
                const diff = Math.floor((new Date() - new Date(timestamp)) / 1000);
                if (diff < 60) relativeEl.textContent = 'Just now';
                else if (diff < 3600) relativeEl.textContent = Math.floor(diff / 60) + 'm ago';
                else if (diff < 86400) relativeEl.textContent = Math.floor(diff / 3600) + 'h ago';
                else relativeEl.textContent = '';
            }
        });
    }

    setInterval(updateRelativeTimes, 30000);
    document.addEventListener('DOMContentLoaded', updateRelativeTimes);
</script>

<?php
// Handle Clear Logs (Admin only) - Restored since merge error removed it
if (isset($_POST['clear_logs']) && $_SESSION['role'] === 'superadmin') {
    file_put_contents($logFile, "");
    logSecurityEvent("LOGS_CLEARED", "Activity logs were cleared by " . $_SESSION['username']);
    echo "<script>window.location.href='logs?cleared=1';</script>";
    exit();
}
?>

</body>

</html>