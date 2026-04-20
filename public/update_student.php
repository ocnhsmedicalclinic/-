<?php
require_once "../config/db.php";
requireLogin();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // 1. Validate ID
        if (empty($_POST['id'])) {
            throw new Exception("Invalid Student ID.");
        }
        $id = $_POST['id'];

        // 2. Validate Required Fields
        $required_fields = ['last_name', 'first_name', 'curriculum', 'address', 'gender', 'birth_date', 'guardian_name'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // 3. Validate Date
        $birth_date = $_POST['birth_date'];
        $d = DateTime::createFromFormat('Y-m-d', $birth_date);
        if (!$d || $d->format('Y-m-d') !== $birth_date) {
            throw new Exception("Invalid date format. Please use YYYY-MM-DD.");
        }

        $year = (int) $d->format('Y');
        if ($year < 1900 || $year > date('Y')) {
            throw new Exception("Invalid birth year. Please enter a valid date.");
        }

        // 4. Combine Name
        $middle = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
        $name = trim($_POST['last_name']) . ", " . trim($_POST['first_name']) . ($middle ? " " . $middle : "");

        // 5. Prepare SQL
        $stmt = $conn->prepare("UPDATE students SET 
            name = ?, 
            lrn = ?, 
            curriculum = ?, 
            address = ?, 
            gender = ?, 
            birth_date = ?, 
            birthplace = ?, 
            religion = ?, 
            guardian = ?, 
            contact = ? 
            WHERE id = ?");

        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }

        // 6. Bind Params
        $lrn = trim($_POST['lrn']);
        $curriculum = trim($_POST['curriculum']);
        $address = trim($_POST['address']);
        $gender = trim($_POST['gender']);
        $birthplace = trim($_POST['birth_place'] ?? '');
        $religion = trim($_POST['religion'] ?? '');
        $guardian = trim($_POST['guardian_name']); // Note: Different name in edit modal
        $contact = trim($_POST['contact_number'] ?? ''); // Note: Different name in edit modal

        // Check if LRN exists for OTHER students (Only if LRN is provided)
        if (!empty($lrn)) {
            $check = $conn->prepare("SELECT id FROM students WHERE lrn = ? AND id != ?");
            $check->bind_param("si", $lrn, $id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception("LRN already exists for another student.");
            }
            $check->close();
        }

        $stmt->bind_param(
            "ssssssssssi",
            $name,
            $lrn,
            $curriculum,
            $address,
            $gender,
            $birth_date,
            $birthplace,
            $religion,
            $guardian,
            $contact,
            $id
        );

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Student record updated successfully!";
        } else {
            throw new Exception("Error updating record: " . $stmt->error);
        }
        $stmt->close();

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $_SESSION['error_message'] = "Database Error: " . $e->getMessage();
    }

    header("Location: student.php");
    exit();
} else {
    header("Location: student.php");
    exit();
}
?>