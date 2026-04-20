<?php
require_once "../config/db.php";
requireAdmin();

$error = '';
$success = '';

if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$userId = $_GET['id'];

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: users.php");
    exit();
}

$user = $result->fetch_assoc();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $role = sanitizeInput($_POST['role']);

    // Check if username already exists for other users
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $username, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $error = "Username already exists.";
    } else {
        // Check if email already exists for other users
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Email already exists.";
        } else {
            // Update user
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
            $stmt->bind_param("sssi", $username, $email, $role, $userId);

            if ($stmt->execute()) {
                $success = "User updated successfully!";
                // Refresh user data
                $user['username'] = $username;
                $user['email'] = $email;
                $user['role'] = $role;
            } else {
                $error = "Update failed: " . $conn->error;
            }
        }
    }
}

include "index_layout.php";
?>

<div class="container-fluid" style="padding: 20px;">
    <div class="panel form"
        style="max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
        <div
            style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0; color: #333; font-size: 1.5rem;">Edit User</h2>
            <a href="users.php" style="color: #666; text-decoration: none;"><i class="fa-solid fa-arrow-left"></i>
                Back</a>
        </div>

        <?php if ($error): ?>
            <div
                style="padding: 12px; background: #fee; border-left: 4px solid #f44; color: #c33; border-radius: 6px; margin-bottom: 20px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= escapeOutput($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div
                style="padding: 12px; background: #d4edda; border-left: 4px solid #28a745; color: #155724; border-radius: 6px; margin-bottom: 20px;">
                <i class="fa-solid fa-check-circle"></i>
                <?= escapeOutput($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; color: #555; font-weight: 500;">Username</label>
                <input type="text" name="username" value="<?= escapeOutput($user['username']) ?>" required
                    class="form-control"
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; color: #555; font-weight: 500;">Email</label>
                <input type="email" name="email" value="<?= escapeOutput($user['email']) ?>" required
                    class="form-control"
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>

            <div style="margin-bottom: 25px;">
                <label style="display: block; margin-bottom: 5px; color: #555; font-weight: 500;">Role</label>
                <select name="role" required class="form-control"
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: white;">
                    <!-- Roles matching Register Page -->
                    <option value="medical_staff" <?= $user['role'] == 'medical_staff' ? 'selected' : '' ?>>Medical Staff
                    </option>
                    <option value="nurse" <?= $user['role'] == 'nurse' ? 'selected' : '' ?>>Nurse</option>
                    <option value="doctor" <?= $user['role'] == 'doctor' ? 'selected' : '' ?>>Doctor</option>

                    <!-- Admin role (only show if current user is admin, or keep it allowing admins to set admins) -->
                    <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>

            <button type="submit"
                style="background: #00ACB1; color: white; border: none; padding: 12px 25px; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%;">
                Update User
            </button>
        </form>
    </div>
</div>