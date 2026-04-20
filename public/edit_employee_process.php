<?php
require_once "../config/db.php";
requireLogin();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // 1. Validate ID
        if (empty($_POST['id'])) {
            throw new Exception("Invalid Employee ID.");
        }
        $id = (int) $_POST['id'];

        // 2. Validate Name
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            throw new Exception("Employee name is required.");
        }

        // 3. Validate Employee No (formerly Dates logic)
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

        // 4. Other Fields
        $gender = trim($_POST['gender'] ?? '');
        $civil_status = trim($_POST['civil_status'] ?? '');
        $school_district_division = trim($_POST['school_district_division'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $first_year_in_service = trim($_POST['first_year_in_service'] ?? '');

        // 5. Upgrade Query
        $sql = "UPDATE employees SET 
                employee_no = ?, 
                name = ?, 
                birth_date = ?, 
                gender = ?, 
                civil_status = ?, 
                school_district_division = ?, 
                position = ?, 
                designation = ?, 
                first_year_in_service = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }

        $stmt->bind_param("sssssssssi", $employee_no, $name, $birth_date, $gender, $civil_status, $school_district_division, $position, $designation, $first_year_in_service, $id);

        if ($stmt->execute()) {
            logSecurityEvent('EMPLOYEE_UPDATED', "Employee: $name (ID: $id) updated by User ID: " . $_SESSION['user_id']);
            $_SESSION['success_message'] = "Employee record updated successfully!";
        } else {
            throw new Exception("Error updating record: " . $stmt->error);
        }
        $stmt->close();

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $_SESSION['error_message'] = "Database Error: " . $e->getMessage();
    }

    header("Location: employees.php");
    exit();
} else {
    header("Location: employees.php");
    exit();
}
?>