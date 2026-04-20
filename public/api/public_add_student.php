<?php
require_once "../../config/db.php";

// Set JSON response header
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // 1. Rate Limiting (Prevent Spam - Max 3 per hour from same IP)
        $rateLimit = checkRateLimit('public_registration', 3, 3600);
        if (!$rateLimit['allowed']) {
            throw new Exception("Too many registration attempts. Please try again in " . $rateLimit['wait'] . " seconds.");
        }

        // 2. CSRF Verification
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            throw new Exception("Security token mismatch. Please refresh the page.");
        }

        // 3. Input Validation
        $required_fields = ['last_name', 'first_name', 'curriculum', 'address', 'gender', 'birth_date', 'guardian'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // 2. Validate Date
        $birth_date = sanitizeInput($_POST['birth_date']);
        $d = DateTime::createFromFormat('Y-m-d', $birth_date);
        if (!$d || $d->format('Y-m-d') !== $birth_date) {
            throw new Exception("Invalid date format. Please use YYYY-MM-DD.");
        }

        $year = (int) $d->format('Y');
        if ($year < 1900 || $year > date('Y')) {
            throw new Exception("Invalid birth year. Please enter a valid date.");
        }

        // 3. Format Name: Last, First Middle
        $middle = isset($_POST['middle_name']) ? sanitizeInput($_POST['middle_name']) : '';
        $name = sanitizeInput($_POST['last_name']) . ", " . sanitizeInput($_POST['first_name']) . ($middle ? " " . $middle : "");

        // 4. Prepare SQL
        $stmt = $conn->prepare("INSERT INTO students (name, lrn, curriculum, address, gender, birth_date, birthplace, religion, guardian, contact) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }

        // Bind Parameters
        $lrn = isset($_POST['lrn']) ? sanitizeInput($_POST['lrn']) : '';
        $curriculum = sanitizeInput($_POST['curriculum']);
        $address = sanitizeInput($_POST['address']);
        $gender = sanitizeInput($_POST['gender']);
        $birthplace = sanitizeInput($_POST['birth_place'] ?? '');
        $religion = sanitizeInput($_POST['religion'] ?? '');
        $guardian = sanitizeInput($_POST['guardian']);
        $contact = sanitizeInput($_POST['contact'] ?? '');

        // Check if LRN already exists (Only if LRN is provided)
        if (!empty($lrn)) {
            $check = $conn->prepare("SELECT id FROM students WHERE lrn = ?");
            $check->bind_param("s", $lrn);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception("A student with this LRN already exists.");
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
            // Create a notification for the admin
            $notifMsg = "New public registration: " . $name;
            $notifLink = "student.php?search=" . urlencode($name);
            $stmtNotif = $conn->prepare("INSERT INTO notifications (type, message, link) VALUES ('registration', ?, ?)");
            if ($stmtNotif) {
                $stmtNotif->bind_param("ss", $notifMsg, $notifLink);
                $stmtNotif->execute();
                $stmtNotif->close();
            }

            echo json_encode(['success' => true, 'message' => "Student registered successfully!"]);
        } else {
            throw new Exception("Error saving student. Please try again later.");
        }
        $stmt->close();

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Database error occurred. Please contact administrator."]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => "Method Not Allowed"]);
}
?>