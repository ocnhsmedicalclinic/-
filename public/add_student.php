<?php
require_once "../config/db.php";
requireLogin();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // 1. Input Validation
        $required_fields = ['last_name', 'first_name', 'curriculum', 'address', 'gender', 'birth_date', 'guardian'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // 2. Validate Date
        $birth_date = $_POST['birth_date'];
        $d = DateTime::createFromFormat('Y-m-d', $birth_date);
        if (!$d || $d->format('Y-m-d') !== $birth_date) {
            throw new Exception("Invalid date format. Please use YYYY-MM-DD.");
        }

        $year = (int) $d->format('Y');
        if ($year < 1900 || $year > date('Y')) {
            throw new Exception("Invalid birth year. Please enter a valid date.");
        }

        // 3. Format Name: Last, First Middle
        $middle = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
        $name = trim($_POST['last_name']) . ", " . trim($_POST['first_name']) . ($middle ? " " . $middle : "");

        // 4. Prepare SQL
        $stmt = $conn->prepare("INSERT INTO students (name, lrn, curriculum, address, gender, birth_date, birthplace, religion, guardian, contact) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }

        // Bind Parameters
        $lrn = trim($_POST['lrn']);
        $curriculum = trim($_POST['curriculum']);
        $address = trim($_POST['address']);
        $gender = trim($_POST['gender']);
        $birthplace = trim($_POST['birth_place'] ?? '');
        $religion = trim($_POST['religion'] ?? '');
        $guardian = trim($_POST['guardian']);
        $contact = trim($_POST['contact'] ?? '');

        // Check if LRN already exists (Only if LRN is provided)
        if (!empty($lrn)) {
            $check = $conn->prepare("SELECT id FROM students WHERE lrn = ?");
            $check->bind_param("s", $lrn);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception("LRN already exists in the system.");
            }
            $check->close();
        }

        // Check for duplicate Name AND LRN together (User request: last name at lrn na same)
        $checkDuplicate = $conn->prepare("SELECT id FROM students WHERE name = ? AND lrn = ?");
        $checkDuplicate->bind_param("ss", $name, $lrn);
        $checkDuplicate->execute();
        if ($checkDuplicate->get_result()->num_rows > 0) {
            throw new Exception("A student with the same name and LRN already exists.");
        }
        $checkDuplicate->close();

        $stmt->bind_param(
            "ssssssssss",
            $name,
            $lrn,
            $curriculum,
            $address,
            $gender,
            $birth_date,
            $birthplace,
            $religion,
            $guardian,
            $contact
        );

        if ($stmt->execute()) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => true, 'message' => "Student added successfully!"]);
                exit;
            }
            $_SESSION['success_message'] = "Student added successfully!";
        } else {
            throw new Exception("Error executing query: " . $stmt->error);
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

    header("Location: student.php");
    exit();
} else {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => "Invalid Request Method"]);
        exit;
    }
    // If accessed directly without POST
    header("Location: student.php");
    exit();
}
?>