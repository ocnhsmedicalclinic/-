<?php
require_once "../config/db.php";
requireLogin();

// Include PHPMailer
require '../lib/PHPMailer/PHPMailer.php';
require '../lib/PHPMailer/SMTP.php';
require '../lib/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Fetch Logic
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $type = isset($_GET['type']) ? $_GET['type'] : 'student';

    if ($type == 'employee') {
        $table = "employees";
        $backLink = "employees.php";
    } elseif ($type == 'others') {
        $table = "others";
        $backLink = "others.php";
    } else {
        $table = "students";
        $backLink = "student.php";
    }

    $result = $conn->query("SELECT * FROM $table WHERE id = '$id'");

    if ($result->num_rows > 0) {
        $person = $result->fetch_assoc();
        $treatment_logs = json_decode($person['treatment_logs_json'] ?? '[]', true);
    } else {
        die("<div style='text-align:center; padding:50px;'><h1>❌ Error</h1><p>" . ucfirst($type) . " record not found.</p><a href='$backLink'>Go Back</a></div>");
    }
} else {
    die("<div style='text-align:center; padding:50px;'><h1>⚠️ Error</h1><p>No Record ID provided.</p><a href='student.php'>Go Back</a></div>");
}

// Pagination Logic
$ROWS_PER_PAGE = 19;

if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $currentPage = (int) $_GET['page'];
} else {
    $currentPage = 1;
    if (isset($treatment_logs) && is_array($treatment_logs) && !empty($treatment_logs)) {
        $maxIndex = 0;
        foreach ($treatment_logs as $index => $row) {
            if (is_array($row)) {
                if (!empty($row['date']) || !empty($row['complaint']) || !empty($row['treatment'])) {
                    if ($index > $maxIndex)
                        $maxIndex = $index;
                }
            }
        }
        $firstRow = $treatment_logs[0] ?? [];
        $hasData = !empty($firstRow['date']) || !empty($firstRow['complaint']);
        if ($hasData) {
            $currentPage = floor(($maxIndex + 1) / $ROWS_PER_PAGE) + 1;
        }
    }
}

if ($currentPage < 1)
    $currentPage = 1;
$offset = ($currentPage - 1) * $ROWS_PER_PAGE;
$endIndex = $offset + $ROWS_PER_PAGE;

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) {
    // Re-fetch to avoid race conditions or use the one at top
    $postedRows = $_POST['rows'] ?? [];

    // Load original data to compare changes
    $originalData = json_decode($person['treatment_logs_json'] ?? '[]', true);

    // Process Rows
    foreach ($postedRows as $key => &$row) {
        $globalIndex = $offset + $key;
        $oldRow = $originalData[$globalIndex] ?? [];

        $newNextVisit = $row['next_visit'] ?? '';
        $oldNextVisit = $oldRow['next_visit'] ?? '';

        // Auto-reset email status if date changed
        if ($newNextVisit != $oldNextVisit) {
            $row['email_sent'] = '0';
            $row['email_status'] = ''; // Clear status
        }

        // INVENTORY DEDUCTION LOGIC (Support 3 medicine slots)
        for ($mIdx = 1; $mIdx <= 3; $mIdx++) {
            $sfx = ($mIdx === 1) ? '' : $mIdx;
            $newP = trim($row['plan' . $sfx] ?? '');
            $oldP = trim($oldRow['plan' . $sfx] ?? '');

            $newQ = (int) ($row['quantity' . $sfx] ?? 1);
            if ($newQ <= 0)
                $newQ = 1;

            $oldQ = (int) ($oldRow['quantity' . $sfx] ?? ($oldP ? 1 : 0));

            $deductQty = 0;
            $medName = '';

            if (!empty($newP)) {
                if ($newP !== $oldP) {
                    $deductQty = $newQ;
                    $medName = $newP;
                } elseif ($newQ > $oldQ) {
                    $deductQty = $newQ - $oldQ;
                    $medName = $newP;
                }
            }

            if ($deductQty > 0 && !empty($medName)) {
                // Clean stock suffix
                if (strpos($medName, ' (Stock:') !== false) {
                    $parts = explode(' (Stock:', $medName);
                    $medName = trim($parts[0]);
                    $row['plan' . $sfx] = $medName;
                }

                $stmt = $conn->prepare("SELECT id, quantity FROM inventory_items WHERE name = ? LIMIT 1");
                $stmt->bind_param("s", $medName);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($res->num_rows > 0) {
                    $item = $res->fetch_assoc();
                    $itemId = $item['id'];
                    $currentQty = $item['quantity'];

                    if ($currentQty > 0) {
                        $update = $conn->prepare("UPDATE inventory_items SET quantity = quantity - ? WHERE id = ?");
                        $update->bind_param("ii", $deductQty, $itemId);
                        $update->execute();

                        $log = $conn->prepare("INSERT INTO inventory_transactions (item_id, type, quantity, remarks, user_id) VALUES (?, 'Dispensed', ?, ?, ?)");
                        $remarks = "Used in treatment for " . $person['name'];
                        $userId = $_SESSION['user_id'] ?? 0;
                        $log->bind_param("iisi", $itemId, $deductQty, $remarks, $userId);
                        $log->execute();
                    }
                }
            }
        }
    }

    // Process Emails
    foreach ($postedRows as $key => &$row) {
        $nextVisit = $row['next_visit'] ?? '';
        $email = $row['email'] ?? '';
        $emailSent = $row['email_sent'] ?? '0';

        if (!empty($nextVisit) && !empty($email) && $emailSent != '1') {
            $formattedDate = date('M d, Y h:i A', strtotime($nextVisit));
            $pName = $person['name'];

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = defined('SMTP_USERNAME') ? SMTP_USERNAME : 'ocnhsmedicalclinic@gmail.com';
                $mail->Password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : 'nbtg ekqp thmo fdee';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;

                $mail->setFrom(defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'ocnhsmedicalclinic@gmail.com', 'OCNHS Medical Clinic');
                $mail->addAddress($email, $pName);

                $mail->isHTML(true);
                $mail->Subject = 'Appointment Reminder - OCNHS Medical Clinic';
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #ccc; border-radius: 5px;'>
                        <h2 style='color: #00ACB1;'>Appointment Reminder</h2>
                        <p>Dear $pName,</p>
                        <p>This is a reminder for your upcoming appointment/follow-up at the OCNHS Medical Clinic.</p>
                        <p><strong>Date & Time:</strong> $formattedDate</p>
                        <p>Please come on time.</p>
                        <br>
                        <p><em>OCNHS Medical Clinic Team</em></p>
                    </div>";
                $mail->send();
                $row['email_sent'] = '1';
                $row['email_status'] = 'Sent';
            } catch (Exception $e) {
                $row['email_status'] = 'Failed';
                error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
            }
        }
    }

    // Merge & Compact
    $fullData = json_decode($person['treatment_logs_json'] ?? '[]', true);
    for ($i = 0; $i < $ROWS_PER_PAGE; $i++) {
        $fullData[$offset + $i] = $postedRows[$i];
    }

    $compacted = [];
    ksort($fullData);
    foreach ($fullData as $r) {
        $hasContent = false;
        foreach (['grade', 'int_pharm', 'int_trad', 'int_mo', 'int_dentist', 'int_agency', 'int_rhu', 'int_hosp', 'int_teacher', 'date', 'complaint', 'subjective_complaint', 'objective_complaint', 'assessment', 'plan', 'quantity', 'plan2', 'quantity2', 'plan3', 'quantity3', 'treatment', 'attended', 'signature', 'attended2', 'signature2', 'attended3', 'signature3', 'remarks', 'remarks_signature', 'next_visit'] as $field) {
            if (!empty($r[$field])) {
                $hasContent = true;
                break;
            }
        }
        if ($hasContent)
            $compacted[] = $r;
    }

    $json_data = mysqli_real_escape_string($conn, json_encode($compacted));
    $sql = "UPDATE $table SET treatment_logs_json='$json_data' WHERE id='$id'";

    if ($conn->query($sql)) {
        $nextPage = isset($_POST['save_next']) ? $currentPage + 1 : $currentPage;
        echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>
                Swal.fire({
                    title: 'Saved Successfully!',
                    text: 'The treatment record has been updated.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false,
                    confirmButtonColor: '#00ACB1'
                }).then(() => {
                    window.location.href = 'edit_treatment.php?id=$id&type=$type&page=$nextPage';
                });
              </script></body></html>";
        exit;
    }
}

