<?php
require_once "../config/db.php";
requireLogin();

function getAgeFromDate($birthDate, $examDate)
{
  if (!$birthDate)
    return 0;
  try {
    $b = new DateTime($birthDate);
    // Use exam date if available, else standard fallback to today (for view purposes)
    $e = $examDate ? new DateTime($examDate) : new DateTime();
    return $b->diff($e)->y;
  } catch (Exception $e) {
    return 0;
  }
}

function classifyBMI_View($bmi, $age)
{
  if ($bmi <= 0 || $age <= 0)
    return '';

  // DepEd/WHO Simplified Thresholds (5-19)
  if ($age >= 5 && $age <= 19) {
    if ($bmi < 14.0)
      return 'Severely Wasted';
    if ($bmi < 16.0)
      return 'Wasted';
    if ($bmi < 23.0)
      return 'Normal Weight';
    if ($bmi < 27.0)
      return 'Overweight';
    return 'Obese';
  }

  // Adult (>19)
  if ($bmi < 16.0)
    return 'Severely Wasted';
  if ($bmi < 18.5)
    return 'Wasted';
  if ($bmi < 25.0)
    return 'Normal Weight';
  if ($bmi < 30.0)
    return 'Overweight';
  return 'Obese';
}

function classifyHeightForAge_View($height, $age, $gender)
{
  if ($height <= 0 || $age <= 0)
    return '';

  // WHO Growth Reference 2007 (5-19 years) - Approximate -2SD (Stunting) thresholds in cm
  // [Age => [Male, Female]]
  $stuntingThresholds = [
    5 => [102, 101],
    6 => [107, 107],
    7 => [113, 113],
    8 => [119, 119],
    9 => [124, 124],
    10 => [129, 130],
    11 => [134, 136],
    12 => [139, 142],
    13 => [145, 146],
    14 => [151, 149],
    15 => [156, 151],
    16 => [160, 152],
    17 => [163, 153],
    18 => [164, 153],
    19 => [165, 153]
  ];

  $gKey = (stripos($gender, 'Female') !== false) ? 1 : 0; // 0 for Male, 1 for Female
  $threshold = isset($stuntingThresholds[$age]) ? $stuntingThresholds[$age][$gKey] : 0;

  // If age is outside map (e.g. adult), assume adult average lower bound (approx 155cm men, 145cm women)
  if ($threshold == 0 && $age > 19) {
    $threshold = ($gKey == 0) ? 155 : 145;
  }

  if ($threshold > 0) {
    if ($height < ($threshold - 10))
      return 'Severely Stunted'; // Approx -3SD
    if ($height < $threshold)
      return 'Stunted';
    if ($height > ($threshold + 30))
      return 'Tall'; // Arbitrary Tall cutoff
    return 'Normal Height';
  }

  return 'Normal Height'; // Fallback
}


// I-check kung may ID sa URL
if (isset($_GET['view_id']) && is_numeric($_GET['view_id'])) {
  $id = intval($_GET['view_id']);
  $patientType = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'student';
  $type = $patientType;
  $primaryColor = ($patientType === 'employee') ? '#795548' : '#00ACB1';

  if ($patientType == 'employee') {
    $table = "employees";
    $backLink = "employees.php";
  } else {
    $table = "students";
    $backLink = "student.php";
  }

  $result = $conn->query("SELECT * FROM $table WHERE id = '$id'");

  if ($result->num_rows > 0) {
    $person = $result->fetch_assoc();
    $health_values = json_decode($person['health_exam_json'] ?? '{}', true);
  } else {
    die("<div style='text-align:center; padding:50px;'><h1>❌ Error</h1><p>" . ucfirst($type) . " record not found.</p><a href='$backLink'>Go Back</a></div>");
  }
} else {
  die("<div style='text-align:center; padding:50px;'><h1>⚠️ Error</h1><p>No Record ID provided.</p><a href='student.php'>Go Back</a></div>");
}

