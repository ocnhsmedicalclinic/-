<?php
header('Content-Type: text/plain');

echo "=== PYTHON INTEGRATION TEST ===\n\n";

// 1. Check if shell_exec is enabled
if (function_exists('shell_exec')) {
    echo "[PASS] shell_exec() is enabled.\n";
} else {
    echo "[FAIL] shell_exec() is disabled. Python cannot be run.\n";
    exit;
}

// 2. Detect Python Command
$pythonCommand = null;
$versionStr = "";

// Try 'python'
$output = shell_exec("python --version 2>&1"); // 2>&1 to catch errors
if ($output && (strpos($output, 'Python') !== false || strpos($output, ' 3.') !== false)) {
    $pythonCommand = 'python';
    $versionStr = trim($output);
} else {
    // Try 'py' (Windows Launcher)
    $output = shell_exec("py --version 2>&1");
    if ($output && (strpos($output, 'Python') !== false || strpos($output, ' 3.') !== false)) {
        $pythonCommand = 'py';
        $versionStr = trim($output);
    }
}

// 3. Auto-Discovery (Hunter)
if (!$pythonCommand) {
    // Check Common Locations
    $candidates = array_merge(
        glob("C:/Python*/python.exe"),
        glob(getenv('LOCALAPPDATA') . "/Programs/Python/Python*/python.exe"),
        glob("C:/Users/*/AppData/Local/Programs/Python/Python*/python.exe")
    );

    foreach ($candidates as $path) {
        if (file_exists($path)) {
            $pythonCommand = '"' . $path . '"'; // Quote for safety
            $versionStr = "Detected at: " . $path;
            break;
        }
    }
}

if ($pythonCommand) {
    echo "[PASS] Python found using command: '$pythonCommand'\n";
    echo "       Version: " . $versionStr . "\n";
} else {
    echo "[FAIL] Python not found. Neither 'python' nor 'py' commands worked.\n";
    echo "       Output was: " . $output . "\n";
    echo "       POSSIBLE FIX: Restart XAMPP (Stop/Start Apache) to load new PATH variables.\n";
    exit;
}

// 3. Test the Forecast Script
echo "\nTesting Forecast Script ($pythonCommand public/outbreak_forecast.py)...\n";
$scriptPath = __DIR__ . '/outbreak_forecast.py';

if (!file_exists($scriptPath)) {
    echo "[FAIL] Script file not found at: $scriptPath\n";
    exit;
}

// Dummy Data
$dummyData = json_encode([
    '2023-01-01' => ['Cough' => 5],
    '2023-01-02' => ['Cough' => 8],
    '2023-01-03' => ['Cough' => 12],
    '2023-01-04' => ['Cough' => 15],
    '2023-01-05' => ['Cough' => 20]
]);

// Windows-safe temp file approach
$tempFile = sys_get_temp_dir() . '/test_python_data.json';
file_put_contents($tempFile, $dummyData);

$cmd = "$pythonCommand " . escapeshellarg($scriptPath) . " < " . escapeshellarg($tempFile);
$output = shell_exec($cmd);
@unlink($tempFile);

if ($output) {
    // Check if output looks like JSON
    $json = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (isset($json['analysis_details'])) {
            echo "[SUCCESS] Valid JSON received. Logic is working!\n";
            // print_r($json['analysis_details']); // Clean output
            echo "Prediction: " . $json['analysis_details'][0]['prediction'] . "\n";
        } else {
            echo "[WARNING] JSON received but format is unexpected.\n";
            echo $output;
        }
    } else {
        echo "[FAIL] Output received but it's not valid JSON. Script says:\n";
        echo $output . "\n";
    }
} else {
    echo "[FAIL] No output from script.\n";
}
?>