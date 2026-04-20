<?php
require_once "../config/db.php";
requireLogin();

// Check ID
if (isset($_GET['view_id']) && is_numeric($_GET['view_id'])) {
    $id = intval($_GET['view_id']);
    $patientType = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'student';
    $primaryColor = ($patientType === 'employee') ? '#795548' : '#00ACB1';

    if ($patientType == 'employee') {
        $table = "employees";
        $backLink = "employees.php";
    } elseif ($patientType == 'others') {
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

function getVal($rowIndex, $colKey, $data)
{
    return isset($data[$rowIndex][$colKey]) ? htmlspecialchars($data[$rowIndex][$colKey]) : '';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treatment Record -
        <?= $person['name'] ?>
    </title>
    <link rel="icon" type="image/x-icon" href="assets/img/ocnhs_logo.png">

    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            padding: 20px;
        }

        .card-container {
            max-width: 900px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            min-height: 98vh;
        }

        /* Header */
        .header {
            text-align: center;
            position: relative;
            margin-bottom: 10px;
        }

        .header-logo {
            width: 70px;
            display: block;
            margin: 0 auto 10px auto;
        }

        .header h3,
        .header h2 {
            margin: 2px 0;
            font-weight: bold;
            line-height: 1.2;
        }

        .header h3 {
            font-size: 14px;
            font-family: "Old English Text MT", serif;
        }

        .header h2 {
            font-size: 18px;
            font-family: "Old English Text MT", serif;
            text-transform: uppercase;
        }

        .header .region,
        .header .division,
        .header .school-name {
            font-size: 10px;
            font-weight: bold;
            font-family: Arial, sans-serif;
        }

        .header .school-name {
            text-decoration: underline;
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

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            flex-grow: 1;
            table-layout: fixed;
            /* Prevents table from stretching horizontally */
            word-wrap: break-word;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
            vertical-align: middle;
            /* Changed from top to middle for better center alignment */
            text-align: center;
            /* Center the text */
        }

        th {
            text-align: center;
            font-weight: bold;
            background: #fff;
        }

        td {
            height: 25px;
            /* Minimum row height */
        }

        /* Print Settings */
        @media print {
            @page {
                size: 8.5in 14in;
                /* Legal */
                margin: 0;
            }

            body {
                margin: 0;
                padding: 10px 10mm;
                /* Small top/bottom padding, side padding */
                font-size: 10px;
                /* Smaller font for print */
            }

            .no-print {
                display: none !important;
            }

            .card-container {
                width: 100%;
                /* Remove fixed height to let content dictate size, but ensure it fits */
                height: 98vh;
                /* Force height to fit page */
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                /* Space out header, table, footer */
            }

            .header {
                margin-bottom: 5px;
                /* Compact header */
            }

            .header h2 {
                font-size: 16px;
                margin: 0;
            }

            .header h3 {
                font-size: 12px;
                margin: 0;
            }

            table {
                font-size: 10px;
                /* Reduce table font */
                margin-bottom: 5px;
            }

            th,
            td {
                padding: 2px 4px;
                /* Tighter padding */
                height: auto;
                /* Allow auto height */
                /* line-height: 1.1; */
            }

            /* Ensure footer stays at bottom but within page */
            .footer-section {
                margin-top: auto;
                padding-top: 5px;
                border-top: 2px solid #333;
            }
        }

        /* Footer specific styles */
        .footer-section {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 30px;
            margin-top: auto;
            /* Push to bottom */
            padding-top: 10px;
            border-top: 2px solid #333;
        }

        .footer-logo {
            height: 40px;
            width: auto;
        }

        .footer-text {
            font-size: 8px;
            line-height: 1.2;
            color: #333;
            text-align: left;
        }

        /* Action Buttons */
        .fab-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            gap: 10px;
        }

        .btn-back,
        .btn-print {
            border: none;
            padding: 15px 20px;
            border-radius: 30px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-back {
            background: #6c757d;
        }

        .btn-print {
            background: #00ACB1;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php include 'assets/inc/console_suppress.php'; ?>
</head>

<body>


    <div class="card-container">
        <div class="header">
            <img src="assets/img/DepEd-logo.png" class="header-logo" alt="DepEd Logo">
            <h3>Republic of the Philippines</h3>
            <h2>Department of Education</h2>
            <div class="region">Region III</div>
            <div class="division">SCHOOLS DIVISION OFFICE OF OLONGAPO CITY</div>
            <div class="school-name">OLONGAPO CITY NATIONAL HIGH SCHOOL</div>
            <div class="no-print"
                style="margin-top: 15px; border-top: 1px solid #ddd; padding-top: 10px; text-align: center;">
                <h1
                    style="margin: 0; font-size: 24px; color: #333; font-family: Arial, sans-serif; text-transform: uppercase;">
                    <?= htmlspecialchars($person['name']) ?>
                </h1>
                <span
                    style="display: inline-block; background: <?= $primaryColor ?>; color: white; padding: 4px 15px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; margin-top: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    <?= ($patientType === 'employee') ? 'Employee' : 'Student' ?>
                </span>
            </div>
        </div>

        <div class="card-title">TREATMENT RECORD - <?= htmlspecialchars($person['name']) ?></div>

        <table>
            <thead>
                <tr>
                    <th width="12%">DATE</th>
                    <th width="28%">COMPLAINT</th>
                    <th width="35%">TREATMENT</th>
                    <th width="25%">ATTENDED <br> BY</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Pagination Logic
                $ROWS_PER_PAGE = 19; // Match print requirement
                $currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
                if ($currentPage < 1)
                    $currentPage = 1;

                $offset = ($currentPage - 1) * $ROWS_PER_PAGE;

                // Generate rows for current page
                for ($i = 0; $i < $ROWS_PER_PAGE; $i++):
                    $globalIndex = $offset + $i;
                    ?>
                    <tr style="height: 50px;"> <!-- Fixed height for uniform boxes -->
                        <td><?= getVal($globalIndex, 'date', $treatment_logs) ?></td>
                        <td>
                            <?php
                            $s = getVal($globalIndex, 'subjective_complaint', $treatment_logs);
                            $o = getVal($globalIndex, 'objective_complaint', $treatment_logs);
                            if ($s || $o) {
                                if ($s)
                                    echo "S: " . $s . "<br>";
                                if ($o)
                                    echo "O: " . $o;
                            } else {
                                echo getVal($globalIndex, 'complaint', $treatment_logs);
                            }
                            ?>
                        </td>
                        <?php
                        $hasRemarks = !empty(getVal($globalIndex, 'remarks', $treatment_logs)) || !empty(getVal($globalIndex, 'remarks_signature', $treatment_logs));
                        ?>
                        <td style="position: relative; vertical-align: middle; padding: 5px; overflow: hidden;">
                            <div
                                style="display: block; width: <?= $hasRemarks ? 'calc(100% - 95px)' : '100%' ?>; <?= $hasRemarks ? 'text-align: left;' : 'text-align: center;' ?> word-wrap: break-word; white-space: normal; line-height: 1.2;">
                                <?php
                                $a = getVal($globalIndex, 'assessment', $treatment_logs);
                                if ($a)
                                    echo "A: " . $a . "<br>";

                                // Display all medicines (P1, P2, P3)
                                $plans = [];
                                if (getVal($globalIndex, 'plan', $treatment_logs) || getVal($globalIndex, 'treatment', $treatment_logs)) {
                                    $p1 = getVal($globalIndex, 'plan', $treatment_logs) ?: getVal($globalIndex, 'treatment', $treatment_logs);
                                    $q1 = getVal($globalIndex, 'quantity', $treatment_logs) ?: 1;
                                    $plans[] = "P1: $p1 ($q1)";
                                }
                                if (getVal($globalIndex, 'plan2', $treatment_logs)) {
                                    $p2 = getVal($globalIndex, 'plan2', $treatment_logs);
                                    $q2 = getVal($globalIndex, 'quantity2', $treatment_logs) ?: 1;
                                    $plans[] = "P2: $p2 ($q2)";
                                }
                                if (getVal($globalIndex, 'plan3', $treatment_logs)) {
                                    $p3 = getVal($globalIndex, 'plan3', $treatment_logs);
                                    $q3 = getVal($globalIndex, 'quantity3', $treatment_logs) ?: 1;
                                    $plans[] = "P3: $p3 ($q3)";
                                }

                                if (!empty($plans)) {
                                    echo implode("<br>", $plans);
                                }
                                ?>
                            </div>
                            <?php if ($hasRemarks): ?>
                                <div
                                    style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); text-align: center; width: 85px;">
                                    <?php
                                    $remarksSignature = getVal($globalIndex, 'remarks_signature', $treatment_logs);
                                    if ($remarksSignature): ?>
                                        <img src="<?= $remarksSignature ?>"
                                            style="max-height: 28px; max-width: 100%; display: block; margin: 0 auto 2px;"
                                            alt="Remarks Signature" />
                                    <?php else: ?>
                                        <div style="height: 25px; margin-bottom: 2px;"></div>
                                    <?php endif; ?>
                                    <div style="font-size: 9px; font-weight: bold; line-height: 1;">
                                        <?= getVal($globalIndex, 'remarks', $treatment_logs) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align: top; padding: 3px;">
                            <?php
                            $signatories = [
                                ['sig' => 'signature', 'name' => 'attended'],
                                ['sig' => 'signature2', 'name' => 'attended2'],
                                ['sig' => 'signature3', 'name' => 'attended3']
                            ];
                            foreach ($signatories as $idx => $s):
                                $sig = getVal($globalIndex, $s['sig'], $treatment_logs);
                                $name = getVal($globalIndex, $s['name'], $treatment_logs);
                                if ($sig || $name):
                                    ?>
                                    <div
                                        style="margin-bottom: 5px; padding-bottom: 3px; <?= $idx < 2 ? 'border-bottom: 1px dotted #ccc;' : '' ?>">
                                        <?php if ($sig): ?>
                                            <img src="<?= $sig ?>"
                                                style="max-height: 22px; max-width: 100%; display: block; margin: 0 auto 1px;"
                                                alt="Signature" />
                                        <?php else: ?>
                                            <div style="height: 15px;"></div>
                                        <?php endif; ?>
                                        <div style="font-size: 8px; text-align: center; font-weight: bold; line-height: 1;">
                                            <?= $name ?>
                                        </div>
                                    </div>
                                    <?php
                                endif;
                            endforeach;
                            ?>
                        </td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <!-- Footer -->
        <div class="footer-section">
            <img src="assets/img/deped-matatag-logos.png" class="footer-logo" alt="DepEd">
            <img src="assets/img/ocnhs_logo.png" class="footer-logo" alt="Lungsod ng Olongapo">

            <div class="footer-text">
                <strong>Address:</strong> Corner 14th St., Rizal Ave. East Tapinac, Olongapo City<br>
                <strong>Contact No.:</strong> (047) 223-3766<br>
                <strong>Email Address:</strong> 301051@deped.gov.ph<br>
                <em>"SDO Olongapo City: Towards a Culture of Excellence"</em>
            </div>
        </div>

        <!-- Pagination Controls (Visible Only on Screen) - Moved to Bottom -->
        <div class="no-print"
            style="margin-top: 20px; padding-bottom: 20px; text-align: center; border-top: 1px dashed #ccc; padding-top: 10px;">
            <?php if ($currentPage > 1): ?>
                <a href="view_treatment.php?view_id=<?= $id ?>&type=<?= $type ?>&page=<?= $currentPage - 1 ?>"
                    style="text-decoration: none; padding: 10px 20px; background: #6c757d; color: white; border-radius: 5px; font-weight: bold; margin-right: 10px;">
                    <i class="fa-solid fa-arrow-left"></i> PREV PAGE
                </a>
            <?php endif; ?>

            <span style="font-size: 14px; margin: 0 15px; font-weight: bold;">PAGE <?= $currentPage ?></span>

            <a href="view_treatment.php?view_id=<?= $id ?>&type=<?= $type ?>&page=<?= $currentPage + 1 ?>"
                style="text-decoration: none; padding: 10px 20px; background: #007bff; color: white; border-radius: 5px; font-weight: bold; margin-left: 10px;">
                NEXT PAGE <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- Action Buttons (No Print) -->
    <div class="fab-container no-print">
        <a href="<?= $backLink ?>?view_id=<?= $id ?>" class="btn-back" title="Go Back">
            <i class="fa-solid fa-arrow-left"></i>
        </a>

        <button onclick="window.print()" class="btn-print" title="Print Record"
            style="background: <?= $primaryColor ?>;">
            <i class="fa-solid fa-print"></i> PRINT RECORD
        </button>
    </div>



</body>

</html>