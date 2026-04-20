<?php
// modal_edit_other.php
?>
<div id="editOtherModal" class="modal-overlay">
    <div class="modal-card wide-modal">
        <div class="modal-header">
            <h2><i class="fa-solid fa-pen-to-square"></i> Edit Record</h2>
            <button class="close-btn" onclick="closeEditOtherModal()">&times;</button>
        </div>

        <form action="edit_other_process.php" method="POST">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-grid">
                    <!-- Row 1 -->
                    <div class="form-group span-2">
                        <label for="edit_name">Full Name</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_birth_date">Date of Birth</label>
                        <input type="date" name="birth_date" id="edit_birth_date"
                            onchange="calculateRealTimeAge(this.value, 'edit_age_display')">
                    </div>

                    <!-- Row 2 -->
                    <div class="form-group">
                        <label for="edit_age_display">Age</label>
                        <input type="number" name="age" id="edit_age_display">
                    </div>
                    <div class="form-group">
                        <label for="edit_gender">Gender</label>
                        <select name="gender" id="edit_gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_sdo">SDO</label>
                        <input type="text" name="sdo" id="edit_sdo">
                    </div>

                    <!-- Row 3 -->
                    <div class="form-group span-2">
                        <label for="edit_address">Address</label>
                        <input type="text" name="address" id="edit_address">
                    </div>
                    <div class="form-group">
                        <label for="edit_remarks">Remarks</label>
                        <input type="text" name="remarks" id="edit_remarks">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditOtherModal()">Cancel</button>
                <button type="submit" class="btn-primary">Update Record</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Reuse the same styles from add modal */
    #editOtherModal .modal-card.wide-modal {
        max-width: 900px;
        width: 95%;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: 90vh;
    }

    #editOtherModal form {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        flex: 1;
    }

    #editOtherModal .modal-body {
        padding: 20px 25px;
        overflow-y: auto;
        flex: 1;
    }

    #editOtherModal .form-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    #editOtherModal .span-2 {
        grid-column: span 2;
    }

    #editOtherModal .span-3 {
        grid-column: span 3;
    }

    #editOtherModal .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    #editOtherModal .form-group label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #444;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    #editOtherModal .form-group input,
    #editOtherModal .form-group select {
        padding: 10px 12px;
        border: 1px solid #dde1e5;
        border-radius: 6px;
        font-size: 0.95rem;
        background: #fff;
    }

    #editOtherModal .modal-footer {
        padding: 15px 25px;
        background: #f8f9fa;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    #editOtherModal .btn-primary {
        background: #fbbf24;
        color: white;
        border: none;
        padding: 10px 24px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
    }

    #editOtherModal .btn-secondary {
        background: white;
        color: #555;
        border: 1px solid #ddd;
        padding: 10px 24px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
    }

    /* Dark Mode Overrides */
    body.dark-mode #editOtherModal .modal-card.wide-modal {
        background: #1e1e1e;
        color: #e0e0e0;
        border: 1px solid #333;
    }

    body.dark-mode #editOtherModal .modal-header {
        border-bottom: 1px solid #333;
    }

    body.dark-mode #editOtherModal .modal-header h2 {
        color: #e0e0e0;
    }

    body.dark-mode #editOtherModal .form-group label {
        color: #bbb;
    }

    body.dark-mode #editOtherModal .form-group input,
    body.dark-mode #editOtherModal .form-group select {
        background: #2b2b2b;
        border-color: #444;
        color: #e0e0e0;
    }

    body.dark-mode #editOtherModal .form-group input:focus,
    body.dark-mode #editOtherModal .form-group select:focus {
        border-color: #00ACB1;
        box-shadow: 0 0 0 3px rgba(0, 172, 177, 0.2);
    }

    body.dark-mode #editOtherModal .modal-footer {
        background: #1a1a1a;
        border-top: 1px solid #333;
    }

    body.dark-mode #editOtherModal .btn-secondary {
        background: #333;
        color: #e0e0e0;
        border-color: #444;
    }

    body.dark-mode #editOtherModal .btn-secondary:hover {
        background: #444;
        border-color: #555;
    }
</style>