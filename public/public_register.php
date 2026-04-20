<?php
require_once "../config/db.php";

// Set security headers specifically for public page
setSecurityHeaders();

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="assets/img/ocnhs_logo.png">
    <title>Student Registration - Clinic System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00ACB1;
            --primary-dark: #008f94;
            --bg: #f5f7fb;
            --card-bg: #ffffff;
            --text: #333;
            --text-muted: #666;
            --border: #dde1e5;
            --shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            width: 100%;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            background: var(--primary);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .header p {
            opacity: 0.9;
            font-size: 1rem;
        }

        .form-body {
            padding: 40px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        @media (max-width: 600px) {
            .form-group.full-width {
                grid-column: span 1;
            }
        }

        label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #444;
        }

        .required {
            color: #e63946;
            margin-left: 2px;
        }

        input,
        select,
        textarea {
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            width: 100%;
            transition: all 0.3s ease;
            outline: none;
            text-transform: uppercase;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(0, 172, 177, 0.1);
        }

        .footer {
            padding: 0 40px 40px;
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0, 172, 177, 0.2);
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 172, 177, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .success-message {
            display: block;
            /* Removed none to allow parent container to control visibility */
            text-align: center;
            padding: 50px 25px;
        }

        .success-message i {
            font-size: 5rem;
            color: #2a9d8f;
            margin-bottom: 20px;
            animation: scaleIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }

            to {
                transform: scale(1);
            }
        }

        .success-message h2 {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #2a9d8f;
        }

        .success-message p {
            color: var(--text-muted);
            font-size: 1.1rem;
            line-height: 1.6;
        }
    </style>
</head>

