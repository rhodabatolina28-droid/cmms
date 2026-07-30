@section('scripts')
<script nonce="{{ $cspNonce }}">
// â”€â”€ AJAX User Management â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
let usersCurrentPage = 1;
let usersLastPage    = 1;
let usersFilterTimer = null;
let usersAbortController = null;
let usersIsFirstLoad = true;
const USERS_DATA_URL = '{{ route("super_admin.users.data") }}';

async function loadUsers(page) {
    page = page || 1;
    const params = new URLSearchParams();
    const search     = document.getElementById('searchUser').value;
    const department = document.getElementById('filterDepartment').value;
    const division   = document.getElementById('filterDivision').value;
    const role       = document.getElementById('filterRole').value;
    const status     = document.getElementById('filterStatus').value;

    if (search)     params.set('search', search);
    if (department) params.set('department', department);
    if (division)   params.set('division', division);
    if (role)       params.set('role', role);
    if (status)     params.set('status', status);
    params.set('page', page);
    params.set('per_page', 20);

    // Cancel any in-flight request
    if (usersAbortController) usersAbortController.abort();
    usersAbortController = new AbortController();

    const tbody = document.getElementById('userTable');
    if (usersIsFirstLoad) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:#94a3b8;"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading...</td></tr>';
    } else {
        tbody.classList.add('fading');
    }

    try {
        const response = await fetch(USERS_DATA_URL + '?' + params.toString(), {
            credentials: 'include',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: usersAbortController.signal
        });
        const result = await response.json();

        if (result.success) {
            usersCurrentPage = result.current_page;
            usersLastPage    = result.last_page;
            usersIsFirstLoad = false;
            renderUsersTable(result.users);
            renderUsersPagination(result.total);
            updateUserStats(result.stats, result.filtered_stats, result.total);
        } else {
            tbody.classList.remove('fading');
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:#ef4444;">Failed to load users.</td></tr>';
        }
    } catch (e) {
        if (e.name === 'AbortError') return;
        tbody.classList.remove('fading');
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:#ef4444;">Error loading users.</td></tr>';
    }
}

