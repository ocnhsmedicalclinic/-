<?php
// modal_add_employee.php
?>
<div id="addEmployeeModal" class="modal-overlay">
    <div class="modal-card wide-modal">
        <div class="modal-header">
            <h2><i class="fa-solid fa-user-plus"></i> Add New Employee</h2>
            <button class="close-btn" onclick="closeAddEmployeeModal()">&times;</button>
        </div>

        <form action="add_employee_process.php" method="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <!-- Row 1 -->
                    <div class="form-group span-2">
                        <label for="add_name">Full Name</label>
                        <input type="text" name="name" id="add_name" placeholder="Last Name, First Name Middle Name" autocomplete="name" required>
                    </div>
                    <div class="form-group">
                        <label for="add_employee_no">Employee No.</label>
                        <input type="text" name="employee_no" id="add_employee_no" placeholder="e.g. EMP-001" autocomplete="off">
                    </div>

                    <!-- Row 2 -->
                    <div class="form-group">
                        <label for="add_birth_date">Date of Birth</label>
                        <input type="date" name="birth_date" id="add_birth_date"
                            onchange="calculateRealTimeAge(this.value, 'add_age_display')" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label for="add_age_display">Age</label>
                        <input type="text" id="add_age_display" placeholder="--" readonly
                            style="background: #f9f9f9; pointer-events: none; color: #666; border: 1px solid #eee;" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="add_gender">Gender</label>
                        <select name="gender" id="add_gender" autocomplete="off" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <!-- Row 3 -->
                    <div class="form-group">
                        <label for="add_civil_status">Civil Status</label>
                        <select name="civil_status" id="add_civil_status" autocomplete="off" required>
                            <option value="">Select Status</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Widowed">Widowed</option>
                            <option value="Separated">Separated</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="add_position">Position</label>
                        <input type="text" name="position" id="add_position" placeholder="e.g. Teacher I" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label for="add_designation">Designation</label>
                        <input type="text" name="designation" id="add_designation" placeholder="e.g. Adviser" autocomplete="off" required>
                    </div>

                    <!-- Row 4 -->
                    <div class="form-group span-2">
                        <label for="add_school_district_division">School/District/Division</label>
                        <input type="text" name="school_district_division" id="add_school_district_division"
                            placeholder="Enter school, district, or division" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label for="add_first_year_in_service">First Year in Service</label>
                        <input type="number" name="first_year_in_service" id="add_first_year_in_service" min="1950" max="<?= date('Y') ?>"
                            placeholder="YYYY" autocomplete="off" required>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAddEmployeeModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Employee</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Specific styles for this wide modal */
    #addEmployeeModal .modal-card.wide-modal {
        max-width: 900px;
        width: 95%;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: 90vh;
    }

    #addEmployeeModal form {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        flex: 1;
    }

    #addEmployeeModal .modal-body {
        padding: 20px 25px;
        overflow-y: auto;
        /* Just in case on very small screens */
        flex: 1;
    }

    #addEmployeeModal .form-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    #addEmployeeModal .span-2 {
        grid-column: span 2;
    }

    #addEmployeeModal .span-3 {
        grid-column: span 3;
    }

    #addEmployeeModal .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    #addEmployeeModal .form-group label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #444;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    #addEmployeeModal .form-group input,
    #addEmployeeModal .form-group select {
        padding: 10px 12px;
        border: 1px solid #dde1e5;
        border-radius: 6px;
        font-size: 0.95rem;
        color: #333;
        transition: all 0.2s ease;
        background: #fff;
    }

    #addEmployeeModal .form-group input:focus,
    #addEmployeeModal .form-group select:focus {
        border-color: #00ACB1;
        box-shadow: 0 0 0 3px rgba(0, 172, 177, 0.1);
        outline: none;
    }

    #addEmployeeModal .modal-footer {
        padding: 15px 25px;
        background: #f8f9fa;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    #addEmployeeModal .btn-primary {
        background: #00ACB1;
        color: white;
        border: none;
        padding: 10px 24px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        transition: background 0.2s;
    }

    #addEmployeeModal .btn-primary:hover {
        background: #008f94;
    }

    #addEmployeeModal .btn-secondary {
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

    #addEmployeeModal .btn-secondary:hover {
        background: #f1f1f1;
        border-color: #ccc;
    }

    /* Dark Mode Overrides */
    body.dark-mode #addEmployeeModal .modal-card.wide-modal {
        background: #1e1e1e;
        color: #e0e0e0;
        border: 1px solid #333;
    }

    body.dark-mode #addEmployeeModal .modal-header {
        border-bottom: 1px solid #333;
    }

    body.dark-mode #addEmployeeModal .modal-header h2 {
        color: #e0e0e0;
    }

    body.dark-mode #addEmployeeModal .form-group label {
        color: #bbb;
    }

    body.dark-mode #addEmployeeModal .form-group input,
    body.dark-mode #addEmployeeModal .form-group select {
        background: #2b2b2b;
        border-color: #444;
        color: #e0e0e0;
    }

    body.dark-mode #addEmployeeModal .form-group input:focus,
    body.dark-mode #addEmployeeModal .form-group select:focus {
        border-color: #00ACB1;
        box-shadow: 0 0 0 3px rgba(0, 172, 177, 0.2);
    }

    body.dark-mode #addEmployeeModal #add_age_display {
        background: #252525 !important;
        border-color: #444 !important;
        color: #999 !important;
    }

    body.dark-mode #addEmployeeModal .modal-footer {
        background: #1a1a1a;
        border-top: 1px solid #333;
    }

    body.dark-mode #addEmployeeModal .btn-secondary {
        background: #333;
        color: #e0e0e0;
        border-color: #444;
    }

    body.dark-mode #addEmployeeModal .btn-secondary:hover {
        background: #444;
        border-color: #555;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        #addEmployeeModal .form-grid {
            grid-template-columns: 1fr 1fr;
        }

        #addEmployeeModal .span-2 {
            grid-column: span 2;
        }
    }

    @media (max-width: 500px) {
        #addEmployeeModal .form-grid {
            grid-template-columns: 1fr;
        }

        #addEmployeeModal .span-2 {
            grid-column: span 1;
        }
    }
</style>