<?php
require_once "../config/db.php";
requireLogin();

// Pagination & Search Logic
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 5;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base Query Conditions
$where_clauses = ["is_archived = 0"];
$params = [];
$types = "";

// Add Search Condition
if (!empty($search)) {
    $where_search = "(name LIKE ? OR position LIKE ? OR designation LIKE ? OR school_district_division LIKE ? OR entry_date LIKE ?)";
    $where_clauses[] = $where_search;
    $searchTerm = "%$search%";

    for ($i = 0; $i < 5; $i++) {
        $params[] = $searchTerm;
    }
    $types .= str_repeat("s", 5);
}

// Sorting Logic
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';
if (!in_array($order, ['ASC', 'DESC']))
    $order = 'ASC';

$allowed_sort = ['name', 'employee_no', 'birth_date', 'gender', 'civil_status', 'first_year_in_service', 'school_district_division', 'position', 'designation'];
if (!in_array($sort, $allowed_sort))
    $sort = 'name';

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Count Total Records
$count_query = "SELECT COUNT(*) as total FROM employees $where_sql";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// Paginated Query with Sorting
$sql = "SELECT * FROM employees $where_sql ORDER BY $sort $order LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

function getSortIcon($column, $currentSort, $currentOrder)
{
    if ($currentSort == $column) {
        return ($currentOrder == 'ASC') ? 'fa-sort-up active-sort' : 'fa-sort-down active-sort';
    }
    return 'fa-sort';
}

$activePage = 'employees.php';
include "index_layout.php";

