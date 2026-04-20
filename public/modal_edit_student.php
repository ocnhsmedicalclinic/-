<div id="editStudentModal" class="modal-overlay">
    <div class="modal-card wide-modal">
        <div class="modal-header">
            <h2><i class="fa-solid fa-user-pen"></i> Edit Student</h2>
            <button class="close-btn" onclick="closeEditModal()">✕</button>
        </div>

        <form method="POST" action="update_student.php">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-grid">
                    <!-- Row 1 -->
                    <div class="form-group">
                        <label for="edit_lrn">LRN Number</label>
                        <input type="text" name="lrn" id="edit_lrn" autocomplete="off">
                    </div>
                    <div class="form-group span-2">
                        <label for="edit_curriculum">Curriculum <span class="required">*</span></label>
                        <select name="curriculum" id="edit_curriculum" required autocomplete="off">
                            <option value="">Select Curriculum</option>
                            <option>BEP</option>
                            <option>SPA</option>
                            <option>SPFL</option>
                            <option>STE</option>
                            <option>SPTVE</option>
                            <option>SPSS</option>
                            <option>SPS</option>
                            <option>SPJ</option>
                        </select>
                    </div>

                    <!-- Row 2 -->
                    <div class="form-group">
                        <label for="edit_last_name">Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" id="edit_last_name" required autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="edit_first_name">First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" id="edit_first_name" required autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="edit_middle_name">Middle Name</label>
                        <input type="text" name="middle_name" id="edit_middle_name" autocomplete="off">
                    </div>

                    <!-- Row 3 -->
                    <div class="form-group">
                        <label for="edit_birth_date">Birth Date <span class="required">*</span></label>
                        <input type="date" name="birth_date" id="edit_birth_date" required autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="edit_gender">Gender <span class="required">*</span></label>
                        <select name="gender" id="edit_gender" required autocomplete="off">
                            <option value="">Select Gender</option>
                            <option>Male</option>
                            <option>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_religion">Religion</label>
                        <input type="text" name="religion" id="edit_religion" autocomplete="off">
                    </div>

                    <!-- Row 4 -->
                    <div class="form-group span-2">
                        <label for="edit_birth_place">Birth Place</label>
                        <input type="text" name="birth_place" id="edit_birth_place" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="edit_contact_number">Contact Number</label>
                        <input type="text" name="contact_number" id="edit_contact_number" autocomplete="off">
                    </div>

                    <!-- Row 5 -->
                    <div class="form-group span-3">
                        <label for="edit_address">Address <span class="required">*</span></label>
                        <input type="text" name="address" id="edit_address" required autocomplete="off">
                    </div>

                    <!-- Row 6 -->
                    <div class="form-group span-3">
                        <label for="edit_guardian_name">Guardian <span class="required">*</span></label>
                        <input type="text" name="guardian_name" id="edit_guardian_name" required autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-primary" style="background:#fbbf24; color:white;">Update
                    Student</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Specific styles for this wide modal */
    #editStudentModal .modal-card.wide-modal {
        max-width: 900px;
        width: 95%;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: 90vh;
    }

    #editStudentModal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 25px;
        border-bottom: 1px solid #eee;
        flex-shrink: 0;
    }

    #editStudentModal .modal-header h2 {
        margin: 0;
        font-size: 1.25rem;
        color: #333;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    #editStudentModal .close-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #777;
    }

    #editStudentModal form {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        flex: 1;
    }

    #editStudentModal .modal-body {
        padding: 20px 25px;
        overflow-y: auto;
        flex: 1;
    }

    #editStudentModal .form-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    #editStudentModal .span-2 {
        grid-column: span 2;
    }

    #editStudentModal .span-3 {
        grid-column: span 3;
    }

    #editStudentModal .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    #editStudentModal .form-group label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #444;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    #editStudentModal .form-group input,
    #editStudentModal .form-group select {
        padding: 10px 12px;
        border: 1px solid #dde1e5;
        border-radius: 6px;
        font-size: 0.95rem;
        color: #333;
        transition: all 0.2s ease;
        background: #fff;
    }

    #editStudentModal .form-group input:focus,
    #editStudentModal .form-group select:focus {
        border-color: #00ACB1;
        box-shadow: 0 0 0 3px rgba(0, 172, 177, 0.1);
        outline: none;
    }

    #editStudentModal .modal-footer {
        padding: 15px 25px;
        background: #f8f9fa;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    #editStudentModal .btn-primary {
        border: none;
        padding: 10px 24px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        transition: filter 0.2s;
    }

    #editStudentModal .btn-primary:hover {
        filter: brightness(0.9);
    }

    #editStudentModal .btn-secondary {
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

    #editStudentModal .btn-secondary:hover {
        background: #f1f1f1;
        border-color: #ccc;
    }

    .required {
        color: #d33;
        margin-left: 2px;
    }

    /* Dark Mode Overrides */
    body.dark-mode #editStudentModal .modal-card.wide-modal {
        background: #1e1e1e;
        color: #e0e0e0;
        border: 1px solid #333;
    }

    body.dark-mode #editStudentModal .modal-header {
        border-bottom: 1px solid #333;
    }

    body.dark-mode #editStudentModal .modal-header h2 {
        color: #e0e0e0;
    }

    body.dark-mode #editStudentModal .form-group label {
        color: #bbb;
    }

    body.dark-mode #editStudentModal .form-group input,
    body.dark-mode #editStudentModal .form-group select {
        background: #2b2b2b;
        border-color: #444;
        color: #e0e0e0;
    }

    body.dark-mode #editStudentModal .form-group input:focus,
    body.dark-mode #editStudentModal .form-group select:focus {
        border-color: #00ACB1;
        box-shadow: 0 0 0 3px rgba(0, 172, 177, 0.2);
    }

    body.dark-mode #editStudentModal .modal-footer {
        background: #1a1a1a;
        border-top: 1px solid #333;
    }

    body.dark-mode #editStudentModal .btn-secondary {
        background: #333;
        color: #e0e0e0;
        border-color: #444;
    }

    body.dark-mode #editStudentModal .btn-secondary:hover {
        background: #444;
        border-color: #555;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        #editStudentModal .form-grid {
            grid-template-columns: 1fr 1fr;
        }

        #editStudentModal .span-2,
        #editStudentModal .span-3 {
            grid-column: span 2;
        }
    }

    @media (max-width: 500px) {
        #editStudentModal .form-grid {
            grid-template-columns: 1fr;
        }

        #editStudentModal .span-2,
        #editStudentModal .span-3 {
            grid-column: span 1;
        }
    }
</style>