function getVal($key, $data)
{
  return isset($data[$key]) ? htmlspecialchars($data[$key]) : '';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/x-icon" href="assets/img/ocnhs_logo.png">
  <title>School Health Examination Card - <?= $person['name'] ?></title>


  <style>
    @media print {
      .no-print {
        display: none !important;
      }

      @page {
        size:
          <?= $type == 'employee' ? '8.5in 14in' : '8.5in 11in' ?>
        ;
        margin: 5mm;
      }

      body {
        margin: 0;
        padding: 0;
        -webkit-print-color-adjust: exact;
        font-size: 12px;
        /* Increased base font size for print */
      }

      .card-container {
        width: 100%;
        max-width: none;
        box-shadow: none;
        border: none;
        padding:
          <?= $type == 'student' ? '5px' : '0' ?>
        ;
        transform: scale(<?= $type == 'student' ? '1' : '1.03' ?>);
        /* Adjusted scaling to prevent right-side cutoff */
        transform-origin: top center;
      }

      /* Relaxed spacing to fill more of the page height */
      .info-row {
        margin-bottom: 5px !important;
      }

      .emp-info-row {
        margin-bottom: 5px !important;
      }

      .card-title {
        margin: 8px 0 !important;
        font-size: 15px !important;
      }

      hr {
        margin: 8px 0 !important;
      }

      .sec-header {
        margin-top: 5px !important;
        margin-bottom: 3px !important;
        font-size: 10px !important;
      }

      table {
        margin-top: 6px !important;
      }

      /* Increased table cell padding for better vertical coverage */
      table td,
      table th {
        padding: 2px 3px !important;
        line-height: 1.1 !important;
        font-size: 10px !important;
      }

      .header h2 {
        font-size: 17px !important;
      }

      .header h3 {
        font-size: 11px !important;
      }

      .footer-section {
        margin-top: 15px !important;
        page-break-inside: avoid;
      }
    }

    body {
      font-family: Arial, sans-serif;
      font-size: 11px;
      color: #000;
      margin: 0;
      padding: 20px;
      background: #fff;
    }

    .card-container {
      max-width: 900px;
      margin: 0 auto;
      position: relative;
    }

    /* Shared / Base Styles from edit_card.php */
    .header {
      text-align: center;
      margin-bottom: 5px;
      position: relative;
    }

    .header-logo {
      width: 50px;
      display: block;
      margin: 0 auto 5px auto;
    }

    .header h3 {
      font-size: 11px;
      font-family: "Old English Text MT", serif;
      margin: 0;
      font-weight: normal;
    }

    .header h2 {
      font-size: 16px;
      font-family: "Old English Text MT", serif;
      margin: 0;
      font-weight: bold;
    }

    .header div {
      font-family: Arial, sans-serif;
      font-weight: bold;
      font-size: 10px;
    }

    .school-name {
      font-size: 11px;
    }

    .card-title {
      text-align: center;
      font-weight: 900;
      font-size: 14px;
      margin: 8px 0;
      border-top: 2px solid #000;
      padding-top: 5px;
      text-transform: uppercase;
    }

    /* Info Form Layout */
    .info-row {
      display: flex;
      flex-wrap: wrap;
      margin-bottom: 4px;
      align-items: flex-end;
    }

    .info-group {
      display: flex;
      align-items: flex-end;
      margin-right: 15px;
      margin-bottom: 2px;
      white-space: nowrap;
    }

    .info-group label {
      font-weight: bold;
      margin-right: 5px;
      font-size: 10px;
      margin-bottom: 2px;
    }

    .info-data {
      border-bottom: 1px solid #000;
      min-width: 30px;
      padding: 0 5px;
      text-align: center;
      font-weight: bold;
      font-size: 11px;
      flex-grow: 1;
      text-transform: uppercase;
    }

    /* Table Styles */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 8px;
      font-size: 10px;
    }

    th,
    td {
      border: 1px solid #000;
      padding: 3px;
      text-align: center;
      vertical-align: middle;
    }

    /* Employee Styles */
    .emp-info-row {
      display: flex;
      align-items: flex-end;
      margin-bottom: 5px;
      font-family: 'Times New Roman', serif;
      font-size: 12px;
      color: #000;
    }

    .emp-label {
      white-space: nowrap;
      margin-right: 5px;
      font-weight: normal;
    }

    .emp-data-line {
      flex: 1;
      border-bottom: 1px solid #000;
      text-align: center;
      padding: 0 5px 2px 5px;
      min-height: 15px;
      font-weight: bold;
      text-transform: uppercase;
    }

    .emp-box-group {
      display: flex;
      gap: 15px;
      align-items: center;
    }

    .emp-box-item {
      display: flex;
      align-items: center;
      gap: 3px;
    }

    .view-box {
      border: 1px solid #000;
      width: 11px;
      height: 11px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      font-weight: bold;
    }

    .sec-header {
      font-weight: bold;
      margin-top: 8px;
      margin-bottom: 4px;
      font-size: 10px;
      text-transform: uppercase;
      text-align: left;
    }

    .emp-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 9px;
      margin-top: 5px;
      border: none !important;
    }

    .emp-table td {
      vertical-align: top;
      padding: 2px;
      border: none !important;
      text-align: left;
    }

    .chk-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 4px;
    }

    .chk-lbl {
      flex: 1;
      font-size: 10px;
      text-align: left;
      padding-left: 20px;
    }

    .chk-boxes {
      display: flex;
      gap: 0;
      width: 50px;
      justify-content: space-around;
    }

    .data-line {
      border-bottom: 1px solid #000;
      display: inline-block;
      min-width: 20px;
      padding: 0 5px;
      text-align: center;
      font-weight: bold;
      text-transform: uppercase;
    }

    /* FAB Styles */
    .fab-container {
      position: fixed;
      bottom: 30px;
      right: 30px;
      display: flex;
      gap: 10px;
    }

    .btn-print {
      background: #e74c3c;
      color: white;
      border: none;
      padding: 15px 30px;
      border-radius: 30px;
      font-weight: bold;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
      cursor: pointer;
      font-size: 14px;
      transition: 0.3s;
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
    }

    .btn-back {
      background: #6c757d;
      color: white;
      border: none;
      padding: 15px 20px;
      border-radius: 50%;
      width: 50px;
      height: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
      transition: 0.3s;
      margin-bottom: 20px;
    }

    .footer-section {
      display: flex;
      justify-content: flex-start;
      align-items: flex-start;
      gap: 20px;
      margin-top: 5px;
      padding-top: 5px;
      border-top: 2px solid #000;
    }

    .footer-logos-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .footer-logos-left img {
      height: 50px;
    }

    .footer-text-right {
      text-align: left;
      font-size: 9px;
      font-family: Arial, sans-serif;
      line-height: 1.3;
    }

    .footer-motto {
      margin-top: 5px;
      font-weight: bold;
      font-style: italic;
      font-size: 11px;
    }
  </style>
  <?php include 'assets/inc/console_suppress.php'; ?>
</head>

