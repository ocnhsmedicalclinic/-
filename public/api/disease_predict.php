<?php
require_once '../../config/db.php';
require_once '../../lib/ClinicAI.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$ai = new ClinicAI($conn);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_symptoms') {
    // Get the symptom list from Python
    $result = $ai->runDiseasePrediction(['action' => 'get_symptoms']);
    echo json_encode($result);

} elseif ($action === 'predict') {
    // Get symptoms from POST body
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);

    $symptoms = $input['symptoms'] ?? [];

    if (empty($symptoms) || !is_array($symptoms)) {
        echo json_encode(['error' => 'No symptoms provided. Please select at least one symptom.']);
        exit;
    }

    $result = $ai->runDiseasePrediction([
        'action' => 'predict',
        'symptoms' => $symptoms
    ]);

    echo json_encode($result);

} elseif ($action === 'quick_predict') {
    $complaint = $_GET['complaint'] ?? '';
    $patientId = $_GET['id'] ?? 0;
    $patientType = $_GET['type'] ?? '';

    if (empty($complaint)) {
        echo json_encode(['predictions' => []]);
        exit;
    }

    $context = null;
    if ($patientId) {
        $context = $ai->getPatientContext($patientId, $patientType);

        // --- Integrated Vision Processing from Medical Records ---
        if ($context && !empty($context['medical_images'])) {
            $visionResults = [];
            foreach ($context['medical_images'] as $img) {
                $absPath = realpath('../../' . $img['path']);
                if (!$absPath)
                    continue;

                // Determine mode based on complaint or file keywords
                $mode = 'rash';
                $txt = strtolower($complaint . ' ' . $img['name']);
                if (str_contains($txt, 'xray') || str_contains($txt, 'chest') || str_contains($txt, 'lung')) {
                    $mode = 'xray';
                }

                $analysis = $ai->runVisionAnalysis($absPath, $mode);
                if ($analysis && !isset($analysis['error'])) {
                    $visionResults[] = $analysis;
                }
            }
            $context['vision_findings'] = $visionResults;
        }
    }

    $result = $ai->runDiseasePrediction([
        'action' => 'quick_predict',
        'complaint' => $complaint,
        'patient_context' => $context
    ]);

    // ── NLM GLOBAL CLINICAL INTEGRATION ──
    // Enrich local predictions with official ICD descriptions from NLM
    if (isset($result['predictions']) && count($result['predictions']) > 0) {
        foreach ($result['predictions'] as &$p) {
            $icd = $p['icd10'];
            $url = "https://clinicaltables.nlm.nih.gov/api/icd10cm/v3/search?terms=" . urlencode($icd) . "&maxList=1";
            $resp = @file_get_contents($url);
            if ($resp) {
                $nlmData = json_decode($resp, true);
                if (isset($nlmData[3][0][1])) {
                    $p['global_description'] = $nlmData[3][0][1]; // Real clinical description from NLM
                    $p['global_source'] = "NLM Diseases Database";
                }
            }
        }
    }

    echo json_encode($result);

} else {
    echo json_encode(['error' => 'Invalid action. Use get_symptoms, predict, or quick_predict.']);
}
?>