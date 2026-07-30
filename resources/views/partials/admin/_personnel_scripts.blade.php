@section('scripts')
<script nonce="{{ $cspNonce }}">
    function openAddPersonnelModal() {
        document.getElementById('addPersonnelModal').style.display = 'flex';
    }

    function closeAddPersonnelModal() {
        document.getElementById('addPersonnelModal').style.display = 'none';
    }

    document.getElementById('addPersonnelForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const payload = Object.fromEntries(formData.entries());

        fetch('{{ route("personnel.store") }}', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('addPersonnelModal').style.display = 'none';
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message,
                    confirmButtonColor: '#0038A8',
                    timer: 2000,
                    showConfirmButton: false
                }).then(function() {
                    location.reload();
                });
            } else {
                document.getElementById('addPersonnelModal').style.display = 'none';
                Swal.fire({
                    icon: 'error',
                    title: 'Failed!',
                    text: data.message || 'An error occurred while saving.',
                    confirmButtonColor: '#0038A8'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('addPersonnelModal').style.display = 'none';
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Connection error. Please try again.',
                confirmButtonColor: '#0038A8'
            });
        });
    });

    function normalizeOfficeDept(officeRaw) {
        if (!officeRaw) return "";
        let cleanRow = officeRaw.toUpperCase().replace(/[^A-Z0-9]/g, '').trim();
        
        if (cleanRow === 'RID' || cleanRow === 'RIDOFFICE' || cleanRow === 'RESEARCHANDINFORMATIONDIVISION' || cleanRow === 'RESEARCHANDINFODIVISION' || cleanRow === 'RESEARCHANDINFO' || cleanRow === 'RESEARCHINFO' || cleanRow === 'RESEARCHANDINFORMATION' || cleanRow === 'ICT' || cleanRow === 'ICTOFFICE' || cleanRow === 'RESEARCH' || cleanRow === 'RESEARCHDIVISION') return 'RESEARCHANDINFORMATIONDIVISION';
        if (cleanRow === 'AD' || cleanRow === 'ADMIN' || cleanRow === 'ADMINOFFICE' || cleanRow === 'ADMINISTRATIVEDIVISION' || cleanRow === 'ADMINISTRATIVE') return 'ADMINISTRATIVEDIVISION';
        if (cleanRow === 'CMD' || cleanRow === 'CMDOFFICE' || cleanRow === 'CONCILIATIONANDMEDIATIONDIVISION' || cleanRow === 'CONCILIATIONANDMEDIATION' || cleanRow === 'CONCILIATIONMEDIATION' || cleanRow === 'CONCILIATION' || cleanRow === 'CONCILIATIONDIVISION') return 'CONCILIATIONANDMEDIATIONDIVISION';
        if (cleanRow === 'OED' || cleanRow === 'OEDOFFICE' || cleanRow === 'OFFICEOFTHEEXECUTIVEDIRECTOR' || cleanRow === 'EXECUTIVEDIRECTOR' || cleanRow === 'EXECUTIVEDIRECTOROFFICE') return 'OFFICEOFTHEEXECUTIVEDIRECTOR';
        if (cleanRow === 'COA' || cleanRow === 'COAOFFICE' || cleanRow === 'COMMISSIONONAUDIT' || cleanRow === 'AUDIT') return 'COMMISSIONONAUDIT';
        if (cleanRow === 'TSD' || cleanRow === 'TSDOFFICE' || cleanRow === 'TECHNICALSERVICESDIVISION' || cleanRow === 'TECHNICALSERVICES' || cleanRow === 'TECHNICALSERVICESDEPARTMENT' || cleanRow === 'TECHNICALSERVICESDIV' || cleanRow === 'TECHNICAL' || cleanRow === 'TECHNICALSERVICESDEPT' || cleanRow === 'TECHNICALDEPT') return 'TECHNICALSERVICESDEPARTMENT';
        if (cleanRow === 'ISD' || cleanRow === 'ISDOFFICE' || cleanRow === 'INTERNALSERVICESDIVISION' || cleanRow === 'INTERNALSERVICES' || cleanRow === 'INTERNALSERVICESDEPARTMENT' || cleanRow === 'INTERNALSERVICESDIV' || cleanRow === 'INTERNAL' || cleanRow === 'INTERNALSERVICESDEPT' || cleanRow === 'INTERNALDEPT') return 'INTERNALSERVICESDEPARTMENT';
        if (cleanRow === 'FMD' || cleanRow === 'FMDOFFICE' || cleanRow === 'FINANCIALANDMANAGEMENTDIVISION' || cleanRow === 'FINANCIALANDMANAGEMENT' || cleanRow === 'FINANCIALMANAGEMENT' || cleanRow === 'FINANCIAL' || cleanRow === 'FINANCE' || cleanRow === 'FINANCEDIVISION' || cleanRow === 'FINANCIALDIVISION' || cleanRow === 'FINANCEMODULE') return 'FINANCIALANDMANAGEMENTDIVISION';
        if (cleanRow === 'VAD' || cleanRow === 'VADOFFICE' || cleanRow === 'VOLUNTARYARBITRATIONDIVISION' || cleanRow === 'VOLUNTARYARBITRATION' || cleanRow === 'VOLUNTARY') return 'VOLUNTARYARBITRATIONDIVISION';
        if (cleanRow === 'WRED' || cleanRow === 'WREDOFFICE' || cleanRow === 'WORKPLACERELATIONSENHANCEMENTDIVISION' || cleanRow === 'WORKPLACERELATIONSENHANCEMENT' || cleanRow === 'WORKPLACERELATIONS' || cleanRow === 'WORKPLACE') return 'WORKPLACERELATIONSENHANCEMENTDIVISION';
        
        return cleanRow;
    }

    function filterTable() {
        const search = document.getElementById('searchPersonnel').value.toLowerCase();
        const division = document.getElementById('filterDivision') ? document.getElementById('filterDivision').value.toUpperCase() : '';
        const department = document.getElementById('filterDepartment').value.toUpperCase();
        const status = document.getElementById('filterStatus').value;

        const rows = document.querySelectorAll('.person-row');

        rows.forEach(row => {
            const name = row.querySelector('.name-cell').textContent.toLowerCase();
            const email = row.querySelector('.email-cell').textContent.toLowerCase();
            const rowStatus = row.getAttribute('data-status');
            const rowDivision = row.getAttribute('data-division') || '';
            const rowDepartment = row.getAttribute('data-department') || '';

            const matchesSearch = name.includes(search) || email.includes(search);
            const matchesStatus = status === '' || rowStatus === status;

            let matchesDiv = division === '';
            if (!matchesDiv) {
                const cleanSearch = division.replace(/[^A-Z0-9]/g, '').trim();
                const normDivision = rowDivision.replace(/[^A-Z0-9]/g, '').trim();
                matchesDiv = normDivision.includes(cleanSearch);
            }

            const normDivision = normalizeOfficeDept(rowDivision);
            const normDepartment = normalizeOfficeDept(rowDepartment);

            const internalOffices = ['ADMINISTRATIVEDIVISION', 'INTERNALSERVICESDEPARTMENT', 'COMMISSIONONAUDIT', 'FINANCIALANDMANAGEMENTDIVISION', 'RESEARCHANDINFORMATIONDIVISION'];
            const technicalOffices = ['CONCILIATIONANDMEDIATIONDIVISION', 'TECHNICALSERVICESDEPARTMENT', 'OFFICEOFTHEEXECUTIVEDIRECTOR', 'VOLUNTARYARBITRATIONDIVISION', 'WORKPLACERELATIONSENHANCEMENTDIVISION'];

            let matchesDept = department === '';
            if (!matchesDept) {
                const cleanSearch = department.replace(/[^A-Z0-9]/g, '').trim();
                if (cleanSearch === 'INTERNALSERVICESDEPARTMENT') {
                    matchesDept = (normDepartment === 'INTERNALSERVICESDEPARTMENT') || internalOffices.includes(normDivision);
                } else if (cleanSearch === 'TECHNICALSERVICESDEPARTMENT') {
                    matchesDept = (normDepartment === 'TECHNICALSERVICESDEPARTMENT') || technicalOffices.includes(normDivision);
                } else {
                    matchesDept = (normDepartment === cleanSearch) || normDepartment.includes(cleanSearch);
                }
            }

            row.style.display = (matchesSearch && matchesStatus && matchesDiv && matchesDept) ? '' : 'none';
        });
    }

    function viewPersonnel(id) {
        const modal = document.getElementById('personnelModal');
        const loading = document.getElementById('modalLoading');
        const content = document.getElementById('modalContent');
        
        modal.style.display = 'flex';
        loading.style.display = 'block';
        content.style.display = 'none';

        fetch("{{ route('personnel.show', ':id') }}".replace(':id', id), {
            credentials: 'include'
        })
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';
                content.style.display = 'block';

                const user = data.user;
                document.getElementById('detName').textContent = user.full_name;
                document.getElementById('detEmail').textContent = user.email;
                document.getElementById('detPosition').textContent = user.position || 'N/A';
                document.getElementById('detOffice').textContent = user.office || 'N/A';
 
                
                document.getElementById('detDepartmentSelect').value = user.department || '';

                const badge = document.getElementById('detStatusBadge');
                badge.textContent = user.is_active ? 'Active' : 'Inactive';
                badge.className = `status-pill sp-${user.is_active ? 'active' : 'inactive'}`;

                renderAssets(data.assets);
                renderStats(data.stats);
                renderRequests(data.requests);

                document.getElementById('btnToggleStatus').onclick = () => toggleStatus(id);
            });
    }

    function getAssetBadge(status) {
        const s = (status || '').toLowerCase();
        if (s === 'active' || s === 'serviceable') return '<span class="status-pill sp-active" style="font-size:10px;">Active</span>';
        if (s === 'spare') return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;background:#e0e7ff;color:#3730a3;">Spare</span>';
        if (s === 'for repair' || s === 'defective') return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;background:#fef3c7;color:#92400e;">For Repair</span>';
        if (s === 'for disposal' || s === 'scrapped') return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;background:#fee2e2;color:#b91c1c;">For Disposal</span>';
        return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;background:#f1f5f9;color:#64748b;">' + (status || 'N/A') + '</span>';
    }

    function getRequestBadge(status) {
        const s = (status || '').toLowerCase();
        if (s === 'completed') return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;background:#dcfce7;color:#166534;">Completed</span>';
        if (s === 'pending') return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;background:#fef3c7;color:#92400e;">Pending</span>';
        if (s === 'ongoing') return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;background:#dbeafe;color:#1e40af;">Ongoing</span>';
        if (s === 'rejected') return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;background:#fee2e2;color:#b91c1c;">Rejected</span>';
        if (s === 'awaiting signature') return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;background:#faf5ff;color:#7e22ce;">Awaiting Signature</span>';
        if (s === 'scheduled') return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;background:#f0fdf4;color:#065f46;">Scheduled</span>';
        if (s === 'cancelled') return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;background:#f3f4f6;color:#6b7280;">Cancelled</span>';
        return '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;background:#f1f5f9;color:#64748b;">' + (status || 'N/A') + '</span>';
    }

    function renderAssets(assets) {
        const container = document.getElementById('detAssets');
        if (assets.length > 0) {
            let html = '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
            html += '<thead><tr style="border-bottom:2px solid #e2e8f0;background:#f8fafc;"><th style="padding:8px 10px;text-align:left;font-weight:700;color:#475569;text-transform:uppercase;font-size:10px;">Asset Name</th><th style="padding:8px 10px;text-align:left;font-weight:700;color:#475569;text-transform:uppercase;font-size:10px;">Serial No</th><th style="padding:8px 10px;text-align:center;font-weight:700;color:#475569;text-transform:uppercase;font-size:10px;">Status</th></tr></thead><tbody>';
            assets.forEach(a => {
                html += `<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:8px 10px;font-weight:600;color:#1e293b;">${a.item_name}</td><td style="padding:8px 10px;color:#64748b;font-size:11px;font-family:monospace;">${a.serial_number || '-'}</td><td style="padding:8px 10px;text-align:center;">${getAssetBadge(a.status)}</td></tr>`;
            });
            container.innerHTML = html + '</tbody></table>';
        } else {
            container.innerHTML = '<div style="text-align:center;padding:20px;color:#94a3b8;"><i class="fa-solid fa-box-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:0.4;"></i><span style="font-size:13px;">No assets currently assigned to this personnel.</span></div>';
        }
    }

    function renderStats(stats) {
        document.getElementById('detStats').innerHTML = `
            <div class="stat-card">
                <p class="stat-label">TOTAL</p>
                <p class="stat-value">${stats.total}</p>
            </div>
            <div class="stat-card-done">
                <p class="stat-label-green">DONE</p>
                <p class="stat-value-green">${stats.completed}</p>
            </div>
            <div class="stat-card-pending">
                <p class="stat-label-yellow">PENDING</p>
                <p class="stat-value-yellow">${stats.pending}</p>
            </div>
            <div class="stat-card-rejected">
                <p class="stat-label-red">REJECTED</p>
                <p class="stat-value-red">${stats.rejected}</p>
            </div>
        `;
    }

    function renderRequests(requests) {
        const container = document.getElementById('detRequests');
        if (requests.length > 0) {
            let html = '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
            html += '<thead><tr style="border-bottom:2px solid #e2e8f0;background:#f8fafc;"><th style="padding:8px 10px;text-align:left;font-weight:700;color:#475569;text-transform:uppercase;font-size:10px;">Request #</th><th style="padding:8px 10px;text-align:left;font-weight:700;color:#475569;text-transform:uppercase;font-size:10px;">Type</th><th style="padding:8px 10px;text-align:center;font-weight:700;color:#475569;text-transform:uppercase;font-size:10px;">Status</th></tr></thead><tbody>';
            requests.forEach(r => {
                html += `<tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:8px 10px;font-weight:700;color:#0038A8;font-size:11px;">${r.display_number || r.request_number}</td><td style="padding:8px 10px;color:#64748b;font-size:11px;">${r.type}</td><td style="padding:8px 10px;text-align:center;">${getRequestBadge(r.status)}</td></tr>`;
            });
            container.innerHTML = html + '</tbody></table>';
        } else {
            container.innerHTML = '<div style="text-align:center;padding:20px;color:#94a3b8;"><i class="fa-solid fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;opacity:0.4;"></i><span style="font-size:13px;">No request history found.</span></div>';
        }
    }

    function toggleStatus(id) {
        closeModal();
        setTimeout(() => {
            Swal.fire({
                title: 'Are you sure?',
                text: "Toggle this personnel's account status?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0038A8',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, toggle it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("{{ route('personnel.toggle', ':id') }}".replace(':id', id), {
                        method: 'POST',
                        credentials: 'include',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(() => location.reload());
                }
            });
        }, 200);
    }



    function closeModal() {
        document.getElementById('personnelModal').style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            closeModal();
            closeAddPersonnelModal();
        }
    }
