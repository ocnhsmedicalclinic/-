<div id="addStudentModal" class="modal-overlay">
  <div class="modal-card wide-modal">
    <div class="modal-header">
      <h2><i class="fa-solid fa-user-graduate"></i> Add New Student</h2>
      <button class="close-btn" onclick="closeModal()">✕</button>
    </div>

    <form method="POST" action="add_student.php">
      <div class="modal-body">
        <div class="form-grid">
          <!-- Row 1 -->
          <div class="form-group">
            <label for="add_lrn">LRN Number</label>
            <input type="text" name="lrn" id="add_lrn" placeholder="Input LRN" autocomplete="off">
          </div>
          <div class="form-group span-2">
            <label for="add_curriculum">Curriculum <span class="required">*</span></label>
            <select name="curriculum" id="add_curriculum" required autocomplete="off">
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
            <label for="add_last_name">Last Name <span class="required">*</span></label>
            <input type="text" name="last_name" id="add_last_name" required autocomplete="off">
          </div>
          <div class="form-group">
            <label for="add_first_name">First Name <span class="required">*</span></label>
            <input type="text" name="first_name" id="add_first_name" required autocomplete="off">
          </div>
          <div class="form-group">
            <label for="add_middle_name">Middle Name</label>
            <input type="text" name="middle_name" id="add_middle_name" autocomplete="off">
          </div>

          <!-- Row 3 -->
          <div class="form-group">
            <label for="add_birth_date">Birth Date <span class="required">*</span></label>
            <input type="date" name="birth_date" id="add_birth_date" required autocomplete="off">
          </div>
          <div class="form-group">
            <label for="add_gender">Gender <span class="required">*</span></label>
            <select name="gender" id="add_gender" required autocomplete="off">
              <option value="">Select Gender</option>
              <option>Male</option>
              <option>Female</option>
            </select>
          </div>
          <div class="form-group">
            <label for="add_religion">Religion</label>
            <input type="text" name="religion" id="add_religion" autocomplete="off">
          </div>

          <!-- Row 4 -->
          <div class="form-group span-2">
            <label for="add_birth_place">Birth Place</label>
            <input type="text" name="birth_place" id="add_birth_place" autocomplete="off">
          </div>
          <div class="form-group">
            <label for="add_contact">Contact Number</label>
            <input type="text" name="contact" id="add_contact" autocomplete="off">
          </div>

          <!-- Row 5 -->
          <div class="form-group span-3">
            <label for="add_address">Address <span class="required">*</span></label>
            <input type="text" name="address" id="add_address"
              placeholder="House No., Street, Barangay, City/Municipality" required autocomplete="off">
          </div>

          <!-- Row 6 -->
          <div class="form-group span-3">
            <label for="add_guardian">Guardian <span class="required">*</span></label>
            <input type="text" name="guardian" id="add_guardian" placeholder="Guardian's Name" required
              autocomplete="off">
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-primary">Add Student</button>
      </div>
    </form>
  </div>
</div>

<style>
  /* Specific styles for this wide modal */
  #addStudentModal .modal-card.wide-modal {
    max-width: 900px;
    width: 95%;
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 90vh;
  }

  #addStudentModal .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 25px;
    border-bottom: 1px solid #eee;
    flex-shrink: 0;
  }

  #addStudentModal .modal-header h2 {
    margin: 0;
    font-size: 1.25rem;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  #addStudentModal .close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #777;
  }

  #addStudentModal form {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    flex: 1;
  }

  #addStudentModal .modal-body {
    padding: 20px 25px;
    overflow-y: auto;
    flex: 1;
  }

  #addStudentModal .form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
  }

  #addStudentModal .span-2 {
    grid-column: span 2;
  }

  #addStudentModal .span-3 {
    grid-column: span 3;
  }

  #addStudentModal .form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  #addStudentModal .form-group label {
    font-weight: 600;
    font-size: 0.85rem;
    color: #444;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  #addStudentModal .form-group input,
  #addStudentModal .form-group select {
    padding: 10px 12px;
    border: 1px solid #dde1e5;
    border-radius: 6px;
    font-size: 0.95rem;
    color: #333;
    transition: all 0.2s ease;
    background: #fff;
  }

  #addStudentModal .form-group input:focus,
  #addStudentModal .form-group select:focus {
    border-color: #00ACB1;
    box-shadow: 0 0 0 3px rgba(0, 172, 177, 0.1);
    outline: none;
  }

  #addStudentModal .modal-footer {
    padding: 15px 25px;
    background: #f8f9fa;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
  }

  #addStudentModal .btn-primary {
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

  #addStudentModal .btn-primary:hover {
    background: #008f94;
  }

  #addStudentModal .btn-secondary {
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

  #addStudentModal .btn-secondary:hover {
    background: #f1f1f1;
    border-color: #ccc;
  }

  .required {
    color: #d33;
    margin-left: 2px;
  }

  /* Dark Mode Overrides */
  body.dark-mode #addStudentModal .modal-card.wide-modal {
    background: #1e1e1e;
    color: #e0e0e0;
    border: 1px solid #333;
  }

  body.dark-mode #addStudentModal .modal-header {
    border-bottom: 1px solid #333;
  }

  body.dark-mode #addStudentModal .modal-header h2 {
    color: #e0e0e0;
  }

  body.dark-mode #addStudentModal .form-group label {
    color: #bbb;
  }

  body.dark-mode #addStudentModal .form-group input,
  body.dark-mode #addStudentModal .form-group select {
    background: #2b2b2b;
    border-color: #444;
    color: #e0e0e0;
  }

  body.dark-mode #addStudentModal .form-group input:focus,
  body.dark-mode #addStudentModal .form-group select:focus {
    border-color: #00ACB1;
    box-shadow: 0 0 0 3px rgba(0, 172, 177, 0.2);
  }

  body.dark-mode #addStudentModal .modal-footer {
    background: #1a1a1a;
    border-top: 1px solid #333;
  }

  body.dark-mode #addStudentModal .btn-secondary {
    background: #333;
    color: #e0e0e0;
    border-color: #444;
  }

  body.dark-mode #addStudentModal .btn-secondary:hover {
    background: #444;
    border-color: #555;
  }

  /* Responsive adjustments */
  @media (max-width: 768px) {
    #addStudentModal .form-grid {
      grid-template-columns: 1fr 1fr;
    }

    #addStudentModal .span-2,
    #addStudentModal .span-3 {
      grid-column: span 2;
    }
  }

  @media (max-width: 500px) {
    #addStudentModal .form-grid {
      grid-template-columns: 1fr;
    }

    #addStudentModal .span-2,
    #addStudentModal .span-3 {
      grid-column: span 1;
    }
  }
</style>