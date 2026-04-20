<?php
require_once "../config/db.php";
requireAdmin();

$type = isset($_GET['type']) ? trim($_GET['type']) : 'student';

// Determine table based on type
$table = 'students'; // default
$backUrl = 'student.php';

if ($type == 'employee') {
    $table = 'employees';
    $backUrl = 'employees.php';
} elseif ($type == 'inventory') {
    $table = 'inventory_items';
    $backUrl = 'inventory.php';
} elseif ($type == 'others') {
    $table = 'others';
    $backUrl = 'others.php';
}

// Get archived records
$result = $conn->query("SELECT * FROM $table WHERE is_archived = 1 ORDER BY archived_at DESC");
include "index_layout.php";
?>

<style>
    :root {
        --tab-text: #666;
        --tab-border: #eee;
        --bg-modal: #fff;
        --text-modal: #333;
        --text-secondary-modal: #666;
        --bg-tooltip: #333;
        --text-tooltip: #fff;
    }

    body.dark-mode {
        --tab-text: #aaa;
        --tab-border: #333;
        --bg-modal: #272727;
        --text-modal: #e0e0e0;
        --text-secondary-modal: #aaa;
        --bg-tooltip: #555;
        --text-tooltip: #e0e0e0;
    }

    .tabs-container {
        margin-bottom: 25px;
        border-bottom: 2px solid var(--tab-border);
        display: flex;
        gap: 20px;
    }

    .tab-link {
        padding: 10px 20px;
        text-decoration: none;
        color: var(--tab-text);
        border-bottom: 3px solid transparent;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .tab-link:hover {
        color: #00ACB1;
    }

    .tab-link.active {
        color: #00ACB1;
        border-bottom: 3px solid #00ACB1;
    }

    /* Modal Styles */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
    }

    /* Back Button Premium Style */
    .btn-back-outline {
        background: #00ACB1;
        /* Solid Default */
        border: 2px solid #00ACB1;
        color: white;
        border-radius: 50px;
        padding: 10px 24px;
        font-weight: 700;
        font-size: 14px;
        letter-spacing: 0.5px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        /* Center content */
        gap: 10px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        box-shadow: 0 4px 10px rgba(0, 172, 177, 0.2);
    }

    .btn-back-outline:hover {
        background: transparent;
        /* Outline on Hover */
        color: #00ACB1;
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(0, 172, 177, 0.3);
    }

    /* DARK MODE OVERRIDES */
    body.dark-mode .btn-back-outline {
        color: #ffffff !important;
        border-color: #00ACB1;
    }

    body.dark-mode .btn-back-outline:hover {
        color: #ffffff !important;
        border-color: #ffffff !important;
        background: rgba(255, 255, 255, 0.1);
        box-shadow: 0 0 15px rgba(255, 255, 255, 0.2);
    }

    .modal-card {
        background: var(--bg-modal);
        padding: 30px;
        border-radius: 12px;
        width: 90%;
        max-width: 400px;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    }

    .modal-card h2 {
        color: var(--text-modal) !important;
    }

    .modal-card p {
        color: var(--text-secondary-modal) !important;
    }

    .modal-card strong span {
        color: var(--text-modal);
    }

    .btn-secondary {
        background: #e0e0e0;
        color: #333;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
    }

    body.dark-mode .btn-secondary {
        background: #444;
        color: #e0e0e0;
    }

    .btn-secondary:hover {
        opacity: 0.9;
    }

    .btn-primary {
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
    }

    .btn-primary:hover {
        opacity: 0.9;
    }

    .modal-actions {
        display: flex;
        justify-content: center;
        gap: 15px;
        margin-top: 25px;
    }
</style>



<?php if (isset($_SESSION['success_message']) || isset($_SESSION['error_message'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            <?php if (isset($_SESSION['success_message'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: '<?= addslashes($_SESSION['success_message']) ?>',
                    confirmButtonColor: '#00ACB1',
                    timer: 3000,
                    timerProgressBar: true
                });
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?= addslashes($_SESSION['error_message']) ?>',
                    confirmButtonColor: '#d33'
                });
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
        });
    </script>
