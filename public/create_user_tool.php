<?php
require_once 'config/db.php';

// Check if run from CLI or Browser
// If browser, simple form. If POST, create user.

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'admin';

    if ($username && $password && $email) {
        // Hash password
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Prepare Statement
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, is_active) VALUES (?, ?, ?, ?, 1)");
        if ($stmt) {
            $stmt->bind_param("ssss", $username, $hashed, $email, $role);
            if ($stmt->execute()) {
                $message = "<div style='color: green;'>✅ User <strong>$username</strong> created successfully! <a href='public/index.php'>Login Here</a></div>";
            } else {
                $message = "<div style='color: red;'>❌ Error: " . $stmt->error . "</div>";
            }
            $stmt->close();
        } else {
            $message = "<div style='color: red;'>❌ Database Error: " . $conn->error . "</div>";
        }
    } else {
        $message = "<div style='color: red;'>❌ All fields are required!</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create System User</title>
    <style>
        body {
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: #f0f2f5;
        }

        .card {
            background: white;
            padding: 2rem;
            borderRadius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        h2 {
            margin-top: 0;
            color: #00ACB1;
        }

        input,
        select,
        button {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            background: #00ACB1;
            color: white;
            border: none;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #008a8e;
        }
    </style>
    <?php include 'assets/inc/console_suppress.php'; ?>
</head>

<body>
    <div class="card">
        <h2>Create Database User</h2>
        <?= $message ?>
        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" placeholder="admin" required>

            <label>Email</label>
            <input type="email" name="email" placeholder="admin@example.com" required>

            <label>Password</label>
            <input type="text" name="password" placeholder="Password123" required>

            <label>Role</label>
            <select name="role">
                <option value="admin">Admin</option>
                <option value="doctor">Doctor</option>
                <option value="nurse">Nurse</option>
                <option value="medical_staff">Medical Staff</option>
            </select>

            <button type="submit">Create User</button>
        </form>
    </div>
</body>

</html>