<body>


  <div class="card-container">
    <div class="no-print"
      style="margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px; text-align: center;">
      <h1 style="margin: 0; font-size: 24px; color: #333; font-family: Arial, sans-serif; text-transform: uppercase;">
        <?= htmlspecialchars($person['name']) ?>
      </h1>
      <span
        style="display: inline-block; background: <?= $primaryColor ?>; color: white; padding: 4px 15px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; margin-top: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <?= ($patientType === 'employee') ? 'Employee' : 'Student' ?>
      </span>
    </div>

    <?php if ($type == 'employee'): ?>
      <!-- EMPLOYEE HEADER -->
      <div style="text-align: center; font-family: 'Times New Roman', serif; color: #000; margin-bottom: 5px;">
        <div style="font-size: 10px; margin-bottom: 1px;">Republic of the Philippines</div>
        <div style="font-size: 10px; font-weight: bold; margin-bottom: 1px;">Department of Education</div>
        <div style="font-size: 10px; margin-bottom: 1px;">Region III</div>
        <div style="font-size: 10px; font-weight: bold; margin-bottom: 1px;">SCHOOL DIVISION OFFICE OF OLONGAPO</div>
        <div style="font-size: 10px; font-weight: bold; margin-bottom: 5px;">OLONGAPO CITY NATIONAL HIGH SCHOOL</div>
        <div style="font-size: 16px; font-weight: bold; margin-top: 5px;">EMPLOYEE HEALTH CARD -
          <?= strtoupper($person['name']) ?>
        </div>
      </div>

      <div class="emp-info-row">
        <span class="emp-label">Date:</span>
        <div class="emp-data-line" style="width: 150px; flex: none;"><?= getVal('date_examined', $health_values) ?></div>
      </div>

      <div class="emp-info-row">
        <span class="emp-label">Name:</span>
        <div class="emp-data-line"><?= strtoupper($person['name']) ?></div>

        <span class="emp-label" style="margin-left: 15px;">Date of Birth:</span>
        <div class="emp-data-line" style="width: 120px; flex: none;">
          <?= $person['birth_date'] ? date('m/d/Y', strtotime($person['birth_date'])) : '' ?>
        </div>

        <span class="emp-label" style="margin-left: 15px;">Age:</span>
        <div class="emp-data-line" style="width: 50px; flex: none;"><?= getVal('age', $health_values) ?></div>

        <span class="emp-label" style="margin-left: 20px; font-weight: bold;">Gender:</span>
        <div class="emp-box-group" style="margin-left: 5px;">
          <div class="emp-box-item">M <div class="view-box"><?= $person['gender'] == 'Male' ? '/' : '' ?></div>
          </div>
          <div class="emp-box-item">F <div class="view-box"><?= $person['gender'] == 'Female' ? '/' : '' ?></div>
          </div>
        </div>
      </div>

      <div class="emp-info-row">
        <span class="emp-label">School/District/Division:</span>
        <div class="emp-data-line"><?= getVal('school_division', $health_values) ?: 'OCNHS / SDO OLONGAPO CITY' ?></div>

        <span class="emp-label" style="margin-left: 20px; font-weight: bold;">Civil Status:</span>
        <div class="emp-box-group" style="margin-left: 5px;">
          <?php $cs = getVal('civil_status', $health_values); ?>
          <div class="emp-box-item">S <div class="view-box"><?= $cs == 'Single' ? '/' : '' ?></div>
          </div>
          <div class="emp-box-item">M <div class="view-box"><?= $cs == 'Married' ? '/' : '' ?></div>
          </div>
          <div class="emp-box-item">W <div class="view-box"><?= $cs == 'Widowed' ? '/' : '' ?></div>
          </div>
          <div class="emp-box-item">S <div class="view-box"><?= $cs == 'Separated' ? '/' : '' ?></div>
          </div>
        </div>
      </div>

      <div class="emp-info-row">
        <span class="emp-label">Position/Designation:</span>
        <div class="emp-data-line"><?= $person['position'] ?? '' ?></div>

        <span class="emp-label" style="margin-left: 15px;">Years In Service:</span>
        <div class="emp-data-line" style="width: 100px; flex: none;"><?= getVal('years_service', $health_values) ?></div>
      </div>

      <div class="emp-info-row">
        <span class="emp-label">First Year in Service:</span>
        <div class="emp-data-line"><?= getVal('first_year_service', $health_values) ?></div>
      </div>

      <hr style="margin: 10px 0;">

      <!-- I. Family History -->
      <table class="emp-table">
        <tr>
          <td style="width: 50%;">
            <div class="sec-header">I. FAMILY HISTORY (pls. check)</div>
            <div class="chk-row" style="width:90%; margin-bottom:2px;">
              <span class="chk-lbl"></span>
              <div class="chk-boxes"><strong>Y</strong> <strong>N</strong></div>
            </div>
            <?php
            $fam_items = ['Hypertension' => 'fam_hibp', 'Cardiovascular Disease' => 'fam_heart', 'Diabetes Mellitus' => 'fam_dm', 'Kidney Disease' => 'fam_kidney', 'Cancer' => 'fam_cancer', 'Asthma' => 'fam_asthma', 'Allergy' => 'fam_allergy'];
            foreach ($fam_items as $l => $k): ?>
              <div class="chk-row" style="width:90%;">
                <span class="chk-lbl"><?= $l ?>:</span>
                <div class="chk-boxes">
                  <div class="view-box"><?= getVal($k, $health_values) == '1' ? '/' : '' ?></div>
                  <div class="view-box"><?= getVal($k . '_no', $health_values) == '1' ? '/' : '' ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </td>
          <td style="width: 50%; padding-left: 10px;">
            <div class="sec-header" style="text-align:center;">Specify Relationship</div>
            <?php for ($i = 0; $i < 7; $i++): ?>
              <div
                style="margin-bottom: 5px; border-bottom: 1px solid #000; height: 16px; text-align: center; font-weight: bold;">
                <?= getVal('fam_relationship_' . $i, $health_values) ?>
              </div>
            <?php endfor; ?>
          </td>
        </tr>
        <tr>
          <td colspan="2">
            <div style="margin-top:4px; display: flex; align-items: center; width: 100%; padding-left: 20px;">
              <span style="font-size: 10px; white-space: nowrap;">Other Remarks:</span>
              <div class="data-line" style="flex: 1; margin-left: 5px; margin-right: 15px;">
                <?= getVal('fam_other_remarks', $health_values) ?>
              </div>
            </div>
          </td>
        </tr>
      </table>

      <hr style="border-top:1px solid #000; margin: 2px 0;">

      <!-- II. Past Medical History -->
      <div class="sec-header">II. PAST MEDICAL HISTORY (check)</div>
      <table class="emp-table">
        <tr>
          <td style="width: 50%;">
            <div class="chk-row" style="width:90%; margin-bottom:2px;"><span class="chk-lbl"></span>
              <div class="chk-boxes"><strong>Y</strong> <strong>N</strong></div>
            </div>
            <?php
            $past_left = ['Hypertension' => 'past_hibp', 'Asthma' => 'past_asthma', 'Diabetes Mellitus' => 'past_dm', 'Cardio Vascular Disease' => 'past_heart', 'Allergy (pls specify)' => 'past_allergy'];
            foreach ($past_left as $l => $k): ?>
              <div class="chk-row" style="width:90%;">
                <span class="chk-lbl"><?= $l ?>     <?php if ($k == 'past_allergy'): ?><span class="data-line"
                      style="min-width:80px;"><?= getVal('past_allergy_desc', $health_values) ?></span><?php endif; ?></span>
                <div class="chk-boxes">
                  <div class="view-box"><?= getVal($k, $health_values) == '1' ? '/' : '' ?></div>
                  <div class="view-box"><?= getVal($k . '_no', $health_values) == '1' ? '/' : '' ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </td>
          <td style="width: 50%;">
            <div class="chk-row" style="width:90%; margin-bottom:2px;"><span class="chk-lbl"></span>
              <div class="chk-boxes"><strong>Y</strong> <strong>N</strong></div>
            </div>
            <?php
            $past_right = ['Tuberculosis' => 'past_tb', 'Surgical Operation (specify)' => 'past_surgery', 'Yellowish discoloration' => 'past_yellow', 'Last hospitalization' => 'past_hospital', 'Others (pls. specify)' => 'past_others'];
            foreach ($past_right as $l => $k): ?>
              <div class="chk-row" style="width:90%;">
                <span class="chk-lbl"><?= $l ?></span>
                <div class="chk-boxes">
                  <div class="view-box"><?= getVal($k, $health_values) == '1' ? '/' : '' ?></div>
                  <div class="view-box"><?= getVal($k . '_no', $health_values) == '1' ? '/' : '' ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </td>
        </tr>
      </table>

      <!-- Tests Row -->
      <table style="border:none;">
        <tr style="border:none;">
          <td style="border:none;">
            <table style="border:none !important; width: 100%;" class="emp-table">
              <tr>
                <td width="20%"><strong>Last Taken</strong></td>
                <td width="15%"><strong>Date</strong></td>
                <td width="20%"><strong>Result</strong></td>
                <td width="2%"></td>
                <td width="23%"></td>
                <td width="10%"><strong>Date</strong></td>
                <td width="10%"><strong>Result</strong></td>
              </tr>
              <tr>
                <td>CXR/Sputum Result</td>
                <td>
                  <div class="data-line" style="width:100%;"><?= getVal('test_cxr_date', $health_values) ?></div>
                </td>
                <td>
                  <div class="data-line" style="width:100%;"><?= getVal('test_cxr', $health_values) ?></div>
                </td>
                <td></td>
                <td>Drug Testing</td>
                <td>
                  <div class="data-line" style="width:100%;"><?= getVal('test_drug_date', $health_values) ?></div>
                </td>
                <td>
                  <div class="data-line" style="width:100%;"><?= getVal('test_drug', $health_values) ?></div>
                </td>
              </tr>
              <tr>
                <td>ECG</td>
                <td>
                  <div class="data-line" style="width:100%;"><?= getVal('test_ecg_date', $health_values) ?></div>
                </td>
                <td>
                  <div class="data-line" style="width:100%;"><?= getVal('test_ecg', $health_values) ?></div>
                </td>
                <td></td>
                <td>Neuropsychiatric exam</td>
                <td>
                  <div class="data-line" style="width:100%;"><?= getVal('test_neuro_date', $health_values) ?></div>
                </td>
                <td>
                  <div class="data-line" style="width:100%;"><?= getVal('test_neuro', $health_values) ?></div>
                </td>
              </tr>
              <tr>
                <td>Urinalysis</td>
                <td>
                  <div class="data-line" style="width:100%;"><?= getVal('test_urine_date', $health_values) ?></div>
                </td>
                <td>
                  <div class="data-line" style="width:100%;"><?= getVal('test_urine', $health_values) ?></div>
                </td>
                <td></td>
                <td>Blood Typing</td>
                <td>
                  <div class="data-line" style="width:100%;"><?= getVal('test_blood_type_date', $health_values) ?></div>
                </td>
                <td>
                  <div class="data-line" style="width:100%;"><?= getVal('test_blood_type', $health_values) ?></div>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- III. Social History -->
      <div class="sec-header">III. SOCIAL HISTORY</div>
      <div style="font-size:9px; padding-left: 20px; display: flex; flex-direction: column; gap: 5px;">
        <div style="display: flex; align-items: center; width: 100%; gap: 10px;">
          <div style="display: flex; align-items: center; width: 120px;">
            Smoking: &nbsp; Y <div class="view-box"><?= getVal('social_smoking', $health_values) ? '/' : '' ?></div>
            &nbsp; N <div class="view-box"><?= getVal('social_smoking_no', $health_values) ? '/' : '' ?></div>
          </div>
          <div style="display: flex; align-items: center; flex: 1; white-space: nowrap;">
            Age Started: <div class="data-line" style="flex:1; margin-left: 5px;">
              <?= getVal('social_age_started', $health_values) ?>
            </div>
          </div>
          <div style="display: flex; align-items: center; flex: 1; white-space: nowrap;">
            Sticks/packs per day: <div class="data-line" style="flex:1; margin-left: 5px;">
              <?= getVal('social_sticks', $health_values) ?>
            </div>
          </div>
          <div style="display: flex; align-items: center; flex: 1; white-space: nowrap;">
            Pack per year: <div class="data-line" style="flex:1; margin-left: 5px;">
              <?= getVal('social_pack_year', $health_values) ?>
            </div>
          </div>
        </div>
        <div style="display: flex; align-items: center; width: 100%; gap: 10px;">
          <div style="display: flex; align-items: center; width: 120px;">
            Alcohol: &nbsp;&nbsp;&nbsp; Y <div class="view-box"><?= getVal('social_alcohol', $health_values) ? '/' : '' ?>
            </div>
            &nbsp; N <div class="view-box"><?= getVal('social_alcohol_no', $health_values) ? '/' : '' ?></div>
          </div>
          <div style="display: flex; align-items: center; flex: 1; white-space: nowrap;">
            How Often: <div class="data-line" style="flex:1; margin-left: 5px;">
              <?= getVal('social_how_often', $health_values) ?>
            </div>
          </div>
          <div style="display: flex; align-items: center; flex: 1; white-space: nowrap;">
            Food preference: <div class="data-line" style="flex:1; margin-left: 5px;">
              <?= getVal('social_food_pref', $health_values) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- IV. OB-GYNE History -->
      <div class="sec-header" style="margin-top:10px;">IV. OB-GYNE HISTORY (pls. encircle) (Female only)</div>
      <table class="emp-table">
        <tr>
          <td colspan="2" style="padding-left: 20px;">
            <div style="display: flex; align-items: center; gap: 10px; width: 100%;">
              Menarche: <div class="data-line" style="flex: 1;"><?= getVal('ob_lmp', $health_values) ?></div>
              Cycle: <div class="data-line" style="flex: 1;"><?= getVal('ob_cycle', $health_values) ?></div>
              Duration: <div class="data-line" style="flex: 1;"><?= getVal('ob_duration', $health_values) ?></div>
            </div>
          </td>
        </tr>
        <tr>
          <td style="width: 50%;">
            <div class="chk-row" style="width:90%;">
              <span class="chk-lbl">Parity:</span>
              <div style="display: flex; gap: 5px; align-items: center;">
                F <div class="view-box"><?= getVal('ob_parity_f', $health_values) ? '/' : '' ?></div>
                P <div class="view-box"><?= getVal('ob_parity_p', $health_values) ? '/' : '' ?></div>
                A <div class="view-box"><?= getVal('ob_parity_a', $health_values) ? '/' : '' ?></div>
                L <div class="view-box"><?= getVal('ob_parity_l', $health_values) ? '/' : '' ?></div>
              </div>
            </div>
          </td>
          <td style="width: 50%;"></td>
        </tr>
        <tr>
          <td style="width: 50%;">
            <div class="chk-row" style="width:90%;">
              <span class="chk-lbl">Papsmear done:</span>
              <div style="display: flex; gap: 5px; align-items: center;">
                Y <div class="view-box"><?= getVal('ob_papsmear', $health_values) ? '/' : '' ?></div>
                N <div class="view-box"><?= getVal('ob_papsmear_no', $health_values) ? '/' : '' ?></div>
              </div>
            </div>
          </td>
          <td style="width: 50%; padding-left: 5px;">
            <div style="display: flex; align-items: center; width: 95%;">
              if Yes, when: <div class="data-line" style="flex: 1; margin-left:5px;">
                <?= getVal('ob_papsmear_when', $health_values) ?>
              </div>
            </div>
          </td>
        </tr>
        <tr>
          <td style="width: 50%;">
            <div class="chk-row" style="width:90%;">
              <span class="chk-lbl">Self Breast Examination done:</span>
              <div style="display: flex; gap: 5px; align-items: center;">
                Y <div class="view-box"><?= getVal('ob_breast_exam', $health_values) ? '/' : '' ?></div>
                N <div class="view-box"><?= getVal('ob_breast_exam_no', $health_values) ? '/' : '' ?></div>
              </div>
            </div>
          </td>
          <td style="width: 50%;"></td>
        </tr>
        <tr>
          <td style="width: 50%;">
            <div class="chk-row" style="width:90%;">
              <span class="chk-lbl">Mass noted:</span>
              <div style="display: flex; gap: 5px; align-items: center;">
                Y <div class="view-box"><?= getVal('ob_mass_noted', $health_values) ? '/' : '' ?></div>
                N <div class="view-box"><?= getVal('ob_mass_noted_no', $health_values) ? '/' : '' ?></div>
              </div>
            </div>
          </td>
          <td style="width: 50%; padding-left: 5px;">
            <div style="display: flex; align-items: center; width: 95%;">
              Specify where: <div class="data-line" style="flex: 1; margin-left:5px;">
                <?= getVal('ob_mass_noted_when', $health_values) ?>
              </div>
            </div>
          </td>
        </tr>
        <tr>
          <td colspan="2" style="font-weight:bold; padding-left:5px;">For Male personnel:</td>
        </tr>
        <tr>
          <td style="width: 50%;">
            <div class="chk-row" style="width:90%;">
              <span class="chk-lbl">Digital rectal examination done:</span>
              <div style="display: flex; gap: 5px; align-items: center;">
                Y <div class="view-box"><?= getVal('male_dre', $health_values) ? '/' : '' ?></div>
                N <div class="view-box"><?= getVal('male_dre_no', $health_values) ? '/' : '' ?></div>
              </div>
            </div>
          </td>
          <td style="width: 50%; padding-left: 5px;">
            <div style="display: flex; align-items: center; width: 95%;">
              Date examined: <div class="data-line" style="width: 100px; margin-right: 5px; margin-left:5px;">
                <?= getVal('male_dre_date', $health_values) ?>
              </div>
              Result: <div class="data-line" style="flex: 1;"><?= getVal('male_dre_result', $health_values) ?></div>
            </div>
          </td>
        </tr>
      </table>

      <!-- V. Present Health Status -->
      <table class="emp-table" style="margin-top: 10px;">
        <tr>
          <td width="50%" style="vertical-align: top;">
            <div class="chk-row" style="width:90%; margin-bottom:5px;">
              <span class="chk-lbl" style="font-weight:bold;">Present Health Status (pls. check)</span>
              <div class="chk-boxes"><strong>Y</strong> <strong>N</strong></div>
            </div>
            <div class="chk-row" style="width:90%;">
              <span class="chk-lbl">Cough <span style="font-size:9px;">2wks 1month longer</span></span>
              <div class="chk-boxes">
                <div class="view-box"><?= getVal('pres_cough', $health_values) ? '/' : '' ?></div>
                <div class="view-box"><?= getVal('pres_cough_no', $health_values) ? '/' : '' ?></div>
              </div>
            </div>
            <?php
            $pres_left = ['Dizziness' => 'pres_dizzy', 'Dyspnea' => 'pres_dyspnea', 'Chest/Back Pain' => 'pres_pain', 'Easy fatigability' => 'pres_fatigue', 'Joint/Extremity Pains' => 'pres_joint', 'Blurring of Vision' => 'pres_blur', 'Wearing Eyeglasses' => 'pres_glasses', 'Vaginal Discharge/Bleeding' => 'pres_discharge'];
            foreach ($pres_left as $l => $k): ?>
              <div class="chk-row" style="width:90%;">
                <span class="chk-lbl"><?= $l ?></span>
                <div class="chk-boxes">
                  <div class="view-box"><?= getVal($k, $health_values) ? '/' : '' ?></div>
                  <div class="view-box"><?= getVal($k . '_no', $health_values) ? '/' : '' ?></div>
                </div>
              </div>
            <?php endforeach; ?>
            <div style="margin-top:5px; padding-left: 20px;">
              Dental Status (pls. specify) <div class="data-line" style="width: 140px; margin-left:5px;">
                <?= getVal('pres_dental', $health_values) ?>
              </div>
            </div>
          </td>
          <td width="50%" style="vertical-align: top;">
            <div class="chk-row" style="width:90%; margin-bottom:5px;">
              <span class="chk-lbl" style="font-weight:bold;">Present Health Status (pls. check)</span>
              <div class="chk-boxes"><strong>Y</strong> <strong>N</strong></div>
            </div>
            <?php
            $pres_right = ['Lumps' => 'pres_lumps', 'Painful Urination' => 'pres_urine', 'Poor/Loss of Hearing' => 'pres_hear', 'Syncope/Fainting' => 'pres_sync', 'Convulsions' => 'pres_conv', 'Malaria' => 'pres_malaria', 'Goiter' => 'pres_goiter', 'Anemia' => 'pres_anemia'];
            foreach ($pres_right as $l => $k): ?>
              <div class="chk-row" style="width:90%;">
                <span class="chk-lbl"><?= $l ?></span>
                <div class="chk-boxes">
                  <div class="view-box"><?= getVal($k, $health_values) ? '/' : '' ?></div>
                  <div class="view-box"><?= getVal($k . '_no', $health_values) ? '/' : '' ?></div>
                </div>
              </div>
            <?php endforeach; ?>
            <div style="margin-top:5px; padding-left: 20px;">
              Others: (pls. specify) <div class="data-line" style="width: 140px; margin-left:5px;">
                <?= getVal('pres_others_desc', $health_values) ?>
              </div>
            </div>
          </td>
        </tr>
      </table>
      <div style="font-size:10px; margin-top:5px; padding-left: 20px;">
        <strong>Present medications taken: (pls. specify)</strong>
        <div class="data-line" style="width:60%; margin-left:5px;"><?= getVal('pres_medications', $health_values) ?></div>
      </div>

      <!-- Legend Section (Employee) -->
      <div style="margin-top: 15px; font-size: 11px; font-family: Arial;">
        <table style="width: 100%; border: none !important; border-collapse: collapse;">
          <tr>
            <td style="width: 80px; vertical-align: top; border: none; font-weight: bold;">Legend</td>
            <td style="width: 60px; border: none; font-weight: bold;">CXR</td>
            <td style="width: 220px; border: none;">-Chest X-ray</td>
            <td style="width: 60px; border: none; font-weight: bold;">PTB</td>
            <td style="border: none;">-Pulmonary Tuberculosis</td>
          </tr>
          <tr>
            <td style="border: none;"></td>
            <td style="border: none; font-weight: bold;">ECG</td>
            <td style="border: none;">-Electro-Cardio Gram</td>
            <td style="border: none; font-weight: bold;">F</td>
            <td style="border: none;">-Full Term</td>
          </tr>
          <tr>
            <td style="border: none;"></td>
            <td style="border: none; font-weight: bold;">Y</td>
            <td style="border: none;">-Yes</td>
            <td style="border: none; font-weight: bold;">P</td>
            <td style="border: none;">-Pre mature</td>
          </tr>
          <tr>
            <td style="border: none;"></td>
            <td style="border: none; font-weight: bold;">N</td>
            <td style="border: none;">-No</td>
            <td style="border: none; font-weight: bold;">A</td>
            <td style="border: none;">-Abortion</td>
          </tr>
          <tr>
            <td style="border: none;"></td>
            <td style="border: none; font-weight: bold;">HPN</td>
            <td style="border: none;">-Hypertension</td>
            <td style="border: none; font-weight: bold;">L</td>
            <td style="border: none;">-Live Birth</td>
          </tr>
          <tr>
            <td style="border: none;"></td>
            <td style="border: none; font-weight: bold;">CVD</td>
            <td style="border: none;">-Cardio Vascular Disease</td>
            <td colspan="2" style="border: none;"></td>
          </tr>
          <tr>
            <td style="border: none;"></td>
            <td style="border: none; font-weight: bold;">DM</td>
            <td style="border: none; font-weight: bold;">-Diabetes Mellitus</td>
            <td colspan="2" rowspan="3" style="border: none; vertical-align: bottom;">
              <div style="display: flex; flex-direction: column; margin-top: 15px;">
                <div style="display: flex; align-items: flex-end; margin-bottom: 5px;">
                  <span style="white-space: nowrap; width: 105px; font-weight: normal;">Interviewed by:</span>
                  <div style="flex: 1;">
                    <?php
                    $interviewerSig = getVal('interviewer_signature', $health_values);
                    if ($interviewerSig): ?>
                      <div style="height: 20px; margin-bottom: 1px; text-align: center;">
                        <img src="<?= $interviewerSig ?>" style="max-height: 20px; max-width: 100%; display: inline-block;"
                          alt="Signature" />
                      </div>
                    <?php else: ?>
                      <div style="height: 20px; margin-bottom: 1px;"></div>
                    <?php endif; ?>
                    <div style="border-bottom: 1px solid #000; text-align: center; font-weight: bold; padding: 1px 0;">
                      <?= getVal('interviewed_by', $health_values) ?>
                    </div>
                  </div>
                </div>
                <div style="display: flex; align-items: flex-end;">
                  <span
                    style="white-space: nowrap; width: 105px; text-align: right; padding-right: 5px; font-weight: normal;">Date:</span>
                  <div style="flex: 1; border-bottom: 1px solid #000; text-align: center; font-weight: bold;">
                    <?= getVal('interview_date', $health_values) ?>
                  </div>
                </div>
              </div>
            </td>
          </tr>
          <tr>
            <td style="border: none; height: 15px;"></td>
            <td style="border: none;"></td>
            <td style="border: none;"></td>
          </tr>
          <tr>
            <td style="border: none; height: 15px;"></td>
            <td style="border: none;"></td>
            <td style="border: none;"></td>
          </tr>
        </table>
      </div>

    <?php else: ?>
      <!-- STUDENT HEADER & INFO -->
      <div class="header">
        <img src="assets/img/DepEd-logo.png" class="header-logo" alt="DepEd Logo">
        <h3>Republic of the Philippines</h3>
        <h2>Department of Education</h2>
        <div class="region">Region III</div>
        <div class="division">SCHOOL DIVISION OFFICE OF OLONGAPO CITY</div>
        <div class="school-name">OLONGAPO CITY NATIONAL HIGH SCHOOL</div>
      </div>
      <div class="card-title">SCHOOL HEALTH EXAMINATION CARD - <?= strtoupper($person['name']) ?></div>

      <div class="info-row">
        <div class="info-group" style="width: 65%;">
          <label>NAME OF STUDENT:</label>
          <div class="info-data"><?= strtoupper($person['name']) ?></div>
        </div>
        <div class="info-group" style="width: 30%;">
          <label>LRN #:</label>
          <div class="info-data"><?= $person['lrn'] ?></div>
        </div>
      </div>
      <div class="info-row">
        <div class="info-group" style="flex: 2;"><label>ADDRESS:</label>
          <div class="info-data"><?= $person['address'] ?? '-' ?></div>
        </div>
        <div class="info-group" style="flex: 1;"><label>GENDER:</label>
          <div class="info-data"><?= $person['gender'] ?></div>
        </div>
        <div class="info-group" style="flex: 1;"><label>CURRICULUM:</label>
          <div class="info-data"><?= $person['curriculum'] ?></div>
        </div>
      </div>
      <div class="info-row" style="flex-wrap: nowrap;">
        <div class="info-group" style="width: 32%; margin-right: 10px;"><label>BIRTH DATE:</label>
          <div class="info-data"><?= date('m/d/Y', strtotime($person['birth_date'])) ?></div>
        </div>
        <div class="info-group" style="width: 35%; margin-right: 10px;"><label>BIRTHPLACE:</label>
          <div class="info-data"><?= $person['birthplace'] ?? '-' ?></div>
        </div>
        <div class="info-group" style="width: 33%; margin-right: 0;"><label>RELIGION:</label>
          <div class="info-data"><?= $person['religion'] ?? '-' ?></div>
        </div>
      </div>
      <div class="info-row">
        <div class="info-group" style="width: 50%;"><label>PARENT OR GUARDIAN:</label>
          <div class="info-data"><?= $person['guardian'] ?? '-' ?></div>
        </div>
        <div class="info-group" style="width: 45%;"><label>CONTACT NUMBER:</label>
          <div class="info-data"><?= $person['contact'] ?? '-' ?></div>
        </div>
      </div>

      <hr style="margin: 10px 0;">

      <!-- Student Table -->
      <table>
        <thead>
          <tr>
            <th width="30%"></th>
            <th colspan="6">GRADE LEVEL (7-12)</th>
          </tr>
          <tr>
            <th>ITEM</th>
            <th>7</th>
            <th>8</th>
            <th>9</th>
            <th>10</th>
            <th>11</th>
            <th>12</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>Date of Examination</strong></td>
            <?php for ($g = 7; $g <= 12; $g++)
              echo "<td>" . getVal('date_' . $g, $health_values) . "</td>"; ?>
          </tr>
          <tr>
            <td colspan="7" style="background:#eee; text-align:left; padding-left:5px;"><strong>Vital Signs:</strong></td>
          </tr>
          <?php
          $health_rows = ["Temperature", "Blood Pressure", "Cardiac/Pulse Rate", "Respiratory Rate", "Height", "Weight"];
          foreach ($health_rows as $row):
            $slug = strtolower(str_replace([' ', '/'], '_', $row)); ?>
            <tr>
              <td style="text-align: left; padding-left: 10px;"><?= $row ?></td>
              <?php for ($g = 7; $g <= 12; $g++)
                echo "<td>" . getVal($slug . '_' . $g, $health_values) . "</td>"; ?>
            </tr>
          <?php endforeach; ?>
          <tr>
            <td colspan="7" style="background:#eee; text-align:left; padding-left:5px;"><strong>Nutritional Status
                (NS)</strong></td>
          </tr>
          <!-- PROCESSED BMI ROW -->
          <tr>
            <td style="text-align: left; padding-left: 10px;">Processed BMI</td>
            <?php for ($g = 7; $g <= 12; $g++) {
              $w = floatval(getVal('weight_' . $g, $health_values));
              $h = floatval(getVal('height_' . $g, $health_values));
              $bmi = ($w > 0 && $h > 0) ? number_format($w / (($h / 100) * ($h / 100)), 1) : '';
              echo "<td>$bmi</td>";
            } ?>
          </tr>
          <!-- AUTOMATED BMI/WEIGHT FOR AGE -->
          <tr>
            <td style="text-align: left; padding-left: 10px;">BMI/Weight for Age</td>
            <?php for ($g = 7; $g <= 12; $g++) {
              $w = floatval(getVal('weight_' . $g, $health_values));
              $h = floatval(getVal('height_' . $g, $health_values));
              // Recalculate BMI locally for classification
              $bmi = ($w > 0 && $h > 0) ? ($w / (($h / 100) * ($h / 100))) : 0;

              // Calculate Age at Exam Date (with fallback inside helper)
              $examDate = getVal('date_' . $g, $health_values);
              $birthDate = $person['birth_date'] ?? '';
              $age = ($birthDate) ? getAgeFromDate($birthDate, $examDate) : 0;

              // Get Auto Status
              $autoStatus = classifyBMI_View($bmi, $age);

              // Show Auto if available, else Manual
              $finalStatus = $autoStatus ?: getVal('bmi_weight_' . $g, $health_values);
              echo "<td>" . $finalStatus . "</td>";
            } ?>
          </tr>

          <!-- AUTOMATED BMI/HEIGHT FOR AGE -->
          <tr>
            <td style="text-align: left; padding-left: 10px;">BMI/Height for Age</td>
            <?php for ($g = 7; $g <= 12; $g++) {
              $h = floatval(getVal('height_' . $g, $health_values));

              // Calculate Age (reused logic)
              $examDate = getVal('date_' . $g, $health_values);
              $birthDate = $person['birth_date'] ?? '';
              $age = ($birthDate) ? getAgeFromDate($birthDate, $examDate) : 0;

              // Auto HFA Status
              $autoStatus = classifyHeightForAge_View($h, $age, $person['gender'] ?? 'Male');

              $finalStatus = $autoStatus ?: getVal('bmi_height_' . $g, $health_values);
              echo "<td>" . $finalStatus . "</td>";
            } ?>
          </tr>
          <tr>
            <td colspan="7" style="background:#eee; text-align:left; padding-left:5px;"><strong>Visual Acuity</strong>
            </td>
          </tr>
          <?php
          $visual_rows = ["Snellen", "Eye chart (Near)", "Ishihara chart"];
          foreach ($visual_rows as $row):
            $slug = strtolower(str_replace([' ', '(', ')'], '_', $row)); ?>
            <tr>
              <td style="text-align: left; padding-left: 10px;"><?= $row ?></td>
              <?php for ($g = 7; $g <= 12; $g++)
                echo "<td>" . getVal($slug . '_' . $g, $health_values) . "</td>"; ?>
            </tr>
          <?php endforeach; ?>
          <tr>
            <td style="text-align: left;"><strong>Auditory screening (Tuning Fork)</strong></td>
            <?php for ($g = 7; $g <= 12; $g++)
              echo "<td>" . getVal('auditory_' . $g, $health_values) . "</td>"; ?>
          </tr>
          <?php
          $rows = ["Skin/Scalp", "Eyes/Ears/Nose", "Mouth/Neck/Throat", "Lungs/Heart", "Abdomen/Genitalia", "Spine/Extremities", "Iron-Folic Acid Supplementation (V o X)", "Deworming (V o X)", "Immunization (specify)", "SBFP Beneficiary (V o X)", "4Ps Beneficiary (V o X)", "Menarche", "Others, Specify"];

          $rowsWithDate = [
            "Iron-Folic Acid Supplementation (V o X)",
            "Deworming (V o X)",
            "Immunization (specify)",
            "Menarche"
          ];

          foreach ($rows as $row):
            $slug = strtolower(str_replace([' ', '/', '(', ')', ','], '_', $row));
            $isCheckbox = (strpos($row, '(V o X)') !== false);
            $hasDate = in_array($row, $rowsWithDate);
            $onlyDate = in_array($row, [
              "Iron-Folic Acid Supplementation (V o X)",
              "Deworming (V o X)",
              "Immunization (specify)",
              "Menarche"
            ]);
            ?>
            <tr>
              <td style="text-align: left;"><strong><?= $row ?></strong></td>
              <?php for ($g = 7; $g <= 12; $g++) {
                $val = getVal($slug . '_' . $g, $health_values);
                $dateVal = $hasDate ? getVal($slug . '_date_' . $g, $health_values) : '';

                echo "<td>";
                if ($onlyDate) {
                  // Only Display Date
                  echo $dateVal;
                } else {
                  // Display Main Value
                  echo ($isCheckbox ? ($val ? '/' : '') : $val);
                  // Display Date below if exists
                  if ($hasDate && $dateVal) {
                    echo "<div style='font-size:9px; margin-top:2px;'>" . $dateVal . "</div>";
                  }
                }
                echo "</td>";
              } ?>
            </tr>
          <?php endforeach; ?>
          <tr>
            <td style="text-align: left;"><strong>Examined by</strong></td>
            <?php for ($g = 7; $g <= 12; $g++): ?>
              <td style="vertical-align: bottom; padding: 2px 0;">
                <?php
                $examinerSig = getVal('examiner_signature_' . $g, $health_values);
                if ($examinerSig): ?>
                  <div style="height: 20px; margin-bottom: 1px; text-align: center;">
                    <img src="<?= $examinerSig ?>" style="max-height: 20px; max-width: 100%; display: inline-block;"
                      alt="Signature" />
                  </div>
                <?php else: ?>
                  <div style="height: 20px; margin-bottom: 1px;"></div>
                <?php endif; ?>
                <div style="font-size: 9px; text-align: center; font-weight: bold; padding: 1px 0;">
                  <?= getVal('examiner_' . $g, $health_values) ?>
                </div>
              </td>
            <?php endfor; ?>
          </tr>
        </tbody>
      </table>

      <!-- Legend Table -->
      <table class="legend-table" style="margin-top: 5px; font-size: 9px;">
        <thead>
          <tr>
            <th width="9%">NS</th>
            <th width="10%">Skin/Scalp</th>
            <th width="10%">Eye/Ear/Nose</th>
            <th width="12%">Mouth/Neck/<br>Throat</th>
            <th width="11%">Lungs/Heart</th>
            <th width="10%">Abdomen/<br>Genitalia</th>
            <th width="10%">Spine/<br>Extremities</th>
            <th width="8%">Deformities</th>
            <th width="10%">Remarks/<br>Intervention</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $legend_data = [
            'a' => ['Normal Weight', 'Normal', 'Normal', 'Normal', 'Normal Breath Sounds', 'Normal Abdomen', 'Normal Spine', 'Acquired', 'Needs Supervision'],
            'b' => ['Wasted', 'Pediculosis', 'Stye', 'Enlarged Tonsils', 'Normal Heart Sounds', 'Normal Genitalia', 'Normal Upper Extremities', 'Congenital (Specify)', 'Needs Close Supervision'],
            'c' => ['Severely Wasted', 'Tinea Flava', 'Conjunctivitis', 'Enlarged Thyroid Gland', 'Rales', 'Mass', 'Normal Lower Extremities', '', 'Needs Follow-up'],
            'd' => ['Overweight', 'Ringworm', 'Squinting', 'Cleft Palate Harelip', 'Wheezes', 'Hemorrhoid', 'Scoliosis', '', 'Corrected'],
            'e' => ['Obese', 'Eczema/Rash', 'Pale conjunctivae', 'With Lymphadenopathy', 'Murmur', 'Hernia', 'Lordosis', '', 'Treated'],
            'f' => ['Normal Height', 'Impetigo/Boil', 'Ear discharged', '', 'Deformed Chest', 'Tenderness', 'Kyphosis', '', 'Advised/ Counseled'],
            'g' => ['Stunted', 'Dandruff', 'Impacted cerumen', '', 'Irregular Heart Rate', 'Bowel sounds', 'Bowlegs/ Knock Knees', '', 'Referred'],
            'h' => ['Severely Stunted', 'Bruises/ Hematoma', 'Deformed nose', '', '', '', 'Flat Foot', '', 'Parents Notified'],
            'i' => ['Tall', 'Acne/Pimple', 'Ear perforation', '', '', '', 'Club foot', '', ''],
            'j' => ['', '', 'Ear tag', '', '', '', 'Polio', '', '']
          ];
          foreach ($legend_data as $key => $row_cells) {
            echo '<tr>';
            foreach ($row_cells as $content) {
              $text = $content ? "<strong>$key.</strong> $content" : "";
              echo "<td style='text-align:left;'>$text</td>";
            }
            echo '</tr>';
          }
          ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php if ($type != 'employee'): ?>
      <div class="footer-section">
        <div class="footer-logos-left">
          <img src="assets/img/deped-matatag-logos.png" alt="DepEd Matatag Logo">
          <img src="assets/img/ocnhs_logo.png" alt="OCNHS Logo">
        </div>
        <div class="footer-text-right">
          <strong>Address:</strong> Corner 14<sup>th</sup> St., Rizal Ave. East Tapinac, Olongapo City<br>
          <strong>Contact No.:</strong> (047) 223-3744<br>
          <strong>Email Address:</strong> 301051@deped.gov.ph
          <div class="footer-motto">"SDO Olongapo City: Towards a Culture of Excellence"</div>
        </div>
      </div>
    <?php endif; ?>

  </div>

  <!-- Actions -->
  <div class="fab-container no-print">
    <a href="<?= $backLink ?>?view_id=<?= $id ?>" class="btn-back" title="Go Back"><i
        class="fa-solid fa-arrow-left"></i></a>

    <button onclick="showAISummary()" class="btn-print" style="background: #7e57c2; margin-right: 10px;">
      <i class="fa-solid fa-robot"></i> AI SUMMARY
    </button>
    <button onclick="window.print()" class="btn-print"><i class="fa-solid fa-print"></i> PRINT</button>
  </div>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    function showAISummary() {
      const studentId = <?= $id ?>;

      Swal.fire({
        title: 'Generating Summary...',
        text: 'AI is analyzing patient records.',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
          fetch(`api/ai_suggestions.php?action=patient_summary&student_id=${studentId}`)
            .then(res => res.json())
            .then(data => {
              Swal.fire({
                title: 'Health Summary',
                html: `<div style="text-align: left; white-space: pre-line;">${data.summary}</div>`,
                icon: 'info',
                confirmButtonColor: '#7e57c2'
              });
            })
            .catch(error => {
              Swal.fire('Error', 'Failed to generate summary.', 'error');
            });
        }
      });
    }
  </script>
</body>

</html>