<?php endif; ?>

<!-- Action Tabs -->
<div class="tabs-container">
    <?php
    $safeType = isset($_GET['type']) ? trim($_GET['type']) : 'student';
    // Force sync type to ensure consistency
    $type = $safeType;
    ?>
    <a href="?type=student" class="tab-link <?= ($safeType == 'student' || empty($safeType)) ? 'active' : '' ?>"
        style="<?= ($safeType == 'student' || empty($safeType)) ? 'color: #00ACB1 !important; border-bottom: 3px solid #00ACB1 !important;' : 'color: #666 !important; border-bottom: 3px solid transparent !important;' ?>">
        <i class="fa-solid fa-graduation-cap"></i> STUDENTS
    </a>
    <a href="?type=employee" class="tab-link <?= ($safeType == 'employee') ? 'active' : '' ?>"
        style="<?= ($safeType == 'employee') ? 'color: #00ACB1 !important; border-bottom: 3px solid #00ACB1 !important;' : 'color: #666 !important; border-bottom: 3px solid transparent !important;' ?>">
        <i class="fa-solid fa-user-tie"></i> EMPLOYEES
    </a>
    <a href="?type=inventory" class="tab-link <?= ($safeType == 'inventory') ? 'active' : '' ?>"
        style="<?= ($safeType == 'inventory') ? 'color: #00ACB1 !important; border-bottom: 3px solid #00ACB1 !important;' : 'color: #666 !important; border-bottom: 3px solid transparent !important;' ?>">
        <i class="fa-solid fa-boxes-stacked"></i> INVENTORY
    </a>
    <a href="?type=others" class="tab-link <?= ($safeType == 'others') ? 'active' : '' ?>"
        style="<?= ($safeType == 'others') ? 'color: #00ACB1 !important; border-bottom: 3px solid #00ACB1 !important;' : 'color: #666 !important; border-bottom: 3px solid transparent !important;' ?>">
        <i class="fa-solid fa-clipboard-user"></i> OTHERS
    </a>
</div>

<section class="controls"
    style="display: flex; align-items: center; justify-content: space-between; flex-wrap: nowrap; gap: 20px; margin-bottom: 20px;">
    <?php
    $backLink = 'student.php';
    $backLabel = 'Back to Student Records';

    if ($safeType == 'employee') {
        $backLink = 'employees.php';
        $backLabel = 'Back to Employee Records';
    } elseif ($safeType == 'inventory') {
        $backLink = 'inventory.php';
        $backLabel = 'Back to Inventory';
    } elseif ($safeType == 'others') {
        $backLink = 'others.php';
        $backLabel = 'Back to Others Records';
    }
    ?>
    <!-- Left: Back Button -->
    <a href="<?= $backLink ?>" class="btn-back-outline">
        <i class="fa-solid fa-arrow-left"></i> <?= strtoupper($backLabel) ?>
    </a>

    <!-- Right Side Group: Search + Download -->
    <div style="display: flex; gap: 10px; align-items: center; flex: 1; justify-content: flex-end;">

        <?php if ($type == 'inventory'): ?>
            <select id="categoryFilter" onchange="searchArchive()"
                style="height: 38px; padding: 0 15px; border-radius: 20px; border: 1px solid #ddd; outline: none; margin-right: 5px;">
                <option value="">All Categories</option>
                <option value="Medicine">Medicine</option>
                <option value="Medical Supply">Medical Supply</option>
                <option value="Equipment">Equipment</option>
            </select>
        <?php endif; ?>
        <!-- Search Box -->
        <div class="search-box" style="margin: 0; height: 38px; width: 300px;">
            <input type="text" id="search" placeholder="Search for Archived <?= ucfirst($type) ?>s"
                onkeyup="searchArchive()" style="height: 100%;">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
        </div>


        <!-- Download Button -->
        <div class="dropdown" style="margin-left: 0;">
            <button class="btn red dropdown-btn" onclick="toggleDropdown(event)"
                style="height: 35px; white-space: nowrap; font-weight: 600; font-size: 13px; padding: 0 16px; letter-spacing: 0.5px;">
                DOWNLOAD <i class="fa-solid fa-caret-down"></i>
            </button>
            <div class="dropdown-content">
                <a href="export_pdf.php?type=<?= $type ?>&archived=1"><i class="fa-solid fa-file-pdf"></i> DOWNLOAD
                    PDF</a>
                <a href="export_xlsx.php?type=<?= $type ?>&archived=1"><i class="fa-solid fa-file-excel"></i> DOWNLOAD
                    XLSX</a>
            </div>
        </div>
    </div>
