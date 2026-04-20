<?php
require_once "../config/db.php";
requireLogin();

// Only admin/superadmin can merge
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin' && $_SESSION['role'] !== 'medical_staff')) {
    header("Location: dashboard.php");
    exit();
}

include "index_layout.php";
?>

<div class="container-fluid" style="padding: 20px;">
    <div class="panel form"
        style="max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">

        <div
            style="border-bottom: 2px solid #00ACB1; padding-bottom: 15px; margin-bottom: 25px; display: flex; align-items: center; gap: 15px;">
            <i class="fa-solid fa-code-merge" style="font-size: 2rem; color: #00ACB1;"></i>
            <div>
                <h2 style="margin: 0; color: #333; font-family: 'Cinzel', serif;">Merge Duplicates</h2>
                <p style="margin: 5px 0 0 0; color: #777; font-size: 0.9rem;">Combine two patient records into one.
                    Useful for records with same name but different details.</p>
            </div>
        </div>

        <div
            style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; border-radius: 6px; margin-bottom: 25px; display: flex; gap: 15px; align-items: flex-start;">
            <i class="fa-solid fa-circle-info" style="color: #1976d2; font-size: 1.2rem; margin-top: 2px;"></i>
            <div style="font-size: 0.85rem; color: #0d47a1; line-height: 1.5;">
                <strong>How it works:</strong>
                <ul style="margin: 5px 0 0 20px; padding: 0;">
                    <li><strong>Primary Record:</strong> This record will be KEPT. Keep the one with the correct LRN/ID.
                    </li>
                    <li><strong>Duplicate Record:</strong> Data from this record will be MOVED to the primary one, then
                        it will be deleted.</li>
                    <li>Treatment logs, medical files, and health exams will be merged automatically.</li>
                </ul>
            </div>
        </div>

        <!-- Suggestions Box -->
        <div id="suggestionsContainer" style="display: none; margin-bottom: 30px;">
            <h3
                style="font-size: 1rem; color: #f39c12; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Potential Duplicates Found
            </h3>
            <div id="suggestionsList"
                style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px;">
                <!-- Suggestions will be injected here -->
            </div>
        </div>

        <form id="mergeForm" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <!-- Record Type -->
            <div style="grid-column: 1 / -1; margin-bottom: 10px;">
                <label style="display: block; font-weight: bold; margin-bottom: 10px; color: #555;">Select Record
                    Type:</label>
                <div style="display: flex; gap: 20px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="type" value="student" checked onchange="updatePlaceholders()"> Student
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="type" value="employee" onchange="updatePlaceholders()"> Employee
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="type" value="other" onchange="updatePlaceholders()"> Others
                    </label>
                </div>
            </div>

            <!-- Primary Record Selection -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; border: 1px solid #eee;">
                <h3
                    style="margin-top: 0; font-size: 1rem; color: #2ecc71; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-star"></i> Primary Record (KEEP)
                </h3>
                <p style="font-size: 0.8rem; color: #777; margin-bottom: 15px;">Search for the record with the correct
                    LRN/ID.</p>

                <div style="position: relative;">
                    <input type="text" id="primarySearch" placeholder="Search by name or LRN..."
                        style="width: 100%; padding: 10px 35px 10px 12px; border: 1px solid #ddd; border-radius: 6px; outline: none;"
                        autocomplete="off">
                    <i class="fa-solid fa-magnifying-glass"
                        style="position: absolute; right: 12px; top: 12px; color: #aaa;"></i>
                    <div id="primaryResults" class="search-results-dropdown"></div>
                </div>

                <input type="hidden" name="primary_id" id="primary_id">
                <div id="primarySelection" class="selection-card" style="display: none;">
                    <div id="primaryDetails"></div>
                    <button type="button" onclick="clearSelection('primary')"
                        style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 0.8rem; margin-top: 10px; padding: 0;">&times;
                        Remove</button>
                </div>
            </div>

            <!-- Duplicate Record Selection -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; border: 1px solid #eee;">
                <h3
                    style="margin-top: 0; font-size: 1rem; color: #e74c3c; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-clone"></i> Duplicate Record (MOVE & DELETE)
                </h3>
                <p style="font-size: 0.8rem; color: #777; margin-bottom: 15px;">Search for the record without LRN or
                    with old data.</p>

                <div style="position: relative;">
                    <input type="text" id="duplicateSearch" placeholder="Search by name or LRN..."
                        style="width: 100%; padding: 10px 35px 10px 12px; border: 1px solid #ddd; border-radius: 6px; outline: none;"
                        autocomplete="off">
                    <i class="fa-solid fa-magnifying-glass"
                        style="position: absolute; right: 12px; top: 12px; color: #aaa;"></i>
                    <div id="duplicateResults" class="search-results-dropdown"></div>
                </div>

                <input type="hidden" name="duplicate_id" id="duplicate_id">
                <div id="duplicateSelection" class="selection-card" style="display: none;">
                    <div id="duplicateDetails"></div>
                    <button type="button" onclick="clearSelection('duplicate')"
                        style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 0.8rem; margin-top: 10px; padding: 0;">&times;
                        Remove</button>
                </div>
            </div>

            <div style="grid-column: 1 / -1; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                <button type="submit" id="mergeBtn" class="btn"
                    style="width: 100%; background: #00ACB1; color: white; padding: 18px; border-radius: 12px; font-weight: bold; font-size: 1.2rem; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 15px; transition: 0.3s; opacity: 0.5; pointer-events: none; box-shadow: 0 4px 15px rgba(0, 172, 177, 0.2);">
                    <i class="fa-solid fa-code-merge" style="font-size: 1.4rem;"></i> Merge Records Now
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .search-results-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-radius: 0 0 6px 6px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        z-index: 100;
        max-height: 250px;
        overflow-y: auto;
        display: none;
    }

    .result-item {
        padding: 10px 15px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: 0.2s;
    }

    .result-item:hover {
        background: #f0f9f9;
    }

    .result-item strong {
        display: block;
        font-size: 0.9rem;
        color: #333;
    }

    .result-item span {
        font-size: 0.75rem;
        color: #888;
    }

    .selection-card {
        margin-top: 15px;
        background: white;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #00ACB1;
        box-shadow: 0 2px 6px rgba(0, 172, 177, 0.1);
    }

    .selection-card strong {
        display: block;
        color: #00ACB1;
        margin-bottom: 5px;
    }

    .selection-card p {
        margin: 2px 0;
        font-size: 0.8rem;
        color: #555;
    }
