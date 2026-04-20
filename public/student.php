<?php
require_once "../config/db.php";
requireLogin();

// AUTO-ARCHIVE LOGIC: Archive students older than 7 years
$archive_query = "UPDATE students SET is_archived = 1, archived_at = NOW() WHERE is_archived = 0 AND created_at <= DATE_SUB(NOW(), INTERVAL 7 YEAR)";
$conn->query($archive_query);

// Pagination & Search Logic
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 5; // Limit to 5 students per page
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base Query Conditions
$where_clauses = ["is_archived = 0"];
$params = [];
$types = "";

// Add Search Condition
if (!empty($search)) {
  // Search across ALL columns
  $where_search = "(name LIKE ? OR lrn LIKE ? OR address LIKE ? OR curriculum LIKE ? OR gender LIKE ? OR birth_date LIKE ? OR birthplace LIKE ? OR religion LIKE ? OR guardian LIKE ? OR contact LIKE ?)";
  $where_clauses[] = $where_search;
  $searchTerm = "%$search%";

  // Bind params for all 10 columns
  for ($i = 0; $i < 10; $i++) {
    $params[] = $searchTerm;
  }
  $types .= str_repeat("s", 10);
}

// Sorting Logic
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';
if (!in_array($order, ['ASC', 'DESC']))
  $order = 'ASC';

$allowed_sort = ['name', 'lrn', 'curriculum', 'address', 'gender', 'birth_date', 'birthplace', 'religion', 'guardian', 'contact'];
if (!in_array($sort, $allowed_sort))
  $sort = 'name';

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Count Total Records
$count_query = "SELECT COUNT(*) as total FROM students $where_sql";
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
$sql = "SELECT * FROM students $where_sql ORDER BY $sort $order LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}
if (!$stmt->execute()) {
  die("Error executing query: " . $stmt->error);
}
$result = $stmt->get_result();
include "index_layout.php";
?>

<script>
  function sortTable(column) {
    const urlParams = new URLSearchParams(window.location.search);
    let currentSort = urlParams.get('sort');
    let currentOrder = urlParams.get('order');

    let newOrder = 'ASC';
    if (currentSort === column && currentOrder === 'ASC') {
      newOrder = 'DESC';
    }

    urlParams.set('sort', column);
    urlParams.set('order', newOrder);
    window.location.href = "?" + urlParams.toString();
  }
</script>

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
  <form method="GET" action="student.php" class="search-box" style="display:flex; align-items:center; width: 100%;">
    <input type="text" name="search" id="searchInput" value="<?= htmlspecialchars($search) ?>"
      placeholder="Search Name, LRN..." autocomplete="off" aria-label="Search Students"
      style="border:none; outline:none; width: 100%; padding: 10px; background: transparent;">
    <i class="fa-solid fa-magnifying-glass search-icon"></i>
  </form>

  <button class="btn" id="toggleTableBtn" onclick="toggleStudentTable()"
    style="background: #00ACB1; white-space: nowrap;">
    <i class="fa-solid fa-eye" id="toggleIcon"></i> Show Students
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
      <a href="export_pdf.php"><i class="fa-solid fa-file-pdf"></i> DOWNLOAD PDF</a>
      <a href="export_xlsx.php"><i class="fa-solid fa-file-excel"></i> DOWNLOAD XLSX</a>
    </div>
  </div>

  <a href="generate_qr.php" class="btn"
    style="background: #607d8b; color: white; text-decoration: none; display: flex; justify-content: center; align-items: center; gap: 8px; white-space: nowrap;">
    <i class="fa-solid fa-qrcode"></i> QR CODE
  </a>

  <button class="btn green" onclick="openModal()" style="white-space: nowrap;">Add Student</button>
