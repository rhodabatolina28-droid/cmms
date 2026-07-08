@extends('layouts.app')

@section('title', 'System Audit Logs | Super Admin')
@section('page-title', 'Audit Logs')

@section('styles')
    <style nonce="{{ $cspNonce }}">
        .audit-logs-container {
            width: 100%;
            margin-top: -10px;
            animation: fadeInSlide 0.4s ease-out;
        }

        .polish-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .card-header-accent {
            background: #f8fafc;
            padding: 20px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .filter-ribbon {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }

        .ribbon-input {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            transition: all 0.2s;
        }

        .ribbon-input:focus {
            border-color: #0038A8;
            box-shadow: 0 0 0 3px rgba(0, 56, 168, 0.05);
        }

        .gov-table-premium {
            width: 100%;
            border-collapse: collapse;
        }

        .gov-table-premium th {
            background: #f1f5f9;
            padding: 12px 15px;
            font-size: 11px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }

        .gov-table-premium td {
            padding: 12px 15px;
            font-size: 13px;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .gov-table-premium tr.tr-hover-row { transition: all 0.2s; position: relative; }
        .gov-table-premium tr.tr-hover-row:hover { background: #f8fafc !important; transform: scale(1.002); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .gov-table-premium tr.tr-hover-row:hover td:first-child { box-shadow: inset 4px 0 0 #0038A8; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }

        /* Summary Stats Ribbon */
        .stats-ribbon {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .stat-item-premium {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-item-premium:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px -8px rgba(0, 56, 168, 0.12);
            border-color: rgba(0, 56, 168, 0.15);
        }

        .stat-info p { margin: 0; font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px; }
        .stat-info h4 { margin: 2px 0 0; font-size: 18px; font-weight: 800; color: #1e293b; }

        /* Module Badges */
        .module-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .mb-auth { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; box-shadow: 0 2px 4px rgba(185, 28, 28, 0.15); }
        .mb-inventory { background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.15); }
        .mb-requests { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; box-shadow: 0 2px 4px rgba(29, 78, 216, 0.15); }
        .mb-users { background: #fef9c3; color: #a16207; border: 1px solid #fef08a; box-shadow: 0 2px 4px rgba(161, 98, 7, 0.15); }
        .mb-default { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

        .timestamp-box {
            font-size: 12px;
            color: #64748b;
            line-height: 1.2;
        }

        .ip-addr {
            font-family: monospace;
            font-size: 11px;
            color: #94a3b8;
            display: block;
            margin-top: 2px;
        }
        .btn-hover-archive:hover { background: #b91c1c !important; }
        #globalAlertError, #globalAlertSuccess { display: none !important; }

        .h3-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; }
        .icon-blue { margin-right: 10px; color: #0038A8; }
        .p-subtitle { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .header-action-group { display: flex; align-items: center; gap: 15px; }
        .btn-archive { font-size: 12px; font-weight: 800; color: white; background: #dc2626; border: none; padding: 10px 20px; border-radius: 99px; cursor: pointer; min-height: 44px; transition: background 0.2s; }
        .sync-badge { font-size: 11px; font-weight: 800; color: #475569; background: #f1f5f9; padding: 4px 12px; border-radius: 99px; }
        .content-padding { padding: 25px 30px; }
        .search-wrapper-lg { position: relative; flex: 1; min-width: 300px; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; }
        .search-input { width: 100%; padding-left: 35px; }
        .w-180 { width: 180px; }
        .table-wrap { overflow-x: auto; }
        .name-bold { font-weight: 800; color: #1e293b; }
        .name-semi { font-weight: 700; color: #1e293b; }
        .time-sub { font-size: 11px; opacity: 0.8; }
        .action-label { font-size: 12px; font-weight: 700; color: #0038A8; text-transform: uppercase; }
        .td-details { color: #475569; line-height: 1.4; font-size: 12px; }
        .td-region { font-weight: 800; color: #64748b; font-size: 11px; }
        .td-empty { padding: 60px; text-align: center; color: #94a3b8; }
        .empty-icon { font-size: 40px; margin-bottom: 15px; display: block; opacity: 0.3; }
        .fw-700 { font-weight: 700; }
        .pagination-flex { margin-top: 25px; display: flex; justify-content: center; }
        .swal2-checkbox, #swal2-checkbox, div.swal2-checkbox { display: none !important; }

        @media (max-width: 767px) {
            .card-header-accent { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 16px 20px !important; }
            .h3-title { font-size: 16px !important; }
            .p-subtitle { font-size: 11px !important; }
            .header-action-group { flex-direction: column !important; align-items: stretch !important; gap: 10px !important; width: 100% !important; }
            .btn-archive { width: 100% !important; justify-content: center !important; text-align: center !important; }
            .sync-badge { display: none !important; }
            .content-padding { padding: 16px !important; }
            .filter-ribbon { flex-direction: column !important; gap: 10px !important; padding: 12px !important; }
            .search-wrapper-lg { width: 100% !important; min-width: 0 !important; }
            .ribbon-input { min-height: 48px !important; font-size: 15px !important; padding: 12px !important; width: 100% !important; }
            .search-input { padding-left: 38px !important; }
            .search-icon { font-size: 14px !important; left: 14px !important; }
            .w-180 { width: 100% !important; }
            .stats-ribbon { grid-template-columns: repeat(2, 1fr) !important; gap: 8px !important; }
            .stat-item-premium { padding: 10px 12px !important; }
            .stat-info p { font-size: 9px !important; }
            .stat-info h4 { font-size: 16px !important; }
            .table-wrap { overflow-x: auto !important; }
            .gov-table-premium th, .gov-table-premium td { padding: 8px 10px !important; font-size: 11px !important; }
            .gov-table-premium th { font-size: 10px !important; letter-spacing: 0.3px !important; }
            .gov-table-premium tr:active { background: #f8fafc !important; }
            .name-bold { font-size: 12px !important; }
            .name-semi { font-size: 12px !important; }
            .time-sub { font-size: 10px !important; }
            .ip-addr { font-size: 10px !important; }
            .action-label { font-size: 11px !important; }
            .td-details { font-size: 11px !important; }
            .td-region { font-size: 10px !important; }
            .timestamp-box { font-size: 11px !important; }
            .module-badge { font-size: 9px !important; padding: 2px 6px !important; }
            .td-empty { padding: 40px 20px !important; }
            .empty-icon { font-size: 32px !important; }
            .pagination-flex { margin-top: 16px !important; }
            #globalAlertError, #globalAlertSuccess { display: none !important; }
        }
    </style>
@endsection

@section('content')
<div class="audit-logs-container">
    <div class="polish-card">
        <!-- HEADER STRIP -->
        <div class="card-header-accent">
            <div>
                <h3 class="h3-title">
                    <i class="fa-solid fa-shield-halved icon-blue"></i>
                    System Activity Logs
                </h3>
                <p class="p-subtitle">Monitoring real-time administrative actions and system events.</p>
            </div>
            <div class="header-action-group">
                <form id="archiveForm" action="{{ route('super_admin.audit_logs.archive') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-hover-archive btn-archive">
                        <i class="fa-solid fa-file-export"></i> ARCHIVE OLD LOGS
                    </button>
                </form>
                <div class="sync-badge">
                    <i class="fa-solid fa-circle-nodes"></i> Department Synchronization: ACTIVE
                </div>
            </div>
        </div>

        <div class="content-padding">
            <!-- STATS RIBBON -->
            <div class="stats-ribbon">
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>Total Logs</p>
                        <h4 id="statTotal">--</h4>
                    </div>
                </div>
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>Auth / Security</p>
                        <h4 id="statAuth">--</h4>
                    </div>
                </div>
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>Inventory</p>
                        <h4 id="statInventory">--</h4>
                    </div>
                </div>
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>Service Requests</p>
                        <h4 id="statRequests">--</h4>
                    </div>
                </div>
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>User Management</p>
                        <h4 id="statUsers">--</h4>
                    </div>
                </div>
            </div>

            <!-- FILTER RIBBON -->
            <div class="filter-ribbon">
                <div class="search-wrapper-lg">
                    <i class="fa-solid fa-search search-icon"></i>
                    <input type="text" id="logSearch" placeholder="Search by user, specific action, or log details..." class="ribbon-input search-input">
                </div>

                <select id="filterModule" class="ribbon-input w-180">
                    <option value="">All System Modules</option>
                    <option value="Auth">Security / Auth</option>
                    <option value="Inventory">Inventory Assets</option>
                    <option value="Requests">Service Requests</option>
                    <option value="User Management">Personnel Accounts</option>
                </select>
            </div>

            <div class="table-wrap">
                <table class="gov-table-premium">
                    <thead>
                        <tr>
                            <th style="width:170px;">Event Timestamp</th>
                            <th style="width:200px;">User Account</th>
                            <th style="width:140px;">Action Type</th>
                            <th style="width:120px;">System Module</th>
                            <th>Event Description / Details</th>
                            <th style="width:100px;">Location</th>
                        </tr>
                    </thead>
                    <tbody id="auditLogTable">
                        <tr><td colspan="6" style="text-align:center;padding:30px;color:#94a3b8;">Loading...</td></tr>
                    </tbody>
                </table>
                <div id="logsPagination" class="pagination-flex"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
const LOGS_DATA_URL = '{{ route("super_admin.audit_logs.data") }}';
let logsCurrentPage = 1;
let logsLastPage = 1;
let logsFilterTimer = null;

async function loadLogs(page) {
    page = page || 1;
    const params = new URLSearchParams();
    params.set('search', document.getElementById('logSearch').value);
    params.set('module', document.getElementById('filterModule').value);
    params.set('page', page);
    params.set('per_page', 50);

    const tbody = document.getElementById('auditLogTable');
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:#94a3b8;"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading...</td></tr>';

    try {
        const response = await fetch(LOGS_DATA_URL + '?' + params.toString(), {
            credentials: 'include',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();

        if (result.success) {
            logsCurrentPage = result.current_page;
            logsLastPage    = result.last_page;
            renderLogsTable(result.logs);
            renderLogsPagination(result.total);
            updateLogStats(result.stats, result.filtered_stats, result.total);
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:#ef4444;">Failed to load logs.</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:#ef4444;">Error loading logs.</td></tr>';
    }
}

function renderLogsTable(logs) {
    const tbody = document.getElementById('auditLogTable');
    if (!logs.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="td-empty"><i class="fa-solid fa-fingerprint empty-icon"></i><span class="fw-700">No logs found.</span><div style="margin-top:8px;font-size:12px;color:#94a3b8;">Try adjusting your search or module filter.</div></td></tr>';
        return;
    }

    tbody.innerHTML = logs.map(log => {
        const created = new Date(log.created_at);
        const dateStr = created.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        const timeStr = created.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const userName = log.user ? log.user.full_name : 'SYSTEM_DAEMON';

        let moduleClass = 'mb-default';
        if (log.module === 'Auth') moduleClass = 'mb-auth';
        else if (log.module === 'Inventory') moduleClass = 'mb-inventory';
        else if (log.module === 'Requests') moduleClass = 'mb-requests';
        else if (log.module === 'User Management') moduleClass = 'mb-users';

        return `<tr class="tr-hover-row">
            <td>
                <div class="timestamp-box">
                    <div class="name-semi">${dateStr}</div>
                    <div class="time-sub">${timeStr}</div>
                </div>
            </td>
            <td>
                <div class="name-bold">${userName}</div>
                <span class="ip-addr">ADDR: ${log.ip_address || 'N/A'}</span>
            </td>
            <td><span class="action-label">${log.action}</span></td>
            <td><span class="module-badge ${moduleClass}">${log.module}</span></td>
            <td class="td-details">${log.details}</td>
            <td class="td-region">${log.region || 'N/A'}</td>
        </tr>`;
    }).join('');
}

function renderLogsPagination(total) {
    const container = document.getElementById('logsPagination');
    if (!container) return;

    const totalPages = logsLastPage;
    if (totalPages <= 1) { container.innerHTML = ''; return; }

    const btnStyle = (active, disabled) =>
        `padding:5px 10px;border:1px solid ${active ? '#0038A8' : '#cbd5e1'};border-radius:4px;` +
        `background:${active ? '#0038A8' : disabled ? '#f1f5f9' : 'white'};` +
        `color:${active ? 'white' : disabled ? '#94a3b8' : '#1e293b'};` +
        `cursor:${disabled ? 'default' : 'pointer'};font-size:12px;font-weight:700;`;

    let html = `<div style="display:flex;align-items:center;justify-content:space-between;margin-top:16px;width:100%;">`;
    html += `<span style="font-size:12px;color:#64748b;">${total} log${total !== 1 ? 's' : ''} found</span>`;
    html += `<div style="display:flex;gap:4px;">`;

    html += `<button onclick="loadLogs(${logsCurrentPage - 1})" style="${btnStyle(false, logsCurrentPage <= 1)}" ${logsCurrentPage <= 1 ? 'disabled' : ''}>&lsaquo; Prev</button>`;

    let start = Math.max(1, logsCurrentPage - 2);
    let end   = Math.min(totalPages, logsCurrentPage + 2);
    if (start > 1) {
        html += `<button onclick="loadLogs(1)" style="${btnStyle(false, false)}">1</button>`;
        if (start > 2) html += `<span style="padding:5px 4px;color:#94a3b8;font-size:12px;">&hellip;</span>`;
    }
    for (let i = start; i <= end; i++) {
        html += `<button onclick="loadLogs(${i})" style="${btnStyle(i === logsCurrentPage, false)}">${i}</button>`;
    }
    if (end < totalPages) {
        if (end < totalPages - 1) html += `<span style="padding:5px 4px;color:#94a3b8;font-size:12px;">&hellip;</span>`;
        html += `<button onclick="loadLogs(${totalPages})" style="${btnStyle(false, false)}">${totalPages}</button>`;
    }

    html += `<button onclick="loadLogs(${logsCurrentPage + 1})" style="${btnStyle(false, logsCurrentPage >= totalPages)}" ${logsCurrentPage >= totalPages ? 'disabled' : ''}>Next &rsaquo;</button>`;
    html += `</div></div>`;
    container.innerHTML = html;
}

function updateLogStats(stats, filteredStats, total) {
    const isFiltered = document.getElementById('logSearch').value || document.getElementById('filterModule').value;
    const s = isFiltered ? filteredStats : stats;
    document.getElementById('statTotal').textContent     = s ? s.total : (total || 0);
    document.getElementById('statAuth').textContent      = s ? s.auth : '--';
    document.getElementById('statInventory').textContent = s ? s.inventory : '--';
    document.getElementById('statRequests').textContent  = s ? s.requests : '--';
    document.getElementById('statUsers').textContent     = s ? s.users : '--';
}

function onLogFilterChange() {
    clearTimeout(logsFilterTimer);
    logsFilterTimer = setTimeout(() => loadLogs(1), 300);
}

document.addEventListener('DOMContentLoaded', function() {
    loadLogs(1);

    document.getElementById('logSearch').addEventListener('keyup', onLogFilterChange);
    document.getElementById('filterModule').addEventListener('change', () => loadLogs(1));

    document.getElementById('archiveForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var form = this;
        Swal.fire({
            icon: 'warning',
            title: 'Archive Old Logs?',
            html: 'This will <strong>export and permanently delete</strong> all logs older than 1 year.<br><br>This action <strong>cannot be undone</strong>.',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="fa-solid fa-file-export"></i> Yes, Archive & Delete',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
</script>
@endsection