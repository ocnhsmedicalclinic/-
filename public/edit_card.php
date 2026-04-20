<?php
require_once "../config/db.php";
requireLogin();

$type = isset($_GET['type']) ? $_GET['type'] : 'student';
$table = ($type == 'employee') ? 'employees' : 'students';
$backUrl = ($type == 'employee') ? 'employees.php' : 'student.php';

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    // 1. Basic Info
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $address = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $birth_date = mysqli_real_escape_string($conn, $_POST['birth_date']);
    $birthplace = mysqli_real_escape_string($conn, $_POST['birthplace'] ?? '');
    $religion = mysqli_real_escape_string($conn, $_POST['religion'] ?? '');

    if ($type == 'employee') {
        $position = mysqli_real_escape_string($conn, $_POST['position'] ?? '');
        $designation = mysqli_real_escape_string($conn, $_POST['designation'] ?? '');
        $sql_basic = "UPDATE employees SET name='$name', address='$address', gender='$gender', birth_date='$birth_date', birthplace='$birthplace', religion='$religion', position='$position', designation='$designation' WHERE id='$id'";
    } else {
        $lrn = mysqli_real_escape_string($conn, $_POST['lrn']);
        $curriculum = mysqli_real_escape_string($conn, $_POST['curriculum']);
        $guardian = mysqli_real_escape_string($conn, $_POST['guardian']);
        $contact = mysqli_real_escape_string($conn, $_POST['contact']);
        $sql_basic = "UPDATE students SET name='$name', lrn='$lrn', address='$address', gender='$gender', curriculum='$curriculum', birth_date='$birth_date', birthplace='$birthplace', religion='$religion', guardian='$guardian', contact='$contact' WHERE id='$id'";
    }

    // 2. Health Data Packaging
    $health_data = [];
    $exclude = ['name', 'lrn', 'address', 'gender', 'curriculum', 'birth_date', 'birthplace', 'religion', 'guardian', 'contact', 'position', 'designation'];
    foreach ($_POST as $key => $value) {
        if (!in_array($key, $exclude)) {
            $health_data[$key] = $value;
        }
    }
    $json_data = mysqli_real_escape_string($conn, json_encode($health_data));

    if ($conn->query($sql_basic)) {
        $sql_json = "UPDATE $table SET health_exam_json='$json_data' WHERE id='$id'";
        $conn->query($sql_json);

        echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body><script>
                Swal.fire({
                    title: 'Saved Successfully!',
                    text: 'The health examination card has been updated.',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false,
                    confirmButtonColor: '#00ACB1'
                }).then(() => {
                    window.location.href = 'edit_card.php?id=$id&type=$type';
                });
              </script></body></html>";
        exit;
    }
}

// Fetch Logic
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $result = $conn->query("SELECT * FROM $table WHERE id = '$id'");
    if ($result->num_rows > 0) {
        $person = $result->fetch_assoc();
        $health_values = json_decode($person['health_exam_json'] ?? '{}', true);
    } else {
        die("Record not found.");
    }
} else {
    die("No ID provided.");
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
    <title>Edit Health Card -
        <?= $person['name'] ?>
    </title>
    <link rel="icon" type="image/x-icon" href="assets/img/ocnhs_logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 20px;
            background: #EAEAEA;
        }

        .card-container {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

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
            text-decoration: underline;
            font-size: 11px;
        }

        .card-title {
            text-align: center;
            font-weight: 900;
            font-size: 12px;
            margin: 5px 0;
            border-top: 2px solid #000;
            padding-top: 5px;
            text-transform: uppercase;
        }

        .info-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 5px;
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

        .info-group input,
        .info-group select {
            border: none;
            border-bottom: 1px solid #000;
            background: transparent;
            padding: 0 5px;
            font-weight: bold;
            outline: none;
            text-align: center;
            font-size: 11px;
            font-family: Arial, sans-serif;
            color: #000;
            flex-grow: 1;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            font-size: 10px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 2px;
            text-align: center;
            vertical-align: middle;
        }

        table input {
            width: 100%;
            border: none;
            background: transparent;
            text-align: center;
            font-size: 10px;
            outline: none;
        }

        .fab-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }

        .btn-save {
            background: #00ACB1;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 30px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: 0.3s;
        }

        .btn-save:hover {
            background: #008a8e;
            transform: translateY(-2px);
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
        }

        .btn-back:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
    </style>
    <?php include 'assets/inc/console_suppress.php'; ?>
</head>

