<?php
require_once "../config/db.php";
requireAdmin();

// Check if user limit reached
// Check if user limit reached
// Limit removed to allow multiple users
$addUserDisabled = false;

// Handle Add User Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    if ($addUserDisabled) {
        $error = "User creation is disabled. Limit reached.";
    } elseif (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid security token.";
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $role = sanitizeInput($_POST['role'] ?? 'medical_staff');

        if (empty($username) || empty($email) || empty($password)) {
            $error = "All fields are required.";
        } elseif (strlen($username) < 3) {
            $error = "Username must be at least 3 characters.";
        } elseif (!validateEmail($email)) {
            $error = "Invalid email address.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match.";
        } else {
            // Check existence
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->bind_param("ss", $username, $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = "Username or Email already exists.";
            } else {
                // Create User
                $hashed = hashPassword($password);
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, is_active) VALUES (?, ?, ?, ?, 1)");
                $stmt->bind_param("ssss", $username, $hashed, $email, $role);

                if ($stmt->execute()) {
                    $success = "User created successfully.";
                    logSecurityEvent('USER_CREATED_BY_ADMIN', "New User: $username by Admin ID: " . $_SESSION['user_id']);
                    // Re-check count to disable button immediately if needed (though page reload handles it)
                    $addUserDisabled = true;
                } else {
                    $error = "Failed to create user.";
                }
            }
        }
    }
}

// Pagination Logic
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Count Total Users
$count_res = $conn->query("SELECT COUNT(*) as total FROM users");
$total_records = $count_res->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get paginated users from database
$stmt = $conn->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

include "index_layout.php";

// Merge GET messages into local variables for display
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
?>


<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if (isset($error) && $error): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?= escapeOutput($error) ?>',
            confirmButtonColor: '#00ACB1'
        });
    </script>
<?php endif; ?>

<?php if (isset($success) && $success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?= escapeOutput($success) ?>',
            confirmButtonColor: '#00ACB1'
        }).then(() => {
            window.location.href = "users.php";
        });
    </script>
<?php endif; ?>