</section>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <?php if ($type == 'student'): ?>
                    <th>LRN Number</th>
                    <th>Curriculum</th>
                <?php elseif ($type == 'employee'): ?>
                    <th>Position</th>
                    <th>Designation</th>
                    <th>Age</th>
                <?php elseif ($type == 'inventory'): ?>
                    <th>Category</th>
                    <th>Initial Stock</th>
                    <th>Unit</th>
                    <th>Expiry Date</th>
                <?php elseif ($type == 'others'): ?>
                    <th>Age</th>
                    <th>SDO</th>
                <?php endif; ?>

                <?php if ($type == 'student' || $type == 'others'): ?>
                    <th>Address</th>
                <?php endif; ?>

                <?php if ($type != 'inventory'): ?>
                    <th>Gender</th>
                    <th>Birth Date</th>
                <?php endif; ?>

                <?php if ($type == 'student'): ?>
                    <th>Birthplace</th>
                    <th>Parent or Guardian</th>
                <?php elseif ($type == 'employee'): ?>
                    <th>Civil Status</th>
                <?php endif; ?>
                <th>Action</th>

            </tr>
        </thead>

        <tbody id="archiveData">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight: bold;"><?= htmlspecialchars($row['name']) ?></td>
                        <?php if ($type == 'student'): ?>
                            <td><?= htmlspecialchars($row['lrn'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['curriculum'] ?? '') ?></td>
                        <?php elseif ($type == 'employee'): ?>
                            <td><?= htmlspecialchars($row['position'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['designation'] ?? '-') ?></td>
                            <td>
                                <?php
                                if (isset($row['birth_date']) && $row['birth_date']) {
                                    $birthDate = new DateTime($row['birth_date']);
                                    $today = new DateTime('today');
                                    echo $birthDate->diff($today)->y;
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        <?php elseif ($type == 'inventory'): ?>
                            <td><?= htmlspecialchars($row['category'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($row['quantity'] ?? '0') ?></td>
                            <td><?= htmlspecialchars($row['unit'] ?? '-') ?></td>
                            <td><?= htmlspecialchars(isset($row['expiry_date']) && !empty($row['expiry_date']) ? date('M d, Y', strtotime($row['expiry_date'])) : '-') ?>
                            </td>
                        <?php elseif ($type == 'others'): ?>
                            <td>
                                <?php
                                if (isset($row['birth_date']) && $row['birth_date']) {
                                    $birthDate = new DateTime($row['birth_date']);
                                    $today = new DateTime('today');
                                    echo $birthDate->diff($today)->y;
                                } else {
                                    echo htmlspecialchars($row['age'] ?? '-');
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($row['sdo'] ?? '-') ?></td>
                        <?php endif; ?>

                        <?php if ($type == 'student' || $type == 'others'): ?>
                            <td><?= htmlspecialchars($row['address'] ?? '-') ?></td>
                        <?php endif; ?>

                        <?php if ($type != 'inventory'): ?>
                            <td><?= htmlspecialchars($row['gender'] ?? '-') ?></td>
                            <td><?= htmlspecialchars((isset($row['birth_date']) && !empty($row['birth_date'])) ? date('m/d/Y', strtotime($row['birth_date'])) : '-') ?>
                            </td>
                        <?php endif; ?>

                        <?php if ($type == 'student'): ?>
                            <td><?= htmlspecialchars($row['birthplace'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['guardian'] ?? '') ?></td>
                        <?php elseif ($type == 'employee'): ?>
                            <td><?= htmlspecialchars($row['civil_status'] ?? '-') ?></td>
                        <?php endif; ?>

                        <td class="archive-actions">
                            <button class="restore" data-tooltip="Restore Record"
                                onclick="restoreItem(<?= $row['id'] ?>, '<?= addslashes($row['name']) ?>')">
                                <i class="fa-solid fa-rotate-left"></i>
                                <span class="btn-label">Restore</span>
                            </button>

                            <button class="force-delete" data-tooltip="Force Delete"
                                onclick="confirmPermanentDelete(<?= $row['id'] ?>, '<?= addslashes($row['name']) ?>')">
                                <i class="fa-solid fa-trash-can"></i>
                                <span class="btn-label">Delete</span>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>

            <?php else: ?>
                <tr>
                    <td colspan="9">
                        <div class="empty-archive">
                            <i class="fa-solid fa-box-archive"></i>
                            <h3>No Archived Records</h3>
                            <p>There are no archived <?= htmlspecialchars($type) ?> records at this time.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    // Search functionality
    function searchArchive() {
        const input = document.getElementById('search');
        const filter = input.value.toUpperCase();
        const table = document.getElementById('archiveData');
        const tr = table.getElementsByTagName('tr');

        for (let i = 0; i < tr.length; i++) {
            let found = false;
            const td = tr[i].getElementsByTagName('td');

            for (let j = 0; j < td.length - 1; j++) {
                if (td[j]) {
                    const txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
            }

            // Category Filter Logic (Only for Inventory)
            const catSelect = document.getElementById('categoryFilter');
            if (catSelect && found) {
                const selectedCat = catSelect.value.toUpperCase();
                if (selectedCat !== "") {
                    // Category is in Column 0
                    const catTd = tr[i].getElementsByTagName('td')[1]; // Index 1 because Name is 0, Category is 1? Wait, check header.
                    // Header Order: Name (0), Category/LRN/Pos (1)
                    // Inventory: Name (0), Category (1). Correct.
                    if (catTd) {
                        const catValue = (catTd.textContent || catTd.innerText).toUpperCase();
                        if (catValue !== selectedCat) {
                            found = false;
                        }
                    }
                }
            }

            tr[i].style.display = found ? "" : "none";
        }
    }

    // Restore item with SweetAlert2
    function restoreItem(itemId, itemName) {
        Swal.fire({
            title: 'Restore Record?',
            html: `Are you sure you want to restore <strong>${itemName}</strong>?<br><span style="font-size: 13px; color: #888;">This record will be moved back to the active list.</span>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#00ACB1',
            cancelButtonColor: '#666',
            confirmButtonText: 'Yes, Restore'
        }).then((result) => {
            if (result.isConfirmed) {
                submitArchiveForm('restore', itemId);
            }
        });
    }


    // Delete permanently with SweetAlert2
    function confirmPermanentDelete(studentId, studentName) {
        Swal.fire({
            title: 'Permanent Delete',
            html: `Are you sure you want to <strong>PERMANENTLY DELETE</strong> the record of <strong>${studentName}</strong>?<br><br><span style="color: #e74c3c; font-weight: bold;">This action cannot be undone.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            cancelButtonColor: '#666',
            confirmButtonText: 'Delete Forever'
        }).then((result) => {
            if (result.isConfirmed) {
                submitArchiveForm('delete', studentId);
            }
        });
    }

    // Helper to submit the abstract form
    function submitArchiveForm(action, id) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'archive_actions.php';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;

        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.name = 'type';
        typeInput.value = '<?= $type ?>';

        form.appendChild(actionInput);
        form.appendChild(idInput);
        form.appendChild(typeInput);
        document.body.appendChild(form);
        form.submit();
    }


    // Dropdown toggle
    function toggleDropdown(event) {
        event.stopPropagation();
        const dropdown = event.target.closest('.dropdown');
        const content = dropdown.querySelector('.dropdown-content');
        content.style.display = content.style.display === 'block' ? 'none' : 'block';
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-content').forEach(content => {
                content.style.display = 'none';
            });
        }
    });
</script>

</body>

</html>