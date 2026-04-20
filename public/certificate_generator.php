<?php
require_once "../config/db.php";
requireLogin();

// "sa doctor na role gumawa ka ng certificate generator para lang sa role nya"
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    $_SESSION['error_message'] = "Access Denied: Only Doctors can access the Certificate Generator.";
    header("Location: dashboard.php");
    exit();
}

$page_title = "Certificate Generator";
include "index_layout.php";
?>

<script>
    // Absolute enforcement of light mode for Certificate Generator
    document.addEventListener('DOMContentLoaded', function () {
        document.body.classList.remove('dark-mode');
        document.documentElement.style.background = 'white';

        // Also observe for changes to prevent other scripts from re-adding it
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'class' && document.body.classList.contains('dark-mode')) {
                    document.body.classList.remove('dark-mode');
                }
            });
        });
        observer.observe(document.body, { attributes: true });
    });
</script>

<style>
    .cert-container {
        display: flex;
        gap: 20px;
        margin-top: 20px;
    }

    .cert-sidebar {
        width: 280px;
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        height: calc(100vh - 120px);
        position: sticky;
        top: 20px;
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
    }

    .cert-list {
        flex: 1;
        overflow-y: auto;
        padding-right: 5px;
    }

    /* Custom Scrollbar for Sidebar */
    .cert-list::-webkit-scrollbar {
        width: 5px;
    }

    .cert-list::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .cert-list::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 10px;
    }

    .cert-list::-webkit-scrollbar-thumb:hover {
        background: #00ACB1;
    }

    .cert-sidebar h3 {
        margin-top: 0;
        font-size: 1.1rem;
        color: #333;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #eee;
    }

    .cert-btn {
        display: block;
        width: 100%;
        text-align: left;
        padding: 12px 15px;
        margin-bottom: 10px;
        border: none;
        background: #f8f9fa;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        color: #555;
        transition: 0.3s;
    }

    .cert-btn:hover {
        background: #e9ecef;
    }

    .cert-btn.active {
        background: #00ACB1;
        color: white;
        box-shadow: 0 4px 10px rgba(0, 172, 177, 0.3);
    }

    .cert-main {
        flex: 1;
        background: #f0f2f5;
        border-radius: 12px;
        border: 1px solid #ddd;
    }

    .controls {
        background: white;
        padding: 15px 20px;
        border-radius: 12px 12px 0 0;
        border-bottom: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .controls-title {
        flex: 1;
        min-width: 200px;
    }

    .controls-title strong {
        display: block;
        font-size: 1.1rem;
        color: #333;
    }

    .controls-title p {
        margin: 0;
        font-size: 12px;
        color: #666;
    }

    .controls-search {
        position: relative;
        flex: 1;
        min-width: 250px;
    }

    .search-box {
        position: relative;
        display: flex;
        align-items: center;
        width: 100%;
        border: 1.5px solid #00ACB1;
        border-radius: 25px;
        padding: 0 15px;
        background: white;
        transition: 0.3s;
        box-sizing: border-box;
    }

    .search-box i {
        color: #00ACB1;
        font-size: 14px;
        margin-right: 10px;
    }

    .search-box input {
        flex: 1;
        padding: 10px 0;
        border: none !important;
        background: transparent !important;
        font-size: 14px;
        outline: none;
        color: #333;
    }

    .search-box:focus-within {
        border-color: #008e91;
        box-shadow: 0 0 0 4px rgba(0, 172, 177, 0.15);
    }

    .controls-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .print-btn {
        background: #2ecc71;
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 14px;
        white-space: nowrap;
    }

    .print-btn:hover {
        background: #27ae60;
        transform: translateY(-1px);
    }

    .print-btn:active {
        transform: translateY(0);
    }

    #searchResults {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        margin-top: 5px;
    }

    .editable-input:hover {
        background: rgba(0, 172, 177, 0.03);
    }

    .editable-input:focus {
        background: rgba(0, 172, 177, 0.08);
        border-bottom: 2px solid #00ACB1;
    }

    .print-btn:hover {
        background: #27ae60;
    }

    /* Print Preview Area */
    .preview-area {
        justify-content: center;
        overflow: auto;
        /* Changed from overflow-y to auto for safe horizontal scaling */
        position: relative;
        -webkit-overflow-scrolling: touch;
    }

    .paper {
        background: white;
        width: 210mm;
        /* Standard A4 width */
        min-height: 297mm;
        /* Standard A4 height */
        padding: 20mm;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        position: relative;
        color: #000;
        font-family: Arial, sans-serif;
        transform-origin: top center;
        transition: transform 0.3s ease;
        break-after: page;
        /* Ensure each paper starts on a new page when printing */
    }

    .paper.legal-paper {
        width: 8.5in;
        height: 14in;
        min-height: 14in;
    }

    .multi-paper-container {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* Header Common Styles */
    .dp-header {
        text-align: center;
        margin-bottom: 30px;
        border-bottom: 2px solid #000;
        padding-bottom: 15px;
    }

    .dp-header img {
        width: 60px;
        height: 60px;
        object-fit: contain;
    }

    .dp-header h5 {
        margin: 5px 0 0;
        font-size: 14px;
        font-weight: normal;
    }

    .dp-header h3 {
        margin: 2px 0 0;
        font-size: 18px;
        font-weight: bold;
    }

    .dp-header p {
        margin: 0;
        font-size: 13px;
    }

    /* Title */
    .cert-title {
        text-align: center;
        font-weight: bold;
        font-size: 24px;
        margin: 20px 0 30px;
        text-decoration: underline;
        text-transform: uppercase;
        font-family: "Times New Roman", Times, serif;
    }

    .cert-title.rx {
        text-decoration: none;
        background: #000;
        color: #fff;
        padding: 5px;
        display: inline-block;
        margin-bottom: 10px;
        width: 100%;
        box-sizing: border-box;
    }

    /* Input Styling for Print */
    .editable-input {
        border: none;
        border-bottom: 1px solid #000;
        font-family: inherit;
        font-size: inherit;
        color: #000;
        background: transparent;
        border-radius: 0;
        padding: 0 5px;
        outline: none;
        transition: border-color 0.3s;
    }

    /* Print Override - Always Black on White */
    @media print {

        .paper,
        .paper * {
            background: white !important;
            color: black !important;
            border-color: black !important;
        }

        .editable-input {
            border-bottom: 1px solid black !important;
        }

        .no-underline,
        .editable-input.no-underline {
            border-bottom: none !important;
            border: none !important;
        }
    }

    .doctor-sig {
        margin-top: 80px;
        text-align: right;
    }

    .doctor-sig strong {
        display: block;
        font-size: 16px;
        text-decoration: underline;
    }

    /* Specific Form Styles */
    .hidden {
        display: none !important;
    }

    /* Footer for DepEd */
    .dp-footer {
        position: absolute;
        bottom: 20mm;
        left: 20mm;
        right: 20mm;
        border-top: 2px solid #000;
        padding-top: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
    }

    .dp-footer img {
        height: 40px;
        margin-right: 5px;
    }

    .checkbox-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 20px;
    }

    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Search results styles */
    .search-result-item {
        padding: 10px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
        background: #fff;
        color: #333;
        transition: 0.2s;
    }

    .search-result-item:hover {
        background: #f5f5f5;
    }

    /* Print Override - Always Black on White */

    @media print {

        /* Hide all UI elements */
        header,
        nav,
        footer,
        .cert-sidebar,
        .controls,
        .persistent-banner,
        .dark-mode-toggle {
            display: none !important;
            visibility: hidden !important;
        }

        /* Show only the necessary containers */
        body,
        .cert-container,
        .cert-main,
        .preview-area {
            visibility: visible !important;
            background: white !important;
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            width: 100% !important;
            height: auto !important;
            display: block !important;
            overflow: visible !important;
        }

        .preview-area {
            min-height: auto !important;
        }

        /* ONLY show the paper if its container is NOT hidden */
        .paper:not(.hidden) {
            visibility: visible !important;
            display: block !important;
            position: relative !important;
            margin: 0 auto !important;
            padding: 15mm !important;
            box-shadow: none !important;
            border: none !important;
            background: white !important;
            color: black !important;
            overflow: visible !important;
            transform: none !important;
            break-after: page;
        }

        #cert2:not(.hidden) {
            display: flex !important;
            flex-direction: column !important;
            gap: 8mm !important;
            padding: 10mm !important;
        }

        .paper:not(.hidden):last-child {
            break-after: auto !important;
        }

        .paper:not(.hidden) * {
            visibility: visible !important;
            color: black !important;
            background: transparent !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .hide-on-print,
        .hidden,
        .hidden .paper,
        .multi-paper-container.hidden {
            display: none !important;
            visibility: hidden !important;
        }

        .paper.legal-paper {
            width: 8.5in !important;
            height: 14in !important;
            padding: 0.25in !important;
        }

        .pad-grid input.editable-input,
        .pad-grid textarea.editable-input {
            border: none !important;
            border-bottom: 1px solid black !important;
            box-shadow: none !important;
            outline: none !important;
            background: transparent !important;
            color: black !important;
        }

        .pad-grid input.no-underline,
        .pad-grid .no-underline {
            border-bottom: none !important;
            border: none !important;
        }
    }

    .pad-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-rows: 1fr 1fr;
        width: 100%;
        height: 100%;
        box-sizing: border-box;
        border: 1px solid #000;
        background-color: #fff;
        gap: 0;
    }

    .pad-item {
        box-sizing: border-box;
        background-color: #fff;
        padding: 3mm 6mm;
        display: flex;
        flex-direction: column;
        position: relative;
        border: 1px solid #000;
        height: 100%;
        overflow: hidden;
    }

    /* Responsive Styles */
    @media (max-width: 992px) {
        .cert-container {
            flex-direction: column;
        }

        .cert-sidebar {
            width: 100%;
            height: auto;
            position: static;
            display: block;
            overflow-x: auto;
            white-space: nowrap;
            padding: 15px;
            -webkit-overflow-scrolling: touch;
        }

        .cert-list {
            display: flex;
            gap: 10px;
            overflow-y: visible;
        }

        .cert-sidebar h3 {
            display: none;
        }

        .cert-btn {
            margin-bottom: 0;
            width: auto;
            flex-shrink: 0;
        }

        .controls-title,
        .controls-search,
        .controls-actions {
            width: 100%;
            min-width: unset;
        }

        .controls-actions {
            flex-direction: row;
            gap: 10px;
        }

        .controls-actions button {
            flex: 1;
            padding: 14px;
        }

        .preview-area {
            padding: 10px;
            overflow-x: hidden;
        }

        #saveToRecordsBtn {
            margin-right: 0 !important;
        }
    }

    @media (max-width: 576px) {
        .controls-actions {
            flex-direction: column;
        }

        .controls-actions button {
            width: 100%;
        }

        .preview-area {
            padding: 5px;
            /* Reduced from 15px */
            overflow-x: auto;
            display: block;
            -webkit-overflow-scrolling: touch;
        }

        .paper {
            margin: 0 auto;
            padding: 5mm !important;
            /* Reduced paper padding to make content larger */
            transform-origin: top center;
        }
    }

    @media (max-width: 768px) {
        .paper {
            padding: 10mm !important;
        }

        .preview-area {
            background: #cbd5e1;
            /* Slightly different background to differentiate preview */
            display: block;
            min-height: unset;
        }

        .paper {
            margin: 0 auto;
        }
    }

    /* Signature Styles */
    .signature-area {
        width: 150px;
        height: 60px;
        border-bottom: 1px dashed #ccc;
        margin: 0 auto 5px;
        position: relative;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }

    .signature-area:hover {
        background: rgba(0, 172, 177, 0.05);
    }

    .signature-area img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        position: relative;
        z-index: 2;
    }

    .signature-placeholder {
        position: absolute;
        font-size: 10px;
        color: #999;
        z-index: 1;
        pointer-events: none;
    }

    /* Modal Styling */
    .sig-modal {
        display: none;
        position: fixed;
        z-index: 99999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        align-items: center;
        justify-content: center;
    }

    .sig-modal-content {
        background-color: white;
        padding: 20px;
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    #sig-canvas {
        border: 2px solid #ddd;
        border-radius: 8px;
        cursor: crosshair;
        background: #fafafa;
        touch-action: none;
    }

    .sig-actions {
        margin-top: 15px;
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    .sig-btn {
        padding: 8px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: 0.2s;
    }

    .sig-btn-clear {
        background: #f1f1f1;
        color: #333;
    }

    .sig-btn-save {
        background: #00ACB1;
        color: white;
    }

    .sig-btn-cancel {
        background: #fee2e2;
        color: #ef4444;
    }

    @media print {
        .signature-area {
            border-bottom: none !important;
        }

        .signature-placeholder {
            display: none !important;
        }
    }
</style>

<div class="cert-container">
    <div class="cert-sidebar">
        <h3><i class="fa-solid fa-file-medical"></i> Templates</h3>
        <div class="cert-list">
            <button class="cert-btn active" data-target="cert1" onclick="switchTab('cert1', this)">Medical Certificate
                (Fit)</button>
            <button class="cert-btn" data-target="cert2" onclick="switchTab('cert2', this)">Medical Certificate
                (Sick)</button>
            <button class="cert-btn" data-target="cert3" onclick="switchTab('cert3', this)">Laboratory Request</button>
            <button class="cert-btn" data-target="cert4" onclick="switchTab('cert4', this)">Prescription Pad</button>
        </div>
    </div>

    <div class="cert-main">
        <div class="controls">
            <div class="controls-title">
                <strong id="tab-title">Medical Certificate (Fitness)</strong>
                <p>Fill out the fields inline and click Print.</p>
            </div>

            <div class="controls-search">
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" id="patientSearch" placeholder="Search Student or Employee..."
                        autocomplete="off">
                </div>
                <div id="searchResults"></div>
            </div>

            <div class="controls-actions">
                <button id="saveToRecordsBtn" class="print-btn" onclick="saveToMedicalRecords()">
                    <i class="fa-solid fa-save"></i> <span>Save Record</span>
                </button>
                <button class="print-btn" onclick="window.print()" style="background:#00ACB1;">
                    <i class="fa-solid fa-print"></i> <span>Print</span>
                </button>
            </div>
        </div>

        <div class="preview-area">
            <!-- TEMPLATE 1: MED CERT (FIT) -->
            <div id="cert1" class="paper" style="padding-top: 10mm;">
                <div class="dp-header"
                    style="border-bottom: 1px solid #000; padding-bottom: 15px; margin-bottom: 30px;">
                    <img src="assets/img/DepEd-logo.png" alt="DepEd Logo" onerror="this.src='assets/img/LOGO.png'"
                        style="width: 55px; height: 55px;">
                    <h5
                        style="margin: 5px 0 0; font-size: 12px; font-weight: normal; font-family: 'Old English Text MT', 'Times New Roman', serif;">
                        Republic of the Philippines</h5>
                    <h3
                        style="margin: 2px 0 0; font-size: 20px; font-weight: bold; font-family: 'Old English Text MT', 'Times New Roman', serif;">
                        Department of Education</h3>
                    <p style="margin: 0; font-size: 11px; font-weight: bold;">Region III</p>
                    <p style="margin: 0; font-size: 11px;"><strong>OLONGAPO CITY NATIONAL HIGH SCHOOL</strong></p>
                </div>

                <div style="text-align: right; margin-bottom: 10px; margin-right: 50px;">
                    <br>
                    <div style="display: inline-block; text-align: center; width: 150px;">
                        <input type="text" class="editable-input" value="" data-field="date"
                            style="width: 100%; text-align: center; font-size: 14px; border-bottom: 1px solid #000; padding-bottom: 2px;"><br>
                        <span style="font-size: 11px; font-weight: bold;">Date</span>
                    </div>
                </div>

                <div class="cert-title"
                    style="font-family: 'Brush Script MT', 'Lucida Handwriting', cursive; font-size: 25px; text-decoration: underline; text-decoration-thickness: 1px; text-underline-offset: 4px; margin-bottom: 50px;">
                    Medical Certificate
                </div>

                <div style="margin-top: 20px; font-size: 12px; line-height: 1.8; padding: 0 30px;">
                    <strong style="font-size: 12px;">To Whom It May Concern:</strong><br><br>
                    <div style="padding-left: 50px;">
                        This is to certify that I have seen and examined Mr./Ms.
                        <input type="text" class="editable-input" style="width: 200px; text-align: center;"
                            placeholder="Patient Name" data-field="name">,
                        <input type="text" class="editable-input" style="width: 40px; text-align: center;"
                            placeholder="Age" data-field="age">
                        from <input type="text" class="editable-input" style="width: 240px; text-align: center;"
                            placeholder="Address / Grade" data-field="address">
                        and is found out to be physically,<br> mentally and emotionally fit to perform any strenuous
                        activities
                        and exercises.
                        <br><br>
                        This certification is being issued to
                        <input type="text" class="editable-input" style="width: 400px; text-align: center;"
                            placeholder="Purpose/Event"> for
                        the
                        <input type="text" class="editable-input"
                            style="width: 100%; margin-top: 5px; text-align: center;"
                            placeholder="Specific event details"><br>
                        to be held at <input type="text" class="editable-input"
                            style="width: 420px; margin-top: 5px; text-align: center;" placeholder="Location">
                        on <input type="text" class="editable-input"
                            style="width: 150px; margin-top: 5px; text-align: center;" placeholder="Date(s)">.
                    </div>
                </div>

                <div style="margin-top: 50px; display: flex; justify-content: flex-end; padding-right: 30px;">
                    <div class="doctor-sig" style="text-align: center; width: 280px; font-size: 12px; margin-top: 0;">
                        <div class="signature-area" onclick="openSignatureModal(this)">
                            <span class="signature-placeholder">Click to Sign</span>
                            <img src="" class="sig-display" style="display: none;">
                        </div>
                        <strong style="display: block; font-size: 12px;">BERNADETTE B. CANLUBO, M.D</strong>
                        License No. 0103586<br>
                        Medical Officer III
                    </div>
                </div>

                <div class="dp-footer" style="border-top: 1px solid #000; padding-top: 10px; margin-bottom: 0;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <img src="assets/img/DepEd-logo.png" alt="DepED" style="height: 55px;"
                            onerror="this.src='assets/img/LOGO.png'">
                        <img src="assets/img/deped-matatag-logos.png" alt="Bagong Pilipinas" style="height: 55px;"
                            onerror="this.style.display='none'">
                        <img src="assets/img/ocnhs_logo.png" alt="OCNHS Logo" style="height: 55px;">
                    </div>
                    <div style="text-align: left; line-height: 1.2; font-size: 10px;">
                        <strong>Address:</strong> Corner 14th St., Rizal Ave. East Tapinac, Olongapo City<br>
                        <strong>Contact No.:</strong> (047) 223-3744<br>
                        <strong>Email Address:</strong> 301051@deped.gov.ph<br>
                        <i style="font-weight:bold;">"SDO Olongapo City: Towards a Culture of Excellence and
                            Character"</i>
                    </div>
                </div>
            </div>

            <!-- TEMPLATE 2: MED CERT (SICK) -->
            <div id="cert2" class="paper hidden"
                style="padding: 10mm 15mm; display: flex; flex-direction: column; gap: 10mm;">
                <?php for ($k = 0; $k < 2; $k++): ?>
                    <div class="pad-item"
                        style="border: 2px solid #000; padding: 5mm 10mm; display: flex; flex-direction: column; box-sizing: border-box; flex: 1; min-height: 0;">
                        <div class="dp-header"
                            style="margin-bottom: 5px; padding-bottom: 5px; transform: scale(0.9); transform-origin: top center;">
                            <img src="assets/img/DepEd-logo.png" alt="DepEd Logo" style="width: 50px; height: 50px;"
                                onerror="this.src='assets/img/LOGO.png'">
                            <h5 style="font-size: 11px; margin: 2px 0 0;">Republic of the Philippines</h5>
                            <h3 style="font-size: 14px; margin: 1px 0 0;">Department of Education</h3>
                            <p style="font-size: 10px; margin: 0;">Region III</p>
                            <p style="font-size: 10px; margin: 0;">SCHOOLS DIVISION OFFICE OF OLONGAPO CITY</p>
                            <p style="font-size: 10px; margin: 0;"><strong>OLONGAPO CITY NATIONAL HIGH SCHOOL</strong></p>
                        </div>

                        <div class="cert-title"
                            style="margin-top:0; margin-bottom: 10px; font-size: 18px; text-align: center; font-weight: bold;">
                            MEDICAL CERTIFICATE</div>

                        <div style="text-align: right; margin-bottom: 10px; font-size: 12px;">
                            Date: <input type="text" class="editable-input" value="" data-field="date"
                                style="width: 120px;">
                        </div>

                        <div style="font-size: 12px; line-height: 1.6; flex-grow: 1;">
                            <strong>TO WHOM IT MAY CONCERN:</strong><br>
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;This is to certify that Mr./Mrs./Ms.
                            <input type="text" class="editable-input" style="width: 200px; text-align: center;"
                                placeholder="Patient Name" data-field="name">,
                            <input type="text" class="editable-input" style="width: 30px; text-align: center;"
                                placeholder="Age" data-field="age">
                            years old
                            has been examined and found out to be suffering from
                            <input type="text" class="editable-input" style="width: 250px; text-align: center;"
                                placeholder="Illness / Disease">
                            advised to rest from <input type="text" class="editable-input"
                                style="width: 100px; text-align: center;" placeholder="Start Date">
                            to <input type="text" class="editable-input" style="width: 100px; text-align: center;"
                                placeholder="End Date">.
                            <br>
                            <strong>DIAGNOSIS:</strong><br>
                            <input type="text" class="editable-input"
                                style="width: 100%; margin-bottom: 5px; text-align: center;"
                                placeholder="Enter diagnosis details..."><br>

                            Patient is fit to resume work/class on <input type="text" class="editable-input"
                                style="width: 180px; text-align: center;" placeholder="Return Date">.
                        </div>

                        <div class="doctor-sig"
                            style="margin-top: 5px; align-self: flex-end; text-align: center; font-size: 11px;">
                            <div class="signature-area" style="width: 120px; height: 50px;"
                                onclick="openSignatureModal(this)">
                                <span class="signature-placeholder">Click to Sign</span>
                                <img src="" class="sig-display" style="display: none;">
                            </div>
                            <strong style="display: block; text-decoration: underline;">BERNADETTE CANLUBO, M.D.</strong>
                            Medical Officer III<br>
                            PRC License No. 0103586
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- TEMPLATE 3: LAB REQUEST -->
            <div id="cert3" class="paper legal-paper hidden" style="padding: 5mm;">
                <div class="pad-grid">
                    <?php for ($j = 0; $j < 4; $j++): ?>
                        <div class="pad-item" style="padding: 2mm 5mm;">

                            <!-- Header -->
                            <!-- Header -->
                            <div class="dp-header"
                                style="margin-bottom: 3px; text-align: center; border: none; padding-bottom: 0;">
                                <img src="assets/img/Deped-logo.png" style="width: 35px; height: 35px;"
                                    onerror="this.src='assets/img/LOGO.png'">
                                <div style="margin-top: 2px;">
                                    <h5 style="margin:0; font-size:7.5px; font-weight: normal; line-height: 1.1;">Republic
                                        of the Philippines</h5>
                                    <h3 style="margin:0; font-size:9.5px; line-height: 1.1;">Department of Education</h3>
                                    <p style="margin:0; font-size:7.5px; line-height: 1.1;">Region III</p>
                                    <p style="margin:0; font-size:9px; font-weight: bold; line-height: 1.1;">SCHOOLS
                                        DIVISION OFFICE OF OLONGAPO CITY</p>
                                    <p style="margin:0; font-size:9.5px; font-weight: bold; line-height: 1.1;">OLONGAPO
                                        CITY NATIONAL HIGH SCHOOL</p>
                                </div>
                            </div>

                            <div
                                style="border-top: 2px solid #000; border-bottom: 2px solid #000; text-align: center; font-weight: bold; font-size: 10px; padding: 2px 0; margin: 3px 0;">
                                LABORATORY REQUEST
                            </div>

                            <!-- Patient Info -->
                            <table class="no-border"
                                style="width: 100%; border-collapse: collapse; font-size: 9.5px; margin-bottom: 3px; border: none !important;">
                                <tr style="border: none !important;">
                                    <td style="font-weight:bold; width: 45px; border: none !important;">Date:</td>
                                    <td
                                        style="border: none !important; border-bottom: 1px solid #000 !important; width: 130px;">
                                        <input type="text" class="editable-input no-underline"
                                            style="width: 100%; border: none !important; padding: 0; border-bottom: none !important;"
                                            value="" data-field="date">
                                    </td>
                                    <td style="font-weight:bold; width: 65px; padding-left: 10px; border: none !important;">
                                        Age/Sex:</td>
                                    <td style="border: none !important; border-bottom: 1px solid #000 !important;">
                                        <input type="text" class="editable-input no-underline"
                                            style="width: 100%; border: none !important; padding: 0; border-bottom: none !important;"
                                            data-field="age_gender">
                                    </td>
                                </tr>
                                <tr style="border: none !important;">
                                    <td style="font-weight:bold; border: none !important; padding-top: 2px;">Name:</td>
                                    <td colspan="3"
                                        style="border: none !important; border-bottom: 1px solid #000 !important; padding-top: 2px;">
                                        <input type="text" class="editable-input no-underline"
                                            style="width: 100%; border: none !important; padding: 0; border-bottom: none !important;"
                                            data-field="name">
                                    </td>
                                </tr>
                                <tr style="border: none !important;">
                                    <td style="font-weight:bold; border: none !important; padding-top: 2px;">Address:</td>
                                    <td colspan="3"
                                        style="border: none !important; border-bottom: 1px solid #000 !important; padding-top: 2px;">
                                        <input type="text" class="editable-input no-underline"
                                            style="width: 100%; border: none !important; padding: 0; border-bottom: none !important;"
                                            data-field="address">
                                    </td>
                                </tr>
                            </table>

                            <div style="font-weight:bold; font-size: 10px; margin-bottom: 2px;">Request for:</div>

                            <!-- Single-column checkbox list -->
                            <div
                                style="flex: 1; min-height: 0; overflow: hidden; font-size: 10px; line-height: 1.5; margin-left: 10px;">
                                <div style="display: flex; align-items: center; margin-bottom: 1px;">
                                    <input type="checkbox"
                                        style="width: 12px; height: 12px; margin-right: 8px; cursor: pointer; accent-color: #000;">
                                    Chest X-ray
                                </div>
                                <div style="display: flex; align-items: center; margin-bottom: 1px;">
                                    <input type="checkbox"
                                        style="width: 12px; height: 12px; margin-right: 8px; cursor: pointer; accent-color: #000;">
                                    ECG
                                </div>
                                <div style="display: flex; align-items: center; margin-bottom: 1px;">
                                    <input type="checkbox"
                                        style="width: 12px; height: 12px; margin-right: 8px; cursor: pointer; accent-color: #000;">
                                    Urinalysis
                                </div>
                                <div style="display: flex; align-items: center; margin-bottom: 1px;">
                                    <input type="checkbox"
                                        style="width: 12px; height: 12px; margin-right: 8px; cursor: pointer; accent-color: #000;">
                                    Fecalysis
                                </div>
                                <div style="display: flex; align-items: center; margin-bottom: 1px;">
                                    <input type="checkbox"
                                        style="width: 12px; height: 12px; margin-right: 8px; cursor: pointer; accent-color: #000;">
                                    Sputum Microscopy
                                </div>
                                <div style="display: flex; align-items: center; margin-bottom: 1px;">
                                    <input type="checkbox"
                                        style="width: 12px; height: 12px; margin-right: 8px; cursor: pointer; accent-color: #000;">
                                    Complete Blood Count (CBC)
                                </div>
                                <div style="display: flex; align-items: center; margin-bottom: 1px;">
                                    <input type="checkbox"
                                        style="width: 12px; height: 12px; margin-right: 8px; cursor: pointer; accent-color: #000;">
                                    Blood Typing
                                </div>
                                <div style="display: flex; align-items: center; margin-bottom: 1px;">
                                    <input type="checkbox"
                                        style="width: 12px; height: 12px; margin-right: 8px; cursor: pointer; accent-color: #000;">
                                    Blood Chemistry
                                </div>
                                <!-- Nested Blood Chem in a single vertical series with compact spacing -->
                                <div
                                    style="margin-left: 20px; font-size: 10px; display: flex; flex-direction: column; gap: 0px; line-height: 1.05;">
                                    <div style="display: flex; align-items: center;">
                                        <input type="checkbox"
                                            style="width: 10px; height: 10px; margin-right: 6px; cursor: pointer; accent-color: #000;">
                                        Fasting Blood Sugar
                                    </div>
                                    <div style="display: flex; align-items: center;">
                                        <input type="checkbox"
                                            style="width: 10px; height: 10px; margin-right: 6px; cursor: pointer; accent-color: #000;">
                                        Blood Urea Nitrogen
                                    </div>
                                    <div style="display: flex; align-items: center;">
                                        <input type="checkbox"
                                            style="width: 10px; height: 10px; margin-right: 6px; cursor: pointer; accent-color: #000;">
                                        Creatinine
                                    </div>
                                    <div style="display: flex; align-items: center;">
                                        <input type="checkbox"
                                            style="width: 10px; height: 10px; margin-right: 6px; cursor: pointer; accent-color: #000;">
                                        Total Cholesterol
                                    </div>
                                    <div style="display: flex; align-items: center;">
                                        <input type="checkbox"
                                            style="width: 10px; height: 10px; margin-right: 6px; cursor: pointer; accent-color: #000;">
                                        HDL/LDL
                                    </div>
                                    <div style="display: flex; align-items: center;">
                                        <input type="checkbox"
                                            style="width: 10px; height: 10px; margin-right: 6px; cursor: pointer; accent-color: #000;">
                                        Triglycerides
                                    </div>
                                    <div style="display: flex; align-items: center;">
                                        <input type="checkbox"
                                            style="width: 10px; height: 10px; margin-right: 6px; cursor: pointer; accent-color: #000;">
                                        Uric Acid
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; margin-top: 1px;">
                                    <input type="checkbox"
                                        style="width: 12px; height: 12px; margin-right: 8px; cursor: pointer; accent-color: #000;">
                                    Drug Test
                                </div>
                                <div style="display: flex; align-items: center; margin-top: 1px;">
                                    <input type="checkbox"
                                        style="width: 12px; height: 12px; margin-right: 8px; cursor: pointer; accent-color: #000;">
                                    Psycho Test
                                </div>
                                <div style="display: flex; align-items: center; margin-top: 1px;">
                                    <input type="checkbox"
                                        style="width: 12px; height: 12px; margin-right: 8px; cursor: pointer; accent-color: #000;">
                                    Others:
                                    <input type="text" class="editable-input"
                                        style="flex: 1; border-bottom: 1px solid #000 !important; font-size: 10px; margin-left: 5px;">
                                </div>
                            </div>

                            <div class="doctor-sig"
                                style="text-align: center; font-size: 10px; align-self: flex-end; width: 100%; margin-top: 4px;">
                                <div style="position: relative; display: inline-block;">
                                    <div class="signature-area" style="width: 100px; height: 40px; border-bottom: none;"
                                        onclick="openSignatureModal(this)">
                                        <span class="signature-placeholder" style="bottom: -5px;">Sign</span>
                                        <img src="" class="sig-display"
                                            style="width: 100px; position: absolute; left: 50%; bottom: 0; transform: translateX(-50%); z-index: 1; display: none;">
                                    </div>
                                    <strong
                                        style="text-decoration: underline; font-size: 11px; position: relative; z-index: 2;">BERNADETTE
                                        CANLUBO, M.D.</strong>
                                </div>
                                <div style="font-size: 9.5px;">Medical Officer III</div>
                                <div style="font-size: 8.5px;">PRC License No. 0103586</div>
                            </div>

                            <!-- Footer -->
                            <div
                                style="margin-top: 4px; border-top: 1px solid #000; padding-top: 3px; display: flex; align-items: center; font-size: 7.5px; line-height: 1.1;">
                                <div style="display: flex; gap: 6px; align-items: center; margin-right: 10px;">
                                    <img src="assets/img/ocnhs_logo.png" style="height: 25px;">
                                </div>
                                <div style="flex: 1; font-size: 7px;">
                                    <strong>Address:</strong> Corner 14th St., Rizal Ave. East Tapinac, Olongapo City<br>
                                    <strong>Contact No.:</strong> (047) 223-3744 | <strong>Email Address:</strong>
                                    301051@deped.gov.ph<br>
                                    <div style="font-weight:bold; text-align: center; margin-top: 1px; font-style: italic;">
                                        "SDO Olongapo City: Towards a Culture of Excellence"</div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- TEMPLATE 4: PRESCRIPTION PAD -->
            <div id="cert4" class="paper legal-paper hidden" style="padding: 5mm;">
                <div class="pad-grid">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="pad-item" style="padding: 3mm 6mm;">

                            <!-- Header -->
                            <!-- Header -->
                            <div class="dp-header"
                                style="margin-bottom: 5px; text-align: center; border: none; padding-bottom: 0;">
                                <img src="assets/img/Deped-logo.png" style="width: 45px; height: 45px;"
                                    onerror="this.src='assets/img/LOGO.png'">
                                <div style="margin-top: 2px;">
                                    <h5 style="margin:0; font-size:8.5px; font-weight: normal; line-height: 1.1;">Republic
                                        of the Philippines</h5>
                                    <h3 style="margin:0; font-size:10.5px; line-height: 1.1;">Department of Education</h3>
                                    <p style="margin:0; font-size:8.5px; line-height: 1.1;">Region III</p>
                                    <p style="margin:0; font-size:10px; font-weight: bold; line-height: 1.1;">SCHOOLS
                                        DIVISION OFFICE OF OLONGAPO CITY</p>
                                    <p style="margin:0; font-size:10.5px; font-weight: bold; line-height: 1.1;">OLONGAPO
                                        CITY NATIONAL HIGH SCHOOL</p>
                                </div>
                            </div>

                            <div
                                style="border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; text-align: center; font-weight: bold; font-size: 11px; padding: 2px 0; margin: 3px 0;">
                                PRESCRIPTION PAD
                            </div>

                            <!-- Patient Info -->
                            <table class="no-border"
                                style="width: 100%; border-collapse: collapse; font-size: 10.5px; margin-bottom: 5px; border: none !important;">
                                <tr style="border: none !important;">
                                    <td style="font-weight:bold; width: 45px; border: none !important;">DATE:</td>
                                    <td style="border: none !important; border-bottom: none !important;">
                                        <input type="text" class="editable-input no-underline"
                                            style="width: 100%; border: none !important; border-bottom: none !important; padding: 0;"
                                            value="" data-field="date">
                                    </td>
                                    <td style="font-weight:bold; width: 65px; padding-left: 10px; border: none !important;">
                                        AGE/SEX:</td>
                                    <td style="border: none !important; border-bottom: none !important;">
                                        <input type="text" class="editable-input no-underline"
                                            style="width: 100%; border: none !important; border-bottom: none !important; padding: 0;"
                                            data-field="age_gender">
                                    </td>
                                </tr>
                                <tr style="border: none !important;">
                                    <td style="font-weight:bold; border: none !important; padding-top: 2px;">NAME:</td>
                                    <td colspan="3"
                                        style="border: none !important; border-bottom: none !important; padding-top: 2px;">
                                        <input type="text" class="editable-input no-underline"
                                            style="width: 100%; border: none !important; border-bottom: none !important; padding: 0;"
                                            data-field="name">
                                    </td>
                                </tr>
                            </table>

                            <div
                                style="font-family: 'Times New Roman', serif; font-size: 26px; font-weight: bold; margin: 8px 0 0 5px;">
                                Rx</div>
                            <textarea class="editable-input"
                                style="width: 100%; flex: 1; min-height: 100px; font-size: 13px; resize: none; border: none !important; line-height: 1.6; padding: 5px;"></textarea>

                            <div class="doctor-sig"
                                style="text-align: center; font-size: 10px; align-self: flex-end; width: 100%; margin-top: 10px;">
                                <div style="position: relative; display: inline-block;">
                                    <div class="signature-area" style="width: 100px; height: 40px; border-bottom: none;"
                                        onclick="openSignatureModal(this)">
                                        <span class="signature-placeholder" style="bottom: -5px;">Sign</span>
                                        <img src="" class="sig-display"
                                            style="width: 100px; position: absolute; left: 50%; bottom: 0; transform: translateX(-50%); z-index: 1; display: none;">
                                    </div>
                                    <strong
                                        style="text-decoration: underline; font-size: 11.5px; position: relative; z-index: 2;">BERNADETTE
                                        CANLUBO, M.D.</strong>
                                </div>
                                <div style="font-size: 10px;">Medical Officer III</div>
                                <div style="font-size: 9px;">PRC License No. 0103586</div>
                            </div>

                            <!-- Footer -->
                            <div
                                style="margin-top: 8px; border-top: 1px solid #000; padding-top: 4px; display: flex; align-items: center; font-size: 8px; line-height: 1.2;">
                                <div style="display: flex; gap: 6px; align-items: center; margin-right: 15px;">
                                    <img src="assets/img/ocnhs_logo.png" style="height: 28px;">
                                </div>
                                <div style="flex: 1; font-size: 7.5px;">
                                    <strong>Address:</strong> Corner 14th St., Rizal Ave. East Tapinac, Olongapo City<br>
                                    <strong>Contact No.:</strong> (047) 223-3744 | <strong>Email Address:</strong>
                                    301051@deped.gov.ph<br>
                                    <div style="font-weight:bold; text-align: center; margin-top: 1px; font-style: italic;">
                                        "SDO Olongapo City: Towards a Culture of Excellence"</div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    function switchTab(tabId, btn) {
        // Hide all papers and multi-containers
        document.querySelectorAll('.paper').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.multi-paper-container').forEach(el => el.classList.add('hidden'));

        // Show selected
        const target = document.getElementById(tabId);
        if (target) {
            target.classList.remove('hidden');
        }

        // Update active button
        document.querySelectorAll('.cert-btn').forEach(el => el.classList.remove('active'));
        btn.classList.add('active');

        // Update Title
        document.getElementById('tab-title').innerText = btn.innerText;

        // Dynamic print style for legal
        let printStyle = document.getElementById('dynamic-print-style');
        if (!printStyle) {
            printStyle = document.createElement('style');
            printStyle.id = 'dynamic-print-style';
            document.head.appendChild(printStyle);
        }

        if (tabId === 'cert4' || tabId === 'cert3') {
            printStyle.innerHTML = '@page { size: legal; margin: 0; }';
        } else {
            printStyle.innerHTML = '@page { size: auto; margin: 0; }';
        }

        // Delay scaling slightly to ensure rendering is complete
        setTimeout(scalePaper, 50);
    }

    function scalePaper() {
        const previewArea = document.querySelector('.preview-area');
        if (!previewArea) return;

        const paper = document.querySelector('.paper:not(.hidden)');
        if (!paper) return;

        // Reset scale first to get actual width
        paper.style.transform = 'scale(1)';
        paper.style.marginBottom = '0';
        paper.style.transformOrigin = 'top center'; // Set transform origin for better alignment

        // Optimized spacing for mobile
        const isMobile = window.innerWidth <= 576;
        const padding = isMobile ? 10 : 40;
        const containerWidth = previewArea.clientWidth - padding;
        const paperWidth = paper.offsetWidth;

        if (containerWidth < paperWidth) {
            const scale = containerWidth / paperWidth;
            paper.style.transform = `scale(${scale})`;

            // Adjust margin to account for collapsed space due to scale
            const scaledHeight = paper.offsetHeight * scale;
            const heightDiff = paper.offsetHeight - scaledHeight;
            paper.style.marginBottom = `-${heightDiff}px`;

            // Ensure paper stays centered after scaling if it's smaller than container
            paper.style.marginLeft = 'auto';
            paper.style.marginRight = 'auto';
        } else {
            paper.style.transform = 'scale(1)';
            paper.style.marginBottom = '0';
            paper.style.marginLeft = 'auto';
            paper.style.marginRight = 'auto';
        }
    }

    // Handle window resize for scaling
    window.addEventListener('resize', scalePaper);

    // Initialize upon load
    window.addEventListener('load', () => {
        switchTab('cert1', document.querySelector('.cert-btn.active'));
        scalePaper();
    });

    // Search Logic
    let currentPatient = null;
    const searchInput = document.getElementById('patientSearch');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout;

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch(`api/search_patients.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    searchResults.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(patient => {
                            const div = document.createElement('div');
                            div.className = 'search-result-item';
                            div.innerHTML = `<strong style="color: inherit;">${patient.name}</strong> <span style="font-size: 12px; color: #666;">(${patient.type})</span>`;

                            div.addEventListener('click', () => {
                                fillPatientData(patient);
                                searchResults.style.display = 'none';
                                searchInput.value = '';
                            });
                            searchResults.appendChild(div);
                        });
                        searchResults.style.display = 'block';
                    } else {
                        searchResults.innerHTML = '<div style="padding: 10px; color: #999;">No results found</div>';
                        searchResults.style.display = 'block';
                    }
                })
                .catch(err => console.error(err));
        }, 300);
    });

    // Hide search results when clicking outside
    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    let activePadIndex = null;

    // Track which pad was last clicked so we can insert there
    document.addEventListener('click', function (e) {
        let padItem = e.target.closest('.pad-item');
        if (padItem) {
            let paper = padItem.closest('.paper');
            if (paper) {
                let allPads = paper.querySelectorAll('.pad-item');
                allPads.forEach((p, idx) => {
                    if (p === padItem) activePadIndex = idx;
                });
            }
        }
    });

    function fillPatientData(patient) {
        currentPatient = patient;
        const isCert4 = !document.getElementById('cert4').classList.contains('hidden');
        const isCert3 = !document.getElementById('cert3').classList.contains('hidden');
        const isCert2 = !document.getElementById('cert2').classList.contains('hidden');
        const isMultiUp = isCert4 || isCert3 || isCert2;

        let targetEl = document;

        if (isMultiUp) {
            const visiblePaper = isCert4 ? document.getElementById('cert4') :
                isCert3 ? document.getElementById('cert3') :
                    document.getElementById('cert2');
            const allPads = visiblePaper.querySelectorAll('.pad-item');

            // If user clicked inside a pad, use that pad
            if (activePadIndex !== null && allPads[activePadIndex]) {
                targetEl = allPads[activePadIndex];
            } else {
                // Otherwise find the first empty pad
                let foundEmpty = false;
                for (let i = 0; i < allPads.length; i++) {
                    let nameInput = allPads[i].querySelector('input[data-field="name"]');
                    if (nameInput && nameInput.value.trim() === '') {
                        targetEl = allPads[i];
                        foundEmpty = true;
                        break;
                    }
                }
                if (!foundEmpty && allPads.length > 0) targetEl = allPads[0]; // fallback to first pad
            }
        }

        // Name
        targetEl.querySelectorAll('input[data-field="name"]').forEach(el => el.value = patient.name);

        // Age
        targetEl.querySelectorAll('input[data-field="age"]').forEach(el => el.value = patient.age);

        // Gender
        targetEl.querySelectorAll('input[data-field="gender"]').forEach(el => el.value = patient.gender || '');

        // Address
        targetEl.querySelectorAll('input[data-field="address"]').forEach(el => el.value = patient.address || '');

        // Date
        const today = new Date();
        const dateStr = today.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
        // Mar 09, 2026 -> Mar. 09, 2026
        const parts = dateStr.split(' ');
        const formattedDate = (parts[0].length === 3 ? parts[0] + '.' : parts[0]) + ' ' + parts[1] + ' ' + parts[2];

        targetEl.querySelectorAll('input[data-field="date"]').forEach(el => {
            if (el.value === '') el.value = formattedDate;
        });

        // Age/Sex combination field
        targetEl.querySelectorAll('input[data-field="age_gender"]').forEach(el => {
            let genderLetter = '';
            if (patient.gender && patient.gender.toLowerCase().startsWith('m')) genderLetter = 'M';
            else if (patient.gender && patient.gender.toLowerCase().startsWith('f')) genderLetter = 'F';

            const ageStr = patient.age ? patient.age : '';
            const val = [ageStr, genderLetter].filter(Boolean).join(' / ');
            el.value = val;
        });

        const Toast = Swal.mixin({
            toast: true,
            position: 'bottom-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
        Toast.fire({
            icon: 'success',
            title: 'Populated ' + patient.name
        });

        // Update Save button color to match patient theme
        const saveBtn = document.getElementById('saveToRecordsBtn');
        if (saveBtn) {
            saveBtn.style.background = (patient.type === 'employee') ? '#795548' : '#00ACB1';
        }
    }

    function saveToMedicalRecords() {
        if (!currentPatient) {
            Swal.fire('Error', 'Please search and select a patient first so the system knows where to save this record.', 'error');
            return;
        }

        // Determine which certificate is active
        const activeBtn = document.querySelector('.cert-btn.active');
        if (!activeBtn) return;
        const targetId = activeBtn.getAttribute('data-target');
        let element = document.getElementById(targetId);

        // If it's a multi-up template, capture only the active pad
        if (['cert2', 'cert3', 'cert4'].includes(targetId)) {
            const allPads = element.querySelectorAll('.pad-item');
            if (activePadIndex !== null && allPads[activePadIndex]) {
                element = allPads[activePadIndex];
            } else {
                element = allPads[0]; // fallback
            }
        }

        let originalBoxShadow = element.style.boxShadow;
        let originalBackground = element.style.background;

        element.style.boxShadow = 'none';
        element.style.background = '#fff'; // Ensure white background for capture
        element.style.color = '#000'; // Ensure black text for capture

        // Temporarily force all children to black text if in dark mode
        const allChildren = element.querySelectorAll('*');
        const originalColors = [];
        allChildren.forEach(child => {
            originalColors.push({ el: child, color: child.style.color });
            child.style.setProperty('color', '#000', 'important');
            if (child.classList.contains('editable-input')) {
                child.style.borderBottomColor = '#000';
            }
        });

        // Temporarily reset transform for clean capture
        const activePaper = document.querySelector('.paper:not(.hidden)');
        let originalTransform = activePaper ? activePaper.style.transform : '';
        let originalMarginBottom = activePaper ? activePaper.style.marginBottom : '';
        if (activePaper) {
            activePaper.style.transform = 'none';
            activePaper.style.marginBottom = '0';
        }

        const replacements = [];
        const inputs = element.querySelectorAll('input, textarea');
        inputs.forEach(input => {
            const span = document.createElement('span');

            if (input.tagName === 'TEXTAREA') {
                span.textContent = input.value || ' ';
                span.style.whiteSpace = 'pre-wrap';
                span.style.width = input.offsetWidth + 'px';
                span.style.height = input.offsetHeight + 'px';
                span.style.display = 'block';
                span.style.fontFamily = window.getComputedStyle(input).fontFamily;
                span.style.fontSize = window.getComputedStyle(input).fontSize;
                span.style.padding = window.getComputedStyle(input).padding;
                span.style.lineHeight = window.getComputedStyle(input).lineHeight;
                span.style.color = '#000';
            } else if (input.type === 'checkbox' || input.type === 'radio') {
                span.innerHTML = input.checked ? '&#9745;' : '&#9744;'; // Checked/Unchecked box
                const parentFS = parseFloat(window.getComputedStyle(input.parentElement).fontSize);
                span.style.fontSize = parentFS + 'px';
                span.style.fontFamily = window.getComputedStyle(input.parentElement).fontFamily;
                span.style.marginRight = window.getComputedStyle(input).marginRight;
                span.style.lineHeight = '1';
                span.style.color = '#000';
            } else {
                span.textContent = input.value || ' ';
                span.style.borderBottom = window.getComputedStyle(input).borderBottom;
                // If it's effectively none, make sure it stays none
                if (window.getComputedStyle(input).borderBottomStyle === 'none') {
                    span.style.borderBottom = 'none';
                }
                span.style.textAlign = window.getComputedStyle(input).textAlign;
                span.style.padding = window.getComputedStyle(input).padding;
                span.style.width = input.offsetWidth + 'px';
                span.style.display = 'inline-block';
                span.style.fontFamily = window.getComputedStyle(input).fontFamily;
                span.style.fontSize = window.getComputedStyle(input).fontSize;
                span.style.color = '#000';
                span.style.verticalAlign = 'baseline';
            }

            input.parentNode.insertBefore(span, input);
            input.style.display = 'none';
            replacements.push({ input, span });
        });

        Swal.fire({
            title: 'Saving...',
            text: 'Generating certificate image, please wait.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        html2canvas(element, { scale: 2, useCORS: true, logging: false }).then(canvas => {
            element.style.boxShadow = originalBoxShadow; // restore box shadow
            element.style.background = originalBackground; // restore background

            // Restore colors
            originalColors.forEach(item => {
                item.el.style.color = item.color;
            });

            // Restore transform
            if (activePaper) {
                activePaper.style.transform = originalTransform;
                activePaper.style.marginBottom = originalMarginBottom;
            }

            // Restore inputs
            replacements.forEach(r => {
                r.input.style.display = '';
                r.span.remove();
            });

            const base64Data = canvas.toDataURL('image/jpeg', 0.8);

            // If offline, push the generated certificate to the Offline Queue
            if (!navigator.onLine && window.ClinicSync) {
                const formData = new FormData();
                formData.append('patient_id', currentPatient.id);
                formData.append('patient_type', currentPatient.type);
                formData.append('title', activeBtn.innerText);
                formData.append('image_data', base64Data);

                window.ClinicSync.save('api/save_certificate.php', formData, 'certificate').then(() => {
                    const btnColor = (currentPatient.type === 'employee') ? '#795548' : '#00ACB1';
                    Swal.fire({
                        title: 'Saved Offline',
                        text: 'Certificate saved locally. It will upload automatically when you reconnect.',
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonText: 'VIEW IN RECORDS',
                        cancelButtonText: 'CLOSE',
                        confirmButtonColor: btnColor,
                        cancelButtonColor: '#6c757d',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = `medical_records.php?id=${currentPatient.id}&type=${currentPatient.type}`;
                        }
                    });
                });
                return; // Stop the standard fetch
            }

            // Normal online fetch
            fetch('api/save_certificate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    patient_id: currentPatient.id,
                    patient_type: currentPatient.type,
                    title: activeBtn.innerText,
                    image_data: base64Data
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const btnColor = (currentPatient.type === 'employee') ? '#795548' : '#00ACB1';
                        Swal.fire({
                            title: 'Saved!',
                            text: 'Certificate successfully saved to Medical Records.',
                            icon: 'success',
                            showCancelButton: true,
                            confirmButtonText: 'VIEW IN RECORDS',
                            cancelButtonText: 'CLOSE',
                            confirmButtonColor: btnColor,
                            cancelButtonColor: '#6c757d',
                            reverseButtons: true,
                            width: '450px'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = `medical_records.php?id=${currentPatient.id}&type=${currentPatient.type}`;
                            }
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Error occurred while saving.', 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Error', 'Server error. Could not save.', 'error');
                });
        });
    }
</script>

<!-- Signature Modal -->
<div id="sigModal" class="sig-modal">
    <div class="sig-modal-content">
        <h3>Electronic Signature</h3>
        <p style="font-size: 12px; color: #666; margin-bottom: 10px;">Sign below using your mouse or touch screen</p>
        <canvas id="sig-canvas" width="400" height="200"></canvas>
        <div class="sig-actions">
            <button class="sig-btn sig-btn-cancel" onclick="closeSignatureModal()">Cancel</button>
            <button class="sig-btn sig-btn-clear" onclick="clearSignature()">Clear</button>
            <button class="sig-btn sig-btn-save" onclick="saveSignature()">Confirm</button>
        </div>
    </div>
</div>

<script>
    // Signature Pad Logic
    let canvas, ctx, drawing = false;
    let currentSigContainer = null;

    document.addEventListener('DOMContentLoaded', function () {
        canvas = document.getElementById('sig-canvas');
        if (!canvas) return;
        ctx = canvas.getContext('2d');
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';

        // Mouse Events
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);

        // Touch Events
        canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            const touch = e.touches[0];
            startDrawing(touch);
        });
        canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            const touch = e.touches[0];
            draw(touch);
        });
        canvas.addEventListener('touchend', stopDrawing);
    });

    function startDrawing(e) {
        drawing = true;
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX || e.pageX) - rect.left;
        const y = (e.clientY || e.pageY) - rect.top;
        ctx.beginPath();
        ctx.moveTo(x, y);
    }

    function draw(e) {
        if (!drawing) return;
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX || e.pageX) - rect.left;
        const y = (e.clientY || e.pageY) - rect.top;
        ctx.lineTo(x, y);
        ctx.stroke();
    }

    function stopDrawing() {
        if (!drawing) return;
        drawing = false;
        ctx.closePath();
    }

    function openSignatureModal(container) {
        currentSigContainer = container;
        document.getElementById('sigModal').style.display = 'flex';
        clearSignature(); // Clear canvas for new signature
    }

    function closeSignatureModal() {
        document.getElementById('sigModal').style.display = 'none';
        currentSigContainer = null;
    }

    function clearSignature() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    function saveSignature() {
        if (!currentSigContainer) return;

        // Check if canvas is empty
        const blank = document.createElement('canvas');
        blank.width = canvas.width;
        blank.height = canvas.height;
        if (canvas.toDataURL() === blank.toDataURL()) {
            Swal.fire({
                icon: 'warning',
                title: 'Empty Signature',
                text: 'Please sign before confirming.',
                confirmButtonColor: '#00ACB1'
            });
            return;
        }

        const dataURL = canvas.toDataURL();
        const img = currentSigContainer.querySelector('.sig-display');
        const placeholder = currentSigContainer.querySelector('.signature-placeholder');

        if (img) {
            img.src = dataURL;
            img.style.display = 'block';
            // Also hide the dashed border once signed
            currentSigContainer.style.borderBottom = 'none';
        }
        if (placeholder) {
            placeholder.style.display = 'none';
        }

        closeSignatureModal();

        // Show success toast
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1500
        });
        Toast.fire({
            icon: 'success',
            title: 'Signature captured'
        });
    }

    // Global click listener to close modal on background click
    window.onclick = function (event) {
        const modal = document.getElementById('sigModal');
        if (event.target == modal) {
            closeSignatureModal();
        }
    }
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

</body>

</html>