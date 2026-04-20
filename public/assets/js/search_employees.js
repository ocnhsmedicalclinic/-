
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.querySelector('input[name="search"]');
    const tableBody = document.querySelector('#employeeTableBody');
    const paginationContainer = document.querySelector('.pagination');
    const searchForm = document.querySelector('.search-box');
    const tableContainer = document.querySelector('.table-container');

    let debounceTimer;
    let currentSort = new URLSearchParams(window.location.search).get('sort') || 'name';
    let currentOrder = new URLSearchParams(window.location.search).get('order') || 'ASC';

    // Make sortTable global
    window.sortTable = function (column) {
        if (currentSort === column) {
            currentOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
        } else {
            currentSort = column;
            currentOrder = 'ASC';
        }
        updateSortIcons(column, currentOrder);
        fetchEmployees(1);
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

    // Prevent form submission
    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            fetchEmployees(1);
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                fetchEmployees(1);
            }, 300);
        });
    }

    if (paginationContainer) {
        paginationContainer.addEventListener('click', function (e) {
            const target = e.target.closest('.pagination-btn');
            if (target && !target.disabled && !target.classList.contains('disabled')) {
                e.preventDefault();
                const page = target.getAttribute('data-page');
                if (page) fetchEmployees(parseInt(page));
            }
        });
    }

    function fetchEmployees(page) {
        const query = searchInput ? searchInput.value.trim() : '';
        const url = `api/search_employees.php?search=${encodeURIComponent(query)}&page=${page}&sort=${currentSort}&order=${currentOrder}`;

        if (tableBody) {
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
                renderTable(data.data);
                renderPagination(data.pagination);

                if (tableContainer && tableContainer.style.display === 'none') {
                    tableContainer.style.display = 'block';
                    const toggleBtn = document.getElementById('toggleTableBtn');
                    if (toggleBtn) {
                        toggleBtn.style.background = '#666';
                        toggleBtn.innerHTML = '<i class="fa-solid fa-eye-slash" id="toggleIcon"></i> Hide Employees';
                    }
                }

                if (tableBody) {
                    tableBody.style.opacity = '1';
                    tableBody.style.pointerEvents = 'auto';
                }

                // Update URL
                const newUrl = `${window.location.pathname}?search=${encodeURIComponent(query)}&page=${page}&sort=${currentSort}&order=${currentOrder}`;
                window.history.pushState({ path: newUrl }, '', newUrl);
            })
            .catch(error => {
                console.error('Error fetching employees:', error);
                if (tableBody) {
                    tableBody.style.opacity = '1';
                    tableBody.style.pointerEvents = 'auto';
                    tableBody.innerHTML = '<tr><td colspan="12" style="text-align:center; color: red;">Error loading data. Please try again.</td></tr>';
                }
            });
    }

    function renderTable(employees) {
        if (!tableBody) return;

        tableBody.innerHTML = '';
        if (employees.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="12" style="text-align:center; padding: 40px; color: #888;">
                        <i class="fa-solid fa-users" style="font-size: 48px; margin-bottom: 10px; opacity: 0.3;"></i><br>
                        No employee records found.
                    </td>
                </tr>`;
            return;
        }

        const escapeHtml = (unsafe) => {
            return String(unsafe || '').replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        };

        const escapeJs = (str) => {
            return String(str || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
        };

        employees.forEach(emp => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${emp.entry_date_formatted}</td>
                <td style="font-weight: bold;">${escapeHtml(emp.name)}</td>
                <td>${emp.birth_date_formatted}</td>
                <td>${emp.age !== null ? emp.age : '-'}</td>
                <td>${escapeHtml(emp.gender)}</td>
                <td>${escapeHtml(emp.civil_status)}</td>
                <td>${emp.service_years}</td>
                <td>${escapeHtml(emp.school_district_division)}</td>
                <td>${escapeHtml(emp.position)}</td>
                <td>${escapeHtml(emp.designation)}</td>
                <td>${escapeHtml(emp.first_year_in_service)}</td>
                <td class="actions">
                    <button class="view" data-tooltip="View Record"
                        onclick="viewEmployeeRecords('${emp.id}', '${escapeJs(emp.name)}')">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    <button class="edit" data-tooltip="Edit Employee" onclick="editEmployee('${emp.id}')">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button class="archive" data-tooltip="Archive"
                        onclick="archiveEmployee('${emp.id}', '${escapeJs(emp.name)}')">
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
        if (currentPage > 1) {
            html += `<button class="pagination-btn" data-page="${currentPage - 1}"><i class="fa-solid fa-chevron-left"></i></button>`;
        } else {
            html += `<button class="pagination-btn disabled" disabled><i class="fa-solid fa-chevron-left"></i></button>`;
        }

        html += `<span class="pagination-info">Page ${currentPage} of ${totalPages}</span>`;

        if (currentPage < totalPages) {
            html += `<button class="pagination-btn" data-page="${currentPage + 1}"><i class="fa-solid fa-chevron-right"></i></button>`;
        } else {
            html += `<button class="pagination-btn disabled" disabled><i class="fa-solid fa-chevron-right"></i></button>`;
        }

        paginationContainer.innerHTML = html;
        paginationContainer.style.display = 'flex';
        paginationContainer.classList.add('pagination-container');
    }
});