</section>
<?php
function getSortIcon($column, $currentSort, $currentOrder)
{
  if ($currentSort == $column) {
    return ($currentOrder == 'ASC') ? 'fa-sort-up active-sort' : 'fa-sort-down active-sort';
  }
  return 'fa-sort';
}
?>
<div class="table-container" style="display: <?= (!empty($search) || isset($_GET['page'])) ? 'block' : 'none' ?>;">
  <table>
    <thead>
      <tr>
        <th onclick="sortTable('name')">Name <i
            class="fa-solid <?= getSortIcon('name', $sort, $order) ?> th-filler"></i>
        </th>
        <th onclick="sortTable('lrn')">LRN <i class="fa-solid <?= getSortIcon('lrn', $sort, $order) ?> th-filler"></i>
        </th>
        <th onclick="sortTable('curriculum')">Curriculum <i
            class="fa-solid <?= getSortIcon('curriculum', $sort, $order) ?> th-filler"></i></th>
        <th onclick="sortTable('address')">Address <i
            class="fa-solid <?= getSortIcon('address', $sort, $order) ?> th-filler"></i></th>
        <th onclick="sortTable('birth_date')">Age <i
            class="fa-solid <?= getSortIcon('birth_date', $sort, $order) ?> th-filler"></i></th>
        <th onclick="sortTable('gender')">Gender <i
            class="fa-solid <?= getSortIcon('gender', $sort, $order) ?> th-filler"></i></th>
        <th onclick="sortTable('birth_date')">Birth Date <i
            class="fa-solid <?= getSortIcon('birth_date', $sort, $order) ?> th-filler"></i></th>
        <th onclick="sortTable('birthplace')">Birthplace <i
            class="fa-solid <?= getSortIcon('birthplace', $sort, $order) ?> th-filler"></i></th>
        <th onclick="sortTable('religion')">Religion <i
            class="fa-solid <?= getSortIcon('religion', $sort, $order) ?> th-filler"></i></th>
        <th onclick="sortTable('guardian')">Guardian <i
            class="fa-solid <?= getSortIcon('guardian', $sort, $order) ?> th-filler"></i></th>
        <th onclick="sortTable('contact')">Contact <i
            class="fa-solid <?= getSortIcon('contact', $sort, $order) ?> th-filler"></i></th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody id="studentData">
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= $row['name'] ?></td>
          <td><?= $row['lrn'] ?></td>
          <td><?= $row['curriculum'] ?></td>
          <td><?= $row['address'] ?></td>
          <td>
            <?php
            if ($row['birth_date']) {
              $birth = new DateTime($row['birth_date']);
              $today = new DateTime('today');
              echo $birth->diff($today)->y;
            } else {
              echo '-';
            }
            ?>
          </td>
          <td><?= $row['gender'] ?></td>
          <td><?= date('m/d/Y', strtotime($row['birth_date'])) ?></td>
          <td><?= $row['birthplace'] ?></td>
          <td><?= $row['religion'] ?></td>
          <td><?= $row['guardian'] ?></td>
          <td><?= $row['contact'] ?></td>
          <td class="actions">
            <button class="view" data-tooltip="View Record"
              onclick="openViewModal('<?= $row['id'] ?>', '<?= addslashes($row['name']) ?>')">
              <i class="fa-solid fa-eye"></i>
            </button>
            <button class="edit" data-tooltip="Edit Student" onclick="openEditModal('<?= $row['id'] ?>')">
              <i class="fa-solid fa-pen-to-square"></i>
            </button>


            <button class="archive" data-tooltip="Archive Student"
              onclick="confirmArchive('<?= $row['id'] ?>', '<?= addslashes($row['name']) ?>')">
              <i class="fa-solid fa-box-archive"></i>
            </button>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <!-- Pagination Controls inside table-container -->
  <?php if ($total_pages > 1): ?>
    <div class="pagination pagination-container" id="paginationContainer"
      style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 15px; margin-bottom: 20px;">
      <!-- Previous Button -->
      <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>" class="btn"
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
        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>" class="btn"
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

<?php include "modal_add_student.php"; ?>
<?php include "modal_edit_student.php"; ?>

<script src="assets/js/app.js?v=<?= time() ?>"></script>
<script src="assets/js/view.js?v=<?= time() ?>"></script>
<script src="assets/js/search_students.js?v=<?= time() ?>"></script>
<script src="assets/js/ajax_forms.js?v=<?= time() ?>"></script>
<link rel="stylesheet" href="assets/css/pagination.css?v=<?= time() ?>">

