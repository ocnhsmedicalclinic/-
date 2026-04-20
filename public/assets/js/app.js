function openModal() {
  document.getElementById("addStudentModal").style.display = "flex";
}

function closeModal() {
  document.getElementById("addStudentModal").style.display = "none";
}

const searchInput = document.getElementById("search");
if (searchInput) {
  searchInput.addEventListener("keyup", function () {
    let value = this.value.toLowerCase();
    const table = document.querySelector('.table-container');

    // If user is searching, show table but only with filtered results
    if (value) {
      if (table) table.style.display = 'block';
      // Filter rows - only show matches
      const rows = document.querySelectorAll("#studentData tr");
      if (rows) {
        rows.forEach(row => {
          row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
        });
      }
    } else {
      // If search is empty, hide table again (back to initial state)
      if (table) table.style.display = 'none';
    }
  });
}

// Dropdown toggle logic
const downloadBtn = document.querySelector('.dropdown-btn');
if (downloadBtn) {
  downloadBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    this.classList.toggle('active');
    const content = this.nextElementSibling;
    if (content) content.classList.toggle('show');
  });
}

// Close dropdowns when clicking outside
window.addEventListener('click', function (e) {
  if (!e.target.closest('.dropdown')) {
    // Close content
    const openDropdowns = document.querySelectorAll('.dropdown-content.show');
    openDropdowns.forEach(dropdown => dropdown.classList.remove('show'));

    // Reset button state
    const activeBtns = document.querySelectorAll('.dropdown-btn.active');
    activeBtns.forEach(btn => btn.classList.remove('active'));
  }
});

document.addEventListener("DOMContentLoaded", () => {
  const userDropdown = document.querySelector(".user-dropdown");
  const userInfo = document.querySelector(".user-info");

  if (userInfo && userDropdown) {
    userInfo.addEventListener("click", (e) => {
      e.stopPropagation();
      userDropdown.classList.toggle("active");
    });

    document.addEventListener("click", () => {
      userDropdown.classList.remove("active");
    });
  }
});
function restoreStudent(id) {
  Swal.fire({
    title: 'Restore Student?',
    text: "Are you sure you want to restore this student's record?",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#00ACB1',
    cancelButtonColor: '#666',
    confirmButtonText: 'Yes, Restore it!'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = "restore_process.php?id=" + id;
    }
  });
}

function confirmPermanentDelete(id) {
  Swal.fire({
    title: 'Permanent Delete?',
    text: "WARNING: This action cannot be undone. Are you sure?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#666',
    confirmButtonText: 'Yes, Delete Permanently!'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = "permanent_delete_process.php?id=" + id;
    }
  });
}

function openEditModal(id) {
  console.log('Opening edit modal for ID:', id);
  fetch('get_student.php?id=' + id)
    .then(res => {
      if (!res.ok) {
        throw new Error('Network response was not ok: ' + res.statusText);
      }
      return res.json();
    })
    .then(data => {
      if (!data) {
        console.error('No data received for student');
        Swal.fire('Error', 'Student data not found.', 'error');
        return;
      }
      document.getElementById('edit_id').value = data.id || '';
      document.getElementById('edit_lrn').value = data.lrn || '';

      // Parse Name: Last, First Middle
      let name = data.name || '';
      let lastName = '';
      let firstName = '';
      let middleName = '';

      let parts = name.split(', ');
      if (parts.length > 0) {
        lastName = parts[0];
        if (parts.length > 1) {
          let firstMiddle = parts[1].trim();
          let nameParts = firstMiddle.split(' ');
          if (nameParts.length > 1) {
            middleName = nameParts.pop(); // Assume last part is middle
            firstName = nameParts.join(' ');
          } else {
            firstName = firstMiddle;
          }
        }
      }

      document.getElementById('edit_last_name').value = lastName;
      document.getElementById('edit_first_name').value = firstName;
      document.getElementById('edit_middle_name').value = middleName;

      document.getElementById('edit_curriculum').value = data.curriculum || '';
      document.getElementById('edit_address').value = data.address || '';
      document.getElementById('edit_gender').value = data.gender || '';
      document.getElementById('edit_birth_date').value = data.birth_date || '';
      document.getElementById('edit_birth_place').value = data.birthplace || ''; // DB column: birthplace
      document.getElementById('edit_religion').value = data.religion || '';
      document.getElementById('edit_guardian_name').value = data.guardian || ''; // DB column: guardian
      document.getElementById('edit_contact_number').value = data.contact || ''; // DB column: contact

      document.getElementById('editStudentModal').style.display = 'flex';
    })
    .catch(error => {
      console.error('Error fetching student data:', error);
      Swal.fire('Error', 'Failed to load student data.\n' + error.message, 'error');
    });
}

function confirmArchive(id, name) {
  Swal.fire({
    title: 'Archive Student?',
    html: `Are you sure you want to archive <strong>${name}</strong>?<br><span style="font-size: 13px; color: #888;">This record will be moved to the archive list and can be restored later.</span>`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#f39c12',
    cancelButtonColor: '#666',
    confirmButtonText: 'Yes, Archive it!'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = "archive_action.php?id=" + id;
    }
  });
}

// Function closeArchiveModal is no longer needed but kept for safety if referenced elsewhere
function closeArchiveModal() {
  const modal = document.getElementById('archiveConfirmModal');
  if (modal) {
    modal.style.display = 'none';
  }
}

function closeEditModal() {
  document.getElementById('editStudentModal').style.display = 'none';
}
