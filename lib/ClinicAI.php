<?php

class ClinicAI
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * SOAP-Aware Treatment Suggestions
     * Bases logic on the relationship between Evidence (S+O) and Management (A+P)
     */
    public function getTreatmentSuggestions($query)
    {
        if (empty($query))
            return [];

        $query = strtolower(trim($query));
        $frequencies = [];

        // Fetch all treatment logs from both Students and Employees
        $queries = [
            "SELECT treatment_logs_json FROM students WHERE treatment_logs_json IS NOT NULL",
            "SELECT treatment_logs_json FROM employees WHERE treatment_logs_json IS NOT NULL"
        ];

        foreach ($queries as $sql) {
            $result = $this->conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                $logs = json_decode($row['treatment_logs_json'], true);
                if (!is_array($logs))
                    continue;

                foreach ($logs as $log) {
                    // ── SOAP FIELDS ──
                    $S = strtolower($log['subjective_complaint'] ?? ($log['complaint'] ?? ''));
                    $O = strtolower($log['objective_complaint'] ?? '');
                    $A = strtolower($log['assessment'] ?? '');
                    $P = strtolower($log['plan'] ?? ($log['treatment'] ?? ''));

                    // The AI "thinks" by checking if the query matches S, O, or A
                    $isMatch = (strpos($S, $query) !== false) ||
                        (strpos($O, $query) !== false) ||
                        (strpos($A, $query) !== false);

                    if ($isMatch && !empty(trim($P))) {
                        $treatment = ucfirst(trim($P));
                        $frequencies[$treatment] = ($frequencies[$treatment] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($frequencies);
        return array_slice(array_keys($frequencies), 0, 5);
    }

    /**
     * Outbreak Prediction
     * Compare current period cases vs historical average
     */
    /**
     * Outbreak Prediction (Hybrid: Python AI + PHP Fallback)
     * Comparing trends over the last 30 days.
     */
    public function getOutbreakRisk()
    {
        // 1. Fetch Data (Last 30 Days)
        $today = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-30 days'));

        $dailyCounts = [];
        // Initialize last 30 days with empty arrays to ensure continuity
        $period = new DatePeriod(
            new DateTime($startDate),
            new DateInterval('P1D'),
            (new DateTime($today))->modify('+1 day')
        );
        foreach ($period as $dt) {
            $dailyCounts[$dt->format('Y-m-d')] = [];
        }

        $queries = [
            "SELECT treatment_logs_json FROM students WHERE treatment_logs_json IS NOT NULL",
            "SELECT treatment_logs_json FROM employees WHERE treatment_logs_json IS NOT NULL"
        ];

        foreach ($queries as $sql) {
            $result = $this->conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                $logs = json_decode($row['treatment_logs_json'], true);
                if (!is_array($logs))
                    continue;

                foreach ($logs as $log) {
                    if (empty($log['date']))
                        continue;

                    // SOAP Evaluation
                    $A = trim($log['assessment'] ?? '');
                    if (!$A) {
                        $A = trim(($log['subjective_complaint'] ?? '') . ' ' . ($log['objective_complaint'] ?? ''));
                    }

                    if (empty($A))
                        continue;

                    $logDate = date('Y-m-d', strtotime($log['date']));

                    if ($logDate >= $startDate && $logDate <= $today) {
                        $ailment = ucfirst(strtolower($A));
                        if (!isset($dailyCounts[$logDate][$ailment])) {
                            $dailyCounts[$logDate][$ailment] = 0;
                        }
                        $dailyCounts[$logDate][$ailment]++;
                    }
                }
            }
        }

        // 2. Try Python Forecast
        $pythonScript = __DIR__ . '/../public/outbreak_forecast.py';
        $pythonResult = null;

        $pythonCmd = $this->detectPythonCommand();

        if ($pythonCmd && file_exists($pythonScript)) {
            $commandData = json_encode($dailyCounts);

            // Escape JSON (Safe Temp File Method)
            $tempFile = sys_get_temp_dir() . '/clinic_ai_data_' . uniqid() . '.json';
            file_put_contents($tempFile, $commandData);

            // Execute Python using detected command
            $cmd = "$pythonCmd " . escapeshellarg($pythonScript) . " < " . escapeshellarg($tempFile);

            // Use shell_exec
            $output = shell_exec($cmd);

            // Clean up
            @unlink($tempFile);

            if ($output) {
                $pythonResult = json_decode($output, true);
            }
        }

        if ($pythonResult && !isset($pythonResult['error'])) {
            return $pythonResult; // Use Python Analysis
        }

        // 3. Fallback: PHP Simple Heuristic (Week over Week)
        // If Python failed or not available, use simple math
        return $this->getSimpleOutbreakRisk($dailyCounts);
    }

    private function detectPythonCommand()
    {
        // Try 'python'
        $output = @shell_exec("python --version 2>&1");
        if ($output && (strpos($output, 'Python') !== false || strpos($output, ' 3.') !== false)) {
            return 'python';
        }

        // Try 'py' (Windows Launcher)
        $output = @shell_exec("py --version 2>&1");
        if ($output && (strpos($output, 'Python') !== false || strpos($output, ' 3.') !== false)) {
            return 'py';
        }

        // Auto-Discovery: Search common Windows Python installation paths
        // Apache may not inherit user PATH, so we search manually
        $candidates = [];

        // Try to get user profile path
        $userProfile = getenv('USERPROFILE');
        if (!$userProfile || !is_dir($userProfile)) {
            $userProfile = null;
        }

        // Common locations - include wildcard user search as fallback
        $searchPaths = [
            'C:\\Python*\\python.exe',
            'C:\\Program Files\\Python*\\python.exe',
            'C:\\Program Files (x86)\\Python*\\python.exe',
        ];

        // Add user-specific paths 
        if ($userProfile) {
            array_unshift(
                $searchPaths,
                $userProfile . '\\AppData\\Local\\Python\\pythoncore-*\\python.exe',
                $userProfile . '\\AppData\\Local\\Python\\Python*\\python.exe',
                $userProfile . '\\AppData\\Local\\Microsoft\\WindowsApps\\python.exe',
                $userProfile . '\\AppData\\Local\\Programs\\Python\\Python*\\python.exe'
            );
        }

        // Also scan ALL C:\Users\*\ profiles (in case Apache user differs)
        $searchPaths[] = 'C:\\Users\\*\\AppData\\Local\\Python\\pythoncore-*\\python.exe';
        $searchPaths[] = 'C:\\Users\\*\\AppData\\Local\\Python\\Python*\\python.exe';
        $searchPaths[] = 'C:\\Users\\*\\AppData\\Local\\Programs\\Python\\Python*\\python.exe';
        $searchPaths[] = 'C:\\Users\\*\\AppData\\Local\\Microsoft\\WindowsApps\\python.exe';

        foreach ($searchPaths as $pattern) {
            // Check if it's a direct path (no glob)
            if (strpos($pattern, '*') === false) {
                if (file_exists($pattern)) {
                    $candidates[] = $pattern;
                }
            } else {
                // Use glob for wildcard patterns
                $found = glob($pattern);
                if ($found) {
                    $candidates = array_merge($candidates, $found);
                }
            }
        }

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                // Verify it actually works
                $quoted = '"' . $path . '"';
                $testOutput = @shell_exec($quoted . " --version 2>&1");
                if ($testOutput && strpos($testOutput, 'Python') !== false) {
                    return $quoted;
                }
            }
        }

        return null;
    }

    private function getSimpleOutbreakRisk($dailyCounts)
    {
        $today = new DateTime();
        $sevenDaysAgo = (clone $today)->modify('-7 days');
        $fourteenDaysAgo = (clone $today)->modify('-14 days');

        $currentWeekCount = 0;
        $previousWeekCount = 0;
        $ailmentTrends = [];

        foreach ($dailyCounts as $date => $ailments) {
            $dt = new DateTime($date);
            if ($dt > $sevenDaysAgo) {
                foreach ($ailments as $name => $count) {
                    $currentWeekCount += $count;
                    if (!isset($ailmentTrends[$name]))
                        $ailmentTrends[$name] = 0;
                    $ailmentTrends[$name] += $count;
                }
            } elseif ($dt > $fourteenDaysAgo) {
                foreach ($ailments as $name => $count) {
                    $previousWeekCount += $count;
                }
            }
        }

        $riskLevel = 'Low';
        $message = 'Normal activity levels (Statistical Fallback).';

        if ($previousWeekCount > 0) {
            $increase = (($currentWeekCount - $previousWeekCount) / $previousWeekCount) * 100;
            if ($increase > 50) {
                $riskLevel = 'High';
                $message = "Detected " . round($increase) . "% increase in cases this week.";
            } elseif ($increase > 20) {
                $riskLevel = 'Moderate';
                $message = "Slight increase (" . round($increase) . "%) in cases.";
            }
        } elseif ($currentWeekCount > 10) {
            $riskLevel = 'Moderate';
            $message = "Significant activity detected without prior baseline.";
        }

        arsort($ailmentTrends);
        $topAilment = array_key_first($ailmentTrends);

        $rationale = "";
        if ($topAilment) {
            if ($riskLevel === 'High') {
                $rationale = "Recent data shows a sharp spike in $topAilment, exceeding safety margins.";
            } elseif ($riskLevel === 'Moderate') {
                $rationale = "Higher than usual volume of $topAilment reported recently.";
            } else {
                $rationale = "Stability maintained for $topAilment across the monitoring period.";
            }
        } else {
            $rationale = "No dominant ailment patterns detected in recent logs.";
        }

        return [
            'risk_level' => $riskLevel,
            'message' => $message,
            'top_ailment' => $topAilment ? $topAilment : 'None',
            'rationale_insight' => $rationale,
            'analysis_type' => 'Basic (Python unavailable)'
        ];
    }

    /**
     * Patient Health Summary (Simple Rule-based)
     */
    public function generatePatientSummary($studentId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();

        if (!$student)
            return "Patient not found.";

        $summary = [];
        $summary[] = "Patient: " . $student['name'] . " (Age: " . $this->calculateAge($student['birth_date']) . ")";

        // Parse Health Exam
        $health = json_decode($student['health_exam_json'] ?? '[]', true);
        $conditions = [];

        // Check common conditions from checkboxes
        $historyKeys = [
            'past_asthma' => 'Asthma',
            'past_heart' => 'Heart Condition',
            'past_allergy' => 'Allergies',
            'past_seizure' => 'Seizures'
        ];

        foreach ($historyKeys as $key => $label) {
            if (isset($health[$key]) && $health[$key] == '1') {
                $conditions[] = $label;
            }
        }

        if (!empty($conditions)) {
            $summary[] = "Medical History: " . implode(", ", $conditions) . ".";
        } else {
            $summary[] = "No significant past medical history noted.";
        }

        // Parse SOAP History
        $treatments = json_decode($student['treatment_logs_json'] ?? '[]', true);
        if (!empty($treatments)) {
            $summary[] = "\n── CLINICAL SOAP SUMMARY ──";

            // Get last 3 encounters for context
            $recent = array_slice($treatments, -3);
            foreach ($recent as $idx => $t) {
                $date = date('M d, Y', strtotime($t['date'] ?? 'today'));
                $S = $t['subjective_complaint'] ?? ($t['complaint'] ?? 'No data');
                $O = $t['objective_complaint'] ?? 'No findings';
                $A = $t['assessment'] ?? 'Pending';
                $P = $t['plan'] ?? ($t['treatment'] ?? 'No plan');

                $summary[] = "Enc #" . ($idx + 1) . " ($date):";
                $summary[] = "  [S] $S";
                $summary[] = "  [O] $O";
                $summary[] = "  [A] $A";
                $summary[] = "  [P] $P";
            }

            // Frequency Analysis
            $ailments = [];
            foreach ($treatments as $t) {
                $a = strtolower(trim($t['assessment'] ?? ''));
                if ($a)
                    $ailments[$a] = ($ailments[$a] ?? 0) + 1;
            }
            arsort($ailments);
            if (!empty($ailments)) {
                $top = array_key_first($ailments);
                $summary[] = "\nChronic/Recurrent Assessment: " . ucfirst($top) . " (" . $ailments[$top] . " occurrences)";
            }
        }

        return implode("\n", $summary);
    }

    /**
     * DFA (Diagnostic & Formulary Assistant)
     * Maps assessments to available medications in the actual inventory
     */
    public function getDiagnosticFormulary($assessment)
    {
        if (empty($assessment))
            return [];

        $assessment = strtolower(trim($assessment));

        // --- 1. DFA CLINICAL MAPPING (The Knowledge Base) ---
        // This maps clinical patterns to medicine keywords/categories
        $dfaMap = [
            'fever' => ['Paracetamol', 'Biogesic', 'Tempra', 'Antipyretic'],
            'headache' => ['Paracetamol', 'Ibuprofen', 'Mefenamic', 'Analgesic'],
            'cough' => ['Cough Syrup', 'Ascof', 'Ambroxol', 'Guaifenesin', 'Lagundi'],
            'cold' => ['Neozep', 'Bioflu', 'Phenylpropanolamine', 'Antihistamine'],
            'sipon' => ['Neozep', 'Bioflu', 'Antihistamine'],
            'allergy' => ['Cetirizine', 'Loratadine', 'Diphenhydramine'],
            'stomach' => ['Antacid', 'Kremil-S', 'Dicycloverine', 'Hyoscine'],
            'diarrhea' => ['Diatabs', 'Loperamide', 'Oral Rehydration', 'ORS'],
            'asthma' => ['Salbutamol', 'Ventolin', 'Nebulizer'],
            'wound' => ['Betadine', 'Alcohol', 'Hydrogen Peroxide', 'Povidone'],
            'muscle' => ['Mefenamic', 'Ibuprofen', 'Topical Cream', 'Methyl Salicylate'],
            'dysmenorrhea' => ['Mefenamic', 'Midol', 'Buscopan'],
            'acid' => ['Antacid', 'Omeprazole', 'Aluminum Hydroxide'],
            'infection' => ['Amoxicillin', 'Antibiotic', 'Cloxacillin'],
            'uti' => ['Nitrofurantoin', 'Ciprofloxacin', 'Co-trimoxazole']
        ];

        $matchedKeywords = [];
        foreach ($dfaMap as $pattern => $meds) {
            if (strpos($assessment, $pattern) !== false) {
                $matchedKeywords = array_merge($matchedKeywords, $meds);
            }
        }

        if (empty($matchedKeywords))
            return [];

        // --- 2. INVENTORY CROSS-REFERENCE ---
        // Find items in inventory that match these keywords and HAVE STOCK
        $results = [];
        $keywordsFilter = [];
        foreach ($matchedKeywords as $k) {
            $keywordsFilter[] = "name LIKE '%" . $this->conn->real_escape_escape_string($k) . "%'";
        }

        $sql = "SELECT name, quantity, unit FROM inventory_items 
                WHERE is_archived = 0 
                AND quantity > 0 
                AND (" . implode(" OR ", $keywordsFilter) . ")
                ORDER BY quantity DESC LIMIT 5";

        $res = $this->conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $results[] = [
                    'item' => $row['name'],
                    'available' => $row['quantity'] . ' ' . $row['unit'],
                    'type' => 'DFA Recommendation'
                ];
            }
        }

        return $results;
    }

    private function calculateAge($dob)
    {
        if (!$dob)
            return '?';
        return date_diff(date_create($dob), date_create('today'))->y;
    }

    /**
     * Get Patient Context for AI Analysis
     * Fetches historical logs and health exam data
     */
    public function getPatientContext($id, $type)
    {
        $table = ($type === 'employee') ? 'employees' : 'students';
        $stmt = $this->conn->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        if (!$res)
            return null;

        return [
            'name' => $res['name'],
            'gender' => $res['gender'] ?? ($res['sex'] ?? 'Unknown'),
            'age' => $this->calculateAge($res['birth_date']),
            'health_history' => json_decode($res['health_exam_json'] ?? '[]', true),
            'past_assessments' => $this->extractPastAssessments($res['treatment_logs_json'] ?? '[]'),
            'medical_images' => $this->extractMedicalImages($id, $type),
            'past_certificates' => $this->extractPastCertificates($id, $type)
        ];
    }

    private function extractPastCertificates($id, $type)
    {
        $table = ($type === 'employee') ? 'employee_files' : 'student_files';
        $idField = ($type === 'employee') ? 'employee_id' : 'student_id';
        
        // Specifically look for titles that match generated certificates
        $stmt = $this->conn->prepare("SELECT filename FROM $table WHERE $idField = ? AND (filename LIKE '%Certificate%' OR filename LIKE '%Request%' OR filename LIKE '%Prescription%') ORDER BY uploaded_at DESC LIMIT 10");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $certs = [];
        while($row = $res->fetch_assoc()) {
            $certs[] = $row['filename'];
        }
        return $certs;
    }

    private function extractMedicalImages($id, $type)
    {
        $table = ($type === 'employee') ? 'employee_files' : 'student_files';
        $idField = ($type === 'employee') ? 'employee_id' : 'student_id';

        // Only get recent images (e.g., last 30 days) to keep it relevant
        $stmt = $this->conn->prepare("SELECT filepath, filename FROM $table WHERE $idField = ? AND file_type IN ('jpg', 'jpeg', 'png') ORDER BY uploaded_at DESC LIMIT 5");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();

        $images = [];
        while ($row = $res->fetch_assoc()) {
            $images[] = [
                'path' => $row['filepath'],
                'name' => $row['filename']
            ];
        }
        return $images;
    }

    private function extractPastAssessments($logsJson)
    {
        $logs = json_decode($logsJson, true);
        if (!is_array($logs))
            return [];
        $assessments = [];
        foreach ($logs as $log) {
            $a = trim($log['assessment'] ?? '');
            if ($a)
                $assessments[] = $a;
        }
        return array_unique($assessments);
    }

    public function runVisionAnalysis($imagePath, $mode = 'rash')
    {
        $pythonScript = __DIR__ . '/../public/vision_predictor.py';
        $pythonCmd = $this->detectPythonCommand();

        if (!$pythonCmd)
            return ['error' => 'Python not detected'];
        if (!file_exists($pythonScript))
            return ['error' => 'Vision engine not found'];

        $inputData = [
            'image_path' => $imagePath,
            'mode' => $mode
        ];

        $tempFile = sys_get_temp_dir() . '/clinic_vision_' . uniqid() . '.json';
        file_put_contents($tempFile, json_encode($inputData));

        $cmd = "$pythonCmd " . escapeshellarg($pythonScript) . " < " . escapeshellarg($tempFile);
        $output = shell_exec($cmd);
        @unlink($tempFile);

        if ($output) {
            return json_decode($output, true);
        }
        return ['error' => 'No response from Vision engine'];
    }

    public function runDiseasePrediction($inputData)
    {
        $pythonScript = __DIR__ . '/../public/disease_predictor.py';
        $pythonCmd = $this->detectPythonCommand();

        if (!$pythonCmd) {
            return ['error' => 'Python is not installed or not detected on this system. Please install Python 3.x.'];
        }

        if (!file_exists($pythonScript)) {
            return ['error' => 'Disease prediction script not found at: ' . $pythonScript];
        }

        // Write input data to temp file
        $tempFile = sys_get_temp_dir() . '/clinic_disease_predict_' . uniqid() . '.json';
        file_put_contents($tempFile, json_encode($inputData));

        // Execute Python
        $cmd = "$pythonCmd " . escapeshellarg($pythonScript) . " < " . escapeshellarg($tempFile);
        $output = shell_exec($cmd);

        // Clean up
        @unlink($tempFile);

        if ($output) {
            $result = json_decode($output, true);
            if ($result !== null) {
                return $result;
            }
            return ['error' => 'Invalid response from Python script.'];
        }

        return ['error' => 'No response from Python script. Check if Python is properly configured.'];
    }
}