<div id="viewRecordModal" class="modal-overlay">
  <div class="modal-card view-modal">
    <button class="close-btn" onclick="closeViewModal()">&times;</button>
    <h2 id="modal-student-name">Records of Student</h2>

    <div class="record-card-container">
      <div class="record-card">
        <div class="card-content">
          <span class="card-title">SCHOOL HEALTH EXAMINATION CARD</span>
        </div>
        <div class="modal-card-actions">
          <a href="view_card.php" id="btn-view-card" class="btn-action view" title="View"><i
              class="fa-solid fa-eye"></i> View</a>
          <a href="edit_card.php" id="btn-edit-card" class="btn-action edit" title="Edit"><i
              class="fa-solid fa-pen-to-square"></i> Edit</a>
        </div>
      </div>

      <div class="record-card">
        <div class="card-content">
          <span class="card-title">TREATMENT RECORD</span>
        </div>
        <div class="modal-card-actions">
          <a href="view_treatment.php" id="btn-view-treatment" class="btn-action view" title="View"><i
              class="fa-solid fa-eye"></i> View</a>
          <a href="edit_treatment.php" id="btn-edit-treatment" class="btn-action edit" title="Edit"><i
              class="fa-solid fa-pen-to-square"></i>
            Edit</a>
        </div>
      </div>

      <div class="record-card">
        <div class="card-content">
          <span class="card-title">PARENT CONSENT RECORD</span>
        </div>
        <div class="modal-card-actions">
          <a href="view_consent.php" id="btn-view-consent" class="btn-action view" title="View"><i
              class="fa-solid fa-eye"></i> View</a>
          <a href="edit_consent.php" id="btn-edit-consent" class="btn-action edit" title="Edit"><i
              class="fa-solid fa-pen-to-square"></i>
            Edit</a>
        </div>
      </div>

      <div class="record-card">
        <div class="card-content">
          <span class="card-title">MEDICAL RECORDS (FILES)</span>
        </div>
        <div class="modal-card-actions">
          <a href="medical_records.php" id="btn-medical-records" class="btn-action view"
            style="width: 100%; justify-content: center;" title="Manage Files">
            <i class="fa-solid fa-file-medical"></i> Manage Files
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  function toggleStudentTable() {
    const table = document.querySelector('.table-container');
    const btn = document.getElementById('toggleTableBtn');
    const icon = document.getElementById('toggleIcon');

    if (table.style.display === 'none') {
      table.style.display = 'block';
      btn.style.background = '#666';
      if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
      btn.innerHTML = '<i class="fa-solid fa-eye-slash" id="toggleIcon"></i> Hide Students';
      localStorage.setItem('studentTableVisible', 'true');
    } else {
      table.style.display = 'none';
      btn.style.background = '#00ACB1';
      if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
      btn.innerHTML = '<i class="fa-solid fa-eye" id="toggleIcon"></i> Show Students';
      localStorage.setItem('studentTableVisible', 'false');
    }
  }

  // Restore table state on load
  document.addEventListener("DOMContentLoaded", () => {
    const table = document.querySelector('.table-container');
    const btn = document.getElementById('toggleTableBtn');
    const savedState = localStorage.getItem('studentTableVisible');
    const hasSearch = window.location.search.includes('search=') || window.location.search.includes('page=');

    if (savedState === 'true' || hasSearch) {
      if (table) table.style.display = 'block';
      if (btn) {
        btn.style.background = '#666';
        btn.innerHTML = '<i class="fa-solid fa-eye-slash" id="toggleIcon"></i> Hide Students';
      }
    } else {
      if (table) table.style.display = 'none';
      if (btn) {
        btn.style.background = '#00ACB1';
        btn.innerHTML = '<i class="fa-solid fa-eye" id="toggleIcon"></i> Show Students';
      }
    }

    // Auto-open view modal if view_id is present
    const urlParams = new URLSearchParams(window.location.search);
    const viewId = urlParams.get('view_id');
    const viewName = urlParams.get('view_name'); // We can try to fetch name or just show generic
    if (viewId) {
      // Fetch student name if not provided
      if (typeof openViewModal === 'function') {
        openViewModal(viewId, viewName || 'Merged Record');
      }
    }
  });
</script>

</body>

</html>