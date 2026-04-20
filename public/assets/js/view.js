function openViewModal(id, studentName) {
    // 1. Ipakita ang modal overlay
    document.getElementById('viewRecordModal').style.display = 'flex';

    // 2. Update modal header with student name
    const modalHeader = document.getElementById('modal-student-name');
    if (modalHeader && studentName) {
        modalHeader.textContent = 'Records of ' + studentName.toUpperCase();
    }

    // 2. Hanapin ang mga links sa loob ng modal at dagdagan ng ID
    // Update SHEC Links
    const viewBtn = document.getElementById('btn-view-card');
    const editBtn = document.getElementById('btn-edit-card');

    const safelyId = String(id).trim();
    // console.log("Opening View Modal for ID:", safelyId);

    // Detect active page type
    const isEmployeePage = window.location.pathname.includes('employees.php');
    const patientType = isEmployeePage ? 'employee' : 'student';

    if (viewBtn) {
        viewBtn.href = `view_card.php?view_id=${safelyId}&type=${patientType}`;
    } else {
        console.error("View Button not found!");
    }

    if (editBtn) {
        editBtn.href = `edit_card.php?id=${safelyId}&type=${patientType}&v=${new Date().getTime()}`;
    }

    const viewTreatmentBtn = document.getElementById('btn-view-treatment');
    const editTreatmentBtn = document.getElementById('btn-edit-treatment');

    if (viewTreatmentBtn) {
        viewTreatmentBtn.href = `view_treatment.php?view_id=${safelyId}&type=${patientType}`;
    }
    if (editTreatmentBtn) {
        editTreatmentBtn.href = `edit_treatment.php?id=${safelyId}&type=${patientType}&v=${new Date().getTime()}`;
    }


    const viewConsentBtn = document.getElementById('btn-view-consent');
    const editConsentBtn = document.getElementById('btn-edit-consent');

    if (viewConsentBtn) viewConsentBtn.href = `view_consent.php?id=${safelyId}&type=${patientType}`;
    if (editConsentBtn) editConsentBtn.href = `edit_consent.php?id=${safelyId}&type=${patientType}&v=${new Date().getTime()}`;

    // Update Medical Records Link
    const medicalRecordsBtn = document.getElementById('btn-medical-records');
    if (medicalRecordsBtn) {
        medicalRecordsBtn.href = `medical_records.php?id=${safelyId}&type=${patientType}`;
    }
}

function closeViewModal() {
    document.getElementById('viewRecordModal').style.display = 'none';

    // Clean URL parameter without reloading
    const url = new URL(window.location);
    if (url.searchParams.has('view_id')) {
        url.searchParams.delete('view_id');
        window.history.replaceState({}, '', url);
    }
}

// Auto-open modal if ID is in URL
// Auto-open modal if ID is in URL
document.addEventListener('DOMContentLoaded', function () {
    const urlParams = new URLSearchParams(window.location.search);
    const viewId = urlParams.get('view_id');

    if (viewId) {
        // Try to find the button on the current page first
        const buttons = document.querySelectorAll('button.view');
        let found = false;

        for (let btn of buttons) {
            const onclickAttr = btn.getAttribute('onclick');
            if (onclickAttr && (onclickAttr.includes(`(${viewId},`) || onclickAttr.includes(`('${viewId}',`))) {
                btn.click();
                found = true;
                break;
            }
        }

        // If not found (e.g., on another page), fetch details and open modal directly
        if (!found) {
            fetch(`get_student.php?id=${viewId}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.name) {
                        openViewModal(viewId, data.name);
                    }
                })
                .catch(err => console.error('Error fetching student for modal:', err));
        }
    }
});