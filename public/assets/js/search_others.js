document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.querySelector('input[name="search"]');
    const tableBody = document.querySelector('#otherTableBody');
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
        fetchOthers(1);
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

    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();
            fetchOthers(1);
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                fetchOthers(1);
            }, 300);
        });
    }

    if (paginationContainer) {
        paginationContainer.addEventListener('click', function (e) {
            const target = e.target.closest('.pagination-btn');
            if (target && !target.disabled && !target.classList.contains('disabled')) {
                e.preventDefault();
                const page = target.getAttribute('data-page');
                if (page) fetchOthers(parseInt(page));
            }
        });
    }

    function fetchOthers(page) {
        const query = searchInput ? searchInput.value.trim() : '';
        const url = `api/search_others.php?search=${encodeURIComponent(query)}&page=${page}&sort=${currentSort}&order=${currentOrder}`;

        if (tableBody) {
            tableBody.style.opacity = '0.5';
            tableBody.style.pointerEvents = 'none';
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                renderTable(data.data);
                renderPagination(data.pagination);

                if (tableContainer && tableContainer.style.display === 'none') {
                    tableContainer.style.display = 'block';
                    const toggleBtn = document.getElementById('toggleTableBtn');
                    if (toggleBtn) {
                        toggleBtn.style.background = '#666';
                        toggleBtn.innerHTML = '<i class="fa-solid fa-eye-slash" id="toggleIcon"></i> Hide Records';
                    }
                }

                if (tableBody) {
                    tableBody.style.opacity = '1';
                    tableBody.style.pointerEvents = 'auto';
                }

                const newUrl = `${window.location.pathname}?search=${encodeURIComponent(query)}&page=${page}&sort=${currentSort}&order=${currentOrder}`;
                window.history.pushState({ path: newUrl }, '', newUrl);
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                if (tableBody) {
                    tableBody.style.opacity = '1';
                    tableBody.style.pointerEvents = 'auto';
                }
            });
    }

    function renderTable(data) {
        if (!tableBody) return;
        tableBody.innerHTML = '';

        if (data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 20px;">No records found.</td></tr>';
            return;
        }

        const escapeHtml = (str) => String(str || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
        const escapeJs = (str) => String(str || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');

        data.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="font-weight: bold; text-transform: uppercase;">${escapeHtml(row.name)}</td>
                <td>${row.age_computed}</td>
                <td>${escapeHtml(row.sdo)}</td>
                <td>${escapeHtml(row.gender)}</td>
                <td>${escapeHtml(row.address)}</td>
                <td>${escapeHtml(row.remarks)}</td>
                <td class="actions">
                    <button class="view" data-tooltip="View Record" onclick="viewOtherRecords('${row.id}', '${escapeJs(row.name)}')">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    <button class="edit" data-tooltip="Edit Record" onclick="editOther('${row.id}')">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button class="archive" data-tooltip="Archive" onclick="archiveOther('${row.id}', '${escapeJs(row.name)}')">
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
            return;
        }

        let html = '';
        html += `<button class="pagination-btn ${currentPage <= 1 ? 'disabled' : ''}" ${currentPage <= 1 ? 'disabled' : ''} data-page="${currentPage - 1}"><i class="fa-solid fa-chevron-left"></i></button>`;
        html += `<span class="pagination-info">Page ${currentPage} of ${totalPages}</span>`;
        html += `<button class="pagination-btn ${currentPage >= totalPages ? 'disabled' : ''}" ${currentPage >= totalPages ? 'disabled' : ''} data-page="${currentPage + 1}"><i class="fa-solid fa-chevron-right"></i></button>`;

        paginationContainer.innerHTML = html;
        paginationContainer.style.display = 'flex';
    }
});
