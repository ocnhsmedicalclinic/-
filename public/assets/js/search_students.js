
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.querySelector('input[name="search"]');
    const tableBody = document.querySelector('#studentData');
    const paginationContainer = document.querySelector('.pagination');
    const searchForm = document.querySelector('.search-box');
    const tableContainer = document.querySelector('.table-container');

    let debounceTimer;
    let currentSort = new URLSearchParams(window.location.search).get('sort') || 'name';
    let currentOrder = new URLSearchParams(window.location.search).get('order') || 'ASC';

    // Make sortTable global so the inline onclick can find it
    window.sortTable = function (column) {
        if (currentSort === column) {
            currentOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
        } else {
            currentSort = column;
            currentOrder = 'ASC';
        }
        updateSortIcons(column, currentOrder);
        fetchStudents(1);
    };

    function updateSortIcons(activeColumn, order) {
        document.querySelectorAll('th .th-filler').forEach(icon => {
            icon.className = 'fa-solid fa-sort th-filler';
        });
        const activeTh = document.querySelector(`th[onclick="sortTable('${activeColumn}')"]`);
        if (activeTh) {
            const icon = activeTh.querySelector('.th-filler');
            if (icon) {
                icon.className = `fa-solid fa-sort-${order === 'ASC' ? 'up' : 'down'} th-filler active-sort`;
            }
        }
    }

    // Prevent form submission on Enter
    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            fetchStudents(1);
        });
    }

    // Debounced Search Input
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                fetchStudents(1);
            }, 300); // 300ms delay
        });
    }

    // Pagination Click Handling (Delegation)
    if (paginationContainer) {
        paginationContainer.addEventListener('click', function (e) {
            // Check for button or link clicks within pagination container
            const target = e.target.closest('.pagination-btn');

            if (target && !target.disabled && !target.classList.contains('disabled')) {
                e.preventDefault();
                const page = target.getAttribute('data-page');
                if (page) {
                    fetchStudents(parseInt(page));
                }
            }
        });
    }

    function fetchStudents(page, isAutoRefresh = false) {
        const query = searchInput ? searchInput.value.trim() : '';
        const url = `api/search_students.php?search=${encodeURIComponent(query)}&page=${page}&sort=${currentSort}&order=${currentOrder}`;

        // Show loading state (only for manual search/pagination, not auto-refresh)
        if (tableBody && !isAutoRefresh) {
            tableBody.style.opacity = '0.5';
            tableBody.style.pointerEvents = 'none';
        }

        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Update tracker for total records
                if (data.pagination && data.pagination.total_records !== undefined) {
                    window.lastTotalRecords = data.pagination.total_records;
                }

                renderTable(data.data);
                renderPagination(data.pagination);

                // Show table if hidden (initial search case)
                if (tableContainer && tableContainer.style.display === 'none') {
                    tableContainer.style.display = 'block';
                    // Update "Show Students" button state if present
                    const toggleBtn = document.getElementById('toggleTableBtn');
                    if (toggleBtn) {
                        toggleBtn.style.background = '#666';
                        toggleBtn.innerHTML = '<i class="fa-solid fa-eye-slash" id="toggleIcon"></i> Hide Students';
                    }
                }

                if (tableBody) {
                    tableBody.style.opacity = '1';
                    tableBody.style.pointerEvents = 'auto';
                }

                // Update URL without reload (History API) - only for manual actions
                if (!isAutoRefresh) {
                    const newUrl = `${window.location.pathname}?search=${encodeURIComponent(query)}&page=${page}&sort=${currentSort}&order=${currentOrder}`;
                    window.history.pushState({ path: newUrl }, '', newUrl);
                }
            })
            .catch(error => {
                console.error('Error fetching students:', error);
                if (tableBody && !isAutoRefresh) {
                    tableBody.style.opacity = '1';
                    tableBody.style.pointerEvents = 'auto';
                    tableBody.innerHTML = '<tr><td colspan="13" style="text-align:center; color: red;">Error loading data. Please try again.</td></tr>';
                }
            });
    }

    // --- LIVE UPDATE POLLING ---
    // Every 10 seconds, check if the student count has changed
    window.lastTotalRecords = parseInt(document.querySelector('.pagination-info')?.textContent.split('of')?.pop() || 0);

    // Initial fetch to get the current count and sync everything
    // Actually we can just get it from the initial page load or first fetch
    if (searchInput && searchInput.value === "") {
        fetchStudents(1, true);
    }

    setInterval(() => {
        const query = searchInput ? searchInput.value.trim() : '';
        // Only auto-refresh if:
        // 1. Not searching (query is empty)
        // 2. Table is visible
        // 3. User is on page 1
        if (query === '' &&
            tableContainer && tableContainer.style.display !== 'none' &&
            (new URLSearchParams(window.location.search).get('page') || '1') === '1') {

            fetch(`api/search_students.php?count_only=1`) // We'll update the API to handle this lightweight request
                .then(r => r.json())
                .then(data => {
                    const currentTotal = data.pagination ? data.pagination.total_records : 0;
                    if (currentTotal > window.lastTotalRecords) {
                        console.log("New student detected! Refreshing list...");
                        fetchStudents(1, true); // Silent refresh

                        // Optional: Show a small toast or notification
                        if (typeof Swal !== 'undefined' && !Swal.isVisible()) {
                            const Toast = Swal.mixin({
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000,
                                timerProgressBar: true
                            });
                            Toast.fire({
                                icon: 'info',
                                title: 'New student registration detected'
                            });
                        }
                    }
                    window.lastTotalRecords = currentTotal;
                })
                .catch(() => { });
        }
    }, 10000); // 10 seconds polling

    function renderTable(students) {
        if (!tableBody) return;

        tableBody.innerHTML = '';
        if (students.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="13" style="text-align:center; padding: 20px;">No students found matching your criteria.</td></tr>';
            return;
        }

        students.forEach(student => {
            const tr = document.createElement('tr');

            // Helper to escape HTML for safety
            const escapeHtml = (unsafe) => {
                return (unsafe || '').replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            };

            // Helper to escape for JS string inside attribute
            const escapeJs = (str) => {
                return (str || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
            };

            tr.innerHTML = `
                <td>${escapeHtml(student.name)}</td>
                <td>${escapeHtml(student.lrn)}</td>
                <td>${escapeHtml(student.curriculum)}</td>
                <td>${escapeHtml(student.address)}</td>
                <td>${student.age !== null ? student.age : '-'}</td>
                <td>${escapeHtml(student.gender)}</td>
                <td>${student.birth_date_formatted || '-'}</td>
                <td>${escapeHtml(student.birthplace)}</td>
                <td>${escapeHtml(student.religion)}</td>
                <td>${escapeHtml(student.guardian)}</td>
                <td>${escapeHtml(student.contact)}</td>
                <td class="actions">
                    <button class="view" data-tooltip="View Record"
                        onclick="openViewModal('${student.id}', '${escapeJs(student.name)}')">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    <button class="edit" data-tooltip="Edit Student" onclick="openEditModal('${student.id}')">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button class="archive" data-tooltip="Archive Student"
                        onclick="confirmArchive('${student.id}', '${escapeJs(student.name)}')">
                        <i class="fa-solid fa-box-archive"></i>
                    </button>
                </td>
            `;
            tableBody.appendChild(tr);
        });
    }

    function renderPagination(pagination) {
        if (!paginationContainer) return;

        const currentPage = parseInt(pagination.current_page);
        const totalPages = parseInt(pagination.total_pages);

        if (totalPages <= 1) {
            paginationContainer.style.display = 'none';
            paginationContainer.innerHTML = '';
            return;
        }

        let html = '';

        // Prev Button
        if (currentPage > 1) {
            html += `<button class="pagination-btn" data-page="${currentPage - 1}"><i class="fa-solid fa-chevron-left"></i></button>`;
        } else {
            html += `<button class="pagination-btn disabled" disabled><i class="fa-solid fa-chevron-left"></i></button>`;
        }

        // Info
        html += `<span class="pagination-info">Page ${currentPage} of ${totalPages}</span>`;

        // Next Button
        if (currentPage < totalPages) {
            html += `<button class="pagination-btn" data-page="${currentPage + 1}"><i class="fa-solid fa-chevron-right"></i></button>`;
        } else {
            html += `<button class="pagination-btn disabled" disabled><i class="fa-solid fa-chevron-right"></i></button>`;
        }

        paginationContainer.innerHTML = html;
        paginationContainer.style.display = 'flex';
        // Apply container class if not present (handled by CSS now)
        paginationContainer.className = 'pagination pagination-container'; // Ensure both classes
    }
});
