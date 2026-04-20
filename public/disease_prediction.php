<?php
require_once "../config/db.php";
requireLogin();
include "index_layout.php";
?>

<style>
    /* ============================================
       DISEASE PREDICTION PAGE STYLES
       Premium, modern design with glassmorphism
    ============================================ */

    :root {
        --dp-primary: #00ACB1;
        --dp-primary-dark: #008e93;
        --dp-primary-light: #e0f7fa;
        --dp-gradient: linear-gradient(135deg, #00ACB1 0%, #007c80 50%, #004d4f 100%);
        --dp-bg: #f0f4f8;
        --dp-card-bg: #ffffff;
        --dp-text: #333333;
        --dp-text-light: #666666;
        --dp-border: #e2e8f0;
        --dp-shadow: 0 4px 24px rgba(0, 172, 177, 0.08);
        --dp-shadow-hover: 0 8px 32px rgba(0, 172, 177, 0.15);
        --dp-success: #10b981;
        --dp-warning: #f59e0b;
        --dp-danger: #ef4444;
        --dp-info: #3b82f6;
    }

    body.dark-mode {
        --dp-bg: #1a1a2e;
        --dp-card-bg: #16213e;
        --dp-text: #e0e0e0;
        --dp-text-light: #a0aec0;
        --dp-border: #2d3748;
        --dp-shadow: 0 4px 24px rgba(0, 0, 0, 0.3);
        --dp-shadow-hover: 0 8px 32px rgba(0, 0, 0, 0.4);
        --dp-primary-light: #0a3d3e;
    }

    .dp-container {
        padding: 24px;
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Page Header */
    .dp-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .dp-header-left {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .dp-header-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        background: var(--dp-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
        box-shadow: 0 4px 16px rgba(0, 172, 177, 0.3);
    }

    .dp-header h1 {
        margin: 0;
        font-size: 1.8em;
        color: var(--dp-primary);
        font-weight: 800;
        letter-spacing: -0.5px;
    }

    .dp-header p {
        margin: 4px 0 0;
        color: var(--dp-text-light);
        font-size: 0.9em;
    }

    .dp-header-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: var(--dp-primary-light);
        color: var(--dp-primary);
        border-radius: 24px;
        font-size: 0.85em;
        font-weight: 600;
    }

    .dp-header-badge i {
        font-size: 1.1em;
    }

    /* Main Layout */
    .dp-main {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        align-items: start;
    }

    /* Cards */
    .dp-card {
        background: var(--dp-card-bg);
        border-radius: 16px;
        padding: 24px;
        box-shadow: var(--dp-shadow);
        border: 1px solid var(--dp-border);
        transition: box-shadow 0.3s ease;
    }

    .dp-card:hover {
        box-shadow: var(--dp-shadow-hover);
    }

    .dp-card-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0 0 20px;
        font-size: 1.1em;
        font-weight: 700;
        color: var(--dp-text);
    }

    .dp-card-title i {
        color: var(--dp-primary);
        font-size: 1.1em;
    }

    /* Symptom Groups */
    .dp-symptom-group {
        margin-bottom: 16px;
    }

    .dp-group-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        background: var(--dp-primary-light);
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.88em;
        color: var(--dp-primary-dark);
        transition: all 0.2s ease;
        user-select: none;
        border: 1px solid transparent;
    }

    .dp-group-header:hover {
        border-color: var(--dp-primary);
        background: rgba(0, 172, 177, 0.15);
    }

    .dp-group-header i.group-icon {
        transition: transform 0.3s ease;
    }

    .dp-group-header.expanded i.group-icon {
        transform: rotate(90deg);
    }

    .dp-group-header .group-count {
        margin-left: auto;
        background: var(--dp-primary);
        color: white;
        border-radius: 10px;
        padding: 2px 8px;
        font-size: 0.78em;
        font-weight: 700;
        min-width: 22px;
        text-align: center;
        display: none;
    }

    .dp-group-header .group-count.has-selection {
        display: inline-block;
        animation: popIn 0.3s ease;
    }

    @keyframes popIn {
        0% {
            transform: scale(0);
        }

        60% {
            transform: scale(1.2);
        }

        100% {
            transform: scale(1);
        }
    }

    .dp-symptom-list {
        display: none;
        flex-wrap: wrap;
        gap: 8px;
        padding: 12px 8px 8px;
    }

    .dp-symptom-list.show {
        display: flex;
    }

    /* Symptom Chips */
    .dp-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 7px 14px;
        border-radius: 20px;
        font-size: 0.82em;
        font-weight: 500;
        cursor: pointer;
        border: 2px solid var(--dp-border);
        background: var(--dp-card-bg);
        color: var(--dp-text);
        transition: all 0.2s ease;
        user-select: none;
    }

    .dp-chip:hover {
        border-color: var(--dp-primary);
        background: var(--dp-primary-light);
        transform: translateY(-1px);
    }

    .dp-chip.selected {
        background: var(--dp-primary);
        color: white;
        border-color: var(--dp-primary);
        box-shadow: 0 2px 8px rgba(0, 172, 177, 0.3);
    }

    .dp-chip.selected:hover {
        background: var(--dp-primary-dark);
    }

    .dp-chip i {
        font-size: 0.9em;
    }

    /* Search */
    .dp-search-box {
        position: relative;
        margin-bottom: 16px;
    }

    .dp-search-box input {
        width: 100%;
        padding: 12px 16px 12px 42px;
        border: 2px solid var(--dp-border);
        border-radius: 12px;
        font-size: 0.9em;
        background: var(--dp-card-bg);
        color: var(--dp-text);
        transition: border-color 0.2s;
        outline: none;
        box-sizing: border-box;
    }

    .dp-search-box input:focus {
        border-color: var(--dp-primary);
    }

    .dp-search-box i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--dp-text-light);
    }

    /* Selected symptoms bar */
    .dp-selected-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        background: var(--dp-gradient);
        color: white;
        border-radius: 12px;
        margin-bottom: 16px;
        gap: 12px;
        flex-wrap: wrap;
    }

    .dp-selected-bar .count-text {
        font-weight: 600;
        font-size: 0.95em;
    }

    .dp-selected-bar .count-text span {
        font-size: 1.3em;
        font-weight: 800;
    }

    .dp-btn-group {
        display: flex;
        gap: 10px;
    }

    /* Buttons */
    .dp-btn {
        padding: 10px 24px;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.9em;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .dp-btn-predict {
        background: white;
        color: var(--dp-primary);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .dp-btn-predict:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
    }

    .dp-btn-predict:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    .dp-btn-clear {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.4);
    }

    .dp-btn-clear:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    /* Results Panel */
    .dp-results {
        position: sticky;
        top: 20px;
    }

    .dp-result-empty {
        text-align: center;
        padding: 60px 20px;
        color: var(--dp-text-light);
    }

    .dp-result-empty i {
        font-size: 4em;
        color: var(--dp-border);
        margin-bottom: 16px;
        display: block;
    }

    .dp-result-empty h3 {
        margin: 0 0 8px;
        color: var(--dp-text);
        font-size: 1.1em;
    }

    .dp-result-empty p {
        margin: 0;
        font-size: 0.9em;
        line-height: 1.6;
    }

    /* Loading Animation */
    .dp-loading {
        text-align: center;
        padding: 60px 20px;
    }

    .dp-loading .spinner {
        width: 48px;
        height: 48px;
        border: 4px solid var(--dp-border);
        border-top: 4px solid var(--dp-primary);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin: 0 auto 16px;
    }

    @keyframes spin {
        100% {
            transform: rotate(360deg);
        }
    }

    .dp-loading p {
        color: var(--dp-text-light);
        font-size: 0.95em;
        font-weight: 500;
    }

    /* Prediction Cards */
    .dp-prediction-card {
        background: var(--dp-card-bg);
        border: 2px solid var(--dp-border);
        border-radius: 14px;
        padding: 20px;
        margin-bottom: 14px;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
        overflow: hidden;
    }

    .dp-prediction-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        border-radius: 4px 0 0 4px;
    }

    .dp-prediction-card.severity-high::before {
        background: var(--dp-danger);
    }

    .dp-prediction-card.severity-moderate::before {
        background: var(--dp-warning);
    }

    .dp-prediction-card.severity-mild::before {
        background: var(--dp-success);
    }

    .dp-prediction-card:hover {
        border-color: var(--dp-primary);
        transform: translateY(-2px);
        box-shadow: var(--dp-shadow-hover);
    }

    .dp-prediction-card.rank-1 {
        border-color: var(--dp-primary);
        box-shadow: 0 4px 20px rgba(0, 172, 177, 0.15);
    }

    .dp-pred-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
        gap: 10px;
    }

    .dp-pred-rank {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--dp-gradient);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.85em;
        flex-shrink: 0;
    }

    .dp-pred-name {
        flex: 1;
        font-weight: 700;
        font-size: 1em;
        color: var(--dp-text);
    }

    .dp-pred-confidence {
        text-align: right;
    }

    .dp-pred-confidence .confidence-value {
        font-size: 1.5em;
        font-weight: 800;
        color: var(--dp-primary);
    }

    .dp-pred-confidence .confidence-label {
        font-size: 0.7em;
        color: var(--dp-text-light);
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    /* Confidence Bar */
    .dp-confidence-bar {
        height: 6px;
        background: var(--dp-border);
        border-radius: 3px;
        margin-bottom: 12px;
        overflow: hidden;
    }

    .dp-confidence-bar .fill {
        height: 100%;
        border-radius: 3px;
        transition: width 1s ease;
        background: var(--dp-gradient);
    }

    /* Severity Badge */
    .dp-severity-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75em;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .dp-severity-badge.high {
        background: #fef2f2;
        color: #dc2626;
    }

    .dp-severity-badge.moderate {
        background: #fffbeb;
        color: #d97706;
    }

    .dp-severity-badge.mild {
        background: #ecfdf5;
        color: #059669;
    }

    body.dark-mode .dp-severity-badge.high {
        background: #450a0a;
    }

    body.dark-mode .dp-severity-badge.moderate {
        background: #451a03;
    }

    body.dark-mode .dp-severity-badge.mild {
        background: #022c22;
    }

    /* Details section (collapsible) */
    .dp-pred-details {
        display: none;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px dashed var(--dp-border);
        animation: fadeIn 0.3s ease;
    }

    .dp-pred-details.show {
        display: block;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .dp-detail-section {
        margin-bottom: 12px;
    }

    .dp-detail-section h5 {
        margin: 0 0 6px;
        font-size: 0.82em;
        color: var(--dp-primary);
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .dp-detail-section p {
        margin: 0;
        font-size: 0.88em;
        color: var(--dp-text-light);
        line-height: 1.6;
    }

    .dp-matched-symptoms {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .dp-matched-chip {
        padding: 4px 10px;
        background: var(--dp-primary-light);
        color: var(--dp-primary);
        border-radius: 16px;
        font-size: 0.78em;
        font-weight: 600;
    }

    /* Disclaimer */
    .dp-disclaimer {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 18px;
        background: #fffbeb;
        border: 1px solid #fbbf24;
        border-radius: 10px;
        margin-top: 16px;
        font-size: 0.82em;
        color: #92400e;
        line-height: 1.5;
    }

    body.dark-mode .dp-disclaimer {
        background: #451a03;
        border-color: #b45309;
        color: #fbbf24;
    }

    .dp-disclaimer i {
        font-size: 1.2em;
        color: #f59e0b;
        margin-top: 2px;
        flex-shrink: 0;
    }

    /* Responsive */
    @media (max-width: 968px) {
        .dp-main {
            grid-template-columns: 1fr;
        }

        .dp-results {
            position: static;
        }
    }

    @media (max-width: 600px) {
        .dp-container {
            padding: 12px;
        }

        .dp-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .dp-selected-bar {
            flex-direction: column;
            text-align: center;
        }
    }

    /* Expand/Collapse Indicator */
    .dp-prediction-card .expand-hint {
        text-align: center;
        font-size: 0.78em;
        color: var(--dp-text-light);
        margin-top: 4px;
    }

    .dp-prediction-card .expand-hint i {
        transition: transform 0.3s;
    }

    .dp-prediction-card.expanded .expand-hint i {
        transform: rotate(180deg);
    }
</style>

<div class="dp-container">
    <!-- Header -->
    <div class="dp-header">
        <div class="dp-header-left">
            <div class="dp-header-icon">
                <i class="fa-solid fa-stethoscope"></i>
            </div>
            <div>
                <h1>Disease Prediction</h1>
                <p>AI-powered symptom analysis for preliminary assessment</p>
            </div>
        </div>
        <div class="dp-header-badge">
            <i class="fa-brands fa-python"></i>
            <span>Python AI Engine</span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="dp-main">
        <!-- LEFT: Symptom Selection -->
        <div>
            <div class="dp-card">
                <h3 class="dp-card-title">
                    <i class="fa-solid fa-clipboard-list"></i>
                    Select Symptoms
                </h3>

                <!-- Search -->
                <div class="dp-search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" id="symptomSearch" placeholder="Search symptoms... (e.g. headache, fever, cough)"
                        autocomplete="off">
                </div>

                <!-- Selected Bar -->
                <div class="dp-selected-bar" id="selectedBar">
                    <div class="count-text">
                        <span id="selectedCount">0</span> symptom(s) selected
                    </div>
                    <div class="dp-btn-group">
                        <button class="dp-btn dp-btn-clear" onclick="clearAll()" id="btnClear" style="display:none;">
                            <i class="fa-solid fa-xmark"></i> Clear
                        </button>
                        <button class="dp-btn dp-btn-predict" onclick="predictDisease()" id="btnPredict" disabled>
                            <i class="fa-solid fa-brain"></i> Analyze
                        </button>
                    </div>
                </div>

                <!-- Symptom Groups -->
                <div id="symptomGroups">
                    <div class="dp-loading" id="symptomLoading">
                        <div class="spinner"></div>
                        <p>Loading symptoms from AI engine...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Results -->
        <div class="dp-results">
            <div class="dp-card">
                <h3 class="dp-card-title">
                    <i class="fa-solid fa-file-medical"></i>
                    Prediction Results
                </h3>

                <div id="resultsContainer">
                    <div class="dp-result-empty">
                        <i class="fa-solid fa-magnifying-glass-chart"></i>
                        <h3>No Analysis Yet</h3>
                        <p>Select symptoms from the left panel and click <strong>"Analyze"</strong> to get AI-powered
                            disease predictions.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // State
    let selectedSymptoms = new Set();
    let symptomData = null;

    // Icons for symptom groups
    const groupIcons = {
        'General / Systemic': 'fa-person',
        'Head & Neurological': 'fa-head-side-virus',
        'Eyes, Ears, Nose': 'fa-eye',
        'Throat & Respiratory': 'fa-lungs',
        'Digestive / Abdominal': 'fa-stomach',
        'Skin & Integumentary': 'fa-hand-dots',
        'Musculoskeletal': 'fa-bone',
        'Urinary & Reproductive': 'fa-droplet',
        'Cardiovascular / Autonomic': 'fa-heart-pulse'
    };

    // Load symptoms on page load
    document.addEventListener('DOMContentLoaded', function () {
        loadSymptoms();
    });

    function loadSymptoms() {
        fetch('api/disease_predict.php?action=get_symptoms')
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('symptomLoading').innerHTML = `
                        <i class="fa-solid fa-circle-exclamation" style="font-size:2em; color: var(--dp-danger); margin-bottom:12px; display:block;"></i>
                        <p style="color: var(--dp-danger);">${data.error}</p>
                    `;
                    return;
                }

                symptomData = data;
                renderSymptomGroups(data.symptom_groups);
            })
            .catch(err => {
                document.getElementById('symptomLoading').innerHTML = `
                    <i class="fa-solid fa-circle-exclamation" style="font-size:2em; color: var(--dp-danger); margin-bottom:12px; display:block;"></i>
                    <p style="color: var(--dp-danger);">Failed to load symptoms. Please check if Python is installed.</p>
                `;
                console.error('Load error:', err);
            });
    }

    function renderSymptomGroups(groups) {
        const container = document.getElementById('symptomGroups');
        container.innerHTML = '';

        for (const [groupName, symptoms] of Object.entries(groups)) {
            const groupDiv = document.createElement('div');
            groupDiv.className = 'dp-symptom-group';
            groupDiv.setAttribute('data-group', groupName);

            const icon = groupIcons[groupName] || 'fa-layer-group';

            groupDiv.innerHTML = `
                <div class="dp-group-header" onclick="toggleGroup(this)">
                    <i class="fa-solid fa-chevron-right group-icon"></i>
                    <i class="fa-solid ${icon}" style="color: var(--dp-primary);"></i>
                    <span>${groupName}</span>
                    <span class="group-count" data-group-count="${groupName}">0</span>
                </div>
                <div class="dp-symptom-list" data-group-list="${groupName}">
                    ${symptoms.map(s => `
                        <div class="dp-chip" data-symptom="${s.key}" onclick="toggleSymptom(this)">
                            <i class="fa-regular fa-circle"></i>
                            ${s.label}
                        </div>
                    `).join('')}
                </div>
            `;

            container.appendChild(groupDiv);
        }
    }

    function toggleGroup(header) {
        header.classList.toggle('expanded');
        const list = header.nextElementSibling;
        list.classList.toggle('show');
    }

    function toggleSymptom(chip) {
        const symptom = chip.getAttribute('data-symptom');

        if (selectedSymptoms.has(symptom)) {
            selectedSymptoms.delete(symptom);
            chip.classList.remove('selected');
            chip.querySelector('i').className = 'fa-regular fa-circle';
        } else {
            selectedSymptoms.add(symptom);
            chip.classList.add('selected');
            chip.querySelector('i').className = 'fa-solid fa-circle-check';
        }

        updateUI();
    }

    function updateUI() {
        const count = selectedSymptoms.size;
        document.getElementById('selectedCount').textContent = count;
        document.getElementById('btnPredict').disabled = count === 0;
        document.getElementById('btnClear').style.display = count > 0 ? 'inline-flex' : 'none';

        // Update group counts
        document.querySelectorAll('[data-group-count]').forEach(badge => {
            const groupName = badge.getAttribute('data-group-count');
            const groupList = document.querySelector(`[data-group-list="${groupName}"]`);
            if (groupList) {
                const chips = groupList.querySelectorAll('.dp-chip.selected');
                badge.textContent = chips.length;
                badge.classList.toggle('has-selection', chips.length > 0);
            }
        });
    }

    function clearAll() {
        selectedSymptoms.clear();
        document.querySelectorAll('.dp-chip.selected').forEach(chip => {
            chip.classList.remove('selected');
            chip.querySelector('i').className = 'fa-regular fa-circle';
        });
        updateUI();
    }

    // Search functionality
    document.getElementById('symptomSearch')?.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();

        document.querySelectorAll('.dp-symptom-group').forEach(group => {
            const chips = group.querySelectorAll('.dp-chip');
            let hasVisible = false;

            chips.forEach(chip => {
                const label = chip.textContent.toLowerCase();
                const match = query === '' || label.includes(query);
                chip.style.display = match ? 'inline-flex' : 'none';
                if (match) hasVisible = true;
            });

            // Auto-expand groups with matching symptoms
            const header = group.querySelector('.dp-group-header');
            const list = group.querySelector('.dp-symptom-list');

            if (query !== '') {
                group.style.display = hasVisible ? 'block' : 'none';
                if (hasVisible) {
                    header.classList.add('expanded');
                    list.classList.add('show');
                }
            } else {
                group.style.display = 'block';
                // Don't collapse on clear to preserve user state
            }
        });
    });

    // Predict
    function predictDisease() {
        if (selectedSymptoms.size === 0) return;

        const container = document.getElementById('resultsContainer');
        container.innerHTML = `
            <div class="dp-loading">
                <div class="spinner"></div>
                <p>AI is analyzing your symptoms...</p>
            </div>
        `;

        fetch('api/disease_predict.php?action=predict', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'predict',
                symptoms: Array.from(selectedSymptoms)
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    container.innerHTML = `
                    <div class="dp-result-empty">
                        <i class="fa-solid fa-circle-exclamation" style="color: var(--dp-danger);"></i>
                        <h3>Error</h3>
                        <p>${data.error}</p>
                    </div>
                `;
                    return;
                }

                renderResults(data);
            })
            .catch(err => {
                container.innerHTML = `
                <div class="dp-result-empty">
                    <i class="fa-solid fa-circle-exclamation" style="color: var(--dp-danger);"></i>
                    <h3>Connection Error</h3>
                    <p>Could not reach the AI engine. Please try again.</p>
                </div>
            `;
                console.error(err);
            });
    }

    function renderResults(data) {
        const container = document.getElementById('resultsContainer');

        if (!data.predictions || data.predictions.length === 0) {
            container.innerHTML = `
                <div class="dp-result-empty">
                    <i class="fa-solid fa-face-meh"></i>
                    <h3>No Matches Found</h3>
                    <p>${data.message || 'Try selecting more or different symptoms.'}</p>
                </div>
            `;
            return;
        }

        let html = `<p style="color: var(--dp-text-light); font-size: 0.88em; margin: 0 0 16px;">
            <i class="fa-solid fa-circle-info" style="color: var(--dp-info);"></i>
            ${data.message}
        </p>`;

        data.predictions.forEach((pred, index) => {
            const rank = index + 1;
            const severityClass = pred.severity.toLowerCase().replace(/ to /g, '-');
            const severityLower = pred.severity.toLowerCase().includes('high') ? 'high' :
                pred.severity.toLowerCase().includes('moderate') ? 'moderate' : 'mild';

            html += `
                <div class="dp-prediction-card severity-${severityLower} ${rank === 1 ? 'rank-1' : ''}" onclick="toggleDetails(this)">
                    <div class="dp-pred-header">
                        <div class="dp-pred-rank">${rank}</div>
                        <div class="dp-pred-name">${pred.disease}</div>
                        <div class="dp-pred-confidence">
                            <div class="confidence-value">${pred.confidence}%</div>
                            <div class="confidence-label">match</div>
                        </div>
                    </div>
                    
                    <div class="dp-confidence-bar">
                        <div class="fill" style="width: 0%;" data-width="${pred.confidence}"></div>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span class="dp-severity-badge ${severityLower}">
                            <i class="fa-solid ${severityLower === 'high' ? 'fa-circle-exclamation' : severityLower === 'moderate' ? 'fa-triangle-exclamation' : 'fa-circle-check'}"></i>
                            ${pred.severity}
                        </span>
                        <span style="font-size: 0.78em; color: var(--dp-text-light);">
                            ${pred.icd10 ? '<span style="background:#f0f0f0;padding:1px 6px;border-radius:4px;font-weight:600;margin-right:6px;">' + pred.icd10 + '</span>' : ''}
                            ${pred.matched_count}/${pred.total_disease_symptoms} matched
                        </span>
                    </div>

                    <div class="expand-hint">
                        <i class="fa-solid fa-chevron-down"></i> Click for details
                    </div>

                    <div class="dp-pred-details">
                        <div class="dp-detail-section">
                            <h5><i class="fa-solid fa-info-circle"></i> Description</h5>
                            <p>${pred.description}</p>
                        </div>

                        ${pred.reasoning ? `<div class="dp-detail-section">
                            <h5><i class="fa-solid fa-brain"></i> AI Clinical Reasoning</h5>
                            <div style="background: #f0fdfa; color: #0f766e; padding: 10px; border-radius: 8px; border-left: 4px solid #00ACB1; font-size: 0.9em; font-style: italic;">
                                <i class="fa-solid fa-microchip" style="margin-right:5px; opacity:0.7;"></i>
                                "${pred.reasoning}"
                            </div>
                        </div>` : ''}
                        
                        ${pred.clinical_pearl ? `<div class="dp-detail-section">
                            <h5><i class="fa-solid fa-lightbulb"></i> Clinical Pearl</h5>
                            <p style="background: var(--dp-primary-light); padding: 8px 10px; border-radius: 6px; border-left: 3px solid var(--dp-primary);">${pred.clinical_pearl}</p>
                        </div>` : ''}
                        
                        <div class="dp-detail-section">
                            <h5><i class="fa-solid fa-check-double"></i> Matched Symptoms</h5>
                            <div class="dp-matched-symptoms">
                                ${pred.matched_symptoms.map(s => `<span class="dp-matched-chip">${s}</span>`).join('')}
                            </div>
                        </div>
                        
                        <div class="dp-detail-section">
                            <h5><i class="fa-solid fa-pills"></i> Management</h5>
                            <p>${pred.management || pred.advice || ''}</p>
                        </div>
                        
                        ${pred.when_to_refer ? `<div class="dp-detail-section">
                            <h5><i class="fa-solid fa-hospital"></i> When to Refer</h5>
                            <p style="background: #fff7ed; padding: 8px 10px; border-radius: 6px; border-left: 3px solid #f97316;">${pred.when_to_refer}</p>
                        </div>` : ''}
                        
                        ${pred.differentials && pred.differentials.length ? `<div class="dp-detail-section">
                            <h5><i class="fa-solid fa-arrows-split-up-and-left"></i> Differential Diagnoses</h5>
                            <div class="dp-matched-symptoms">
                                ${pred.differentials.map(d => `<span class="dp-matched-chip" style="background:#e8eaf6;color:#3949ab;">${d}</span>`).join('')}
                            </div>
                        </div>` : ''}
                    </div>
                </div>
            `;
        });

        html += `
            <div class="dp-disclaimer">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>Disclaimer:</strong> ${data.disclaimer || 'This is an AI-assisted prediction tool for reference only. Always consult a licensed medical professional for proper diagnosis and treatment.'}
                </div>
            </div>
        `;

        container.innerHTML = html;

        // Animate confidence bars
        setTimeout(() => {
            document.querySelectorAll('.dp-confidence-bar .fill').forEach(bar => {
                bar.style.width = bar.getAttribute('data-width') + '%';
            });
        }, 100);
    }

    function toggleDetails(card) {
        const details = card.querySelector('.dp-pred-details');
        const isShowing = details.classList.contains('show');

        // Close all others
        document.querySelectorAll('.dp-pred-details.show').forEach(d => {
            d.classList.remove('show');
            d.closest('.dp-prediction-card').classList.remove('expanded');
        });

        if (!isShowing) {
            details.classList.add('show');
            card.classList.add('expanded');
        }
    }
</script>