function getVal($rowIndex, $colKey, $data, $offset)
{
    $globalIndex = $offset + $rowIndex;
    $val = isset($data[$globalIndex][$colKey]) ? $data[$globalIndex][$colKey] : '';
    return htmlspecialchars($val);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Treatment Record - <?= $person['name'] ?></title>
    <link rel="icon" type="image/x-icon" href="assets/img/ocnhs_logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 20px;
            background: #f4f4f4;
        }

        .card-container {
            max-width: 98%;
            margin: 0 auto 80px auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            min-height: 98vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header-logo {
            width: 70px;
            display: block;
            margin: 0 auto 10px;
        }

        .header h3,
        .header h2 {
            margin: 2px 0;
            font-weight: bold;
        }

        .header h2 {
            text-transform: uppercase;
        }

        .card-title {
            text-align: center;
            font-weight: 900;
            font-size: 16px;
            margin: 10px 0;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 5px 0;
            text-transform: uppercase;
        }

        .page-info {
            text-align: right;
            font-style: italic;
            margin-bottom: 5px;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            flex-grow: 1;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 2px;
            text-align: center;
        }

        th {
            background: #eee;
        }

        input {
            width: 100%;
            border: none;
            background: transparent;
            padding: 5px;
            font-size: 11px;
            text-align: center;
        }

        input:focus {
            background: #e0f7fa;
            outline: none;
        }

        .fab-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            gap: 10px;
            flex-direction: row-reverse;
        }

        .btn {
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            height: 50px;
            text-decoration: none;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-save {
            background: #00ACB1;
        }

        .btn-next {
            background: #007bff;
        }

        .btn-prev {
            background: #6c757d;
        }

        .btn-back-home {
            background: #343a40;
            border-radius: 50%;
            width: 50px;
            justify-content: center;
        }
    </style>
    <?php include 'assets/inc/console_suppress.php'; ?>
</head>

<body>

    <form action="edit_treatment.php?id=<?= $id ?>&type=<?= $type ?>&page=<?= $currentPage ?>" method="post">
        <div class="card-container">
            <div class="header">
                <img src="assets/img/DepEd-logo.png" class="header-logo" alt="DepEd Logo">
                <h3>Republic of the Philippines</h3>
                <h2>Department of Education</h2>
                <div class="region">Region III</div>
                <div class="division">SCHOOLS DIVISION OFFICE OF OLONGAPO CITY</div>
                <div class="school-name">OLONGAPO CITY NATIONAL HIGH SCHOOL</div>
            </div>
            <div class="card-title">TREATMENT RECORD - <?= strtoupper($person['name']) ?></div>
            <div class="page-info">Page <?= $currentPage ?></div>
            <table>
                <thead>
                    <tr>
                        <th width="4%" rowspan="2">
                            <?= $type == 'student' ? 'GRADE' : ($type == 'employee' ? 'POS' : 'SDO') ?>
                        </th>
                        <th width="7%" rowspan="2">DATE</th>
                        <th width="10%" rowspan="2">SUBJECTIVE COMPLAINT</th>
                        <th width="10%" rowspan="2">OBJECTIVE COMPLAINT</th>
                        <th width="10%" rowspan="2">ASSESSMENT</th>
                        <th width="12%" rowspan="2">PLAN / QTY</th>
                        <th width="10%" rowspan="2">ATTENDED BY</th>
                        <th width="10%" rowspan="2">ENDORSED TO</th>
                        <th colspan="8" style="background: #e0f2f1; color: #00796b;">INTERVENTIONS</th>
                        <th width="9%" rowspan="2">NEXT VISIT</th>
                        <th width="10%" rowspan="2">NOTIFY EMAIL</th>
                    </tr>
                    <tr style="font-size: 8px;">
                        <th>Pharm</th>
                        <th>Trad</th>
                        <th>MedOff</th>
                        <th>Dentist</th>
                        <th>Agency</th>
                        <th>RHU</th>
                        <th>Hosp</th>
                        <th>Teacher</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < $ROWS_PER_PAGE; $i++): ?>
                        <tr>
                            <td><input type="text" name="rows[<?= $i ?>][grade]"
                                    value="<?= getVal($i, 'grade', $treatment_logs, $offset) ?>"></td>
                            <td><input type="date" name="rows[<?= $i ?>][date]"
                                    value="<?= getVal($i, 'date', $treatment_logs, $offset) ?>"></td>
                            <td><input type="text" name="rows[<?= $i ?>][subjective_complaint]"
                                    value="<?= getVal($i, 'subjective_complaint', $treatment_logs, $offset) ?: getVal($i, 'complaint', $treatment_logs, $offset) ?>"
                                    placeholder="S:"></td>
                            <td><input type="text" name="rows[<?= $i ?>][objective_complaint]"
                                    value="<?= getVal($i, 'objective_complaint', $treatment_logs, $offset) ?>"
                                    placeholder="O:"></td>
                            <td><input type="text" name="rows[<?= $i ?>][assessment]"
                                    value="<?= getVal($i, 'assessment', $treatment_logs, $offset) ?>" placeholder="A:"></td>
                            <td style="vertical-align: top; padding: 3px;">
                                <!-- Med 1 -->
                                <div
                                    style="display: flex; gap: 2px; margin-bottom: 2px; border-bottom: 1px dotted #ccc; padding-bottom: 2px;">
                                    <input type="text" name="rows[<?= $i ?>][plan]"
                                        value="<?= getVal($i, 'plan', $treatment_logs, $offset) ?: getVal($i, 'treatment', $treatment_logs, $offset) ?>"
                                        placeholder="P1:" style="flex: 1; min-width: 0; font-size: 10px;">
                                    <input type="number" name="rows[<?= $i ?>][quantity]"
                                        value="<?= getVal($i, 'quantity', $treatment_logs, $offset) ?: 1 ?>"
                                        style="width: 30px; text-align: center; padding: 2px; font-size: 10px;" min="1"
                                        placeholder="#">
                                </div>
                                <!-- Med 2 -->
                                <div
                                    style="display: flex; gap: 2px; margin-bottom: 2px; border-bottom: 1px dotted #ccc; padding-bottom: 2px;">
                                    <input type="text" name="rows[<?= $i ?>][plan2]"
                                        value="<?= getVal($i, 'plan2', $treatment_logs, $offset) ?>" placeholder="P2:"
                                        style="flex: 1; min-width: 0; font-size: 10px;">
                                    <input type="number" name="rows[<?= $i ?>][quantity2]"
                                        value="<?= getVal($i, 'quantity2', $treatment_logs, $offset) ?: 1 ?>"
                                        style="width: 30px; text-align: center; padding: 2px; font-size: 10px;" min="1"
                                        placeholder="#">
                                </div>
                                <!-- Med 3 -->
                                <div style="display: flex; gap: 2px;">
                                    <input type="text" name="rows[<?= $i ?>][plan3]"
                                        value="<?= getVal($i, 'plan3', $treatment_logs, $offset) ?>" placeholder="P3:"
                                        style="flex: 1; min-width: 0; font-size: 10px;">
                                    <input type="number" name="rows[<?= $i ?>][quantity3]"
                                        value="<?= getVal($i, 'quantity3', $treatment_logs, $offset) ?: 1 ?>"
                                        style="width: 30px; text-align: center; padding: 2px; font-size: 10px;" min="1"
                                        placeholder="#">
                                </div>
                            </td>
                            <td style="vertical-align: top; padding: 3px;">
                                <!-- ── Person 1 ── -->
                                <div
                                    style="position: relative; margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px dashed #eee;">
                                    <div class="signature-container" onclick="openSignaturePad(<?= $i ?>, 'signature')"
                                        style="height: 25px; border: 1px dashed #999; cursor: pointer; background: #f9f9f9; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
                                        <img class="signature-display-<?= $i ?>"
                                            style="max-height: 23px; max-width: 100%; display: none;" />
                                        <span class="signature-placeholder-<?= $i ?>"
                                            style="font-size: 8px; color: #999;">Sign 1</span>
                                    </div>
                                    <input type="hidden" name="rows[<?= $i ?>][signature]" class="signature-data-<?= $i ?>"
                                        value="<?= getVal($i, 'signature', $treatment_logs, $offset) ?>">
                                    <input type="text" name="rows[<?= $i ?>][attended]"
                                        value="<?= getVal($i, 'attended', $treatment_logs, $offset) ?>"
                                        style="font-size: 9px; padding: 2px; border: none; border-bottom: 1px solid #ccc; width: 100%;"
                                        placeholder="Name 1">
                                </div>
                                <!-- ── Person 2 ── -->
                                <div
                                    style="position: relative; margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px dashed #eee;">
                                    <div class="signature-container" onclick="openSignaturePad(<?= $i ?>, 'signature2')"
                                        style="height: 25px; border: 1px dashed #999; cursor: pointer; background: #f9f9f9; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
                                        <img class="signature2-display-<?= $i ?>"
                                            style="max-height: 23px; max-width: 100%; display: none;" />
                                        <span class="signature2-placeholder-<?= $i ?>"
                                            style="font-size: 8px; color: #999;">Sign 2</span>
                                    </div>
                                    <input type="hidden" name="rows[<?= $i ?>][signature2]"
                                        class="signature2-data-<?= $i ?>"
                                        value="<?= getVal($i, 'signature2', $treatment_logs, $offset) ?>">
                                    <input type="text" name="rows[<?= $i ?>][attended2]"
                                        value="<?= getVal($i, 'attended2', $treatment_logs, $offset) ?>"
                                        style="font-size: 9px; padding: 2px; border: none; border-bottom: 1px solid #ccc; width: 100%;"
                                        placeholder="Name 2">
                                </div>
                                <!-- ── Person 3 ── -->
                                <div style="position: relative;">
                                    <div class="signature-container" onclick="openSignaturePad(<?= $i ?>, 'signature3')"
                                        style="height: 25px; border: 1px dashed #999; cursor: pointer; background: #f9f9f9; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
                                        <img class="signature3-display-<?= $i ?>"
                                            style="max-height: 23px; max-width: 100%; display: none;" />
                                        <span class="signature3-placeholder-<?= $i ?>"
                                            style="font-size: 8px; color: #999;">Sign 3</span>
                                    </div>
                                    <input type="hidden" name="rows[<?= $i ?>][signature3]"
                                        class="signature3-data-<?= $i ?>"
                                        value="<?= getVal($i, 'signature3', $treatment_logs, $offset) ?>">
                                    <input type="text" name="rows[<?= $i ?>][attended3]"
                                        value="<?= getVal($i, 'attended3', $treatment_logs, $offset) ?>"
                                        style="font-size: 9px; padding: 2px; border: none; border-bottom: 1px solid #ccc; width: 100%;"
                                        placeholder="Name 3">
                                </div>
                            </td>
                            <td style="vertical-align: top; padding: 3px;">
                                <div style="position: relative;">
                                    <!-- Signature Display/Button -->
                                    <div class="signature-container"
                                        onclick="openSignaturePad(<?= $i ?>, 'remarks_signature')"
                                        style="height: 25px; border: 1px dashed #999; cursor: pointer; background: #f9f9f9; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
                                        <img class="remarks_signature-display-<?= $i ?>"
                                            style="max-height: 23px; max-width: 100%; display: none;" />
                                        <span class="remarks_signature-placeholder-<?= $i ?>"
                                            style="font-size: 9px; color: #999;">Click to Sign</span>
                                    </div>
                                    <input type="hidden" name="rows[<?= $i ?>][remarks_signature]"
                                        class="remarks_signature-data-<?= $i ?>"
                                        value="<?= getVal($i, 'remarks_signature', $treatment_logs, $offset) ?>">
                                    <!-- Remarks Input -->
                                    <input type="text" name="rows[<?= $i ?>][remarks]"
                                        value="<?= getVal($i, 'remarks', $treatment_logs, $offset) ?>"
                                        style="font-size: 9px; padding: 2px; border: none; border-bottom: 1px solid #ccc; width: 100%;"
                                        placeholder="Endorsed To (Name)">
                                </div>
                            </td>
                            <td><input type="checkbox" name="rows[<?= $i ?>][int_pharm]" value="1" <?= getVal($i, 'int_pharm', $treatment_logs, $offset) == '1' ? 'checked' : '' ?>></td>
                            <td><input type="checkbox" name="rows[<?= $i ?>][int_trad]" value="1" <?= getVal($i, 'int_trad', $treatment_logs, $offset) == '1' ? 'checked' : '' ?>></td>
                            <td><input type="checkbox" name="rows[<?= $i ?>][int_mo]" value="1" <?= getVal($i, 'int_mo', $treatment_logs, $offset) == '1' ? 'checked' : '' ?>></td>
                            <td><input type="checkbox" name="rows[<?= $i ?>][int_dentist]" value="1" <?= getVal($i, 'int_dentist', $treatment_logs, $offset) == '1' ? 'checked' : '' ?>></td>
                            <td><input type="checkbox" name="rows[<?= $i ?>][int_agency]" value="1" <?= getVal($i, 'int_agency', $treatment_logs, $offset) == '1' ? 'checked' : '' ?>></td>
                            <td><input type="checkbox" name="rows[<?= $i ?>][int_rhu]" value="1" <?= getVal($i, 'int_rhu', $treatment_logs, $offset) == '1' ? 'checked' : '' ?>></td>
                            <td><input type="checkbox" name="rows[<?= $i ?>][int_hosp]" value="1" <?= getVal($i, 'int_hosp', $treatment_logs, $offset) == '1' ? 'checked' : '' ?>></td>
                            <td><input type="checkbox" name="rows[<?= $i ?>][int_teacher]" value="1" <?= getVal($i, 'int_teacher', $treatment_logs, $offset) == '1' ? 'checked' : '' ?>></td>
                            <td><input type="datetime-local" name="rows[<?= $i ?>][next_visit]"
                                    value="<?= getVal($i, 'next_visit', $treatment_logs, $offset) ?>"></td>
                            <td>
                                <input type="email" name="rows[<?= $i ?>][email]"
                                    value="<?= getVal($i, 'email', $treatment_logs, $offset) ?>">
                                <input type="hidden" name="rows[<?= $i ?>][email_sent]"
                                    value="<?= getVal($i, 'email_sent', $treatment_logs, $offset) ?>">
                                <?php if (getVal($i, 'email_sent', $treatment_logs, $offset) == '1'): ?><span
                                        style="color: green;">✓ Sent</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <div class="fab-container">
            <button type="submit" name="save_only" class="btn btn-save"><i class="fa-solid fa-floppy-disk"></i>
                SAVE</button>
            <button type="submit" name="save_next" class="btn btn-next">NEXT <i
                    class="fa-solid fa-arrow-right"></i></button>
            <?php if ($currentPage > 1): ?>
                <a href="edit_treatment.php?id=<?= $id ?>&type=<?= $type ?>&page=<?= $currentPage - 1 ?>"
                    class="btn btn-prev"><i class="fa-solid fa-arrow-left"></i> PREV</a>
            <?php endif; ?>
            <a href="view_treatment.php?view_id=<?= $id ?>&type=<?= $type ?>" class="btn btn-back-home"><i
                    class="fa-solid fa-times"></i></a>
        </div>
    </form>

    <!-- Signature Pad Modal -->
    <div id="signatureModal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center;">
        <div style="background: white; padding: 20px; border-radius: 10px; max-width: 500px; width: 90%;">
            <h3 style="margin-top: 0;">Draw Your Signature</h3>
            <canvas id="signatureCanvas" width="460" height="200"
                style="border: 2px solid #000; cursor: crosshair; touch-action: none;"></canvas>
            <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="clearSignature()"
                    style="padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    <i class="fa-solid fa-eraser"></i> Clear
                </button>
                <button type="button" onclick="saveSignature()"
                    style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    <i class="fa-solid fa-check"></i> Save
                </button>
                <button type="button" onclick="closeSignaturePad()"
                    style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    <i class="fa-solid fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        let canvas, ctx, isDrawing = false, currentRowIndex = null, currentSignaturePrefix = null;

        document.addEventListener('DOMContentLoaded', function () {
            canvas = document.getElementById('signatureCanvas');
            ctx = canvas.getContext('2d');
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';

            // Mouse events
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseout', stopDrawing);

            // Touch events
            canvas.addEventListener('touchstart', handleTouchStart);
            canvas.addEventListener('touchmove', handleTouchMove);
            canvas.addEventListener('touchend', stopDrawing);

            // Load existing signatures
            loadExistingSignatures();
        });

        function loadExistingSignatures() {
            <?php for ($i = 0; $i < $ROWS_PER_PAGE; $i++): ?>
                ['signature', 'signature2', 'signature3', 'remarks_signature'].forEach(prefix => {
                    const sigDataElem = document.querySelector(`.${prefix}-data-<?= $i ?>`);
                    if (sigDataElem && sigDataElem.value) {
                        const img = document.querySelector(`.${prefix}-display-<?= $i ?>`);
                        const placeholder = document.querySelector(`.${prefix}-placeholder-<?= $i ?>`);
                        if (img && placeholder) {
                            img.src = sigDataElem.value;
                            img.style.display = 'block';
                            placeholder.style.display = 'none';
                        }
                    }
                });
            <?php endfor; ?>
        }

        function openSignaturePad(rowIndex, prefix) {
            currentRowIndex = rowIndex;
            currentSignaturePrefix = prefix;
            document.getElementById('signatureModal').style.display = 'flex';
            clearSignature();

            // Load existing signature if any
            const existingSig = document.querySelector(`.${prefix}-data-${rowIndex}`).value;
            if (existingSig) {
                const img = new Image();
                img.onload = function () {
                    ctx.drawImage(img, 0, 0);
                };
                img.src = existingSig;
            }
        }

        function closeSignaturePad() {
            document.getElementById('signatureModal').style.display = 'none';
            currentRowIndex = null;
            currentSignaturePrefix = null;
        }

        function startDrawing(e) {
            isDrawing = true;
            const rect = canvas.getBoundingClientRect();
            ctx.beginPath();
            ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
        }

        function draw(e) {
            if (!isDrawing) return;
            const rect = canvas.getBoundingClientRect();
            ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
            ctx.stroke();
        }

        function stopDrawing() {
            isDrawing = false;
        }

        function handleTouchStart(e) {
            e.preventDefault();
            isDrawing = true;
            const rect = canvas.getBoundingClientRect();
            const touch = e.touches[0];
            ctx.beginPath();
            ctx.moveTo(touch.clientX - rect.left, touch.clientY - rect.top);
        }

        function handleTouchMove(e) {
            e.preventDefault();
            if (!isDrawing) return;
            const rect = canvas.getBoundingClientRect();
            const touch = e.touches[0];
            ctx.lineTo(touch.clientX - rect.left, touch.clientY - rect.top);
            ctx.stroke();
        }

        function clearSignature() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }

        function saveSignature() {
            if (currentRowIndex === null) return;

            const signatureData = canvas.toDataURL('image/png');

            // Save to hidden input
            document.querySelector(`.${currentSignaturePrefix}-data-${currentRowIndex}`).value = signatureData;

            // Display in table cell
            const imgDisplay = document.querySelector(`.${currentSignaturePrefix}-display-${currentRowIndex}`);
            const placeholder = document.querySelector(`.${currentSignaturePrefix}-placeholder-${currentRowIndex}`);

            imgDisplay.src = signatureData;
            imgDisplay.style.display = 'block';
            placeholder.style.display = 'none';

            closeSignaturePad();
        }
    </script>
    <!-- AI Suggestion Box -->
    <div id="aiSuggestionBox"
        style="position: absolute; background: white; border: 1px solid #ccc; border-radius: 4px; display: none; z-index: 10000; box-shadow: 0 4px 10px rgba(0,0,0,0.1); min-width: 200px;">
    </div>

    <script>
        // AI Treatment Suggestions
        let debounceTimer;

        document.addEventListener('input', function (e) {
            // Check if input is a field that should trigger suggestions: Assessment, Subjective, or Objective
            if (e.target.tagName === 'INPUT' && e.target.name && (
                e.target.name.includes('[assessment]') ||
                e.target.name.includes('[subjective_complaint]') ||
                e.target.name.includes('[objective_complaint]')
            )) {
                const query = e.target.value;

                // Extract Index
                const match = e.target.name.match(/rows\[(\d+)\]/);
                if (!match) return;
                const index = match[1];

                // Find corresponding Plan/Treatment field
                const treatmentField = document.querySelector(`input[name="rows[${index}][plan]"]`);

                if (query.length < 3) {
                    hideSuggestions();
                    return;
                }

                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    fetchSuggestions(query, e.target, treatmentField);
                }, 500);
            }
            // Medicine Search (Treatment/Plan Field)
            else if (e.target.tagName === 'INPUT' && e.target.name && (e.target.name.includes('[plan]') || e.target.name.includes('[treatment]'))) {
                const query = e.target.value;
                if (query.length < 3) {
                    hideSuggestions();
                    return;
                }

                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    fetchMedicine(query, e.target);
                }, 500);
            }
        });

        // Hide on click outside
        document.addEventListener('click', function (e) {
            const box = document.getElementById('aiSuggestionBox');
            if (box.style.display === 'block' && !box.contains(e.target) && !e.target.name.includes('[subjective_complaint]') && !e.target.name.includes('[objective_complaint]') && !e.target.name.includes('[plan]') && !e.target.name.includes('[assessment]') && !e.target.name.includes('[treatment]')) {
                hideSuggestions();
            }
        });

        function fetchSuggestions(complaint, inputField, targetField) {
            fetch(`api/ai_suggestions.php?action=suggest_treatment&complaint=${encodeURIComponent(complaint)}`)
                .then(res => res.json())
                .then(data => {
                    if ((data.suggestions && data.suggestions.length > 0) || (data.dfa && data.dfa.length > 0)) {
                        showSuggestions(data.suggestions, inputField, targetField, 'AI Analysis', data.dfa);
                    } else {
                        hideSuggestions();
                    }
                })
                .catch(console.error);
        }

        function fetchMedicine(query, inputField) {
            fetch(`api/external_data.php?action=search_medicine&query=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.medicines && data.medicines.length > 0) {
                        showSuggestions(data.medicines, inputField, inputField, 'FDA Drug Database');
                    } else {
                        hideSuggestions();
                    }
                })
                .catch(console.error);
        }

        function showSuggestions(suggestions, inputField, targetField, title = 'Suggestions', dfa = []) {
            const box = document.getElementById('aiSuggestionBox');
            box.innerHTML = '';
            box.style.padding = '0';

            // ── 1. DFA (DIAGNOSTIC & FORMULARY) ──
            if (dfa && dfa.length > 0) {
                const dfaHdr = document.createElement('div');
                dfaHdr.innerHTML = '<i class="fa-solid fa-boxes-stacked"></i> DFA (INVENTORY-READY)';
                dfaHdr.style.padding = '8px 10px';
                dfaHdr.style.background = '#f0fdf4';
                dfaHdr.style.color = '#15803d';
                dfaHdr.style.fontSize = '10px';
                dfaHdr.style.fontWeight = '800';
                dfaHdr.style.borderBottom = '1px solid #dcfce7';
                box.appendChild(dfaHdr);

                dfa.forEach(d => {
                    const item = document.createElement('div');
                    item.innerHTML = `<div>${d.item}</div><div style="font-size:9px; color:#16a34a; font-weight:bold;">${d.available} in stock</div>`;
                    item.style.padding = '8px 10px';
                    item.style.cursor = 'pointer';
                    item.style.fontSize = '11px';
                    item.style.borderBottom = '1px solid #f0fdf4';
                    item.onclick = () => { targetField.value = d.item; hideSuggestions(); targetField.focus(); };
                    item.onmouseover = () => item.style.background = '#f0fdf4';
                    item.onmouseout = () => item.style.background = 'white';
                    box.appendChild(item);
                });
            }

            // ── 2. AI TREATMENT ADVICE / EXTERNAL DATA ──
            if (suggestions && suggestions.length > 0) {
                const header = document.createElement('div');
                header.innerHTML = '<i class="fa-solid fa-earth-americas"></i> ' + title;
                header.style.padding = '8px 10px';
                header.style.background = title.includes('FDA') || title.includes('Pharmacopeia') ? '#fff7ed' : '#f0f9ff';
                header.style.color = title.includes('FDA') || title.includes('Pharmacopeia') ? '#c2410c' : '#0369a1';
                header.style.fontSize = '10px';
                header.style.fontWeight = 'bold';
                header.style.borderBottom = '1px solid #e0f2fe';
                box.appendChild(header);

                suggestions.forEach(s => {
                    const item = document.createElement('div');

                    // Handle object format from NLM Integration
                    if (typeof s === 'object') {
                        const sourceColor = s.source.includes('Local') ? '#16a34a' : '#6d28d9';
                        item.innerHTML = `
                            <div style="font-weight:bold;">${s.item}</div>
                            <div style="display:flex; justify-content:space-between; font-size:9px; margin-top:2px;">
                                <span style="color:${sourceColor}; font-weight:600;">${s.source}</span>
                                <span style="color:#666;">${s.stock}</span>
                            </div>
                        `;
                        item.onclick = () => { targetField.value = s.item; hideSuggestions(); targetField.focus(); };
                    } else {
                        item.innerText = s;
                        item.onclick = () => { targetField.value = s; hideSuggestions(); targetField.focus(); };
                    }

                    item.style.padding = '8px 10px';
                    item.style.cursor = 'pointer';
                    item.style.fontSize = '11px';
                    item.style.borderBottom = '1px solid #f1f5f9';
                    item.onmouseover = () => item.style.background = '#f8fafc';
                    item.onmouseout = () => item.style.background = 'white';
                    box.appendChild(item);
                });
            }

            // Position
            const rect = inputField.getBoundingClientRect();
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

            box.style.display = 'block';
            box.style.top = (rect.bottom + scrollTop + 2) + 'px';
            box.style.left = (rect.left + scrollLeft) + 'px';
            box.style.width = Math.max(rect.width, 240) + 'px';
        }

        function hideSuggestions() {
            document.getElementById('aiSuggestionBox').style.display = 'none';
        }
    </script>

    <!-- ================================================
         AI DIFFERENTIAL DIAGNOSIS — FLOATING PANEL
    ================================================ -->
    <div class="ai-panel" id="aiPanel">
        <!-- Header -->
        <div class="ai-panel-header">
            <div class="ai-panel-hdr-left">
                <i class="fa-solid fa-stethoscope"></i>
                <div>
                    <div class="ai-panel-title">AI Differential Diagnosis</div>
                    <div class="ai-panel-sub">PhD Engine v2.0</div>
                </div>
            </div>
            <button class="ai-panel-toggle" onclick="toggleAiPanel()">
                <i class="fa-solid fa-chevron-down" id="aiToggleIcon"></i>
            </button>
        </div>

        <!-- Complaint bar -->
        <div class="ai-complaint-bar" id="aiComplaintBar">
            <i class="fa-solid fa-comment-medical"></i>
            <span id="aiComplaintText">Type a complaint to analyze...</span>
        </div>

        <!-- Body -->
        <div class="ai-panel-body" id="aiPanelBody">
            <div class="ai-empty-state">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
                <p>Start typing a complaint to get AI-powered differential diagnosis</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="ai-panel-footer">
            <i class="fa-solid fa-shield-halved"></i>
            Clinical decision support — does NOT replace professional evaluation.
        </div>
    </div>

    <style>
        /* ── FLOATING AI PANEL ── */
        .ai-panel {
            position: fixed;
            top: 80px;
            right: 20px;
            width: 400px;
            max-height: 580px;
            background: #f8fafc;
            border-radius: 16px;
            box-shadow: 0 10px 50px rgba(0, 0, 0, 0.18);
            z-index: 9990;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 2px solid #00ACB1;
            font-family: 'Segoe UI', Arial, sans-serif;
            transition: all 0.3s ease;
        }

        .ai-panel.collapsed {
            max-height: 52px;
        }

        .ai-panel.collapsed .ai-panel-body,
        .ai-panel.collapsed .ai-complaint-bar,
        .ai-panel.collapsed .ai-panel-footer {
            display: none;
        }

        .ai-panel.collapsed #aiToggleIcon {
            transform: rotate(180deg);
        }

        .ai-panel.has-redflags {
            border-color: #ef4444;
            box-shadow: 0 10px 50px rgba(239, 68, 68, 0.2);
        }

        /* Header */
        .ai-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: linear-gradient(135deg, #00ACB1, #005f62);
            color: white;
            cursor: default;
            flex-shrink: 0;
        }

        .ai-panel-hdr-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ai-panel-hdr-left>i {
            font-size: 20px;
        }

        .ai-panel-title {
            font-size: 13px;
            font-weight: 800;
        }

        .ai-panel-sub {
            font-size: 9px;
            opacity: 0.75;
            letter-spacing: 0.5px;
            margin-top: 1px;
        }

        .ai-panel-toggle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            border: none;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .ai-panel-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        #aiToggleIcon {
            transition: transform 0.3s;
            font-size: 12px;
        }

        /* Complaint bar */
        .ai-complaint-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #e0f7fa;
            font-size: 11.5px;
            color: #00695c;
            font-weight: 600;
            border-bottom: 1px solid #b2dfdb;
            flex-shrink: 0;
        }

        .ai-complaint-bar i {
            font-size: 13px;
        }

        /* Body */
        .ai-panel-body {
            flex: 1;
            overflow-y: auto;
            padding: 12px 14px;
            max-height: 440px;
        }

        /* Footer */
        .ai-panel-footer {
            padding: 7px 14px;
            background: #fffbeb;
            color: #92400e;
            font-size: 9px;
            display: flex;
            align-items: center;
            gap: 5px;
            border-top: 1px solid #fde68a;
            flex-shrink: 0;
        }

        /* ── EMPTY / LOADING ── */
        .ai-empty-state {
            text-align: center;
            padding: 28px 16px;
            color: #aaa;
        }

        .ai-empty-state i {
            font-size: 28px;
            margin-bottom: 8px;
            display: block;
        }

        .ai-empty-state p {
            font-size: 11.5px;
            margin: 0;
            line-height: 1.5;
        }

        .ai-loading {
            text-align: center;
            padding: 30px 16px;
        }

        .ai-spinner {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 3px solid #e0e0e0;
            border-top-color: #00ACB1;
            animation: aiSpin 0.7s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes aiSpin {
            to {
                transform: rotate(360deg);
            }
        }

        .ai-loading p {
            font-size: 11px;
            color: #888;
            margin: 0;
        }

        /* ── RED FLAG BANNER ── */
        .ai-redflag-banner {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            border: 1px solid #fca5a5;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }

        .ai-redflag-title {
            font-size: 11px;
            font-weight: 800;
            color: #dc2626;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 6px;
        }

        .ai-redflag-item {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            padding: 4px 0;
            font-size: 10.5px;
            color: #7f1d1d;
            line-height: 1.4;
        }

        .ai-redflag-item i {
            color: #ef4444;
            margin-top: 2px;
            flex-shrink: 0;
            font-size: 10px;
        }

        /* ── DISEASE CARD ── */
        .ai-disease-card {
            background: white;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 10px;
            border: 1px solid #e5e7eb;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.2s;
        }

        .ai-disease-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            border-radius: 12px 0 0 12px;
        }

        .ai-disease-card.sev-high::after {
            background: #ef4444;
        }

        .ai-disease-card.sev-moderate::after {
            background: #f59e0b;
        }

        .ai-disease-card.sev-mild::after {
            background: #10b981;
        }

        .ai-disease-card:hover {
            border-color: #00ACB1;
            box-shadow: 0 3px 12px rgba(0, 172, 177, 0.1);
        }

        .ai-disease-card.top-pick {
            border-color: #00ACB1;
            background: #f0fdfa;
        }

        /* Card head */
        .ai-card-head {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ai-conf-ring {
            width: 44px;
            height: 44px;
            position: relative;
            flex-shrink: 0;
        }

        .ai-conf-ring svg {
            transform: rotate(-90deg);
        }

        .ai-conf-ring circle {
            fill: none;
            stroke-width: 4;
        }

        .ai-conf-ring .bg {
            stroke: #e5e7eb;
        }

        .ai-conf-ring .fg {
            stroke: #00ACB1;
            stroke-linecap: round;
            transition: stroke-dashoffset 1s ease;
        }

        .ai-conf-val {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            color: #00ACB1;
        }

        .ai-card-info {
            flex: 1;
            min-width: 0;
        }

        .ai-card-disease {
            font-size: 13px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 3px;
        }

        .ai-card-meta {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
        }

        .ai-icd-tag {
            font-size: 9px;
            background: #ede9fe;
            color: #6d28d9;
            padding: 1px 6px;
            border-radius: 5px;
            font-weight: 700;
        }

        .ai-cat-tag {
            font-size: 9px;
            background: #e0f2f1;
            color: #00796b;
            padding: 1px 6px;
            border-radius: 5px;
            font-weight: 600;
        }

        .ai-sev-tag {
            font-size: 8px;
            padding: 1px 6px;
            border-radius: 5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .ai-sev-tag.high {
            background: #fef2f2;
            color: #dc2626;
        }

        .ai-sev-tag.moderate {
            background: #fffbeb;
            color: #d97706;
        }

        .ai-sev-tag.mild {
            background: #ecfdf5;
            color: #059669;
        }

        .ai-card-expand-hint {
            text-align: center;
            font-size: 9px;
            color: #c0c0c0;
            margin-top: 6px;
        }

        .ai-disease-card:hover .ai-card-expand-hint {
            color: #00ACB1;
        }

        /* ── EXPANDED DETAILS ── */
        .ai-card-details {
            display: none;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
        }

        .ai-card-details.show {
            display: block;
            animation: slideDown 0.25s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .ai-info-block {
            margin-bottom: 10px;
        }

        .ai-info-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .ai-info-label.teal {
            color: #00ACB1;
        }

        .ai-info-label.amber {
            color: #d97706;
        }

        .ai-info-label.purple {
            color: #7c3aed;
        }

        .ai-info-label.blue {
            color: #2563eb;
        }

        .ai-info-text {
            font-size: 11px;
            color: #444;
            line-height: 1.6;
        }

        .ai-info-box {
            padding: 8px 10px;
            border-radius: 7px;
            font-size: 11px;
            line-height: 1.55;
            color: #444;
        }

        .ai-info-box.pearl {
            background: #f0fdfa;
            border-left: 3px solid #00ACB1;
        }

        .ai-info-box.refer {
            background: #fff7ed;
            border-left: 3px solid #f97316;
        }

        .ai-info-box.mgt {
            background: #f5f3ff;
            border-left: 3px solid #7c3aed;
        }

        /* ── VISION RESULTS ── */
        .ai-vision-res {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }

        .ai-vision-badge {
            display: inline-block;
            font-size: 8px;
            background: #0ea5e9;
            color: white;
            padding: 1px 6px;
            border-radius: 4px;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .ai-vision-findings {
            font-size: 10px;
            color: #334155;
            list-style: none;
            padding: 0;
            margin: 5px 0;
        }

        .ai-vision-findings li {
            position: relative;
            padding-left: 12px;
            margin-bottom: 3px;
        }

        .ai-vision-findings li::before {
            content: "•";
            position: absolute;
            left: 0;
            color: #0ea5e9;
        }

        .ai-vision-thumb {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
            margin-top: 5px;
            border: 1px solid #cbd5e1;
        }

        .ai-diff-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        .ai-diff-chip {
            font-size: 9px;
            padding: 2px 8px;
            border-radius: 10px;
            background: #e8eaf6;
            color: #3949ab;
            font-weight: 600;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .ai-panel {
                width: 340px;
                right: 10px;
                top: 70px;
            }
        }

        @media (max-width: 480px) {
            .ai-panel {
                width: calc(100% - 20px);
                right: 10px;
                top: 60px;
            }
        }

        @media print {
            .ai-panel {
                display: none !important;
            }
        }
    </style>

    <script>
        let aiDebounce, lastComplaint = '';

        function toggleAiPanel() {
            document.getElementById('aiPanel').classList.toggle('collapsed');
        }

        // Listen for assessment input — AI Panel bases its analysis on the formalized Assessment
        document.addEventListener('input', function (e) {
            if (e.target.name && e.target.name.includes('[assessment]')) {
                const val = e.target.value.trim();
                if (val.length < 3) return;
                clearTimeout(aiDebounce);
                aiDebounce = setTimeout(() => {
                    if (val !== lastComplaint) {
                        lastComplaint = val;
                        runQuickPredict(val);
                    }
                }, 800);
            }
        });

        function runQuickPredict(complaint) {
            const body = document.getElementById('aiPanelBody');
            const panel = document.getElementById('aiPanel');
            const bar = document.getElementById('aiComplaintText');

            bar.textContent = `"${complaint}"`;
            panel.classList.remove('collapsed', 'has-redflags');

            // Initial AI Content with Vision Option
            body.innerHTML = `
                <div class="ai-loading"><div class="ai-spinner"></div><p>Analyzing Assessment...</p></div>
                <div style="margin-top:15px; padding-top:10px; border-top:1px dashed #e2e8f0; text-align:center;">
                    <div style="font-size:9px; color:#64748b; margin-bottom:5px;">HAVE A MODALITY IMAGE?</div>
                    <label class="btn" style="background:#0ea5e9; color:white; font-size:10px; padding:5px 12px; cursor:pointer; display:inline-flex; align-items:center; gap:5px;">
                        <i class="fa-solid fa-camera"></i> ANALYZE IMAGE (RASH/X-RAY)
                        <input type="file" accept="image/*" style="display:none;" onchange="analyzeImage(this)">
                    </label>
                </div>
                <div id="visionResultArea"></div>
            `;

            const patientId = '<?= $id ?>';
            const patientType = '<?= $type ?>';
            fetch(`api/disease_predict.php?action=quick_predict&complaint=${encodeURIComponent(complaint)}&id=${patientId}&type=${patientType}`)
                .then(r => r.json())
                .then(data => {
                    const loadingMsg = body.querySelector('.ai-loading');
                    if (loadingMsg) loadingMsg.remove();

                    if (!data.predictions || data.predictions.length === 0) {
                        const empty = document.createElement('div');
                        empty.className = 'ai-empty-state';
                        empty.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#10b981;"></i><p>No specific condition matched. Consider general assessment.</p>';
                        body.prepend(empty);
                        return;
                    }
                    if (data.red_flags && data.red_flags.length > 0) panel.classList.add('has-redflags');

                    const resDiv = document.createElement('div');
                    buildResults(data, resDiv);
                    body.prepend(resDiv);
                })
                .catch(() => {
                    const err = body.querySelector('.ai-loading');
                    if (err) err.innerHTML = '<i class="fa-solid fa-plug-circle-xmark" style="color:#ef4444;"></i><p>AI engine unavailable</p>';
                });
        }

        function analyzeImage(input) {
            if (!input.files || !input.files[0]) return;
            const resArea = document.getElementById('visionResultArea');
            resArea.innerHTML = '<div style="font-size:10px; color:#0ea5e9; text-align:center; padding:10px;"><i class="fa-solid fa-spinner fa-spin"></i> Processing Image...</div>';

            const formData = new FormData();
            formData.append('action', 'analyze_image');
            formData.append('image', input.files[0]);

            // Auto-detect mode based on assessment or prompt if needed
            const assessment = document.getElementById('aiComplaintText').textContent.toLowerCase();
            const mode = assessment.includes('xray') || assessment.includes('chest') ? 'xray' : 'rash';
            formData.append('mode', mode);

            fetch('api/vision_analyze.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        resArea.innerHTML = `<div style="font-size:10px; color:#ef4444; text-align:center;">${data.error}</div>`;
                        return;
                    }

                    let findingsHtml = data.findings.map(f => `<li>${f}</li>`).join('');
                    resArea.innerHTML = `
                    <div class="ai-vision-res">
                        <div class="ai-vision-badge"><i class="fa-solid fa-eye"></i> COMPUTER VISION RESULT</div>
                        <img src="${data.image_url}" class="ai-vision-thumb">
                        <div style="font-size:11px; font-weight:bold; margin-top:8px; color:#1e293b;">Findings:</div>
                        <ul class="ai-vision-findings">${findingsHtml || '<li>No specific visual anomalies detected.</li>'}</ul>
                        <div style="font-size:9px; color:#64748b; margin-top:5px; border-top:1px solid #e2e8f0; padding-top:4px;">
                            ${data.engine} • Suggested Focus: ${data.suggested_focus.join(', ')}
                        </div>
                    </div>
                `;
                });
        }

        function buildResults(data, container) {
            let html = '';

            // Red flags
            if (data.red_flags && data.red_flags.length > 0) {
                html += `<div class="ai-redflag-banner">
                    <div class="ai-redflag-title"><i class="fa-solid fa-triangle-exclamation"></i> RED FLAG</div>`;
                data.red_flags.forEach(rf => {
                    html += `<div class="ai-redflag-item"><i class="fa-solid fa-bolt"></i><div><strong>${rf.symptom}:</strong> ${rf.warning}</div></div>`;
                });
                html += '</div>';
            }

            // Mapped symptoms
            if (data.mapped_symptoms && data.mapped_symptoms.length) {
                html += `<div style="margin-bottom:10px;display:flex;flex-wrap:wrap;gap:4px;align-items:center;">
                    <span style="font-size:9px;color:#888;font-weight:600;margin-right:3px;">MAPPED:</span>
                    ${data.mapped_symptoms.map(s => `<span style="font-size:9px;background:#e0f7fa;color:#00695c;padding:2px 7px;border-radius:8px;font-weight:600;">${s}</span>`).join('')}
                </div>`;
            }

            // Disease cards
            data.predictions.forEach((p, i) => {
                const sev = p.severity.toLowerCase().includes('high') ? 'high' :
                    p.severity.toLowerCase().includes('moderate') ? 'moderate' : 'mild';
                const circ = 2 * Math.PI * 17;
                const offset = circ - (p.confidence / 100) * circ;

                html += `
                <div class="ai-disease-card sev-${sev} ${i === 0 ? 'top-pick' : ''}" onclick="this.querySelector('.ai-card-details').classList.toggle('show')">
                    <div class="ai-card-head">
                        <div class="ai-conf-ring">
                            <svg width="44" height="44" viewBox="0 0 44 44">
                                <circle class="bg" cx="22" cy="22" r="17"/>
                                <circle class="fg" cx="22" cy="22" r="17"
                                    stroke-dasharray="${circ}" stroke-dashoffset="${offset}"/>
                            </svg>
                            <div class="ai-conf-val">${p.confidence}%</div>
                        </div>
                        <div class="ai-card-info">
                            <div class="ai-card-disease">${i === 0 ? '🥇 ' : ''}${p.disease}</div>
                            <div class="ai-card-meta">
                                <span class="ai-icd-tag">${p.icd10}</span>
                                <span class="ai-cat-tag">${p.category}</span>
                                <span class="ai-sev-tag ${sev}">${p.severity}</span>
                            </div>
                        </div>
                    </div>
                    <div class="ai-card-expand-hint"><i class="fa-solid fa-chevron-down"></i> click for details</div>
                    <div class="ai-card-details">
                        <div class="ai-info-block">
                            <div class="ai-info-label teal"><i class="fa-solid fa-circle-info"></i> Description</div>
                            <div class="ai-info-text">${p.description}</div>
                        </div>
                        ${p.global_description ? `
                        <div class="ai-info-block">
                            <div class="ai-info-label blue" style="color:#2563eb;"><i class="fa-solid fa-earth-americas"></i> ${p.global_source || 'Clinical Reference'}</div>
                            <div class="ai-info-box" style="background:#eff6ff; border-left:3px solid #3b82f6; font-size:10.5px; font-style:italic;">
                                "${p.global_description}"
                            </div>
                        </div>` : ''}
                        <div class="ai-info-block">
                            <div class="ai-info-label teal"><i class="fa-solid fa-lightbulb"></i> Clinical Pearl</div>
                            <div class="ai-info-box pearl">${p.clinical_pearl}</div>
                        </div>
                        <div class="ai-info-block">
                            <div class="ai-info-label purple"><i class="fa-solid fa-pills"></i> Management</div>
                            <div class="ai-info-box mgt">${p.management}</div>
                        </div>
                        <div class="ai-info-block">
                            <div class="ai-info-label amber"><i class="fa-solid fa-hospital"></i> When to Refer</div>
                            <div class="ai-info-box refer">${p.when_to_refer}</div>
                        </div>
                        ${p.differentials && p.differentials.length ? `
                        <div class="ai-info-block">
                            <div class="ai-info-label blue"><i class="fa-solid fa-arrows-split-up-and-left"></i> Differentials</div>
                            <div class="ai-diff-chips">${p.differentials.map(d => `<span class="ai-diff-chip">${d}</span>`).join('')}</div>
                        </div>` : ''}
                        <div style="font-size:9px;color:#bbb;text-align:right;margin-top:4px;">
                            ${p.matched_count}/${p.total_disease_symptoms} symptoms matched
                        </div>
                    </div>
                </div>`;
            });

            container.innerHTML = html;
        }
    </script>
</body>

</html>