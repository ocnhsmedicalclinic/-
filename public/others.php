<?php
require_once "../config/db.php";
requireLogin();

// Handle Add Other Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_other') {
    $name = $_POST['name'] ?? '';
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $age = !empty($_POST['age']) ? (int) $_POST['age'] : null;
    $sdo = $_POST['sdo'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $address = $_POST['address'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if (!empty($name)) {
        $stmt_i = $conn->prepare("INSERT INTO others (name, birth_date, age, sdo, gender, address, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_i->bind_param("ssissss", $name, $birth_date, $age, $sdo, $gender, $address, $remarks);
        if ($stmt_i->execute()) {
            $_SESSION['success_message'] = "Added successfully!";
            header("Location: others.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to add record.";
        }
    }
}

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
    $where_search = "(name LIKE ? OR sdo LIKE ? OR address LIKE ?)";
    $where_clauses[] = $where_search;
    $searchTerm = "%$search%";

    for ($i = 0; $i < 3; $i++) {
        $params[] = $searchTerm;
    }
    $types .= str_repeat("s", 3);
}

// Sorting Logic
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';
if (!in_array($order, ['ASC', 'DESC']))
    $order = 'ASC';

$allowed_sort = ['name', 'birth_date', 'sdo', 'gender', 'address', 'remarks'];
if (!in_array($sort, $allowed_sort))
    $sort = 'name';

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Count Total Records
$count_query = "SELECT COUNT(*) as total FROM others $where_sql";
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
$sql = "SELECT * FROM others $where_sql ORDER BY $sort $order LIMIT $limit OFFSET $offset";
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

$activePage = 'others.php';
include "index_layout.php";

function calculateAge($birthDate, $age)
{
    if (!empty($birthDate)) {
        $birthDate = new DateTime($birthDate);
        $today = new DateTime('today');
        return $birthDate->diff($today)->y;
    }
    return $age ?: '-';
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
    <form method="GET" action="others.php" class="search-box" style="display:flex; align-items:center; width: 100%;">
        <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>"
            placeholder="Search Name, SDO, Address..." autocomplete="off"
            style="border:none; outline:none; width: 100%; padding: 10px; background: transparent;">
        <i class="fa-solid fa-magnifying-glass search-icon"></i>
    </form>

    <button class="btn" id="toggleTableBtn" onclick="toggleOtherTable()"
        style="background: #00ACB1; white-space: nowrap;">
        <i class="fa-solid fa-eye" id="toggleIcon"></i> Show Records
    </button>

    <div class="dropdown" style="margin-left: auto;">
        <button class="btn red dropdown-btn" style="white-space: nowrap;">
            DOWNLOAD <i class="fa-solid fa-caret-down"></i>
        </button>
        <div class="dropdown-content">
            <a href="export_pdf.php?type=others"><i class="fa-solid fa-file-pdf"></i> DOWNLOAD PDF</a>
            <a href="export_xlsx.php?type=others"><i class="fa-solid fa-file-excel"></i> DOWNLOAD XLSX</a>
        </div>
    </div>

    <a href="census.php" class="btn"
        style="background: #795548; color: white; text-decoration: none; display: flex; justify-content: center; align-items: center; gap: 8px; white-space: nowrap; text-transform: uppercase; font-weight: bold;">
        <i class="fa-solid fa-clipboard-list"></i> CENSUS REPORT
    </a>

    <button class="btn green" onclick="openAddOtherModal()" style="white-space: nowrap;">Add Record</button>
</section>

<div class="table-container" style="display: <?= (!empty($search) || isset($_GET['page'])) ? 'block' : 'none' ?>;">
    <table style="font-size: 11.5px;">
        <thead>
            <tr>
                <th onclick="sortTable('name')">NAME <i
                        class="fa-solid <?= getSortIcon('name', $sort, $order) ?> th-filler"></i></th>
                <th onclick="sortTable('birth_date')">AGE <i
                        class="fa-solid <?= getSortIcon('birth_date', $sort, $order) ?> th-filler"></i></th>
                <th onclick="sortTable('sdo')">SDO <i
                        class="fa-solid <?= getSortIcon('sdo', $sort, $order) ?> th-filler"></i></th>
                <th onclick="sortTable('gender')">GENDER <i
                        class="fa-solid <?= getSortIcon('gender', $sort, $order) ?> th-filler"></i></th>
                <th onclick="sortTable('address')">ADDRESS <i
                        class="fa-solid <?= getSortIcon('address', $sort, $order) ?> th-filler"></i></th>
                <th onclick="sortTable('remarks')">REMARKS <i
                        class="fa-solid <?= getSortIcon('remarks', $sort, $order) ?> th-filler"></i></th>
                <th>ACTION</th>
            </tr>
        </thead>
        <tbody id="otherTableBody">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight: bold; text-transform: uppercase;">
                            <?= htmlspecialchars($row['name']) ?>
                        </td>
                        <td>
                            <?= calculateAge($row['birth_date'], $row['age']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['sdo'] ?: '-') ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['gender']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['address']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['remarks'] ?? '-') ?>
                        </td>
                        <td class="actions">
                            <button class="view" data-tooltip="View Record"
                                onclick="viewOtherRecords('<?= $row['id'] ?>', '<?= addslashes($row['name']) ?>')">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <button class="edit" data-tooltip="Edit Record" onclick="editOther('<?= $row['id'] ?>')">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button class="archive" data-tooltip="Archive"
                                onclick="archiveOther('<?= $row['id'] ?>', '<?= addslashes($row['name']) ?>')">
                                <i class="fa-solid fa-box-archive"></i>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding: 40px; color: #888;">
                        <i class="fa-solid fa-users" style="font-size: 48px; margin-bottom: 10px; opacity: 0.3;"></i><br>
                        No records found.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="pagination pagination-container" id="paginationContainer"
            style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 15px; margin-bottom: 20px;">
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

            <span style="font-weight: bold; font-size: 13px; color: #555;">
                Page <?= $page ?> of <?= $total_pages ?>
            </span>

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

<!-- View Other Records Modal -->
<div id="viewOtherRecordsModal" class="modal-overlay">
    <div class="modal-card view-modal">
        <button class="close-btn" onclick="closeViewOtherRecordsModal()">&times;</button>
        <h2 id="modal-other-name">Records of Person</h2>

        <div class="record-card-container">
            <div class="record-card">
                <div class="card-content">
                    <span class="card-title">TREATMENT RECORD</span>
                </div>
                <div class="modal-card-actions">
                    <a href="#" id="btn-view-other-treatment" class="btn-action view" title="View"><i
                            class="fa-solid fa-eye"></i> View</a>
                    <a href="#" id="btn-edit-other-treatment" class="btn-action edit" title="Edit"><i
                            class="fa-solid fa-pen-to-square"></i> Edit</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "modal_add_other.php"; ?>
<?php include "modal_edit_other.php"; ?>

<link rel="stylesheet" href="assets/css/pagination.css?v=<?= time() ?>">

<script>
    function openAddOtherModal() {
        document.getElementById('addOtherModal').style.display = 'flex';
    }

    function closeAddOtherModal() {
        document.getElementById('addOtherModal').style.display = 'none';
    }

    function openEditOtherModal() {
        document.getElementById('editOtherModal').style.display = 'flex';
    }

    function closeEditOtherModal() {
        document.getElementById('editOtherModal').style.display = 'none';
    }

    function viewOtherRecords(id, name) {
        document.getElementById('modal-other-name').innerText = "Records of " + name;

        // Set URLs
        document.getElementById('btn-view-other-treatment').href = `view_treatment.php?view_id=${id}&type=others`;
        document.getElementById('btn-edit-other-treatment').href = `edit_treatment.php?id=${id}&type=others`;

        document.getElementById('viewOtherRecordsModal').style.display = 'flex';
    }

    function closeViewOtherRecordsModal() {
        document.getElementById('viewOtherRecordsModal').style.display = 'none';
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
        document.getElementById(displayId).value = age;
    }

    function editOther(id) {
        fetch(`get_other.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const person = data.data;
                    document.getElementById('edit_id').value = person.id;
                    document.getElementById('edit_name').value = person.name;
                    document.getElementById('edit_birth_date').value = person.birth_date || '';
                    document.getElementById('edit_age_display').value = person.age || '';
                    document.getElementById('edit_gender').value = person.gender || '';
                    document.getElementById('edit_sdo').value = person.sdo || '';
                    document.getElementById('edit_address').value = person.address || '';
                    document.getElementById('edit_remarks').value = person.remarks || '';

                    if (person.birth_date) {
                        calculateRealTimeAge(person.birth_date, 'edit_age_display');
                    }

                    openEditOtherModal();
                } else {
                    Swal.fire('Error', 'Could not fetch record details.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'An error occurred while fetching details.', 'error');
            });
    }

    function archiveOther(id, name) {
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
                window.location.href = `archive_other.php?id=${id}`;
            }
        });
    }

    function toggleOtherTable() {
        const table = document.querySelector('.table-container');
        const btn = document.getElementById('toggleTableBtn');
        const icon = document.getElementById('toggleIcon');

        if (table.style.display === 'none') {
            table.style.display = 'block';
            btn.style.background = '#666';
            if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
            btn.innerHTML = '<i class="fa-solid fa-eye-slash" id="toggleIcon"></i> Hide Records';
            localStorage.setItem('othersTableVisible', 'true');
        } else {
            table.style.display = 'none';
            btn.style.background = '#00ACB1';
            if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
            btn.innerHTML = '<i class="fa-solid fa-eye" id="toggleIcon"></i> Show Records';
            localStorage.setItem('othersTableVisible', 'false');
        }
    }



    window.addEventListener('DOMContentLoaded', () => {
        const table = document.querySelector('.table-container');
        const btn = document.getElementById('toggleTableBtn');
        const savedState = localStorage.getItem('othersTableVisible');
        const hasSearch = window.location.search.includes('search=') || window.location.search.includes('page=');

        if (savedState === 'true' || hasSearch) {
            table.style.display = 'block';
            btn.style.background = '#666';
            btn.innerHTML = '<i class="fa-solid fa-eye-slash" id="toggleIcon"></i> Hide Records';
        } else {
            table.style.display = 'none';
            btn.style.background = '#00ACB1';
            btn.innerHTML = '<i class="fa-solid fa-eye" id="toggleIcon"></i> Show Records';
        }
    });
</script>

</body>

</html>