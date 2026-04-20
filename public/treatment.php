<?php
require_once "../config/db.php";
requireLogin();
$result = $conn->query("SELECT * FROM students");
include "index_layout.php";
?>

<section class="controls">
  <div class="dropdown">
    <button class="btn red dropdown-btn" style="white-space: nowrap;">
      DOWNLOAD <i class="fa-solid fa-caret-down"></i>
    </button>
    <div class="dropdown-content">
      <a href="export_pdf.php"><i class="fa-solid fa-file-pdf"></i> DOWNLOAD PDF</a>
      <a href="export_xlsx.php"><i class="fa-solid fa-file-excel"></i> DOWNLOAD XLSX</a>
    </div>
  </div>

  <button class="btn green" onclick="openModal()" style="white-space: nowrap;">Add Student</button>

  <div class="search-box" style="margin-left: auto;">
    <input type="text" id="search" placeholder="Search for Records">
    <i class="fa-solid fa-magnifying-glass search-icon"></i>
  </div>
</section>

<table>
  <thead>
    <tr>
      <th>Name</th>
      <th>LRN</th>
      <th>Curriculum</th>
      <th>Address</th>
      <th>Gender</th>
      <th>Birth Date</th>
      <th>Birthplace</th>
      <th>Religion</th>
      <th>Guardian</th>
      <th>Contact</th>
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
        <td><?= $row['gender'] ?></td>
        <td><?= $row['birth_date'] ?></td>
        <td><?= $row['birthplace'] ?></td>
        <td><?= $row['religion'] ?></td>
        <td><?= $row['guardian'] ?></td>
        <td><?= $row['contact'] ?></td>
        <td class="actions">
          <a href="view_card.php?id=<?= $row['id'] ?>" class="view" data-tooltip="View Record">
            <i class="fa-solid fa-eye"></i>
          </a>

          <button class="edit" data-tooltip="Edit Student">
            <i class="fa-solid fa-pen-to-square"></i>
          </button>

          <button class="archive" data-tooltip="Archive Student">
            <i class="fa-solid fa-box-archive"></i>
          </button>
        </td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<?php include "modal_add_student.php"; ?>

<script src="assets/js/app.js"></script>
</body>

</html>