<?php
require_once '../../config/db.php';
require_once '../../lib/ClinicAI.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$ai = new ClinicAI($conn);

if ($action === 'suggest_treatment') {
    $complaint = $_GET['complaint'] ?? '';
    // Call the static-like method (though implemented as instance method)
    // Actually method is getTreatmentSuggestions($complaint).
    // Wait, the method is defined in ClinicAI.php inside `public function getTreatmentSuggestions`.
    // I need to instantiate ClinicAI. I did that above.
    // However, I need to check the implementation of `getTreatmentSuggestions`.
    // It accepts $complaint.

    // I need to implement getTreatmentSuggestions in ClinicAI.php properly.
    // The previous write_to_file for ClinicAI.php had `public function getTreatmentSuggestions($complaint)`.

    $suggestions = $ai->getTreatmentSuggestions($complaint);
    $dfa = $ai->getDiagnosticFormulary($complaint);

    echo json_encode([
        'suggestions' => $suggestions,
        'dfa' => $dfa
    ]);

} elseif ($action === 'outbreak_risk') {
    $risk = $ai->getOutbreakRisk();
    echo json_encode($risk);

} elseif ($action === 'patient_summary') {
    $studentId = $_GET['student_id'] ?? 0;
    if ($studentId) {
        $summary = $ai->generatePatientSummary($studentId);
        echo json_encode(['summary' => $summary]);
    } else {
        echo json_encode(['error' => 'No student ID provided']);
    }

} else {
    echo json_encode(['error' => 'Invalid action']);
}
?>