function renderUsersTable(users) {
    const tbody = document.getElementById('userTable');
    if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:30px;color:#94a3b8;">No users found.</td></tr>';
        tbody.classList.remove('fading');
        return;
    }

    const roleLabels = {
        user: 'User', admin: 'Division Admin', supply_officer: 'Supply Officer',
        it: 'IT Personnel', super_admin: 'Super Admin'
    };

    tbody.innerHTML = users.map(u => {
        const roleLabel = roleLabels[u.role] || u.role.replace(/_/g, ' ');
        const statusPill = u.is_active
            ? '<span class="status-pill sp-active">Active</span>'
            : '<span class="status-pill sp-inactive">Inactive</span>';
        const dept = u.department ? `<div class="dept-sub">${u.department}</div>` : '';

        return `<tr class="tr-hover-row">
            <td>
                <div class="name-bold">${u.full_name}</div>
                <div class="email-mono">${u.email}</div>
            </td>
            <td><span class="role-pill">${roleLabel}</span></td>
            <td class="td-bold">${u.office || 'No Office Assigned'}${dept}</td>
            <td class="td-center">${statusPill}</td>
            <td class="td-right">
                <div class="action-group">
                    <button onclick="resetPassword(${u.id})" class="btn-action-modern" title="Reset Password">
                        <i class="fa-solid fa-key"></i>
                    </button>
                    <button onclick="toggleUserStatus(${u.id})" class="btn-action-modern" title="Toggle Access">
                        <i class="fa-solid fa-power-off"></i>
                    </button>
                    <button onclick="editUser(${u.id})" class="btn-action-modern" title="Edit User">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
    tbody.classList.remove('fading');
}

function renderUsersPagination(total) {
    const container = document.getElementById('usersPagination');
    if (!container) return;

    const totalPages = usersLastPage;
    if (totalPages <= 1) { container.innerHTML = ''; return; }

    const btnStyle = (active, disabled) =>
        `padding:5px 10px;border:1px solid ${active ? '#0038A8' : '#cbd5e1'};border-radius:4px;` +
        `background:${active ? '#0038A8' : disabled ? '#f1f5f9' : 'white'};` +
        `color:${active ? 'white' : disabled ? '#94a3b8' : '#1e293b'};` +
        `cursor:${disabled ? 'default' : 'pointer'};font-size:12px;font-weight:700;`;

    let html = `<div style="display:flex;align-items:center;justify-content:space-between;margin-top:16px;">`;
    html += `<span style="font-size:12px;color:#64748b;">${total} user${total !== 1 ? 's' : ''} found</span>`;
    html += `<div style="display:flex;gap:4px;">`;

    html += `<button onclick="loadUsers(${usersCurrentPage - 1})" style="${btnStyle(false, usersCurrentPage <= 1)}" ${usersCurrentPage <= 1 ? 'disabled' : ''}>&lsaquo; Prev</button>`;

    let start = Math.max(1, usersCurrentPage - 2);
    let end   = Math.min(totalPages, usersCurrentPage + 2);
    if (start > 1) {
        html += `<button onclick="loadUsers(1)" style="${btnStyle(false, false)}">1</button>`;
        if (start > 2) html += `<span style="padding:5px 4px;color:#94a3b8;font-size:12px;">&hellip;</span>`;
    }
    for (let i = start; i <= end; i++) {
        html += `<button onclick="loadUsers(${i})" style="${btnStyle(i === usersCurrentPage, false)}">${i}</button>`;
    }
    if (end < totalPages) {
        if (end < totalPages - 1) html += `<span style="padding:5px 4px;color:#94a3b8;font-size:12px;">&hellip;</span>`;
        html += `<button onclick="loadUsers(${totalPages})" style="${btnStyle(false, false)}">${totalPages}</button>`;
    }

    html += `<button onclick="loadUsers(${usersCurrentPage + 1})" style="${btnStyle(false, usersCurrentPage >= totalPages)}" ${usersCurrentPage >= totalPages ? 'disabled' : ''}>Next &rsaquo;</button>`;
    html += `</div></div>`;
    container.innerHTML = html;
}

function updateUserStats(stats, filteredStats, total) {
    const isFiltered = document.getElementById('searchUser').value ||
        document.getElementById('filterDepartment').value ||
        document.getElementById('filterDivision').value ||
        document.getElementById('filterRole').value ||
        document.getElementById('filterStatus').value;

    const s = isFiltered ? filteredStats : stats;
    document.getElementById('statTotal').textContent    = s ? s.total    : (total || 0);
    document.getElementById('statActive').textContent   = s ? s.active   : '--';
    document.getElementById('statInactive').textContent = s ? s.inactive : '--';
}

function onUserFilterChange() {
    clearTimeout(usersFilterTimer);
    usersFilterTimer = setTimeout(() => loadUsers(1), 300);
}

// â”€â”€ Modal helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function openAddUserModal() {
    document.getElementById('addUserModal').style.display = 'flex';
}

function closeAddUserModal() {
    document.getElementById('addUserModal').style.display = 'none';
}

document.getElementById('addUserForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const payload = Object.fromEntries(formData.entries());

    try {
        const response = await fetch('{{ route("super_admin.users.store") }}', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            await Swal.fire({ icon: 'success', title: 'Success!', text: result.message, confirmButtonColor: '#0038A8', timer: 2000, showConfirmButton: false });
            closeAddUserModal();
            loadUsers(1);
        } else if (result.errors) {
            Swal.fire({ icon: 'error', title: 'Validation Error', html: Object.values(result.errors).flat().join('<br>'), confirmButtonColor: '#0038A8' });
        } else {
            Swal.fire({ icon: 'error', title: 'Error!', text: result.message || 'An unexpected error occurred.', confirmButtonColor: '#0038A8' });
        }
    } catch (error) {
        Swal.fire('Error!', 'An unexpected error occurred.', 'error');
    }
});

async function toggleUserStatus(id) {
    const confirmResult = await Swal.fire({
        title: 'Are you sure?', text: "You are about to toggle this account's status.", icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#0038A8', cancelButtonColor: '#d33', confirmButtonText: 'Yes, toggle it!'
    });
    if (!confirmResult.isConfirmed) return;

    try {
        const response = await fetch("{{ route('super_admin.users.toggle', ':id') }}".replace(':id', id), {
            method: 'POST', credentials: 'include',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        if (result.success) { loadUsers(usersCurrentPage); }
        else { Swal.fire('Error!', result.message, 'error'); }
    } catch (error) { Swal.fire('Error!', 'An error occurred while toggling status.', 'error'); }
}

async function resetPassword(id) {
    const confirmResult = await Swal.fire({
        title: 'Reset Password?', text: "Are you sure you want to reset this user's password?", icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#0038A8', cancelButtonColor: '#6c757d', confirmButtonText: 'Yes, reset it!'
    });
    if (!confirmResult.isConfirmed) return;

    try {
        const response = await fetch("{{ route('super_admin.users.reset_password', ':id') }}".replace(':id', id), {
            method: 'POST', credentials: 'include',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        if (result.success) { Swal.fire('Password Reset!', result.message, 'success'); }
        else { Swal.fire('Error!', result.message, 'error'); }
    } catch (error) { Swal.fire('Error!', 'An error occurred while resetting the password.', 'error'); }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) event.target.style.display = 'none';
}

// â”€â”€ Edit modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function filterEditDivisionByDept(dept) {
    const officeSelect = document.getElementById('editUserOffice');
    officeSelect.querySelectorAll('option').forEach(opt => {
        if (!opt.value) return;
        const d = opt.getAttribute('data-dept');
        opt.style.display = (!dept || !d || (dept === 'INTERNAL SERVICES DEPARTMENT' && d === 'INTERNAL') || (dept === 'TECHNICAL SERVICES DEPARTMENT' && d === 'TECHNICAL')) ? '' : 'none';
    });
}

async function editUser(id) {
    try {
        const response = await fetch("{{ route('super_admin.users') }}?get_user=" + id);
        const result = await response.json();
        if (result.success) {
            const u = result.user;
            document.getElementById('editUserId').value = u.id;
            document.getElementById('editFullName').value = u.full_name;
            document.getElementById('editEmail').value = u.email;
            document.getElementById('editUserRole').value = u.role;
            document.getElementById('editRegion').value = u.region || '';
            document.getElementById('editBranch').value = u.branch || '';
            document.getElementById('editUserDepartment').value = u.department || '';
            filterEditDivisionByDept(u.department || '');
            document.getElementById('editUserOffice').value = u.office || '';
            document.getElementById('editUserModal').style.display = 'flex';
        } else {
            Swal.fire('Error!', result.message || 'Failed to load user data', 'error');
        }
    } catch (error) {
        Swal.fire('Error!', 'An error occurred while loading user data.', 'error');
    }
}

document.getElementById('editUserForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const payload = Object.fromEntries(formData.entries());
    const userId = payload.user_id;

    try {
        const response = await fetch("{{ route('super_admin.users.update', ':id') }}".replace(':id', userId), {
            method: 'PUT', credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (result.success) {
            await Swal.fire({ icon: 'success', title: 'Success!', text: result.message, confirmButtonColor: '#0038A8', timer: 2000, showConfirmButton: false });
            document.getElementById('editUserModal').style.display = 'none';
            loadUsers(usersCurrentPage);
        } else if (result.errors) {
            Swal.fire({ icon: 'error', title: 'Validation Error', html: Object.values(result.errors).flat().join('<br>'), confirmButtonColor: '#0038A8' });
        } else {
            Swal.fire({ icon: 'error', title: 'Error!', text: result.message || 'An unexpected error occurred.', confirmButtonColor: '#0038A8' });
        }
    } catch (error) {
        Swal.fire('Error!', 'An unexpected error occurred.', 'error');
    }
});

// â”€â”€ DOMContentLoaded â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
document.addEventListener('DOMContentLoaded', function() {
    // Load users on page init
    loadUsers(1);

    // Filter listeners
    document.getElementById('searchUser').addEventListener('keyup', onUserFilterChange);
    document.getElementById('filterDepartment').addEventListener('change', function() {
        // Cascade division dropdown
        const dept = this.value;
        const divSelect = document.getElementById('filterDivision');
        divSelect.querySelectorAll('option').forEach(opt => {
            if (!opt.value) return;
            const d = opt.getAttribute('data-dept-group');
            opt.style.display = (!dept || !d || (dept === 'INTERNAL SERVICES DEPARTMENT' && d === 'INTERNAL') || (dept === 'TECHNICAL SERVICES DEPARTMENT' && d === 'TECHNICAL')) ? '' : 'none';
        });
        divSelect.value = '';
        loadUsers(1);
    });
    document.getElementById('filterDivision').addEventListener('change', () => loadUsers(1));
    document.getElementById('filterRole').addEventListener('change', () => loadUsers(1));
    document.getElementById('filterStatus').addEventListener('change', () => loadUsers(1));

    document.getElementById('addUserBtn').addEventListener('click', openAddUserModal);
    document.querySelectorAll('.close-modal-btn').forEach(el => el.addEventListener('click', closeAddUserModal));

    // Password toggle
    document.getElementById('toggleNewUserPassword').addEventListener('click', function() {
        const pwInput = document.getElementById('newUserPassword');
        const icon = this.querySelector('i');
        pwInput.type = pwInput.type === 'password' ? 'text' : 'password';
        icon.className = pwInput.type === 'password' ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
    });

    // Edit modal — department â†’ division filter
    document.getElementById('editUserDepartment').addEventListener('change', function() {
        const currentOffice = document.getElementById('editUserOffice').value;
        filterEditDivisionByDept(this.value);
        const opt = document.getElementById('editUserOffice').querySelector(`option[value="${currentOffice}"]`);
        document.getElementById('editUserOffice').value = (opt && opt.style.display !== 'none') ? currentOffice : '';
    });

    // Add modal — department â†’ division filter
    document.getElementById('newUserDepartment').addEventListener('change', function() {
        const dept = this.value;
        const officeSelect = document.getElementById('newUserOffice');
        officeSelect.querySelectorAll('option').forEach(opt => {
            if (!opt.value) return;
            const d = opt.getAttribute('data-dept');
            opt.style.display = (!dept || !d || (dept === 'INTERNAL SERVICES DEPARTMENT' && d === 'INTERNAL') || (dept === 'TECHNICAL SERVICES DEPARTMENT' && d === 'TECHNICAL')) ? '' : 'none';
        });
        officeSelect.value = '';
    });

    document.getElementById('newUserOffice').addEventListener('change', function() {
        const deptMap = {
            'RESEARCH AND INFORMATION DIVISION': 'INTERNAL SERVICES DEPARTMENT',
            'ADMINISTRATIVE DIVISION': 'INTERNAL SERVICES DEPARTMENT',
            'FINANCIAL AND MANAGEMENT DIVISION': 'INTERNAL SERVICES DEPARTMENT',
            'COMMISSION ON AUDIT': 'INTERNAL SERVICES DEPARTMENT',
            'CONCILIATION AND MEDIATION DIVISION': 'TECHNICAL SERVICES DEPARTMENT',
            'VOLUNTARY ARBITRATION DIVISION': 'TECHNICAL SERVICES DEPARTMENT',
            'WORKPLACE RELATIONS ENHANCEMENT DIVISION': 'TECHNICAL SERVICES DEPARTMENT',
            'OFFICE OF THE EXECUTIVE DIRECTOR': 'TECHNICAL SERVICES DEPARTMENT',
        };
        document.getElementById('newUserDepartment').value = deptMap[this.value] || '';
    });
});
</script>
@endsection