// Helper functions for dynamic calculations
function calculateAge($birthDate)
{
    if (empty($birthDate))
        return '-';
    $birthDate = new DateTime($birthDate);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

function calculateServiceYears($firstYear)
{
    if (empty($firstYear))
        return '-';
    $currentYear = date("Y");
    return $currentYear - $firstYear;
}
?>

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

<section class="controls">
    <form method="GET" action="employees.php" class="search-box" style="display:flex; align-items:center; width: 100%;">
        <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>"
            placeholder="Search Name, Position, Division..." aria-label="Search Employees" autocomplete="off"
            style="border:none; outline:none; width: 100%; padding: 10px; background: transparent;">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
    </form>

    <button class="btn" id="toggleTableBtn" onclick="toggleEmployeeTable()"
        style="background: #00ACB1; white-space: nowrap;">
        <i class="fa-solid fa-eye" id="toggleIcon"></i> Show Employees
    </button>

    <a href="census.php" class="btn"
        style="background: #795548; color: white; text-decoration: none; display: flex; justify-content: center; align-items: center; gap: 8px; white-space: nowrap; text-transform: uppercase; font-weight: bold;">
        <i class="fa-solid fa-clipboard-list"></i> CENSUS REPORT
    </a>

    <div class="dropdown" style="margin-left: auto;">
        <button class="btn red dropdown-btn" style="white-space: nowrap;">
            DOWNLOAD <i class="fa-solid fa-caret-down"></i>
        </button>
        <div class="dropdown-content">
            <a href="export_pdf.php?type=employee"><i class="fa-solid fa-file-pdf"></i> DOWNLOAD PDF</a>
            <a href="export_xlsx.php?type=employee"><i class="fa-solid fa-file-excel"></i> DOWNLOAD XLSX</a>
        </div>
    </div>

    <a href="generate_qr_employee.php" class="btn"
        style="background: #607d8b; color: white; text-decoration: none; display: flex; justify-content: center; align-items: center; gap: 8px; white-space: nowrap;">
        <i class="fa-solid fa-qrcode"></i> QR CODE
    </a>

    <button class="btn green" onclick="openAddEmployeeModal()" style="white-space: nowrap;">Add Employee</button>
</section>

<div class="table-container" style="display: <?= (!empty($search) || isset($_GET['page'])) ? 'block' : 'none' ?>;">
    <table style="font-size: 11.5px;">
        <thead>
            <tr>
                <th onclick="sortTable('employee_no')">Employee No. <i
                        class="fa-solid <?= getSortIcon('employee_no', $sort, $order) ?> th-filler"></i></th>
                <th onclick="sortTable('name')">Name <i
                        class="fa-solid <?= getSortIcon('name', $sort, $order) ?> th-filler"></i></th>
                <th onclick="sortTable('birth_date')">Date of Birth <i
                        class="fa-solid <?= getSortIcon('birth_date', $sort, $order) ?> th-filler"></i></th>
                <th onclick="sortTable('birth_date')">Age <i
                        class="fa-solid <?= getSortIcon('birth_date', $sort, $order) ?> th-filler"></i></th>
                <th onclick="sortTable('gender')">Gender <i
                        class="fa-solid <?= getSortIcon('gender', $sort, $order) ?> th-filler"></i></th>
                <th onclick="sortTable('civil_status')">Civil Status <i
                        class="fa-solid <?= getSortIcon('civil_status', $sort, $order) ?> th-filler"></i></th>
                <th onclick="sortTable('first_year_in_service')">Years in Service <i
                        class="fa-solid <?= getSortIcon('first_year_in_service', $sort, $order) ?> th-filler"></i></th>
                <th onclick="sortTable('school_district_division')">School/District/Division <i
                        class="fa-solid <?= getSortIcon('school_district_division', $sort, $order) ?> th-filler"></i>
                </th>
                <th onclick="sortTable('position')">Position <i
                        class="fa-solid <?= getSortIcon('position', $sort, $order) ?> th-filler"></i></th>
                <th onclick="sortTable('designation')">Designation <i
                        class="fa-solid <?= getSortIcon('designation', $sort, $order) ?> th-filler"></i></th>
                <th onclick="sortTable('first_year_in_service')">First Year in Service <i
                        class="fa-solid <?= getSortIcon('first_year_in_service', $sort, $order) ?> th-filler"></i></th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="employeeTableBody">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($row['employee_no'] ?: '-') ?>
                        </td>
                        <td style="font-weight: bold;"><?= htmlspecialchars($row['name']) ?></td>
                        <td>
                            <?= $row['birth_date'] ? date('m/d/Y', strtotime($row['birth_date'])) : '-' ?>
                        </td>
                        <td><?= calculateAge($row['birth_date']) ?></td>
                        <td><?= htmlspecialchars($row['gender']) ?></td>
                        <td><?= htmlspecialchars($row['civil_status']) ?></td>
                        <td><?= calculateServiceYears($row['first_year_in_service']) ?></td>
                        <td><?= htmlspecialchars($row['school_district_division']) ?></td>
                        <td><?= htmlspecialchars($row['position']) ?></td>
                        <td><?= htmlspecialchars($row['designation']) ?></td>
                        <td><?= htmlspecialchars($row['first_year_in_service'] ?: '-') ?></td>
                        <td class="actions">
                            <button class="view" data-tooltip="View Record"
                                onclick="viewEmployeeRecords('<?= $row['id'] ?>', '<?= addslashes($row['name']) ?>')">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <button class="edit" data-tooltip="Edit Employee" onclick="editEmployee('<?= $row['id'] ?>')">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button class="archive" data-tooltip="Archive"
                                onclick="archiveEmployee('<?= $row['id'] ?>', '<?= addslashes($row['name']) ?>')">
                                <i class="fa-solid fa-box-archive"></i>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="12" style="text-align:center; padding: 40px; color: #888;">
                        <i class="fa-solid fa-users" style="font-size: 48px; margin-bottom: 10px; opacity: 0.3;"></i><br>
                        No employee records found.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="pagination pagination-container" id="paginationContainer"
            style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 15px; margin-bottom: 20px;">
            <!-- Previous Button -->
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>"
                    class="btn"
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
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>"
                    class="btn"
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
</div>

<!-- View Employee Records Modal -->
<div id="viewEmployeeRecordsModal" class="modal-overlay">
    <div class="modal-card view-modal">
        <button class="close-btn" onclick="closeViewEmployeeRecordsModal()">&times;</button>
        <h2 id="modal-employee-name">Records of Employee</h2>

        <div class="record-card-container">
            <div class="record-card">
                <div class="card-content">
                    <span class="card-title">EMPLOYEE HEALTH CARD</span>
                </div>
                <div class="modal-card-actions">
                    <a href="#" id="btn-view-emp-card" class="btn-action view" title="View"><i
                            class="fa-solid fa-eye"></i> View</a>
                    <a href="#" id="btn-edit-emp-card" class="btn-action edit" title="Edit"><i
                            class="fa-solid fa-pen-to-square"></i> Edit</a>
                </div>
            </div>

            <div class="record-card">
                <div class="card-content">
                    <span class="card-title">TREATMENT RECORD</span>
                </div>
                <div class="modal-card-actions">
                    <a href="#" id="btn-view-emp-treatment" class="btn-action view" title="View"><i
                            class="fa-solid fa-eye"></i> View</a>
                    <a href="#" id="btn-edit-emp-treatment" class="btn-action edit" title="Edit"><i
                            class="fa-solid fa-pen-to-square"></i> Edit</a>
                </div>
            </div>

            <div class="record-card">
                <div class="card-content">
                    <span class="card-title">MEDICAL RECORDS (FILES)</span>
                </div>
                <div class="modal-card-actions">
                    <a href="#" id="btn-emp-medical-records" class="btn-action view"
                        style="width: 100%; justify-content: center;" title="Manage Files">
                        <i class="fa-solid fa-file-medical"></i> Manage Files
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "modal_add_employee.php"; ?>
<?php include "modal_edit_employee.php"; ?>
<script src="assets/js/search_employees.js?v=<?= time() ?>"></script>
<script src="assets/js/ajax_forms.js?v=<?= time() ?>"></script>
<link rel="stylesheet" href="assets/css/pagination.css?v=<?= time() ?>">

<script>
    function openAddEmployeeModal() {
        document.getElementById('addEmployeeModal').style.display = 'flex';
    }

    function closeAddEmployeeModal() {
        document.getElementById('addEmployeeModal').style.display = 'none';
    }

    function openEditEmployeeModal() {
        document.getElementById('editEmployeeModal').style.display = 'flex';
    }

    function closeEditEmployeeModal() {
        document.getElementById('editEmployeeModal').style.display = 'none';
    }

    function viewEmployeeRecords(id, name) {
        document.getElementById('modal-employee-name').innerText = "Records of " + name;

        // Set URLs
        document.getElementById('btn-view-emp-card').href = `view_card.php?view_id=${id}&type=employee`;
        document.getElementById('btn-edit-emp-card').href = `edit_card.php?id=${id}&type=employee`;
        document.getElementById('btn-view-emp-treatment').href = `view_treatment.php?view_id=${id}&type=employee`;
        document.getElementById('btn-edit-emp-treatment').href = `edit_treatment.php?id=${id}&type=employee`;
        document.getElementById('btn-emp-medical-records').href = `medical_records.php?id=${id}&type=employee`;

        document.getElementById('viewEmployeeRecordsModal').style.display = 'flex';
    }

    function closeViewEmployeeRecordsModal() {
        document.getElementById('viewEmployeeRecordsModal').style.display = 'none';
    }

    function calculateRealTimeAge(birthDate, displayId) {
        if (!birthDate) return;
        const today = new Date();
        const birth = new Date(birthDate);
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        document.getElementById(displayId).value = age + " years old";
    }

    function editEmployee(id) {
        fetch(`get_employee.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const emp = data.data;
                    document.getElementById('edit_id').value = emp.id;
                    document.getElementById('edit_employee_no').value = emp.employee_no || '';
                    document.getElementById('edit_name').value = emp.name;
                    document.getElementById('edit_birth_date').value = emp.birth_date;
                    document.getElementById('edit_gender').value = emp.gender;
                    document.getElementById('edit_civil_status').value = emp.civil_status;
                    document.getElementById('edit_school_district_division').value = emp.school_district_division;
                    document.getElementById('edit_position').value = emp.position;
                    document.getElementById('edit_designation').value = emp.designation;
                    document.getElementById('edit_first_year_in_service').value = emp.first_year_in_service;

                    // Update Age Display
                    calculateRealTimeAge(emp.birth_date, 'edit_age_display');

                    openEditEmployeeModal();
                } else {
                    Swal.fire('Error', 'Could not fetch employee details.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'An error occurred while fetching details.', 'error');
            });
    }

    function archiveEmployee(id, name) {
        Swal.fire({
            title: 'Archive Record?',
            text: `Are you sure you want to archive ${name}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ff5c5c',
            cancelButtonColor: '#666',
            confirmButtonText: 'Yes, Archive it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `archive_employee.php?id=${id}`;
            }
        });
    }

    function toggleEmployeeTable() {
        const table = document.querySelector('.table-container');
        const btn = document.getElementById('toggleTableBtn');
        const icon = document.getElementById('toggleIcon');

        if (table.style.display === 'none') {
            table.style.display = 'block';
            btn.style.background = '#666';
            if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
            btn.innerHTML = '<i class="fa-solid fa-eye-slash" id="toggleIcon"></i> Hide Employees';
            localStorage.setItem('employeeTableVisible', 'true');
        } else {
            table.style.display = 'none';
            btn.style.background = '#00ACB1';
            if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
            btn.innerHTML = '<i class="fa-solid fa-eye" id="toggleIcon"></i> Show Employees';
            localStorage.setItem('employeeTableVisible', 'false');
        }
    }



    // Auto-open modal if view_id is present and restore table visibility
    window.addEventListener('DOMContentLoaded', () => {
        const table = document.querySelector('.table-container');
        const btn = document.getElementById('toggleTableBtn');
        const savedState = localStorage.getItem('employeeTableVisible');
        const hasSearch = window.location.search.includes('search=') || window.location.search.includes('page=');

        if (savedState === 'true' || hasSearch) {
            table.style.display = 'block';
            btn.style.background = '#666';
            btn.innerHTML = '<i class="fa-solid fa-eye-slash" id="toggleIcon"></i> Hide Employees';
        } else {
            table.style.display = 'none';
            btn.style.background = '#00ACB1';
            btn.innerHTML = '<i class="fa-solid fa-eye" id="toggleIcon"></i> Show Employees';
        }

        const urlParams = new URLSearchParams(window.location.search);
        const viewId = urlParams.get('view_id');
        if (viewId) {
            // Find the name from the table if possible, or fetch it
            fetch(`get_employee.php?id=${viewId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        viewEmployeeRecords(viewId, data.data.name);
                    }
                });
        }
    });
</script>

</body>

</html>