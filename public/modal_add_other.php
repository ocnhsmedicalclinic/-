<?php
// modal_add_other.php
?>
<div id="addOtherModal" class="modal-overlay">
    <div class="modal-card wide-modal">
        <div class="modal-header">
            <h2><i class="fa-solid fa-user-plus"></i> Add New Record</h2>
            <button class="close-btn" onclick="closeAddOtherModal()">&times;</button>
        </div>

        <form action="others.php" method="POST">
            <input type="hidden" name="action" value="add_other">
            <div class="modal-body">
                <div class="form-grid">
                    <!-- Row 1 -->
                    <div class="form-group span-2">
                        <label for="add_name">Full Name</label>
                        <input type="text" name="name" id="add_name" placeholder="Last Name, First Name Middle Name"
                            autocomplete="name" required>
                    </div>
                    <div class="form-group">
                        <label for="add_birth_date">Date of Birth</label>
                        <input type="date" name="birth_date" id="add_birth_date"
                            onchange="calculateRealTimeAge(this.value, 'add_age_display')" autocomplete="off">
                    </div>

                    <!-- Row 2 -->
                    <div class="form-group">
                        <label for="add_age">Age (if birth date unknown)</label>
                        <input type="number" name="age" id="add_age_display" min="1" max="150" placeholder="Enter age"
                            autocomplete="off">
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
                    <div class="form-group">
                        <label for="add_sdo">SDO</label>
                        <input type="text" name="sdo" id="add_sdo" placeholder="e.g. SDO Olongapo" autocomplete="off">
                    </div>

                    <!-- Row 3 -->
                    <div class="form-group span-2">
                        <label for="add_address">Address</label>
                        <input type="text" name="address" id="add_address" placeholder="Complete Address"
                            autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label for="add_remarks">Remarks</label>
                        <input type="text" name="remarks" id="add_remarks" placeholder="Optional notes"
                            autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAddOtherModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Record</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* Specific styles for this wide modal */
    #addOtherModal .modal-card.wide-modal {
        max-width: 900px;
        width: 95%;
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: 90vh;
    }

    #addOtherModal form {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        flex: 1;
    }

    #addOtherModal .modal-body {
        padding: 20px 25px;
        overflow-y: auto;
        flex: 1;
    }

    #addOtherModal .form-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    #addOtherModal .span-2 {
        grid-column: span 2;
    }

    #addOtherModal .span-3 {
        grid-column: span 3;
    }

    #addOtherModal .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    #addOtherModal .form-group label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #444;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    #addOtherModal .form-group input,
    #addOtherModal .form-group select {
        padding: 10px 12px;
        border: 1px solid #dde1e5;
        border-radius: 6px;
        font-size: 0.95rem;
        color: #333;
        transition: all 0.2s ease;
        background: #fff;
    }

    #addOtherModal .form-group input:focus,
    #addOtherModal .form-group select:focus {
        border-color: #00ACB1;
        box-shadow: 0 0 0 3px rgba(0, 172, 177, 0.1);
        outline: none;
    }

    #addOtherModal .modal-footer {
        padding: 15px 25px;
        background: #f8f9fa;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }

    #addOtherModal .btn-primary {
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

    #addOtherModal .btn-primary:hover {
        background: #008f94;
    }

    #addOtherModal .btn-secondary {
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

    #addOtherModal .btn-secondary:hover {
        background: #f1f1f1;
        border-color: #ccc;
    }

    /* Dark Mode Overrides */
    body.dark-mode #addOtherModal .modal-card.wide-modal {
        background: #1e1e1e;
        color: #e0e0e0;
        border: 1px solid #333;
    }

    body.dark-mode #addOtherModal .modal-header {
        border-bottom: 1px solid #333;
    }

    body.dark-mode #addOtherModal .modal-header h2 {
        color: #e0e0e0;
    }

    body.dark-mode #addOtherModal .form-group label {
        color: #bbb;
    }

    body.dark-mode #addOtherModal .form-group input,
    body.dark-mode #addOtherModal .form-group select {
        background: #2b2b2b;
        border-color: #444;
        color: #e0e0e0;
    }

    body.dark-mode #addOtherModal .form-group input:focus,
    body.dark-mode #addOtherModal .form-group select:focus {
        border-color: #00ACB1;
        box-shadow: 0 0 0 3px rgba(0, 172, 177, 0.2);
    }

    body.dark-mode #addOtherModal .modal-footer {
        background: #1a1a1a;
        border-top: 1px solid #333;
    }

    body.dark-mode #addOtherModal .btn-secondary {
        background: #333;
        color: #e0e0e0;
        border-color: #444;
    }

    body.dark-mode #addOtherModal .btn-secondary:hover {
        background: #444;
        border-color: #555;
    }
</style>