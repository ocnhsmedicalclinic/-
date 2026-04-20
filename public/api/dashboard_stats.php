<?php
header('Content-Type: application/json');
require_once "../../config/db.php";

// Set default period to 'year' for backward compatibility or future use
$period = isset($_GET['period']) ? $_GET['period'] : 'year';
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$response = [
    'ailments' => [],
    'visits' => array_fill(1, 12, 0),
    'grades' => [],
    'inventory' => [
        'status' => ['Good' => 0, 'Low Stock' => 0, 'Out of Stock' => 0, 'Expired' => 0],
        'categories' => []
    ],
    'nutri_status' => ['Severely Wasted' => 0, 'Wasted' => 0, 'Normal' => 0, 'Overweight' => 0, 'Obese' => 0, 'No Data' => 0]
];

// --- 1. AILMENTS, VISITS, GRADES, NUTRI STATUS (From Students & Employees) ---
$sql = "SELECT treatment_logs_json, health_exam_json, birth_date, 'student' as type FROM students WHERE is_archived = 0
        UNION ALL
        SELECT treatment_logs_json, '' as health_exam_json, birth_date, 'employee' as type FROM employees WHERE is_archived = 0";
$res = $conn->query($sql);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        // A. Treatment Logs
        $logs = json_decode($row['treatment_logs_json'] ?? '[]', true);
        $type = $row['type'];

        if (is_array($logs)) {
            foreach ($logs as $log) {
                $logYear = isset($log['date']) ? date('Y', strtotime($log['date'])) : null;

                if ($logYear == $year) {
                    // Ailments
                    if (!empty($log['assessment'])) {
                        $c = strtolower(trim($log['assessment']));
                        $response['ailments'][$c] = ($response['ailments'][$c] ?? 0) + 1;
                    }
                    // Monthly Visits
                    if (!empty($log['date'])) {
                        $month = (int) date('n', strtotime($log['date']));
                        $response['visits'][$month]++;
                    }
                    // Grades / Categories
                    if ($type == 'employee') {
                        $g = 'Staff';
                    } else {
                        $g = !empty($log['grade']) ? ('Grade ' . $log['grade']) : 'Others';
                    }
                    $response['grades'][$g] = ($response['grades'][$g] ?? 0) + 1;
                }
            }
        }

        // B. Nutritional Status (Reuse logic from dashboard.php)
        $health = json_decode($row['health_exam_json'] ?? '{}', true);
        $height = 0;
        $weight = 0;
        // Check Grade 12 down to 7
        for ($g = 12; $g >= 7; $g--) {
            // Helper to parse metric
            $hVal = $health["height_$g"] ?? '';
            $wVal = $health["weight_$g"] ?? '';
            $h = floatval(preg_replace('/[^0-9.]/', '', $hVal));
            $w = floatval(preg_replace('/[^0-9.]/', '', $wVal));

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
            $hm = $height > 3 ? $height / 100 : $height; // Convert cm to m if needed
            if ($hm > 0)
                $bmi = round($weight / ($hm * $hm), 1);
        }

        // Classification Logic
        $status = 'No Data';
        if ($bmi > 0 && $age > 0) {
            if ($age >= 5 && $age <= 19) {
                if ($bmi < 14.0)
                    $status = 'Severely Wasted';
                elseif ($bmi < 16.0)
                    $status = 'Wasted';
                elseif ($bmi < 23.0)
                    $status = 'Normal';
                elseif ($bmi < 27.0)
                    $status = 'Overweight';
                else
                    $status = 'Obese';
            } else {
                if ($bmi < 16.0)
                    $status = 'Severely Wasted';
                elseif ($bmi < 18.5)
                    $status = 'Wasted';
                elseif ($bmi < 25.0)
                    $status = 'Normal';
                elseif ($bmi < 30.0)
                    $status = 'Overweight';
                else
                    $status = 'Obese';
            }
        }

        $response['nutri_status'][$status]++;
    }
}

// Format Top Ailments (Sort and Slice)
arsort($response['ailments']);
$response['ailments'] = array_slice($response['ailments'], 0, 8, true);
// Capitalize keys for frontend
$formattedAilments = [];
foreach ($response['ailments'] as $k => $v) {
    $formattedAilments[ucfirst($k)] = $v;
}
$response['ailments'] = $formattedAilments;

// Format Grades
ksort($response['grades']);


// --- 2. INVENTORY STATS ---
$invSql = "SELECT category, quantity, reorder_level, expiry_date, DATEDIFF(expiry_date, CURDATE()) as days_to_expiry FROM inventory_items WHERE is_archived = 0";
$invRes = $conn->query($invSql);

if ($invRes) {
    while ($row = $invRes->fetch_assoc()) {
        // Categories
        $cat = !empty($row['category']) ? $row['category'] : 'Uncategorized';
        $response['inventory']['categories'][$cat] = ($response['inventory']['categories'][$cat] ?? 0) + 1;

        // Status
        if ($row['quantity'] == 0) {
            $response['inventory']['status']['Out of Stock']++;
        } elseif (!empty($row['expiry_date']) && $row['days_to_expiry'] < 0) {
            $response['inventory']['status']['Expired']++;
        } elseif ($row['quantity'] <= 10 && $row['quantity'] > 0) {
            $response['inventory']['status']['Low Stock']++;
        } else {
            $response['inventory']['status']['Good']++;
        }
    }
}

// Reset array keys for JSON
$response['visits'] = array_values($response['visits']);

echo json_encode($response);
exit;
