<?php
// modal_edit_employee.php
?>
<div id="editEmployeeModal" class="modal-overlay">
    <div class="modal-card wide-modal">
        <div class="modal-header">
            <h2><i class="fa-solid fa-user-pen"></i> Edit Employee Record</h2>
            <button class="close-btn" onclick="closeEditEmployeeModal()">&times;</button>
        </div>

        <form action="edit_employee_process.php" method="POST" id="editEmployeeForm">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-grid">
                    <!-- Row 1 -->
                    <div class="form-group span-2">
                        <label for="edit_name">Full Name</label>
                        <input type="text" name="name" id="edit_name" placeholder="Last Name, First Name Middle Name" autocomplete="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_employee_no">Employee No.</label>
                        <input type="text" name="employee_no" id="edit_employee_no" placeholder="e.g. EMP-001" autocomplete="off">
                    </div>

                    <!-- Row 2 -->
                    <div class="form-group">
                        <label for="edit_birth_date">Date of Birth</label>
                        <input type="date" name="birth_date" id="edit_birth_date"
                            onchange="calculateRealTimeAge(this.value, 'edit_age_display')" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_age_display">Age</label>
                        <input type="text" id="edit_age_display" placeholder="--" readonly
                            style="background: #f9f9f9; pointer-events: none; color: #666; border: 1px solid #eee;" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="edit_gender">Gender</label>
                        <select name="gender" id="edit_gender" autocomplete="off" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <!-- Row 3 -->
                    <div class="form-group">
                        <label for="edit_civil_status">Civil Status</label>
                        <select name="civil_status" id="edit_civil_status" autocomplete="off" required>
                            <option value="">Select Status</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Widowed">Widowed</option>
                            <option value="Separated">Separated</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_position">Position</label>
                        <input type="text" name="position" id="edit_position" placeholder="e.g. Teacher I" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_designation">Designation</label>
                        <input type="text" name="designation" id="edit_designation" placeholder="e.g. Adviser" autocomplete="off" required>
                    </div>

                    <!-- Row 4 -->
                    <div class="form-group span-2">
                        <label for="edit_school_district_division">School/District/Division</label>
                        <input type="text" name="school_district_division" id="edit_school_district_division"
                            placeholder="Enter school, district, or division" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_first_year_in_service">First Year in Service</label>
                        <input type="number" name="first_year_in_service" id="edit_first_year_in_service" min="1950" max="<?= date('Y') ?>"
                            placeholder="YYYY" autocomplete="off" required>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditEmployeeModal()">Cancel</button>
                <button type="submit" class="btn-primary" style="background:#fbbf24; color:white;">Update Records</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Specific styles for this wide modal */
    #editEmployeeModal .modal-card.wide-modal {
        max-width: 900px;
        width: 95%;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: 90vh;
    }

    #editEmployeeModal .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 25px;
      border-bottom: 1px solid #eee;
      flex-shrink: 0;
    }

    #editEmployeeModal .modal-header h2 {
      margin: 0;
      font-size: 1.25rem;
      color: #333;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    #editEmployeeModal .close-btn {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: #777;
    }

    #editEmployeeModal form {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        flex: 1;
    }

    #editEmployeeModal .modal-body {
        padding: 20px 25px;
        overflow-y: auto;
        /* Just in case on very small screens */
        flex: 1;
    }

    #editEmployeeModal .form-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    #editEmployeeModal .span-2 {
        grid-column: span 2;
    }

    #editEmployeeModal .span-3 {
        grid-column: span 3;
    }

    #editEmployeeModal .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    #editEmployeeModal .form-group label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #444;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    #editEmployeeModal .form-group input,
    #editEmployeeModal .form-group select {
        padding: 10px 12px;
        border: 1px solid #dde1e5;
        border-radius: 6px;
        font-size: 0.95rem;
        color: #333;
        transition: all 0.2s ease;
        background: #fff;
    }

    #editEmployeeModal .form-group input:focus,
    #editEmployeeModal .form-group select:focus {
        border-color: #00ACB1;
        box-shadow: 0 0 0 3px rgba(0, 172, 177, 0.1);
        outline: none;
    }

    #editEmployeeModal .modal-footer {
        padding: 15px 25px;
        background: #f8f9fa;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    #editEmployeeModal .btn-primary {
        border: none;
        padding: 10px 24px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        transition: filter 0.2s;
    }

    #editEmployeeModal .btn-primary:hover {
        filter: brightness(0.9);
    }

    #editEmployeeModal .btn-secondary {
        background: white;
        color: #555;
        border: 1px solid #ddd;
        padding: 10px 24px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s;
    }

    #editEmployeeModal .btn-secondary:hover {
        background: #f1f1f1;
        border-color: #ccc;
    }

    /* Dark Mode Overrides */
    body.dark-mode #editEmployeeModal .modal-card.wide-modal {
        background: #1e1e1e;
        color: #e0e0e0;
        border: 1px solid #333;
    }

    body.dark-mode #editEmployeeModal .modal-header {
        border-bottom: 1px solid #333;
    }

    body.dark-mode #editEmployeeModal .modal-header h2 {
        color: #e0e0e0;
    }

    body.dark-mode #editEmployeeModal .form-group label {
        color: #bbb;
    }

    body.dark-mode #editEmployeeModal .form-group input,
    body.dark-mode #editEmployeeModal .form-group select {
        background: #2b2b2b;
        border-color: #444;
        color: #e0e0e0;
    }

    body.dark-mode #editEmployeeModal .form-group input:focus,
    body.dark-mode #editEmployeeModal .form-group select:focus {
        border-color: #00ACB1;
        box-shadow: 0 0 0 3px rgba(0, 172, 177, 0.2);
    }

    body.dark-mode #editEmployeeModal #edit_age_display {
        background: #252525 !important;
        border-color: #444 !important;
        color: #999 !important;
    }

    body.dark-mode #editEmployeeModal .modal-footer {
        background: #1a1a1a;
        border-top: 1px solid #333;
    }

    body.dark-mode #editEmployeeModal .btn-secondary {
        background: #333;
        color: #e0e0e0;
        border-color: #444;
    }

    body.dark-mode #editEmployeeModal .btn-secondary:hover {
        background: #444;
        border-color: #555;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        #editEmployeeModal .form-grid {
            grid-template-columns: 1fr 1fr;
        }

        #editEmployeeModal .span-2 {
            grid-column: span 2;
        }
    }

    @media (max-width: 500px) {
        #editEmployeeModal .form-grid {
            grid-template-columns: 1fr;
        }

        #editEmployeeModal .span-2 {
            grid-column: span 1;
        }
    }
</style>