</style>

<script>
    const primaryInput = document.getElementById('primarySearch');
    const duplicateInput = document.getElementById('duplicateSearch');
    let searchTimeout;

    function updatePlaceholders() {
        const type = document.querySelector('input[name="type"]:checked').value;
        let placeholder = 'Search by name or LRN...';
        if (type === 'employee') {
            placeholder = 'Search by name or Employee No...';
        } else if (type === 'other') {
            placeholder = 'Search by name or status...';
        }
        primaryInput.placeholder = placeholder;
        duplicateInput.placeholder = placeholder;
        clearSelection('primary');
        clearSelection('duplicate');
        loadSuggestions();
    }

    function loadSuggestions() {
        const type = document.querySelector('input[name="type"]:checked').value;
        const container = document.getElementById('suggestionsContainer');
        const list = document.getElementById('suggestionsList');

        fetch(`api/get_duplicate_suggestions.php?type=${type}`)
            .then(res => res.json())
            .then(data => {
                if (data.length > 0) {
                    container.style.display = 'block';
                    list.innerHTML = data.map(item => `
                        <div style="background: #fff9eb; border: 1px solid #f39c12; padding: 12px; border-radius: 8px; cursor: pointer; transition: 0.2s;" 
                             onmouseover="this.style.background='#fff3cd'" onmouseout="this.style.background='#fff9eb'"
                             onclick="autoFillMerge('${type}', ${JSON.stringify(item.records).replace(/"/g, '&quot;')})">
                            <strong style="color: #d35400; font-size: 0.9rem;">${item.name}</strong>
                            <p style="margin: 2px 0; font-size: 0.8rem; color: #555;">Age/Birth: ${item.age || 'N/A'}</p>
                            <p style="margin: 5px 0 0 0; font-size: 0.75rem; color: #7f8c8d;">${item.count} records found with this data.</p>
                            <span style="font-size: 0.7rem; color: #00ACB1; font-weight: bold;">Click to select for merge</span>
                        </div>
                    `).join('');
                } else {
                    container.style.display = 'none';
                }
            });
    }

    function autoFillMerge(type, records) {
        // Assume latest is primary, older is duplicate
        // Or check which one has LRN
        let primary = records[0];
        let duplicate = records[1];

        // Preference: The one WITH identifier is Primary
        if (!primary.identifier && duplicate.identifier) {
            [primary, duplicate] = [duplicate, primary];
        }

        selectPatient('primary', {
            id: primary.id,
            name: primary.name,
            lrn: primary.identifier,
            employee_no: primary.identifier
        }); selectPatient('duplicate', {
            id: duplicate.id,
            name: duplicate.name,
            lrn: duplicate.identifier,
            employee_no: duplicate.identifier
        });

        Swal.fire({
            title: 'Records Selected',
            text: `Selected ${primary.name} for merging. Please review if the Primary record is correct before proceeding.`,
            icon: 'info',
            timer: 3000,
            toast: true,
            position: 'top-end',
            showConfirmButton: false
        });
    }

    function searchPatients(query, side) {
        const type = document.querySelector('input[name="type"]:checked').value;
        const resultsDiv = document.getElementById(side + 'Results');

        if (query.length < 2) {
            resultsDiv.style.display = 'none';
            return;
        }

        fetch(`api/search_patients.php?query=${encodeURIComponent(query)}&type=${type}`)
            .then(res => res.json())
            .then(data => {
                if (data.length > 0) {
                    resultsDiv.innerHTML = data.map(p => {
                        let sub = '';
                        if (type === 'student') sub = 'LRN: ' + (p.lrn || 'N/A');
                        else if (type === 'employee') sub = 'ID: ' + (p.employee_no || 'N/A');
                        else sub = 'SDO: ' + (p.sdo || 'N/A');
                        
                        return `
                        <div class="result-item" onclick="selectPatient('${side}', ${JSON.stringify(p).replace(/"/g, '&quot;')})">
                            <strong>${p.name}</strong>
                            <span>${sub}</span>
                        </div>
                    `;}).join('');
                    resultsDiv.style.display = 'block';
                } else {
                    resultsDiv.innerHTML = '<div style="padding: 15px; text-align: center; color: #999; font-size: 0.85rem;">No records found</div>';
                    resultsDiv.style.display = 'block';
                }
            });
    }

    function selectPatient(side, patient) {
        document.getElementById(side + '_id').value = patient.id;
        document.getElementById(side + 'Search').style.display = 'none';
        document.getElementById(side + 'Results').style.display = 'none';

        const type = document.querySelector('input[name="type"]:checked').value;
        const detailsDiv = document.getElementById(side + 'Details');
        let subDetails = '';
        if (type === 'student') subDetails = 'LRN: ' + (patient.lrn || 'N/A');
        else if (type === 'employee') subDetails = 'ID: ' + (patient.employee_no || 'N/A');
        else subDetails = 'SDO: ' + (patient.sdo || 'N/A');

        detailsDiv.innerHTML = `
            <strong>${patient.name}</strong>
            <p>${subDetails}</p>
            <p>${patient.curriculum || patient.position || patient.remarks || ''}</p>
        `;
        document.getElementById(side + 'Selection').style.display = 'block';
        checkMergeButton();
    }

    function clearSelection(side) {
        document.getElementById(side + '_id').value = '';
        document.getElementById(side + 'Search').value = '';
        document.getElementById(side + 'Search').style.display = 'block';
        document.getElementById(side + 'Selection').style.display = 'none';
        checkMergeButton();
    }

    function checkMergeButton() {
        const pId = document.getElementById('primary_id').value;
        const dId = document.getElementById('duplicate_id').value;
        const btn = document.getElementById('mergeBtn');

        if (pId && dId && pId !== dId) {
            btn.style.opacity = '1';
            btn.style.pointerEvents = 'auto';
        } else {
            btn.style.opacity = '0.5';
            btn.style.pointerEvents = 'none';
        }
    }

    primaryInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => searchPatients(e.target.value, 'primary'), 300);
    });

    duplicateInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => searchPatients(e.target.value, 'duplicate'), 300);
    });

    // Close results when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.position-relative')) {
            document.getElementById('primaryResults').style.display = 'none';
            document.getElementById('duplicateResults').style.display = 'none';
        }
    });

    document.getElementById('mergeForm').addEventListener('submit', function (e) {
        e.preventDefault();

        const pId = document.getElementById('primary_id').value;
        const dId = document.getElementById('duplicate_id').value;
        const type = document.querySelector('input[name="type"]:checked').value;

        Swal.fire({
            title: 'Confirm Merge',
            text: "This action will permanently MOVE all data from the duplicate record to the primary one and DELETE the duplicate. This cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#00ACB1',
            cancelButtonColor: '#666',
            confirmButtonText: 'Yes, Merge Records'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Merging...',
                    text: 'Please wait while we process the records.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                const formData = new FormData();
                formData.append('primary_id', pId);
                formData.append('duplicate_id', dId);
                formData.append('type', type);

                fetch('api/merge_patients.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Merged!', data.message, 'success').then(() => {
                                const nameParam = encodeURIComponent(data.primary_name);
                                let targetUrl = 'student';
                                if (type === 'employee') targetUrl = 'employees';
                                else if (type === 'other') targetUrl = 'others';
                                window.location.href = `${targetUrl}?view_id=${pId}&view_name=${nameParam}`;
                            });
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', 'An unexpected error occurred.', 'error');
                    });
            }
        });
    });

    // Initial load
    document.addEventListener('DOMContentLoaded', loadSuggestions);
</script>