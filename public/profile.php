<?php
require_once '../config/db.php';
requireLogin();

$error = '';
$success = '';

// Get current user info
$stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Validate inputs
    if (empty($username) || empty($email)) {
        $error = "All fields are required.";
    } elseif (strlen($username) < 4 || strlen($username) > 20) {
        $error = "Username must be 4-20 characters long.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = "Username can only contain letters, numbers and underscores.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if username is taken by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Username is already taken.";
        } else {
            // Check if email is taken by another user
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "Email is already in use.";
            } else {
                // Update profile
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssi", $username, $email, $_SESSION['user_id']);

                if ($stmt->execute()) {
                    $_SESSION['username'] = $username;
                    $success = "Profile updated successfully!";
                    $user['username'] = $username;
                    $user['email'] = $email;
                    logSecurityEvent('PROFILE_UPDATED', "User ID: " . $_SESSION['user_id']);
                } else {
                    $error = "Failed to update profile.";
                }
            }
        }
        $stmt->close();
    }
}

include "index_layout.php";
?>

<style>
    :root {
        --bg-card: #fff;
        --text-primary: #333;
        --text-secondary: #777;
        --text-label: #444;
        --input-bg: #fff;
        --input-border: #E2E8F0;
        --input-focus-border: #00ACB1;
        --btn-cancel-bg: #F1F5F9;
        --btn-cancel-text: #333;
        --btn-cancel-border: #E2E8F0;
        --shadow-color: rgba(0, 0, 0, 0.05);
    }

    body.dark-mode {
        --bg-card: #272727;
        /* Darker card background */
        --text-primary: #e0e0e0;
        --text-secondary: #aaa;
        --text-label: #ccc;
        --input-bg: #333;
        --input-border: #555;
        --btn-cancel-bg: #444;
        --btn-cancel-text: #ddd;
        --btn-cancel-border: #555;
        --shadow-color: rgba(0, 0, 0, 0.2);
    }

    /* Profile Page Specific Styles */
    .profile-container {
        max-width: 600px;
        margin: 50px auto;
        padding: 20px 20px;
        border-radius: 15px;
    }

    .profile-header-card {
        background: var(--bg-card);
        border-radius: 15px;
        padding: 25px 35px;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 4px 15px var(--shadow-color);
        margin-bottom: 30px;
    }

    .profile-icon {
        background: #00ACB1;
        color: white;
        width: 60px;
        height: 60px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }

    .profile-title h2 {
        margin: 0;
        color: var(--text-primary);
        font-size: 24px;
        font-weight: 700;
    }

    .profile-title p {
        margin: 5px 0 0;
        color: var(--text-secondary);
        font-size: 14px;
    }

    .profile-form-card {
        background: var(--bg-card);
        border-radius: 15px;
        padding: 40px;
        box-shadow: 0 4px 15px var(--shadow-color);
    }

    .profile-input-group {
        margin-bottom: 25px;
    }

    .profile-input-group label {
        display: block;
        font-weight: 600;
        color: var(--text-label);
        margin-bottom: 10px;
        font-size: 15px;
    }

    .profile-input-group label span {
        color: #ff5c5c;
    }

    .profile-input-group input {
        width: 100%;
        padding: 15px 20px;
        border: 1px solid var(--input-border);
        border-radius: 12px;
        background-color: var(--input-bg);
        color: var(--text-primary);
        /* Ensure text is visible in input */
        font-size: 15px;
        outline: none;
        transition: all 0.3s ease;
    }

    .profile-input-group input:focus {
        border-color: var(--input-focus-border);
        box-shadow: 0 0 0 4px rgba(0, 172, 177, 0.1);
    }

    .profile-input-group small {
        display: block;
        margin-top: 8px;
        color: var(--text-secondary);
        font-size: 12px;
        line-height: 1.4;
    }

    .profile-actions {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-top: 40px;
    }

    .btn-save-profile {
        background: linear-gradient(135deg, #00ACB1 0%, #00d4aa 100%);
        color: white;
        padding: 14px 40px;
        border-radius: 12px;
        border: none;
        font-weight: 700;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 172, 177, 0.3);
    }

    .btn-save-profile:hover {
        background: linear-gradient(135deg, #008e91 0%, #00b89a 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 172, 177, 0.4);
    }

    .btn-cancel-profile {
        background: var(--btn-cancel-bg);
        color: var(--btn-cancel-text);
        padding: 14px 40px;
        border-radius: 12px;
        border: 1px solid var(--btn-cancel-border);
        font-weight: 700;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-cancel-profile:hover {
        background: var(--input-border);
        /* Slightly darker */
    }

    body.dark-mode .btn-cancel-profile:hover {
        background: #555;
    }

    .alert {
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-error {
        background: #fee;
        border-left: 4px solid #ff5c5c;
        color: #c33;
    }

    .alert-success {
        background: #efe;
        border-left: 4px solid #2ecc71;
        color: #2a6;
    }

    @media screen and (max-width: 480px) {
        .profile-actions {
            flex-direction: column;
            gap: 15px;
        }

        .btn-save-profile,
        .btn-cancel-profile {
            width: 100%;
        }

        .profile-header-card {
            flex-direction: column;
            text-align: center;
        }
    }
</style>

<div class="profile-container">
    <div class="profile-header-card">
        <div class="profile-icon">
            <i class="fa-solid fa-pen-to-square"></i>
        </div>
        <div class="profile-title">
            <h2>Edit Profile</h2>
            <p>Update your personal information or preferences</p>
        </div>
    </div>

    <div class="profile-form-card">
        <?php if ($error): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: '<?= escapeOutput($error) ?>',
                        confirmButtonColor: '#d33'
                    });
                });
            </script>
        <?php endif; ?>

        <?php if ($success): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: '<?= escapeOutput($success) ?>',
                        confirmButtonColor: '#00ACB1'
                    });
                });
            </script>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="profile-input-group">
                <label for="username">Username <span>*</span></label>
                <input type="text" name="username" id="username" value="<?= escapeOutput($user['username']) ?>"
                    pattern="[a-zA-Z0-9_]{4,20}" required autocomplete="username">
                <small>Username must be 4-20 characters long and contain only letters, numbers and underscores.</small>
            </div>

            <div class="profile-input-group">
                <label for="email">Email <span>*</span></label>
                <input type="email" name="email" id="email" value="<?= escapeOutput($user['email'] ?? '') ?>" required
                    autocomplete="email">
                <small>We'll use this email for important notifications and account recovery.</small>
            </div>

            <div class="profile-actions">
                <button type="submit" class="btn-save-profile">Save Changes</button>
                <button type="button" class="btn-cancel-profile"
                    onclick="window.location.href='student.php'">Cancel</button>
            </div>
        </form>
    </div>
</div>

</body>

</html>