<style>
    /* User Management Specific Styles */
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-badge.active {
        background: #d4edda;
        color: #155724;
    }

    .status-badge.inactive {
        background: #f8d7da;
        color: #721c24;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
    }

    .status-dot.active {
        background: #28a745;
    }

    .status-dot.inactive {
        background: #dc3545;
    }

    .role-badge {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .role-badge.admin {
        background: #e3f2fd;
        color: #1565c0;
    }

    .role-badge.medical_staff {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .role-badge.nurse {
        background: #fce4ec;
        color: #c2185b;
    }

    .role-badge.doctor {
        background: #e1f5f e;
        color: #0277bd;
    }
</style>

<section class="controls">
    <select class="filter-select" id="statusFilter" onchange="searchUsers()" autocomplete="off"
        aria-label="Filter Status">
        <option value="all">Show All</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select>

    <?php if (!$addUserDisabled): ?>
        <button onclick="openAddUserModal()"
            style="padding: 10px 20px; background: #00ACB1; color: white; border: none; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 600; margin-left: auto;">
            <i class="fa-solid fa-plus"></i> Add User
        </button>
    <?php else: ?>
        <button disabled
            style="padding: 10px 20px; background: #ccc; color: #666; border: none; border-radius: 8px; cursor: not-allowed; display: flex; align-items: center; gap: 8px; font-weight: 600; margin-left: auto;"
            title="User limit reached (Max 1 user)">
            <i class="fa-solid fa-ban"></i> Max Users Reached
        </button>
    <?php endif; ?>


    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search users by name or email..." onkeyup="searchUsers()"
            autocomplete="off" aria-label="Search Users">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
    </div>
</section>

<div class="table-container">
    <table id="usersTable">
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Created Date</th>
                <th>Status</th>
                <th style="text-align: center;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($user = $result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fa-solid fa-circle-user" style="font-size: 24px; color: #00ACB1;"></i>
                            <strong><?= escapeOutput($user['username']) ?></strong>
                        </div>
                    </td>
                    <td><?= escapeOutput($user['email'] ?? 'N/A') ?></td>
                    <td>
                        <?php
                        // Get raw role and normalize
                        $rawRole = $user['role'] ?? '';
                        $normalized = strtolower(trim($rawRole));

                        // Define allowed roles and mappings
                        $roleLabels = [
                            'admin' => 'Admin',
                            'medical_staff' => 'Medical Staff',
                            'nurse' => 'Nurse',
                            'doctor' => 'Doctor'
                        ];

                        // Determine display role
                        if (array_key_exists($normalized, $roleLabels)) {
                            $displayRole = $normalized;
                        } elseif ($normalized === 'staff' || $normalized === 'viewer') {
                            $displayRole = 'medical_staff';
                        } else {
                            $displayRole = 'medical_staff'; // Default fallback
                        }

                        $labelText = $roleLabels[$displayRole];
                        ?>
                        <span class="role-badge <?= escapeOutput($displayRole) ?>">
                            <?= escapeOutput($labelText) ?>
                        </span>
                    </td>
                    <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                    <td>
                        <span class="status-badge <?= $user['is_active'] ? 'active' : 'inactive' ?>">
                            <span class="status-dot <?= $user['is_active'] ? 'active' : 'inactive' ?>"></span>
                            <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td style="text-align: center;">
                        <div class="action-dropdown">
                            <button class="action-btn" onclick="toggleActionMenu(this)">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <div class="action-menu">
                                <?php if (!$user['is_active']): ?>
                                    <button onclick="activateUser(<?= $user['id'] ?>)">
                                        <i class="fa-solid fa-check"></i> Approve
                                    </button>
                                    <button class="btnreject" onclick="rejectNewUser(<?= $user['id'] ?>)">
                                        <i class="fa-solid fa-ban"></i> Reject
                                    </button>
                                <?php else: ?>
                                    <button onclick="deactivateUser(<?= $user['id'] ?>)">
                                        <i class="fa-solid fa-ban"></i> Reject
                                    </button>
                                    <button onclick="editUser(<?= $user['id'] ?>)">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Pagination Controls -->
<?php if ($total_pages > 1): ?>
    <div class="pagination pagination-container" id="paginationContainer"
        style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 15px; margin-bottom: 20px; flex-wrap: wrap;">
        <!-- Previous Button -->
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>" class="btn"
                style="background: #00ACB1; color: white; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; padding: 0; font-size: 12px; text-decoration: none; border-radius: 4px;">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
        <?php else: ?>
            <button class="btn"
                style="background: #ccc; cursor: not-allowed; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; padding: 0; font-size: 12px; border: none; border-radius: 4px;">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
        <?php endif; ?>

        <!-- Page Info -->
        <span style="font-weight: bold; font-size: 13px; color: #555;">
            Page <?= $page ?> of <?= $total_pages ?>
        </span>

        <!-- Next Button -->
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>" class="btn"
                style="background: #00ACB1; color: white; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; padding: 0; font-size: 12px; text-decoration: none; border-radius: 4px;">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
        <?php else: ?>
            <button class="btn"
                style="background: #ccc; cursor: not-allowed; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; padding: 0; font-size: 12px; border: none; border-radius: 4px;">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
    // Search functionality
    function searchUsers() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toUpperCase();
        const table = document.getElementById('usersTable');
        const tr = table.getElementsByTagName('tr');

        for (let i = 1; i < tr.length; i++) {
            const tdUsername = tr[i].getElementsByTagName('td')[0];
            const tdEmail = tr[i].getElementsByTagName('td')[1];

            if (tdUsername || tdEmail) {
                const usernameValue = tdUsername.textContent || tdUsername.innerText;
                const emailValue = tdEmail.textContent || tdEmail.innerText;

                if (usernameValue.toUpperCase().indexOf(filter) > -1 ||
                    emailValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }

    // Toggle action menu
    function toggleActionMenu(btn) {
        document.querySelectorAll('.action-menu').forEach(menu => {
            if (menu !== btn.nextElementSibling) menu.classList.remove('show');
        });
        btn.nextElementSibling.classList.toggle('show');
    }

    // Close menu when clicking outside
    window.onclick = function (event) {
        if (!event.target.closest('.action-dropdown')) {
            document.querySelectorAll('.action-menu').forEach(menu => menu.classList.remove('show'));
        }
        // Close modal
        if (event.target == document.getElementById('addUserModal')) {
            closeAddUserModal();
        }
    }

    // Combined Search and Filter Function
    function searchUsers() {
        var input = document.getElementById("searchInput");
        var filter = input ? input.value.toUpperCase() : "";
        var statusFilter = document.getElementById("statusFilter").value;
        var table = document.getElementById("usersTable");
        var tr = table.getElementsByTagName("tr");

        for (var i = 1; i < tr.length; i++) { // Start loop from 1 to skip header row
            var tdName = tr[i].getElementsByTagName("td")[0];
            var tdEmail = tr[i].getElementsByTagName("td")[1];
            var tdStatus = tr[i].getElementsByTagName("td")[4]; // Status column index

            if (tdName && tdStatus) {
                var txtName = tdName.textContent || tdName.innerText;
                var txtEmail = tdEmail.textContent || tdEmail.innerText;
                var txtStatus = tdStatus.textContent || tdStatus.innerText;

                // Check Text Search
                var searchMatch = (txtName.toUpperCase().indexOf(filter) > -1) || (txtEmail.toUpperCase().indexOf(filter) > -1);

                // Check Status Filter
                var statusMatch = false;
                if (statusFilter === 'all') {
                    statusMatch = true;
                } else if (statusFilter === 'active' && txtStatus.trim().toLowerCase() === 'active') {
                    statusMatch = true;
                } else if (statusFilter === 'inactive' && txtStatus.trim().toLowerCase() === 'inactive') {
                    statusMatch = true;
                }

                // Show row only if both match
                if (searchMatch && statusMatch) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }

    // User actions
    function activateUser(userId) {
        Swal.fire({
            title: 'Approve User?',
            text: "Are you sure you want to approve this user?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, Approve!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `user_actions.php?action=activate&id=${userId}`;
            }
        });
    }

    function deactivateUser(userId) {
        Swal.fire({
            title: 'Reject User?',
            text: "Are you sure you want to reject this user?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Reject!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `user_actions.php?action=deactivate&id=${userId}`;
            }
        });
    }

    function editUser(userId) {
        window.location.href = `edit_user.php?id=${userId}`;
    }

    function deleteUser(userId) {
        Swal.fire({
            title: 'Delete User?',
            text: "Are you sure you want to delete this user? This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Delete!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `user_actions.php?action=delete&id=${userId}`;
            }
        });
    }

    function rejectNewUser(userId) {
        Swal.fire({
            title: 'Reject User Application?',
            text: "This will remove the user application permanently. Are you sure?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Reject'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `user_actions.php?action=delete&id=${userId}`;
            }
        });
    }

    // Add User Modal Functions
    function openAddUserModal() {
        document.getElementById('addUserModal').style.display = 'flex';
    }

    function closeAddUserModal() {
        document.getElementById('addUserModal').style.display = 'none';
    }

    function toggleModalPassword(inputId, icon) {
        const input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>

<!-- Add User Modal -->
<div id="addUserModal" class="modal-overlay"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="modal-content"
        style="background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; position: relative; max-height: 90vh; overflow-y: auto;">
        <span class="close-modal" onclick="closeAddUserModal()"
            style="position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: #666;">&times;</span>

        <h2 style="color: #00ACB1; margin-bottom: 20px; font-family: 'Cinzel', serif;">Add New User</h2>

        <form method="POST" action="">
            <input type="hidden" name="add_user" value="1">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

            <div style="margin-bottom: 15px;">
                <label for="add_username"
                    style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Username</label>
                <input type="text" name="username" id="add_username" required autocomplete="username"
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>

            <div style="margin-bottom: 15px;">
                <label for="add_email"
                    style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Email</label>
                <input type="email" name="email" id="add_email" required autocomplete="email"
                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>

            <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                <div style="flex: 1;">
                    <label for="add_role"
                        style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Role</label>
                    <select name="role" id="add_role"
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;"
                        autocomplete="off">
                        <option value="admin">Admin</option>
                        <option value="medical_staff">Medical Staff</option>
                        <option value="nurse">Nurse</option>
                        <option value="doctor">Doctor</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 15px;">
                <label for="modalPassword"
                    style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Password</label>
                <div style="position: relative;">
                    <input type="password" name="password" id="modalPassword" required autocomplete="new-password"
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    <i class="fa-solid fa-eye" onclick="toggleModalPassword('modalPassword', this)"
                        style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999;"></i>
                </div>
            </div>

            <div style="margin-bottom: 25px;">
                <label for="modalConfirmPassword"
                    style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Confirm
                    Password</label>
                <div style="position: relative;">
                    <input type="password" name="confirm_password" id="modalConfirmPassword" required
                        autocomplete="new-password"
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    <i class="fa-solid fa-eye" onclick="toggleModalPassword('modalConfirmPassword', this)"
                        style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999;"></i>
                </div>
            </div>

            <div style="text-align: right;">
                <button type="button" onclick="closeAddUserModal()"
                    style="padding: 10px 20px; background: #eee; border: none; border-radius: 6px; cursor: pointer; margin-right: 10px;">Cancel</button>
                <button type="submit"
                    style="padding: 10px 25px; background: #00ACB1; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">Create
                    User</button>
            </div>
        </form>
    </div>
</div>


</body>

</html>