<body>

    <form action="edit_card.php?id=<?= $id ?>&type=<?= $type ?>" method="post">
        <div class="card-container">
            <?php if ($type == 'employee'): ?>
                <div style="text-align: center; font-family: 'Times New Roman', serif; color: #000; margin-bottom: 20px;">
                    <div style="font-size: 11px; margin-bottom: 2px;">Republic of the Philippines</div>
                    <div style="font-size: 11px; font-weight: bold; margin-bottom: 2px;">Department of Education</div>
                    <div style="font-size: 11px; margin-bottom: 2px;">Region III</div>
                    <div style="font-size: 11px; font-weight: bold; margin-bottom: 2px;">SCHOOL DIVISION OFFICE OF OLONGAPO
                    </div>
                    <div style="font-size: 11px; font-weight: bold; margin-bottom: 10px;">OLONGAPO CITY NATIONAL HIGH SCHOOL
                    </div>
                    <div style="font-size: 18px; font-weight: bold; margin-top: 20px;">EMPLOYEE HEALTH CARD</div>
                </div>

                <style>
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

                    .emp-input {
                        flex: 1;
                        border: none;
                        border-bottom: 1px solid #000;
                        outline: none;
                        font-family: inherit;
                        font-size: inherit;
                        background: transparent;
                        padding-left: 5px;
                        text-align: center;
                    }

                    /* Remove standard table styles for this section */
                    .emp-radio-group {
                        display: flex;
                        gap: 15px;
                        align-items: center;
                    }

                    .emp-radio-group label {
                        display: flex;
                        align-items: center;
                        gap: 3px;
                        cursor: pointer;
                    }
                </style>

                <div class="emp-info-row">
                    <span class="emp-label">Date:</span>
                    <input type="date" name="date_examined" class="emp-input" style="width: 150px; flex: none;"
                        value="<?= getVal('date_examined', $health_values) ?>" autocomplete="off">
                </div>

                <div class="emp-info-row">
                    <span class="emp-label">Name:</span>
                    <input type="text" name="name" class="emp-input" value="<?= $person['name'] ?>" autocomplete="off">

                    <span class="emp-label" style="margin-left: 15px;">Date of Birth:</span>
                    <input type="date" name="birth_date" class="emp-input" style="width: 120px; flex: none;"
                        value="<?= $person['birth_date'] ?>" autocomplete="off">

                    <span class="emp-label" style="margin-left: 15px;">Age:</span>
                    <input type="text" name="age" class="emp-input" style="width: 50px; flex: none;"
                        value="<?= getVal('age', $health_values) ?>" autocomplete="off">

                    <span class="emp-label" style="margin-left: 20px; font-weight: bold;">Gender:</span>
                    <div class="emp-radio-group" style="margin-left: 5px;">
                        <label>M <input type="radio" name="gender" value="Male" <?= $person['gender'] == 'Male' ? 'checked' : '' ?>></label>
                        <label>F <input type="radio" name="gender" value="Female" <?= $person['gender'] == 'Female' ? 'checked' : '' ?>></label>
                    </div>
                </div>

                <div class="emp-info-row">
                    <span class="emp-label">School/District/Division:</span>
                    <input type="text" name="school_division" class="emp-input"
                        value="<?= getVal('school_division', $health_values) ?: 'OCNHS / SDO OLONGAPO CITY' ?>"
                        autocomplete="off">

                    <span class="emp-label" style="margin-left: 20px; font-weight: bold;">Civil Status:</span>
                    <div class="emp-radio-group" style="margin-left: 5px;">
                        <?php $cs = getVal('civil_status', $health_values); ?>
                        <label>S <input type="radio" name="civil_status" value="Single" <?= $cs == 'Single' ? 'checked' : '' ?>></label>
                        <label>M <input type="radio" name="civil_status" value="Married" <?= $cs == 'Married' ? 'checked' : '' ?>></label>
                        <label>W <input type="radio" name="civil_status" value="Widowed" <?= $cs == 'Widowed' ? 'checked' : '' ?>></label>
                        <label>S <input type="radio" name="civil_status" value="Separated" <?= $cs == 'Separated' ? 'checked' : '' ?>></label>
                    </div>
                </div>

                <div class="emp-info-row">
                    <span class="emp-label">Position/Designation:</span>
                    <input type="text" name="position" class="emp-input" value="<?= $person['position'] ?? '' ?>"
                        autocomplete="off">

                    <span class="emp-label" style="margin-left: 15px;">Years In Service:</span>
                    <input type="text" name="years_service" class="emp-input"
                        style="width: 100px; flex: none; text-align: center;"
                        value="<?= getVal('years_service', $health_values) ?>" autocomplete="off">
                </div>

                <div class="emp-info-row">
                    <span class="emp-label">First Year in Service:</span>
                    <input type="text" name="first_year_service" class="emp-input"
                        value="<?= getVal('first_year_service', $health_values) ?>" autocomplete="off">
                </div>

            <?php else: ?>
                <!-- STUDENT HEADER & INFO -->
                <div class="header">
                    <img src="assets/img/DepEd-logo.png" class="header-logo" alt="DepEd Logo">
                    <h3>Republic of the Philippines</h3>
                    <h2>Department of Education</h2>
                    <div class="region">Region III</div>
                    <div class="division">SDO OLONGAPO CITY</div>
                    <div class="school-name">OLONGAPO CITY NATIONAL HIGH SCHOOL</div>
                </div>
                <div class="card-title">SCHOOL HEALTH EXAMINATION CARD</div>

                <!-- Row 1 -->
                <div class="info-row">
                    <div class="info-group" style="width: 65%;">
                        <label for="std_name">NAME:</label>
                        <input type="text" name="name" id="std_name" value="<?= $person['name'] ?>" autocomplete="off">
                    </div>
                    <div class="info-group" style="width: 30%;">
                        <label for="std_lrn">LRN #:</label>
                        <input type="text" name="lrn" id="std_lrn" value="<?= $person['lrn'] ?>" autocomplete="off">
                    </div>
                </div>

                <!-- Row 2 -->
                <div class="info-row">
                    <div class="info-group" style="flex: 2;">
                        <label for="std_address">ADDRESS:</label>
                        <input type="text" name="address" id="std_address" value="<?= $person['address'] ?? '' ?>"
                            autocomplete="off">
                    </div>

                    <div class="info-group" style="flex: 1;">
                        <label for="std_gender">GENDER:</label>
                        <select name="gender" id="std_gender"
                            style="border:none; border-bottom:1px solid #000; background:transparent; width:100%; font-weight:bold; font-size:11px;"
                            autocomplete="off">
                            <option value="Male" <?= $person['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $person['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>

                    <div class="info-group" style="flex: 1;">
                        <label for="std_curriculum">CURRICULUM:</label>
                        <input type="text" name="curriculum" id="std_curriculum" value="<?= $person['curriculum'] ?>"
                            autocomplete="off">
                    </div>
                </div>

                <!-- Row 3 -->
                <div class="info-row">
                    <div class="info-group" style="width: 30%;">
                        <label for="std_birth_date">BIRTH DATE:</label>
                        <input type="date" name="birth_date" id="std_birth_date" value="<?= $person['birth_date'] ?>"
                            autocomplete="off">
                    </div>

                    <div class="info-group" style="width: 35%;">
                        <label for="std_birthplace">BIRTHPLACE:</label>
                        <input type="text" name="birthplace" id="std_birthplace" value="<?= $person['birthplace'] ?? '' ?>"
                            autocomplete="off">
                    </div>
                    <div class="info-group" style="width: 30%;">
                        <label for="std_religion">RELIGION:</label>
                        <input type="text" name="religion" id="std_religion" value="<?= $person['religion'] ?? '' ?>"
                            autocomplete="off">
                    </div>
                </div>

                <!-- Row 4 -->
                <div class="info-row">
                    <div class="info-group" style="width: 50%;">
                        <label for="std_guardian">PARENT OR GUARDIAN:</label>
                        <input type="text" name="guardian" id="std_guardian" value="<?= $person['guardian'] ?? '' ?>"
                            autocomplete="off">
                    </div>
                    <div class="info-group" style="width: 45%;">
                        <label for="std_contact">CONTACT NUMBER:</label>
                        <input type="text" name="contact" id="std_contact" value="<?= $person['contact'] ?? '' ?>"
                            autocomplete="off">
                    </div>
                </div>
            <?php endif; ?>

            <hr style="margin: 20px 0;">

            <?php if ($type == 'employee'): ?>
                <style>
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

                    .chk-box-edit {
                        width: auto;
                        margin: 0;
                    }

                    .line-edit {
                        border: none;
                        border-bottom: 1px solid #000;
                        background: transparent;
                        outline: none;
                        font-size: 10px;
                        font-family: Arial, sans-serif;
                        padding: 0 3px;
                    }

                    .sec-header {
                        font-weight: bold;
                        margin-top: 8px;
                        margin-bottom: 4px;
                        font-size: 10px;
                        text-transform: uppercase;
                        text-align: left;
                    }

                    .test-table {
                        width: 100%;
                        border-collapse: collapse;
                        font-size: 9px;
                        border: none !important;
                    }

                    .test-table td {
                        padding: 3px 2px;
                        border: none !important;
                        vertical-align: middle;
                        text-align: left;
                    }

                    .test-table td:first-child {
                        padding-left: 20px;
                    }

                    .test-table input {
                        width: 100%;
                    }
                </style>

                <!-- I. Family History -->
                <table class="emp-table">
                    <tr>
                        <td style="width: 50%;">
                            <div class="sec-header" style="text-align: left;">I. FAMILY HISTORY (pls. check)</div>
                            <div class="chk-row" style="width:90%; margin-bottom:2px;">
                                <span class="chk-lbl"></span>
                                <div class="chk-boxes"><strong>Y</strong> <strong>N</strong></div>
                            </div>
                            <?php
                            $fam_items = ['Hypertension' => 'fam_hibp', 'Cardiovascular Disease' => 'fam_heart', 'Diabetes Mellitus' => 'fam_dm', 'Kidney Disease' => 'fam_kidney', 'Cancer' => 'fam_cancer', 'Asthma' => 'fam_asthma', 'Allergy' => 'fam_allergy'];
                            foreach ($fam_items as $l => $k):
                                ?>
                                <div class="chk-row" style="width:90%;">
                                    <span class="chk-lbl"><?= $l ?>:</span>
                                    <div class="chk-boxes">
                                        <input type="checkbox" class="chk-box-edit" name="<?= $k ?>" value="1" <?= getVal($k, $health_values) ? 'checked' : '' ?>>
                                        <input type="checkbox" class="chk-box-edit" name="<?= $k ?>_no" value="1" <?= getVal($k . '_no', $health_values) ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </td>
                        <td style="width: 50%; padding-left: 10px;">
                            <div class="sec-header" style="text-align:center;">Specify Relationship</div>
                            <?php for ($i = 0; $i < 7; $i++): ?>
                                <div style="margin-bottom: 5px;">
                                    <input type="text" class="line-edit" name="fam_relationship_<?= $i ?>" style="width:100%;"
                                        value="<?= getVal('fam_relationship_' . $i, $health_values) ?>">
                                </div>
                            <?php endfor; ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div
                                style="margin-top:4px; text-align: left; display: flex; align-items: center; width: 100%; padding-left: 20px;">
                                <span style="font-size: 10px; white-space: nowrap;">Other Remarks:</span>
                                <input type="text" class="line-edit" name="fam_other_remarks"
                                    style="flex: 1; margin-left: 5px; margin-right: 15px;"
                                    value="<?= getVal('fam_other_remarks', $health_values) ?>">
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
                            <?php
                            echo '<div class="chk-row" style="width:90%; margin-bottom:2px;"><span class="chk-lbl"></span><div class="chk-boxes"><strong>Y</strong> <strong>N</strong></div></div>';
                            $past_left = ['Hypertension' => 'past_hibp', 'Asthma' => 'past_asthma', 'Diabetes Mellitus' => 'past_dm', 'Cardio Vascular Disease' => 'past_heart', 'Allergy (pls specify)' => 'past_allergy'];
                            foreach ($past_left as $l => $k):
                                ?>
                                <div class="chk-row" style="width:90%;">
                                    <span class="chk-lbl">
                                        <?= $l ?>
                                        <?php if ($k == 'past_allergy'): ?>
                                            <input type="text" class="line-edit" name="past_allergy_desc" style="width: 100px;"
                                                value="<?= getVal('past_allergy_desc', $health_values) ?>">
                                        <?php endif; ?>
                                    </span>
                                    <div class="chk-boxes">
                                        <input type="checkbox" class="chk-box-edit" name="<?= $k ?>" value="1" <?= getVal($k, $health_values) ? 'checked' : '' ?>>
                                        <input type="checkbox" class="chk-box-edit" name="<?= $k ?>_no" value="1" <?= getVal($k . '_no', $health_values) ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </td>
                        <td style="width: 50%;">
                            <?php
                            echo '<div class="chk-row" style="width:90%; margin-bottom:2px;"><span class="chk-lbl"></span><div class="chk-boxes"><strong>Y</strong> <strong>N</strong></div></div>';
                            $past_right = ['Tuberculosis' => 'past_tb', 'Surgical Operation (specify)' => 'past_surgery', 'Yellowish discoloration' => 'past_yellow', 'Last hospitalization' => 'past_hospital', 'Others (pls. specify)' => 'past_others'];
                            foreach ($past_right as $l => $k):
                                ?>
                                <div class="chk-row" style="width:90%;">
                                    <span class="chk-lbl"><?= $l ?></span>
                                    <div class="chk-boxes">
                                        <input type="checkbox" class="chk-box-edit" name="<?= $k ?>" value="1" <?= getVal($k, $health_values) ? 'checked' : '' ?>>
                                        <input type="checkbox" class="chk-box-edit" name="<?= $k ?>_no" value="1" <?= getVal($k . '_no', $health_values) ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>

                <!-- Tests Row -->
                <style>
                    /* Specific styles for test table alignment */
                    .test-table td:nth-child(2),
                    .test-table td:nth-child(3),
                    .test-table td:nth-child(6),
                    .test-table td:nth-child(7) {
                        text-align: center !important;
                    }

                    .test-table td:nth-child(2),
                    .test-table td:nth-child(6) {
                        padding-right: 10px !important;
                        /* Visual gap */
                    }

                    .test-table input {
                        text-align: center;
                    }
                </style>
                <table class="test-table">
                    <tr>
                        <td width="15%"><strong>Last Taken</strong></td>
                        <td width="10%"><strong>Date</strong></td>
                        <td width="15%"><strong>Result</strong></td>
                        <td width="2%"></td>
                        <td width="20%"></td>
                        <td width="10%"><strong>Date</strong></td>
                        <td width="15%"><strong>Result</strong></td>
                    </tr>
                    <tr>
                        <td>CXR/Sputum Result</td>
                        <td><input type="text" class="line-edit" name="test_cxr_date"
                                value="<?= getVal('test_cxr_date', $health_values) ?>"></td>
                        <td><input type="text" class="line-edit" name="test_cxr"
                                value="<?= getVal('test_cxr', $health_values) ?>"></td>
                        <td></td>
                        <td>Drug Testing</td>
                        <td><input type="text" class="line-edit" name="test_drug_date"
                                value="<?= getVal('test_drug_date', $health_values) ?>"></td>
                        <td><input type="text" class="line-edit" name="test_drug"
                                value="<?= getVal('test_drug', $health_values) ?>"></td>
                    </tr>
                    <tr>
                        <td>ECG</td>
                        <td><input type="text" class="line-edit" name="test_ecg_date"
                                value="<?= getVal('test_ecg_date', $health_values) ?>"></td>
                        <td><input type="text" class="line-edit" name="test_ecg"
                                value="<?= getVal('test_ecg', $health_values) ?>"></td>
                        <td></td>
                        <td>Neuropsychiatric exam</td>
                        <td><input type="text" class="line-edit" name="test_neuro_date"
                                value="<?= getVal('test_neuro_date', $health_values) ?>"></td>
                        <td><input type="text" class="line-edit" name="test_neuro"
                                value="<?= getVal('test_neuro', $health_values) ?>"></td>
                    </tr>
                    <tr>
                        <td>Urinalysis</td>
                        <td><input type="text" class="line-edit" name="test_urine_date"
                                value="<?= getVal('test_urine_date', $health_values) ?>"></td>
                        <td><input type="text" class="line-edit" name="test_urine"
                                value="<?= getVal('test_urine', $health_values) ?>"></td>
                        <td></td>
                        <td>Blood Typing</td>
                        <td><input type="text" class="line-edit" name="test_blood_type_date"
                                value="<?= getVal('test_blood_type_date', $health_values) ?>"></td>
                        <td><input type="text" class="line-edit" name="test_blood_type"
                                value="<?= getVal('test_blood_type', $health_values) ?>"></td>
                    </tr>
                </table>

                <!-- III. Social History -->
                <div class="sec-header">III. SOCIAL HISTORY</div>
                <div style="font-size:9px; padding-left: 20px; display: flex; flex-direction: column; gap: 5px;">
                    <!-- Row 1: Smoking -->
                    <div style="display: flex; align-items: center; width: 100%; gap: 10px;">
                        <div style="display: flex; align-items: center; width: 120px;">
                            Smoking: &nbsp; Y <input type="checkbox" class="chk-box-edit" name="social_smoking" value="1"
                                <?= getVal('social_smoking', $health_values) ? 'checked' : '' ?>>
                            &nbsp; N <input type="checkbox" class="chk-box-edit" name="social_smoking_no" value="1"
                                <?= getVal('social_smoking_no', $health_values) ? 'checked' : '' ?>>
                        </div>
                        <div style="display: flex; align-items: center; flex: 1; white-space: nowrap;">
                            Age Started: <input type="text" class="line-edit" name="social_age_started"
                                style="width: 100%; margin-left: 5px;"
                                value="<?= getVal('social_age_started', $health_values) ?>">
                        </div>
                        <div style="display: flex; align-items: center; flex: 1; white-space: nowrap;">
                            Sticks/packs per day: <input type="text" class="line-edit" name="social_sticks"
                                style="width: 100%; margin-left: 5px;"
                                value="<?= getVal('social_sticks', $health_values) ?>">
                        </div>
                        <div style="display: flex; align-items: center; flex: 1; white-space: nowrap;">
                            Pack per year: <input type="text" class="line-edit" name="social_pack_year"
                                style="width: 100%; margin-left: 5px;"
                                value="<?= getVal('social_pack_year', $health_values) ?>">
                        </div>
                    </div>

                    <!-- Row 2: Alcohol -->
                    <div style="display: flex; align-items: center; width: 100%; gap: 10px;">
                        <div style="display: flex; align-items: center; width: 120px;">
                            Alcohol: &nbsp;&nbsp;&nbsp; Y <input type="checkbox" class="chk-box-edit" name="social_alcohol"
                                value="1" <?= getVal('social_alcohol', $health_values) ? 'checked' : '' ?>>
                            &nbsp; N <input type="checkbox" class="chk-box-edit" name="social_alcohol_no" value="1"
                                <?= getVal('social_alcohol_no', $health_values) ? 'checked' : '' ?>>
                        </div>
                        <div style="display: flex; align-items: center; flex: 1; white-space: nowrap;">
                            How Often: <input type="text" class="line-edit" name="social_how_often"
                                style="width: 100%; margin-left: 5px;"
                                value="<?= getVal('social_how_often', $health_values) ?>">
                        </div>
                        <div style="display: flex; align-items: center; flex: 1; white-space: nowrap;">
                            Food preference: <input type="text" class="line-edit" name="social_food_pref"
                                style="width: 100%; margin-left: 5px;"
                                value="<?= getVal('social_food_pref', $health_values) ?>">
                        </div>
                    </div>
                </div>

                <!-- IV. OB-GYNE History -->
                <div class="sec-header">IV. OB-GYNE HISTORY (pls. encircle) (Female only)</div>
                <table class="emp-table">
                    <tr>
                        <td colspan="2" style="padding-left: 20px;">
                            <div style="display: flex; align-items: center; gap: 10px; width: 100%;">
                                Menarche: <input type="text" class="line-edit" name="ob_lmp" style="flex: 1;"
                                    value="<?= getVal('ob_lmp', $health_values) ?>">
                                Cycle: <input type="text" class="line-edit" name="ob_cycle" style="flex: 1;"
                                    value="<?= getVal('ob_cycle', $health_values) ?>">
                                Duration: <input type="text" class="line-edit" name="ob_duration" style="flex: 1;"
                                    value="<?= getVal('ob_duration', $health_values) ?>">
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <div class="chk-row" style="width:90%;">
                                <span class="chk-lbl">Parity:</span>
                                <div style="display: flex; gap: 5px; align-items: center;">
                                    F <input type="checkbox" class="chk-box-edit" name="ob_parity_f" value="1"
                                        <?= getVal('ob_parity_f', $health_values) ? 'checked' : '' ?>>
                                    P <input type="checkbox" class="chk-box-edit" name="ob_parity_p" value="1"
                                        <?= getVal('ob_parity_p', $health_values) ? 'checked' : '' ?>>
                                    A <input type="checkbox" class="chk-box-edit" name="ob_parity_a" value="1"
                                        <?= getVal('ob_parity_a', $health_values) ? 'checked' : '' ?>>
                                    L <input type="checkbox" class="chk-box-edit" name="ob_parity_l" value="1"
                                        <?= getVal('ob_parity_l', $health_values) ? 'checked' : '' ?>>
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
                                    Y <input type="checkbox" class="chk-box-edit" name="ob_papsmear" value="1"
                                        <?= getVal('ob_papsmear', $health_values) ? 'checked' : '' ?>>
                                    N <input type="checkbox" class="chk-box-edit" name="ob_papsmear_no" value="1"
                                        <?= getVal('ob_papsmear_no', $health_values) ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </td>
                        <td style="width: 50%; padding-left: 5px;">
                            <div style="display: flex; align-items: center; width: 95%;">
                                if Yes, when: <input type="text" class="line-edit" name="ob_papsmear_when" style="flex: 1;"
                                    value="<?= getVal('ob_papsmear_when', $health_values) ?>">
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <div class="chk-row" style="width:90%;">
                                <span class="chk-lbl">Self Breast Examination done:</span>
                                <div style="display: flex; gap: 5px; align-items: center;">
                                    Y <input type="checkbox" class="chk-box-edit" name="ob_breast_exam" value="1"
                                        <?= getVal('ob_breast_exam', $health_values) ? 'checked' : '' ?>>
                                    N <input type="checkbox" class="chk-box-edit" name="ob_breast_exam_no" value="1"
                                        <?= getVal('ob_breast_exam_no', $health_values) ? 'checked' : '' ?>>
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
                                    Y <input type="checkbox" class="chk-box-edit" name="ob_mass_noted" value="1"
                                        <?= getVal('ob_mass_noted', $health_values) ? 'checked' : '' ?>>
                                    N <input type="checkbox" class="chk-box-edit" name="ob_mass_noted_no" value="1"
                                        <?= getVal('ob_mass_noted_no', $health_values) ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </td>
                        <td style="width: 50%; padding-left: 5px;">
                            <div style="display: flex; align-items: center; width: 95%;">
                                Specify where: <input type="text" class="line-edit" name="ob_mass_noted_when"
                                    style="flex: 1;" value="<?= getVal('ob_mass_noted_when', $health_values) ?>">
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="font-weight:bold; padding-left:5px;">For Male
                            personnel:</td>
                    </tr>
                    <tr>
                        <td style="width: 50%;">
                            <div class="chk-row" style="width:90%;">
                                <span class="chk-lbl">Digital rectal examination done:</span>
                                <div style="display: flex; gap: 5px; align-items: center;">
                                    Y <input type="checkbox" class="chk-box-edit" name="male_dre" value="1"
                                        <?= getVal('male_dre', $health_values) ? 'checked' : '' ?>>
                                    N <input type="checkbox" class="chk-box-edit" name="male_dre_no" value="1"
                                        <?= getVal('male_dre_no', $health_values) ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </td>
                        <td style="width: 50%; padding-left: 5px;">
                            <div style="display: flex; align-items: center; width: 95%;">
                                Date examined: <input type="text" class="line-edit" name="male_dre_date"
                                    style="width: 100px; margin-right: 5px;"
                                    value="<?= getVal('male_dre_date', $health_values) ?>">
                                Result: <input type="text" class="line-edit" name="male_dre_result" style="flex: 1;"
                                    value="<?= getVal('male_dre_result', $health_values) ?>">
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- V. Present Health Status -->
                <!-- Note: The requested layout splits the header Check Y/N across columns -->
                <table class="emp-table" style="margin-top: 10px;">
                    <tr>
                        <!-- Left Column -->
                        <td width="50%" style="vertical-align: top;">
                            <div class="chk-row" style="width:90%; margin-bottom:5px;">
                                <span class="chk-lbl" style="font-weight:bold;">Present Health Status (pls. check)</span>
                                <div class="chk-boxes"><strong>Y</strong> <strong>N</strong></div>
                            </div>

                            <!-- Detailed Cough -->
                            <div class="chk-row" style="width:90%;">
                                <span class="chk-lbl">Cough <span style="font-size:9px;">2wks 1month longer</span></span>
                                <div class="chk-boxes">
                                    <input type="checkbox" class="chk-box-edit" name="pres_cough" value="1"
                                        <?= getVal('pres_cough', $health_values) ? 'checked' : '' ?>>
                                    <input type="checkbox" class="chk-box-edit" name="pres_cough_no" value="1"
                                        <?= getVal('pres_cough_no', $health_values) ? 'checked' : '' ?>>
                                </div>
                            </div>

                            <?php
                            $pres_left = [
                                'Dizziness' => 'pres_dizzy',
                                'Dyspnea' => 'pres_dyspnea',
                                'Chest/Back Pain' => 'pres_pain',
                                'Easy fatigability' => 'pres_fatigue',
                                'Joint/Extremity Pains' => 'pres_joint',
                                'Blurring of Vision' => 'pres_blur',
                                'Wearing Eyeglasses' => 'pres_glasses',
                                'Vaginal Discharge/Bleeding' => 'pres_discharge'
                            ];
                            foreach ($pres_left as $l => $k):
                                ?>
                                <div class="chk-row" style="width:90%;">
                                    <span class="chk-lbl"><?= $l ?></span>
                                    <div class="chk-boxes">
                                        <input type="checkbox" class="chk-box-edit" name="<?= $k ?>" value="1" <?= getVal($k, $health_values) ? 'checked' : '' ?>>
                                        <input type="checkbox" class="chk-box-edit" name="<?= $k ?>_no" value="1" <?= getVal($k . '_no', $health_values) ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div style="margin-top:5px; padding-left: 20px;">
                                Dental Status (pls. specify) <input type="text" class="line-edit" name="pres_dental"
                                    style="width: 140px;" value="<?= getVal('pres_dental', $health_values) ?>">
                            </div>
                        </td>

                        <!-- Right Column -->
                        <td width="50%" style="vertical-align: top;">
                            <div class="chk-row" style="width:90%; margin-bottom:5px;">
                                <span class="chk-lbl" style="font-weight:bold;">Present Health Status (pls. check)</span>
                                <div class="chk-boxes"><strong>Y</strong> <strong>N</strong></div>
                            </div>

                            <?php
                            $pres_right = [
                                'Lumps' => 'pres_lumps',
                                'Painful Urination' => 'pres_urine',
                                'Poor/Loss of Hearing' => 'pres_hear',
                                'Syncope/Fainting' => 'pres_sync',
                                'Convulsions' => 'pres_conv',
                                'Malaria' => 'pres_malaria',
                                'Goiter' => 'pres_goiter',
                                'Anemia' => 'pres_anemia'
                            ];
                            foreach ($pres_right as $l => $k):
                                ?>
                                <div class="chk-row" style="width:90%;">
                                    <span class="chk-lbl"><?= $l ?></span>
                                    <div class="chk-boxes">
                                        <input type="checkbox" class="chk-box-edit" name="<?= $k ?>" value="1" <?= getVal($k, $health_values) ? 'checked' : '' ?>>
                                        <input type="checkbox" class="chk-box-edit" name="<?= $k ?>_no" value="1" <?= getVal($k . '_no', $health_values) ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div style="margin-top:5px; padding-left: 20px;">
                                Others: (pls. specify) <input type="text" class="line-edit" name="pres_others_desc"
                                    style="width: 140px;" value="<?= getVal('pres_others_desc', $health_values) ?>">
                            </div>
                        </td>
                    </tr>
                </table>
                <div style="font-size:10px; margin-top:5px; padding-left: 20px;">
                    <strong>Present medications taken: (pls. specify)</strong> <input type="text" class="line-edit"
                        name="pres_medications" style="width:60%;"
                        value="<?= getVal('pres_medications', $health_values) ?>">
                </div>

                <!-- Legend Section -->
                <div style="margin-top: 40px; font-size: 10px; font-family: Arial;">
                    <div style="display: flex;">
                        <!-- Legend Left -->
                        <div style="width: 45%;">
                            <table style="width: 100%; border-collapse: collapse; border: none;">
                                <tr>
                                    <td style="width: 20%; font-weight: bold; border:none; vertical-align: top;">Legend</td>
                                    <td style="width: 10%; font-weight: bold; border:none;">CXR</td>
                                    <td style="border:none;">-Chest X-ray</td>
                                </tr>
                                <tr>
                                    <td style="border:none;"></td>
                                    <td style="font-weight: bold; border:none;">ECG</td>
                                    <td style="border:none;">-Electro-Cardio Gram</td>
                                </tr>
                                <tr>
                                    <td style="border:none;"></td>
                                    <td style="font-weight: bold; border:none;">Y</td>
                                    <td style="border:none;">-Yes</td>
                                </tr>
                                <tr>
                                    <td style="border:none;"></td>
                                    <td style="font-weight: bold; border:none;">N</td>
                                    <td style="border:none;">-No</td>
                                </tr>
                                <tr>
                                    <td style="border:none;"></td>
                                    <td style="font-weight: bold; border:none;">HPN</td>
                                    <td style="border:none;">-Hypertension</td>
                                </tr>
                                <tr>
                                    <td style="border:none;"></td>
                                    <td style="font-weight: bold; border:none;">CVD</td>
                                    <td style="border:none;">-Cardio Vascular Disease</td>
                                </tr>
                                <tr>
                                    <td style="border:none;"></td>
                                    <td style="font-weight: bold; border:none;">DM</td>
                                    <td style="border:none;">-Diabetes Mellitus</td>
                                </tr>
                            </table>
                        </div>

                        <!-- Legend Right -->
                        <div style="width: 55%;">
                            <table style="width: 100%; border-collapse: collapse; border: none;">
                                <tr>
                                    <td style="width: 10%; font-weight: bold; border:none;">PTB</td>
                                    <td style="border:none;">-Pulmonary Tuberculosis</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; border:none;">F</td>
                                    <td style="border:none;">-Full Term</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; border:none;">P</td>
                                    <td style="border:none;">-Pre mature</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; border:none;">A</td>
                                    <td style="border:none;">-Abortion</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold; border:none;">L</td>
                                    <td style="border:none;">-Live Birth</td>
                                </tr>
                            </table>
                            <div style="margin-top: 20px;">
                                <table style="width: 100%; border:none;">
                                    <tr>
                                        <td style="border:none; width: 80px; vertical-align: top;">Interviewed by:</td>
                                        <td style="border:none; padding: 2px 0;">
                                            <!-- Signature Display/Button -->
                                            <div onclick="openInterviewerSignaturePad()"
                                                style="height: 20px; border: 1px dashed #999; cursor: pointer; background: #f9f9f9; display: flex; align-items: center; justify-content: center; margin-bottom: 1px;">
                                                <img id="interviewer-sig-display"
                                                    style="max-height: 18px; max-width: 100%; display: none;" />
                                                <span id="interviewer-sig-placeholder"
                                                    style="font-size: 8px; color: #999;">Click to Sign</span>
                                            </div>
                                            <input type="hidden" name="interviewer_signature" id="interviewer-sig-data"
                                                value="<?= getVal('interviewer_signature', $health_values) ?>">
                                            <!-- Name Input -->
                                            <input type="text" name="interviewed_by"
                                                value="<?= getVal('interviewed_by', $health_values) ?>"
                                                style="width:100%; border:none; border-bottom: 1px solid #000; outline:none; font-size:10px; padding: 1px 0; text-align: center;">
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="border:none;">Date:</td>
                                        <td style="border:none; border-bottom: 1px solid #000;"><input type="text"
                                                style="width:100%; border:none; outline:none; font-size:10px;"
                                                name="interview_date"
                                                value="<?= getVal('interview_date', $health_values) ?>"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
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
                            <?php for ($g = 7; $g <= 12; $g++): ?>
                                <td><input type="date" name="date_<?= $g ?>"
                                        value="<?= getVal('date_' . $g, $health_values) ?>"></td>
                            <?php endfor; ?>
                        </tr>

                        <tr>
                            <td colspan="7" style="background:#eee; text-align:left; padding-left:5px;"><strong>Vital
                                    Signs:</strong></td>
                        </tr>
                        <?php
                        $health_rows = ["Temperature", "Blood Pressure", "Cardiac/Pulse Rate", "Respiratory Rate", "Height", "Weight"];
                        foreach ($health_rows as $row):
                            $slug = strtolower(str_replace([' ', '/'], '_', $row));
                            ?>
                            <tr>
                                <td style="text-align: left; padding-left: 10px;">
                                    <?= $row ?>
                                </td>
                                <?php for ($g = 7; $g <= 12; $g++): ?>
                                    <td><input type="text" name="<?= $slug . '_' . $g ?>"
                                            value="<?= getVal($slug . '_' . $g, $health_values) ?>"></td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>

                        <tr>
                            <td colspan="7" style="background:#eee; text-align:left; padding-left:5px;">
                                <strong>Nutritional
                                    Status (NS)</strong>
                            </td>
                        </tr>
                        <?php
                        $nutri_rows = ["BMI/Weight for Age" => "bmi_weight", "BMI/Height for Age" => "bmi_height"];
                        foreach ($nutri_rows as $row => $slug):
                            ?>
                            <tr>
                                <td style="text-align: left; padding-left: 10px;">
                                    <?= $row ?>
                                </td>
                                <?php for ($g = 7; $g <= 12; $g++): ?>
                                    <td><input type="text" name="<?= $slug . '_' . $g ?>"
                                            value="<?= getVal($slug . '_' . $g, $health_values) ?>"></td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>

                        <tr>
                            <td colspan="7" style="background:#eee; text-align:left; padding-left:5px;"><strong>Visual
                                    Acuity</strong></td>
                        </tr>
                        <?php
                        $visual_rows = ["Snellen", "Eye chart (Near)", "Ishihara chart"];
                        foreach ($visual_rows as $row):
                            $slug = strtolower(str_replace([' ', '(', ')'], '_', $row));
                            ?>
                            <tr>
                                <td style="text-align: left; padding-left: 10px;">
                                    <?= $row ?>
                                </td>
                                <?php for ($g = 7; $g <= 12; $g++): ?>
                                    <td><input type="text" name="<?= $slug . '_' . $g ?>"
                                            value="<?= getVal($slug . '_' . $g, $health_values) ?>"></td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>

                        <tr>
                            <td style="text-align: left;"><strong>Auditory screening (Tuning Fork)</strong></td>
                            <?php for ($g = 7; $g <= 12; $g++): ?>
                                <td><input type="text" name="auditory_<?= $g ?>"
                                        value="<?= getVal('auditory_' . $g, $health_values) ?>"></td>
                            <?php endfor; ?>
                        </tr>



                        <!-- Standard Rows -->
                        <?php
                        // Standard Rows
                        $rows = [
                            "Skin/Scalp",
                            "Eyes/Ears/Nose",
                            "Mouth/Neck/Throat",
                            "Lungs/Heart",
                            "Abdomen/Genitalia",
                            "Spine/Extremities",
                            "Iron-Folic Acid Supplementation (V o X)",
                            "Deworming (V o X)",
                            "Immunization (specify)",
                            "SBFP Beneficiary (V o X)",
                            "4Ps Beneficiary (V o X)",
                            "Menarche",
                            "Others, Specify"
                        ];

                        // Rows that require a date field on the right side
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

                            // Rows where we ONLY want the date, no checkbox/text input
                            $onlyDate = in_array($row, [
                                "Iron-Folic Acid Supplementation (V o X)",
                                "Deworming (V o X)",
                                "Immunization (specify)",
                                "Menarche"
                            ]);
                            ?>
                            <tr>
                                <td style="text-align: left;"><strong><?= $row ?></strong></td>

                                <?php for ($g = 7; $g <= 12; $g++): ?>
                                    <td>
                                        <?php if ($hasDate): ?>
                                            <div style="display:flex; align-items:center; justify-content:center; width:100%;">
                                                <!-- Main Input (Hidden for specific rows) -->
                                                <?php if (!$onlyDate): ?>
                                                    <?php if ($isCheckbox): ?>
                                                        <input type="checkbox" name="<?= $slug . '_' . $g ?>" value="1" <?= getVal($slug . '_' . $g, $health_values) ? 'checked' : '' ?>
                                                            style="width: 20px; height: 20px; cursor: pointer;">
                                                    <?php else: ?>
                                                        <input type="text" name="<?= $slug . '_' . $g ?>"
                                                            value="<?= getVal($slug . '_' . $g, $health_values) ?>"
                                                            style="width: 50%; text-align: center;">
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <!-- Date Input on the Right -->
                                                <input type="date" name="<?= $slug . '_date_' . $g ?>"
                                                    value="<?= getVal($slug . '_date_' . $g, $health_values) ?>"
                                                    style="width: <?= $onlyDate ? '100%' : '50%' ?>; border: none; border-bottom: 1px solid #000; outline: none; font-size: 11px; text-align: center;">
                                            </div>
                                            <?php if ($row === 'Immunization (specify)'): ?>
                                                <input type="text" name="<?= $slug . '_remarks_' . $g ?>"
                                                    value="<?= getVal($slug . '_remarks_' . $g, $health_values) ?>"
                                                    style="width: 100%; border: none; border-bottom: 1px solid #ccc; font-size: 10px; margin-top: 4px; text-align: center;"
                                                    placeholder="Specify">
                                            <?php elseif ($row === 'Iron-Folic Acid Supplementation (V o X)' || $row === 'Deworming (V o X)'): ?>
                                                <input type="date" name="<?= $slug . '_date_2_' . $g ?>"
                                                    value="<?= getVal($slug . '_date_2_' . $g, $health_values) ?>"
                                                    style="width: 100%; border: none; border-bottom: 1px solid #ccc; outline: none; font-size: 11px; margin-top: 4px; text-align: center;">
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- Standard Single Input -->
                                            <?php if ($isCheckbox): ?>
                                                <input type="checkbox" name="<?= $slug . '_' . $g ?>" value="1" <?= getVal($slug . '_' . $g, $health_values) ? 'checked' : '' ?> style="width: 20px; height: 20px; cursor: pointer;">
                                            <?php else: ?>
                                                <input type="text" name="<?= $slug . '_' . $g ?>"
                                                    value="<?= getVal($slug . '_' . $g, $health_values) ?>">
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>

                        <tr>
                            <td style="text-align: left;"><strong>Examined by</strong></td>
                            <?php for ($g = 7; $g <= 12; $g++): ?>
                                <td style="vertical-align: bottom; padding: 2px 0;">
                                    <!-- Signature Display/Button -->
                                    <div class="signature-container-g<?= $g ?>" onclick="openExaminerSignaturePad(<?= $g ?>)"
                                        style="height: 20px; border: 1px dashed #999; cursor: pointer; background: #f9f9f9; display: flex; align-items: center; justify-content: center; margin-bottom: 1px;">
                                        <img class="examiner-sig-display-<?= $g ?>"
                                            style="max-height: 18px; max-width: 100%; display: none;" />
                                        <span class="examiner-sig-placeholder-<?= $g ?>"
                                            style="font-size: 8px; color: #999;">Sign</span>
                                    </div>
                                    <input type="hidden" name="examiner_signature_<?= $g ?>" class="examiner-sig-data-<?= $g ?>"
                                        value="<?= getVal('examiner_signature_' . $g, $health_values) ?>">
                                    <!-- Name Input -->
                                    <input type="text" name="examiner_<?= $g ?>"
                                        value="<?= getVal('examiner_' . $g, $health_values) ?>"
                                        style="border: none; border-bottom: 1px solid #ccc; font-size: 9px; width: 100%; text-align: center; padding: 1px 0;"
                                        placeholder="Name">
                                </td>
                            <?php endfor; ?>
                        </tr>
                    </tbody>
                </table>

                <br>
                <!-- Legend Table (Static View) -->
                <table class="legend-table" style="margin-top: 20px; font-size: 9px;">
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

                        foreach ($legend_data as $key => $rows) {
                            echo '<tr>';
                            foreach ($rows as $content) {
                                $text = $content ? "<strong>$key.</strong> $content" : "";
                                echo "<td style='text-align:left;'>$text</td>";
                            }
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="fab-container">
            <a href="view_card.php?view_id=<?= $id ?>&type=<?= $type ?>" class="btn-back"><i
                    class="fa-solid fa-arrow-left"></i></a>
            <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> SAVE</button>
        </div>
    </form>

    <!-- Examiner Signature Pad Modal -->
    <div id="examinerSignatureModal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center;">
        <div style="background: white; padding: 20px; border-radius: 10px; max-width: 500px; width: 90%;">
            <h3 style="margin-top: 0;">Draw Examiner Signature</h3>
            <canvas id="examinerSignatureCanvas" width="460" height="200"
                style="border: 2px solid #000; cursor: crosshair; touch-action: none;"></canvas>
            <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="clearExaminerSignature()"
                    style="padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    <i class="fa-solid fa-eraser"></i> Clear
                </button>
                <button type="button" onclick="saveExaminerSignature()"
                    style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    <i class="fa-solid fa-check"></i> Save
                </button>
                <button type="button" onclick="closeExaminerSignaturePad()"
                    style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    <i class="fa-solid fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        let examinerCanvas, examinerCtx, examinerIsDrawing = false, currentGradeLevel = null;
        let signatureType = null; // 'examiner' or 'interviewer'

        document.addEventListener('DOMContentLoaded', function () {
            examinerCanvas = document.getElementById('examinerSignatureCanvas');
            examinerCtx = examinerCanvas.getContext('2d');
            examinerCtx.strokeStyle = '#000';
            examinerCtx.lineWidth = 2;
            examinerCtx.lineCap = 'round';

            // Mouse events
            examinerCanvas.addEventListener('mousedown', startExaminerDrawing);
            examinerCanvas.addEventListener('mousemove', drawExaminer);
            examinerCanvas.addEventListener('mouseup', stopExaminerDrawing);
            examinerCanvas.addEventListener('mouseout', stopExaminerDrawing);

            // Touch events
            examinerCanvas.addEventListener('touchstart', handleExaminerTouchStart);
            examinerCanvas.addEventListener('touchmove', handleExaminerTouchMove);
            examinerCanvas.addEventListener('touchend', stopExaminerDrawing);

            // Load existing signatures
            loadExistingExaminerSignatures();

            // Load interviewer signature (for employees)
            const interviewerSig = document.getElementById('interviewer-sig-data')?.value;
            if (interviewerSig) {
                const img = document.getElementById('interviewer-sig-display');
                const placeholder = document.getElementById('interviewer-sig-placeholder');
                if (img && placeholder) {
                    img.src = interviewerSig;
                    img.style.display = 'block';
                    placeholder.style.display = 'none';
                }
            }
        });

        function loadExistingExaminerSignatures() {
            for (let grade = 7; grade <= 12; grade++) {
                const sigData = document.querySelector('.examiner-sig-data-' + grade).value;
                if (sigData) {
                    const img = document.querySelector('.examiner-sig-display-' + grade);
                    const placeholder = document.querySelector('.examiner-sig-placeholder-' + grade);
                    img.src = sigData;
                    img.style.display = 'block';
                    placeholder.style.display = 'none';
                }
            }
        }

        function openExaminerSignaturePad(gradeLevel) {
            currentGradeLevel = gradeLevel;
            signatureType = 'examiner';
            document.getElementById('examinerSignatureModal').style.display = 'flex';
            clearExaminerSignature();

            // Load existing signature if any
            const existingSig = document.querySelector('.examiner-sig-data-' + gradeLevel).value;
            if (existingSig) {
                const img = new Image();
                img.onload = function () {
                    examinerCtx.drawImage(img, 0, 0);
                };
                img.src = existingSig;
            }
        }

        function closeExaminerSignaturePad() {
            document.getElementById('examinerSignatureModal').style.display = 'none';
            currentGradeLevel = null;
            signatureType = null;
        }

        function startExaminerDrawing(e) {
            examinerIsDrawing = true;
            const rect = examinerCanvas.getBoundingClientRect();
            examinerCtx.beginPath();
            examinerCtx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
        }

        function drawExaminer(e) {
            if (!examinerIsDrawing) return;
            const rect = examinerCanvas.getBoundingClientRect();
            examinerCtx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
            examinerCtx.stroke();
        }

        function stopExaminerDrawing() {
            examinerIsDrawing = false;
        }

        function handleExaminerTouchStart(e) {
            e.preventDefault();
            examinerIsDrawing = true;
            const rect = examinerCanvas.getBoundingClientRect();
            const touch = e.touches[0];
            examinerCtx.beginPath();
            examinerCtx.moveTo(touch.clientX - rect.left, touch.clientY - rect.top);
        }

        function handleExaminerTouchMove(e) {
            e.preventDefault();
            if (!examinerIsDrawing) return;
            const rect = examinerCanvas.getBoundingClientRect();
            const touch = e.touches[0];
            examinerCtx.lineTo(touch.clientX - rect.left, touch.clientY - rect.top);
            examinerCtx.stroke();
        }

        function clearExaminerSignature() {
            examinerCtx.clearRect(0, 0, examinerCanvas.width, examinerCanvas.height);
        }

        function saveExaminerSignature() {
            const signatureData = examinerCanvas.toDataURL('image/png');

            if (signatureType === 'interviewer') {
                // Save interviewer signature
                document.getElementById('interviewer-sig-data').value = signatureData;
                const imgDisplay = document.getElementById('interviewer-sig-display');
                const placeholder = document.getElementById('interviewer-sig-placeholder');
                imgDisplay.src = signatureData;
                imgDisplay.style.display = 'block';
                placeholder.style.display = 'none';
            } else if (currentGradeLevel !== null) {
                // Save examiner signature (for specific grade)
                document.querySelector('.examiner-sig-data-' + currentGradeLevel).value = signatureData;
                const imgDisplay = document.querySelector('.examiner-sig-display-' + currentGradeLevel);
                const placeholder = document.querySelector('.examiner-sig-placeholder-' + currentGradeLevel);
                imgDisplay.src = signatureData;
                imgDisplay.style.display = 'block';
                placeholder.style.display = 'none';
            }

            closeExaminerSignaturePad();
        }

        // Interviewer Signature Functions (for employee cards)
        function openInterviewerSignaturePad() {
            signatureType = 'interviewer';
            document.getElementById('examinerSignatureModal').style.display = 'flex';
            clearExaminerSignature();

            // Load existing signature if any
            const existingSig = document.getElementById('interviewer-sig-data').value;
            if (existingSig) {
                const img = new Image();
                img.onload = function () {
                    examinerCtx.drawImage(img, 0, 0);
                };
                img.src = existingSig;
            }
        }
    </script>
    <script>
        // Automatic BMI and Nutritional Status Calculation
        const studentData = {
            birthDate: "<?= $person['birth_date'] ?? '' ?>",
            gender: "<?= $person['gender'] ?? '' ?>"
        };

        function calculateAge(birthDateString, examDateString) {
            if (!birthDateString) return 0;
            const birthDate = new Date(birthDateString);
            const examDate = examDateString ? new Date(examDateString) : new Date();
            let age = examDate.getFullYear() - birthDate.getFullYear();
            const m = examDate.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && examDate.getDate() < birthDate.getDate())) {
                age--;
            }
            return age;
        }

        function getBMIStatus(bmi, age) {
            if (!bmi || age <= 0) return '';
            const b = parseFloat(bmi);

            // DepEd/WHO Simplified Logic (Age 5-19)
            if (age >= 5 && age <= 19) {
                if (b < 14.0) return 'Severely Wasted';
                if (b < 16.0) return 'Wasted';
                if (b < 23.0) return 'Normal Weight';
                if (b < 27.0) return 'Overweight';
                return 'Obese';
            }
            // Adult (>19)
            if (b < 16.0) return 'Severely Wasted';
            if (b < 18.5) return 'Wasted';
            if (b < 25.0) return 'Normal Weight';
            if (b < 30.0) return 'Overweight';
            return 'Obese';
        }

        function getHeightStatus(height, age, gender) {
            if (!height || age <= 0) return '';

            // WHO Growth Reference 2007 (5-19 years) - Approximate -2SD (Stunting) thresholds in cm
            const thresholds = {
                5: [102, 101], 6: [107, 107], 7: [113, 113], 8: [119, 119],
                9: [124, 124], 10: [129, 130], 11: [134, 136], 12: [139, 142],
                13: [145, 146], 14: [151, 149], 15: [156, 151], 16: [160, 152],
                17: [163, 153], 18: [164, 153], 19: [165, 153]
            };

            const gKey = (gender && gender.toLowerCase().includes('female')) ? 1 : 0;
            let threshold = thresholds[age] ? thresholds[age][gKey] : 0;

            // Adult fallback
            if (threshold === 0 && age > 19) threshold = (gKey === 0) ? 155 : 145;

            if (threshold > 0) {
                if (height < (threshold - 10)) return 'Severely Stunted';
                if (height < threshold) return 'Stunted';
                if (height > (threshold + 30)) return 'Tall';
                return 'Normal Height';
            }
            return 'Normal Height';
        }

        function updateNutritionalStatus(grade) {
            const weightInput = document.querySelector(`input[name="weight_${grade}"]`);
            const heightInput = document.querySelector(`input[name="height_${grade}"]`);
            const dateInput = document.querySelector(`input[name="date_${grade}"]`);

            const bmiWeightInput = document.querySelector(`input[name="bmi_weight_${grade}"]`);
            const bmiHeightInput = document.querySelector(`input[name="bmi_height_${grade}"]`);

            if (!weightInput || !heightInput || !dateInput) return;

            const weight = parseFloat(weightInput.value);
            const height = parseFloat(heightInput.value);
            const examDate = dateInput.value; // Can be empty, calculates current age

            if (weight > 0 && height > 0) {
                // Calculate BMI
                const heightM = height / 100;
                const bmi = (weight / (heightM * heightM)).toFixed(1);

                // Calculate Age
                const age = calculateAge(studentData.birthDate, examDate);

                // BMI/Weight Status
                if (bmiWeightInput) {
                    const status = getBMIStatus(bmi, age);
                    if (status) bmiWeightInput.value = status;
                }

                // BMI/Height Status (Stunting)
                if (bmiHeightInput) {
                    const hStatus = getHeightStatus(height, age, studentData.gender);
                    if (hStatus) bmiHeightInput.value = hStatus;
                }
            }
        }

        // Attach listeners
        document.addEventListener('DOMContentLoaded', () => {
            for (let g = 7; g <= 12; g++) {
                const inputs = [`input[name="weight_${g}"]`, `input[name="height_${g}"]`, `input[name="date_${g}"]`];
                inputs.forEach(selector => {
                    const el = document.querySelector(selector);
                    if (el) {
                        el.addEventListener('input', () => updateNutritionalStatus(g));
                        // Run once on load to populate/correct existing data
                        updateNutritionalStatus(g);
                    }
                });
            }
        });
    </script>
</body>


</html>