<body>
    <div class="container" id="formContainer">
        <div class="header">
            <h1><i class="fas fa-user-graduate"></i> Student Registration</h1>
            <p>Please fill out the form below to register.</p>
        </div>

        <form id="publicAddStudentForm" class="form-body">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="form-grid">
                <!-- LRN -->
                <div class="form-group">
                    <label for="lrn">LRN Number</label>
                    <input type="text" id="lrn" name="lrn" placeholder="Input LRN" autocomplete="off">
                </div>

                <!-- Curriculum -->
                <div class="form-group">
                    <label for="curriculum">Curriculum <span class="required">*</span></label>
                    <select id="curriculum" name="curriculum" required>
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

                <!-- Last Name -->
                <div class="form-group">
                    <label for="last_name">Last Name <span class="required">*</span></label>
                    <input type="text" id="last_name" name="last_name" required autocomplete="family-name">
                </div>

                <!-- First Name -->
                <div class="form-group">
                    <label for="first_name">First Name <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name" required autocomplete="given-name">
                </div>

                <!-- Middle Name -->
                <div class="form-group">
                    <label for="middle_name">Middle Name</label>
                    <input type="text" id="middle_name" name="middle_name" autocomplete="additional-name">
                </div>

                <!-- Gender -->
                <div class="form-group">
                    <label for="gender">Gender <span class="required">*</span></label>
                    <select id="gender" name="gender" required autocomplete="sex">
                        <option value="">Select Gender</option>
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                </div>

                <!-- Birth Date -->
                <div class="form-group">
                    <label for="birth_date">Birth Date <span class="required">*</span></label>
                    <input type="date" id="birth_date" name="birth_date" required autocomplete="bday">
                </div>

                <!-- Birth Place -->
                <div class="form-group">
                    <label for="birth_place">Birth Place</label>
                    <input type="text" id="birth_place" name="birth_place" placeholder="City/Municipality"
                        autocomplete="address-level2">
                </div>

                <!-- Religion -->
                <div class="form-group">
                    <label for="religion">Religion</label>
                    <input type="text" id="religion" name="religion" autocomplete="off">
                </div>

                <!-- Contact -->
                <div class="form-group">
                    <label for="contact">Contact Number</label>
                    <input type="text" id="contact" name="contact" autocomplete="tel">
                </div>

                <!-- Guardian -->
                <div class="form-group">
                    <label for="guardian">Guardian <span class="required">*</span></label>
                    <input type="text" id="guardian" name="guardian" placeholder="Guardian's Name" required
                        autocomplete="name">
                </div>

                <!-- Address -->
                <div class="form-group full-width">
                    <label for="address">Address <span class="required">*</span></label>
                    <input type="text" id="address" name="address"
                        placeholder="House No., Street, Barangay, City/Municipality" required
                        autocomplete="street-address">
                </div>
            </div>

            <div style="margin-top: 40px;">
                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Submit Registration
                </button>
            </div>
        </form>
    </div>

    <div class="container" id="successContainer" style="display: none;">
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <h2>Registration Successful!</h2>
            <p>Thank you for registering. Your information has been saved successfully in our system.</p>

            <!-- STRICT INSTRUCTION BOX -->
            <div
                style="background: #fff3f3; padding: 25px; border-radius: 15px; margin: 30px 0; border: 3px solid #ff4d4d; box-shadow: 0 8px 20px rgba(255, 77, 77, 0.15); animation: pulse-border 2s infinite;">
                <p
                    style="color: #d32f2f; font-weight: 900; margin: 0; font-size: 1.4rem; text-transform: uppercase; letter-spacing: 1px;">
                    <i class="fas fa-exclamation-circle"></i> STRICT INSTRUCTION:
                </p>
                <p style="color: #333; font-weight: 700; margin-top: 15px; font-size: 1.2rem; line-height: 1.5;">
                    Please do the next step and ask the medical staff if you have any questions.
                </p>
            </div>
        </div>
    </div>

    <style>
        @keyframes pulse-border {
            0% {
                border-color: #ff4d4d;
                box-shadow: 0 0 0 0 rgba(255, 77, 77, 0.4);
            }

            70% {
                border-color: #ff1a1a;
                box-shadow: 0 0 0 10px rgba(255, 77, 77, 0);
            }

            100% {
                border-color: #ff4d4d;
                box-shadow: 0 0 0 0 rgba(255, 77, 77, 0);
            }
        }
    </style>

    <div class="container" id="alreadyRegisteredContainer" style="display: none;">
        <div class="success-message">
            <i class="fas fa-info-circle" style="color: #00ACB1;"></i>
            <h2>Already Registered</h2>
            <p>This device has already been used to submit a registration. Only one submission is allowed per
                student/device.</p>
            <p style="margin-top: 15px; font-weight: 600;">If you need to make changes, please approach the medical
                staff.</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // --- DEVICE LOCK LOGIC ---
        // Check if this device has already registered
        document.addEventListener('DOMContentLoaded', function () {
            if (localStorage.getItem('student_registered') === 'true') {
                document.getElementById('formContainer').style.display = 'none';
                document.getElementById('alreadyRegisteredContainer').style.display = 'block';
            }
        });

        document.getElementById('publicAddStudentForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            // Double check lock before submit
            if (localStorage.getItem('student_registered') === 'true') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Already Submitted',
                    text: 'You have already submitted a registration from this device.'
                });
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            const originalBtnContent = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

            const formData = new FormData(this);

            try {
                const response = await fetch('api/public_add_student.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    // Lock the device upon success
                    localStorage.setItem('student_registered', 'true');

                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Student registered successfully.',
                        confirmButtonColor: '#00ACB1',
                        timer: 3000,
                        timerProgressBar: true
                    }).then(() => {
                        document.getElementById('formContainer').style.display = 'none';
                        document.getElementById('successContainer').style.display = 'block';
                        window.scrollTo(0, 0);
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Registration Failed',
                        text: result.message || 'An error occurred. Please try again.',
                        confirmButtonColor: '#00ACB1'
                    });
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnContent;
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'System Error',
                    text: 'Unable to connect to the server. Please check your internet connection.',
                    confirmButtonColor: '#00ACB1'
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnContent;
            }
        });
    </script>
    <?php include 'assets/inc/console_suppress.php'; ?>
    <script>
        /**
         * UI SECURITY & DEVTOOLS PROTECTION
         */
        document.addEventListener('contextmenu', event => event.preventDefault());

        document.onkeydown = function (e) {
            if (e.keyCode == 123) return false;
            if (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) return false;
            if (e.ctrlKey && e.shiftKey && e.keyCode == 'C'.charCodeAt(0)) return false;
            if (e.ctrlKey && e.shiftKey && e.keyCode == 'J'.charCodeAt(0)) return false;
            if (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0)) return false;
        };
    </script>
</body>

</html>