document.addEventListener('DOMContentLoaded', function() {
    // Password toggle
    var togglePw = document.getElementById('togglePersonnelPassword');
    if (togglePw) {
        togglePw.addEventListener('click', function() {
            var pwInput = document.getElementById('personnelPassword');
            var icon = this.querySelector('i');
            if (pwInput.type === 'password') {
                pwInput.type = 'text';
                icon.className = 'fa-solid fa-eye-slash';
            } else {
                pwInput.type = 'password';
                icon.className = 'fa-solid fa-eye';
            }
        });
    }

    document.getElementById('searchPersonnel').addEventListener('keyup', filterTable);
    var fd = document.getElementById('filterDivision');
    if (fd) { fd.addEventListener('keyup', filterTable); }
    document.getElementById('filterDepartment').addEventListener('change', filterTable);
    document.getElementById('filterStatus').addEventListener('change', filterTable);
    document.getElementById('addPersonnelBtn').addEventListener('click', openAddPersonnelModal);
    document.querySelectorAll('.close-add-personnel-btn').forEach(function(el) {
        el.addEventListener('click', closeAddPersonnelModal);
    });
    document.querySelectorAll('.close-view-modal-btn').forEach(function(el) {
        el.addEventListener('click', closeModal);
    });
});
document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-action="view-personnel"]');
    if (btn) { viewPersonnel(parseInt(btn.dataset.id)); }
});
</script>
@endsection