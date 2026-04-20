<?php
require_once "../config/db.php";
requireLogin();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // 1. Data Collection & Validation
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            throw new Exception("Employee name is required.");
        }

        // Employee Number
        $employee_no = trim($_POST['employee_no'] ?? '');

        $birth_date = $_POST['birth_date'];
        if (!empty($birth_date)) {
            $d = DateTime::createFromFormat('Y-m-d', $birth_date);
            if (!$d || $d->format('Y-m-d') !== $birth_date) {
                throw new Exception("Invalid birth date format.");
            }
            $year = (int) $d->format('Y');
            if ($year < 1900 || $year > date('Y')) {
                throw new Exception("Invalid birth year.");
            }
        } else {
            throw new Exception("Birth date is required.");
        }

        // Other Fields
        $gender = trim($_POST['gender'] ?? '');
        $civil_status = trim($_POST['civil_status'] ?? '');
        $school_district_division = trim($_POST['school_district_division'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $first_year_in_service = trim($_POST['first_year_in_service'] ?? '');

        // Check if Employee No already exists (Only if provider)
        if (!empty($employee_no)) {
            $check = $conn->prepare("SELECT id FROM employees WHERE employee_no = ?");
            $check->bind_param("s", $employee_no);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception("Employee number already exists in the system.");
            }
            $check->close();
        }

        // Check for duplicate Name AND Employee No together (User request: last name at employee number na same)
        $checkDuplicate = $conn->prepare("SELECT id FROM employees WHERE name = ? AND employee_no = ?");
        $checkDuplicate->bind_param("ss", $name, $employee_no);
        $checkDuplicate->execute();
        if ($checkDuplicate->get_result()->num_rows > 0) {
            throw new Exception("An employee with the same name and employee number already exists.");
        }
        $checkDuplicate->close();

        // 2. Prepare SQL
        $sql = "INSERT INTO employees (employee_no, name, birth_date, gender, civil_status, school_district_division, position, designation, first_year_in_service, entry_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }

        $stmt->bind_param("sssssssss", $employee_no, $name, $birth_date, $gender, $civil_status, $school_district_division, $position, $designation, $first_year_in_service);

        // 3. Execute
        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            logSecurityEvent('EMPLOYEE_ADDED', "Employee: $name (ID: $id) added by User ID: " . $_SESSION['user_id']);

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => true, 'message' => "Employee Record Added Successfully!"]);
                exit;
            }
            $_SESSION['success_message'] = "Employee Record Added Successfully!";
        } else {
            throw new Exception("Error adding record: " . $stmt->error);
        }
        $stmt->close();

    } catch (Exception $e) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        $_SESSION['error_message'] = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            http_response_code(500); // Server Error
            echo json_encode(['success' => false, 'message' => "Database Error: " . $e->getMessage()]);
            exit;
        }
        $_SESSION['error_message'] = "Database Error: " . $e->getMessage();
    }

    header("Location: employees.php");
    exit();
} else {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => "Invalid Request Method"]);
        exit;
    }
    header("Location: employees.php");
    exit();
}
?>