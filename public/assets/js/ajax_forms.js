
document.addEventListener('DOMContentLoaded', function () {

    // --- ADD STUDENT AJAX ---
    const studentModal = document.getElementById('addStudentModal');
    if (studentModal) {
        const studentForm = studentModal.querySelector('form');
        if (studentForm) {
            studentForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn ? submitBtn.innerHTML : 'Add Student';

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
                }

                fetch('add_student.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => {
                        return response.json().then(data => {
                            if (!response.ok) {
                                throw new Error(data.message || 'Network response was not ok');
                            }
                            return data;
                        });
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: data.message,
                                confirmButtonColor: '#00ACB1',
                                timer: 2000,
                                timerProgressBar: true
                            }).then(() => {
                                // Close modal and refresh/reload
                                if (typeof closeModal === 'function') closeModal(); // Existing close function

                                // If search_students.js logic is present, we could reload table. 
                                // But safest is reload page to reflect changes properly.
                                location.reload();
                            });
                        } else {
                            // Show Error but stay in modal
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message,
                                confirmButtonColor: '#d33'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message || 'An unexpected error occurred.',
                            confirmButtonColor: '#d33'
                        });
                    })
                    .finally(() => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    });
            });
        }
    }

    // --- ADD EMPLOYEE AJAX ---
    const employeeModal = document.getElementById('addEmployeeModal');
    if (employeeModal) {
        const employeeForm = employeeModal.querySelector('form');
        if (employeeForm) {
            employeeForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn ? submitBtn.innerHTML : 'Save Employee';

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
                }

                fetch('add_employee_process.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => {
                        return response.json().then(data => {
                            if (!response.ok) {
                                throw new Error(data.message || 'Network response was not ok');
                            }
                            return data;
                        });
                    })
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: data.message,
                                confirmButtonColor: '#00ACB1',
                                timer: 2000,
                                timerProgressBar: true
                            }).then(() => {
                                if (typeof closeAddEmployeeModal === 'function') closeAddEmployeeModal();
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message,
                                confirmButtonColor: '#d33'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: error.message || 'An unexpected error occurred.',
                            confirmButtonColor: '#d33'
                        });
                    })
                    .finally(() => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    });
            });
        }
    }
});
