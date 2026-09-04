@extends('layouts.app')

@section('title', 'Master System Requests | Super Admin')
@section('page-title', 'Master List of Requests')

@section('styles')
    <style nonce="{{ $cspNonce }}">
        .master-container {
            width: 100%;
            margin-top: -10px;
            /* animation removed to prevent flash on page navigation */
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

        .card-body-content { padding: 25px 30px; }
        .h3-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; }
        .p-subtitle { margin: 2px 0 0; font-size: 12px; color: #64748b; }

        /* Summary Stats Ribbon - inside card body */
        .stats-ribbon {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .stat-item-premium {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-item-premium:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 20px -8px rgba(0, 56, 168, 0.12);
            border-color: rgba(0, 56, 168, 0.15);
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .stat-info p { margin: 0; font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px; }
        .stat-info h4 { margin: 2px 0 0; font-size: 18px; font-weight: 800; color: #1e293b; }

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
        }

        .gov-table-premium tr.tr-hover-row { transition: all 0.2s; position: relative; }
        .gov-table-premium tr.tr-hover-row:hover { background: #f8fafc !important; transform: scale(1.002); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .gov-table-premium tr.tr-hover-row:hover td:first-child { box-shadow: inset 4px 0 0 #0038A8; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }

        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .sp-pending { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; box-shadow: 0 2px 4px rgba(194, 65, 12, 0.15); }
        .sp-ongoing { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; box-shadow: 0 2px 4px rgba(29, 78, 216, 0.15); }
        .sp-completed { background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.15); }

        .type-pill {
            font-size: 10px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 20px;
            text-transform: uppercase;
        }

        .btn-action-modern {
            padding: 8px 14px;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-action-modern:hover {
            background: #0038A8;
            border-color: #0038A8;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 56, 168, 0.2);
        }
        
        .btn-action-modern:active {
            transform: scale(0.97);
        }
        .sa-title-icon { margin-right: 10px; color: #0038A8; }
        .sa-export-btn { padding: 10px 20px; }
        .sa-search-wrap { position: relative; flex: 1; min-width: 300px; }
        .sa-search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; }
        .sa-search-input { width: 100%; padding-left: 35px; }
        .sa-filter-select-wide { width: 180px; }
        .sa-filter-select-med { width: 220px; }
        .sa-filter-select-sm { width: 120px; }
        .sa-my-assigned-btn { padding: 8px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; background: white; color: #475569; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; white-space: nowrap; }
        .sa-table-wrap { overflow-x: auto; }
        /* Mobile swipe-table hint — hidden on desktop, shown ≤767px */
        .mobile-table-hint { display: none; }
        .gov-table-premium tbody { transition: opacity 0.15s ease; }
        .gov-table-premium tbody.fading { opacity: 1; }
        .sa-td-id { font-weight: 800; color: #0038A8; font-size: 13px; }
        .sa-td-desc { font-size: 11px; color: #64748b; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sa-td-office { font-weight: 700; color: #475569; }
        .sa-type-pill { font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; }
        .sa-type-ict { background: #eff6ff; color: #3b82f6; border: 1px solid #dbeafe; }
        .sa-type-pm { background: #f5f3ff; color: #8b5cf6; border: 1px solid #ede9fe; }
        .sa-td-requestor { color: #1e293b; font-weight: 600; }
        .sa-td-requestor-sub { font-size: 11px; color: #64748b; font-weight: normal; margin-top: 2px; }
        .sa-td-assigned { color: #1e293b; font-size: 12px; }
        .sa-assigned-highlight { font-weight: 800; color: #0038A8; }
        .sa-assigned-normal { font-weight: 600; color: #475569; }
        .sa-assigned-none { color: #94a3b8; font-style: italic; }
        .sa-td-date { color: #64748b; font-size: 12px; }
        .sa-td-center { text-align: center !important; vertical-align: middle; }
        .sa-review-icon-green { color: #047857; font-size: 14px; }
        .sa-review-icon-red { color: #b91c1c; font-size: 14px; }
        .sa-review-icon-gray { color: #cbd5e1; font-size: 14px; }
        .sa-btn-row-inline { display: inline-flex; align-items: center; gap: 5px; }
        .sa-empty { padding: 60px; text-align: center; color: #94a3b8; }
        .sa-empty-icon { font-size: 40px; margin-bottom: 15px; display: block; opacity: 0.5; }
        .sa-empty-text { font-weight: 700; }
        .sa-pagination { margin-top: 20px; }
        .sa-row-assigned { background: #eff6ff; }
        .sa-stat-icon-bg-orange { background: #fff7ed; color: #c2410c; }
        .sa-stat-icon-bg-blue { background: #eff6ff; color: #1d4ed8; }
        .sa-stat-icon-bg-green { background: #ecfdf5; color: #059669; }
        .sa-stat-icon-bg-gray { background: #f8fafc; color: #1e293b; }
        .sa-stat-card-accent { border-right: 4px solid #0038A8; }
        @media screen and (max-width: 767px) {
            .stats-ribbon { grid-template-columns: repeat(2, 1fr) !important; gap: 10px !important; }
            .stats-ribbon .stat-item-premium:first-child { grid-column: 1 / -1 !important; }
            .stat-item-premium { padding: 10px 12px !important; }
            .stat-icon { width: 28px !important; height: 28px !important; font-size: 13px !important; }
            .stat-info p { font-size: 9px !important; }
            .stat-info h4 { font-size: 16px !important; }
            .card-header-accent { flex-direction: column !important; gap: 10px !important; }
            .card-body-content { padding: 16px !important; }
            .filter-ribbon { flex-direction: column !important; gap: 10px !important; }
            .sa-search-wrap { width: 100% !important; min-width: 0 !important; }
            .ribbon-input { min-height: 48px !important; font-size: 15px !important; padding: 12px !important; width: 100% !important; }
            .sa-search-input { padding-left: 38px !important; }
            .sa-search-icon { font-size: 14px !important; left: 14px !important; }
            .sa-filter-select-wide, .sa-filter-select-med, .sa-filter-select-sm { width: 100% !important; }
            .sa-my-assigned-btn { width: 100% !important; justify-content: center !important; min-height: 44px !important; }
            .sa-table-wrap { overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; width: 100% !important; max-width: 100% !important; overscroll-behavior-x: contain !important; scroll-snap-type: none !important; }
            .card-body-content { overflow-x: clip !important; }
            .gov-table-premium th, .gov-table-premium td { padding: 8px 10px !important; font-size: 11px !important; }
            .gov-table-premium th { font-size: 10px !important; letter-spacing: 0.3px !important; }
            .btn-action-modern { min-height: 44px !important; min-width: 44px !important; }
            /* Mobile swipe hint — shown only on mobile */
            .mobile-table-hint {
                display: flex !important;
                align-items: center;
                justify-content: center;
                gap: 7px;
                padding: 9px 12px;
                background: #eff6ff;
                color: #1e40af;
                font-size: 11.5px;
                font-weight: 700;
                letter-spacing: 0.03em;
                border: 1px solid #dbeafe;
                border-bottom: none;
                border-radius: 10px 10px 0 0;
            }
            /* Table keeps natural column widths — scroll instead of squishing */
            .sa-table-wrap .gov-table-premium {
                min-width: 1080px !important;
                width: 1080px !important;
                table-layout: auto !important;
            }
            .sa-table-wrap .gov-table-premium th,
            .sa-table-wrap .gov-table-premium td { white-space: nowrap !important; }
            .sa-empty { padding: 30px !important; }
            .sa-pagination { margin-top: 12px !important; }
        }
    </style>
@endsection

@section('content')
<div class="master-container">

    <div class="polish-card">
        {{-- HEADER STRIP --}}
        <div class="card-header-accent">
            <div>
                <h3 class="h3-title">
                    <i class="fa-solid fa-clipboard-list sa-title-icon"></i>
                    Master List of Requests
                </h3>
                <p class="p-subtitle">Centralized monitoring of all office maintenance and ICT support tickets.</p>
            </div>
            <button id="exportBtn" class="btn-action-modern sa-export-btn">
                <i class="fa-solid fa-file-export"></i> Export Report
            </button>
        </div>

        <div class="card-body-content">
            {{-- STATS RIBBON - inside the card --}}
            <div class="stats-ribbon">
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>Total Pending</p>
                        <h4 id="statPending">--</h4>
                    </div>
                </div>
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>Ongoing Repairs</p>
                        <h4 id="statOngoing">--</h4>
                    </div>
                </div>
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>Completed</p>
                        <h4 id="statCompleted">--</h4>
                    </div>
                </div>
            </div>

            {{-- FILTERS --}}
            <div class="filter-ribbon">
                <div class="sa-search-wrap">
                    <i class="fa-solid fa-magnifying-glass sa-search-icon"></i>
                    <input type="text" id="masterSearch" placeholder="Search by request #, requestor, or issue..." class="ribbon-input sa-search-input">
                </div>

                <select id="filterDepartment" class="ribbon-input sa-filter-select-wide">
                    <option value="">All Departments</option>
                    <option value="INTERNAL SERVICES DEPARTMENT">Internal Services Department</option>
                    <option value="TECHNICAL SERVICES DEPARTMENT">Technical Services Department</option>
                </select>

                <select id="filterDivision" class="ribbon-input sa-filter-select-med">
                    <option value="">All Divisions</option>
                    <option value="RESEARCH AND INFORMATION DIVISION" data-dept-group="INTERNAL">Research & Information Division</option>
                    <option value="ADMINISTRATIVE DIVISION" data-dept-group="INTERNAL">Administrative Division</option>
                    <option value="FINANCIAL AND MANAGEMENT DIVISION" data-dept-group="INTERNAL">Financial & Management Division</option>
                    <option value="COMMISSION ON AUDIT" data-dept-group="INTERNAL">Commission on Audit</option>
                    <option value="CONCILIATION AND MEDIATION DIVISION" data-dept-group="TECHNICAL">Conciliation & Mediation Division</option>
                    <option value="VOLUNTARY ARBITRATION DIVISION" data-dept-group="TECHNICAL">Voluntary Arbitration Division</option>
                    <option value="WORKPLACE RELATIONS ENHANCEMENT DIVISION" data-dept-group="TECHNICAL">Workplace Relations Enhancement Division</option>
                    <option value="OFFICE OF THE EXECUTIVE DIRECTOR" data-dept-group="TECHNICAL">Office of the Executive Director</option>
                </select>


                <select id="filterStatus" class="ribbon-input sa-filter-select-sm">
                    <option value="">All Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Ongoing">Ongoing</option>
                    <option value="Completed">Completed</option>
                </select>

                <button id="myAssignedToggle" class="sa-my-assigned-btn">
                    <i class="fa-solid fa-user-check"></i> My Assigned
                </button>
            </div>

            {{-- TABLE --}}
            <div class="mobile-table-hint"><i class="fa-solid fa-arrow-right-arrow-left"></i> Swipe table horizontally to view all columns</div>
            <div class="sa-table-wrap">
                <table class="gov-table-premium">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Office/Division</th>
                            <th>Requestor</th>
                            <th>Assigned IT</th>
                            <th>Date Requested</th>
                            <th>Completed At</th>
                            <th class="sa-td-center">Status</th>
                            <th class="sa-td-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="masterRequestTable">
                        <tr><td colspan="8" style="text-align:center;padding:30px;color:#94a3b8;">Loading...</td></tr>
                    </tbody>
                </table>
                <div id="requestsPagination" class="sa-pagination"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
const REQUESTS_DATA_URL = '{{ route("super_admin.requests.data") }}';
let requestsCurrentPage = 1;
let requestsLastPage = 1;
let requestsFilterTimer = null;
let requestsAbortController = null;
let requestsIsFirstLoad = true;
let myAssignedOnly = false;

function toggleMyAssigned() {
    myAssignedOnly = !myAssignedOnly;
    const btn = document.getElementById('myAssignedToggle');
    if (myAssignedOnly) {
        btn.style.background = '#0038A8';
        btn.style.color = 'white';
        btn.style.borderColor = '#0038A8';
    } else {
        btn.style.background = 'white';
        btn.style.color = '#475569';
        btn.style.borderColor = '#cbd5e1';
    }
    loadRequests(1);
}

async function loadRequests(page) {
    page = page || 1;
    const params = new URLSearchParams();
    params.set('search', document.getElementById('masterSearch').value);
    params.set('department', document.getElementById('filterDepartment').value);
    params.set('division', document.getElementById('filterDivision').value);
    params.set('status', document.getElementById('filterStatus').value);
    params.set('my_assigned', myAssignedOnly ? '1' : '0');
    params.set('page', page);
    params.set('per_page', 20);

    // Cancel any in-flight request
    if (requestsAbortController) requestsAbortController.abort();
    requestsAbortController = new AbortController();

    const tbody = document.getElementById('masterRequestTable');
    if (requestsIsFirstLoad) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;color:#94a3b8;"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading...</td></tr>';
    } else {
        tbody.classList.add('fading');
    }

    try {
        const response = await fetch(REQUESTS_DATA_URL + '?' + params.toString(), {
            credentials: 'include',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: requestsAbortController.signal
        });
        const result = await response.json();

        if (result.success) {
            requestsCurrentPage = result.current_page;
            requestsLastPage    = result.last_page;
            requestsIsFirstLoad = false;
            renderRequestsTable(result.requests);
            renderRequestsPagination(result.total);
            updateRequestStats(result.stats, result.filtered_stats);
        } else {
            tbody.classList.remove('fading');
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;color:#ef4444;">Failed to load requests.</td></tr>';
        }
    } catch (e) {
        if (e.name === 'AbortError') return;
        tbody.classList.remove('fading');
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;color:#ef4444;">Error loading requests.</td></tr>';
    }
}

function renderRequestsTable(requests) {
    const tbody = document.getElementById('masterRequestTable');
    if (!requests.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="sa-empty"><i class="fa-solid fa-inbox sa-empty-icon"></i><span class="sa-empty-text">No requests found.</span></td></tr>';
        tbody.classList.remove('fading');
        return;
    }

    tbody.innerHTML = requests.map(req => {
        const created = new Date(req.created_at);
        const dateStr = created.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        const timeStr = created.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

        const completed = req.completed_at ? new Date(req.completed_at) : null;
        const completedDateStr = completed ? completed.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
        const completedTimeStr = completed ? completed.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '';
        const completedStr = completed ? `${completedDateStr} | ${completedTimeStr}` : '—';

        let statusClass = '';
        if (req.status === 'Pending') statusClass = 'sp-pending';
        else if (req.status === 'Ongoing') statusClass = 'sp-ongoing';
        else if (req.status === 'Completed') statusClass = 'sp-completed';

        const assignedName = req.assigned_to ? req.assigned_to.full_name : null;
        const isAssignedToMe = req.assigned_to && req.assigned_to.id === window.CMMS_USER_ID;
        const rowClass = isAssignedToMe ? 'tr-hover-row sa-row-assigned' : 'tr-hover-row';

        return `<tr class="${rowClass}">
            <td>
                <div class="sa-td-id">${req.display_number || req.request_number}</div>
                <div class="sa-td-desc">${req.description || ''}</div>
            </td>
            <td class="sa-td-office">${req.office || 'N/A'}</td>
            <td class="sa-td-requestor">
                ${req.requestor_name}
                <div class="sa-td-requestor-sub">${req.office || 'N/A'}</div>
            </td>
            <td class="sa-td-assigned">
                ${assignedName
                    ? `<span class="${isAssignedToMe ? 'sa-assigned-highlight' : 'sa-assigned-normal'}">${isAssignedToMe ? '★ ' : ''}${assignedName}</span>`
                    : '<span class="sa-assigned-none">Unassigned</span>'}
            </td>
            <td class="sa-td-date">${dateStr} | ${timeStr}</td>
            <td class="sa-td-date">${completedStr}</td>
            <td class="sa-td-center"><span class="status-pill ${statusClass}">${req.status}</span></td>
            <td class="sa-td-center">
                <a href="/requests/ict/${req.id}" class="btn-action-modern">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Details
                </a>
            </td>
        </tr>`;
    }).join('');
    tbody.classList.remove('fading');
}

function renderRequestsPagination(total) {
    const container = document.getElementById('requestsPagination');
    if (!container) return;

    const totalPages = requestsLastPage;
    if (totalPages <= 1) { container.innerHTML = ''; return; }

    const btnStyle = (active, disabled) =>
        `padding:5px 10px;border:1px solid ${active ? '#0038A8' : '#cbd5e1'};border-radius:4px;` +
        `background:${active ? '#0038A8' : disabled ? '#f1f5f9' : 'white'};` +
        `color:${active ? 'white' : disabled ? '#94a3b8' : '#1e293b'};` +
        `cursor:${disabled ? 'default' : 'pointer'};font-size:12px;font-weight:700;`;

    let html = `<div style="display:flex;align-items:center;justify-content:space-between;margin-top:16px;">`;
    html += `<span style="font-size:12px;color:#64748b;">${total} request${total !== 1 ? 's' : ''} found</span>`;
    html += `<div style="display:flex;gap:4px;">`;

    html += `<button onclick="loadRequests(${requestsCurrentPage - 1})" style="${btnStyle(false, requestsCurrentPage <= 1)}" ${requestsCurrentPage <= 1 ? 'disabled' : ''}>&lsaquo; Prev</button>`;

    let start = Math.max(1, requestsCurrentPage - 2);
    let end   = Math.min(totalPages, requestsCurrentPage + 2);
    if (start > 1) {
        html += `<button onclick="loadRequests(1)" style="${btnStyle(false, false)}">1</button>`;
        if (start > 2) html += `<span style="padding:5px 4px;color:#94a3b8;font-size:12px;">&hellip;</span>`;
    }
    for (let i = start; i <= end; i++) {
        html += `<button onclick="loadRequests(${i})" style="${btnStyle(i === requestsCurrentPage, false)}">${i}</button>`;
    }
    if (end < totalPages) {
        if (end < totalPages - 1) html += `<span style="padding:5px 4px;color:#94a3b8;font-size:12px;">&hellip;</span>`;
        html += `<button onclick="loadRequests(${totalPages})" style="${btnStyle(false, false)}">${totalPages}</button>`;
    }

    html += `<button onclick="loadRequests(${requestsCurrentPage + 1})" style="${btnStyle(false, requestsCurrentPage >= totalPages)}" ${requestsCurrentPage >= totalPages ? 'disabled' : ''}>Next &rsaquo;</button>`;
    html += `</div></div>`;
    container.innerHTML = html;
}

function updateRequestStats(stats, filteredStats) {
    const isFiltered = document.getElementById('masterSearch').value ||
        document.getElementById('filterDepartment').value ||
        document.getElementById('filterDivision').value ||
        document.getElementById('filterStatus').value ||
        myAssignedOnly;
    const s = isFiltered ? filteredStats : stats;
    document.getElementById('statPending').textContent   = s ? s.pending : '--';
    document.getElementById('statOngoing').textContent   = s ? s.ongoing : '--';
    document.getElementById('statCompleted').textContent = s ? s.completed : '--';
}

function onFilterChange() {
    clearTimeout(requestsFilterTimer);
    requestsFilterTimer = setTimeout(() => loadRequests(1), 300);
}

function exportData() {
    Swal.fire({
        icon: 'info',
        title: 'Coming Soon',
        text: 'Preparing report... (Experimental Feature)',
        confirmButtonColor: '#0038A8'
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Store current user ID for my assigned check
    window.CMMS_USER_ID = {{ Auth::id() }};

    loadRequests(1);

    document.getElementById('masterSearch').addEventListener('keyup', onFilterChange);
    document.getElementById('filterDepartment').addEventListener('change', function() {
        // Cascade division dropdown based on selected department
        const dept = this.value;
        const divSelect = document.getElementById('filterDivision');
        divSelect.querySelectorAll('option').forEach(opt => {
            if (!opt.value) return;
            const d = opt.getAttribute('data-dept-group');
            opt.style.display = (!dept || !d || (dept === 'INTERNAL SERVICES DEPARTMENT' && d === 'INTERNAL') || (dept === 'TECHNICAL SERVICES DEPARTMENT' && d === 'TECHNICAL')) ? '' : 'none';
        });
        divSelect.value = '';
        loadRequests(1);
    });
    document.getElementById('filterDivision').addEventListener('change', () => loadRequests(1));
    document.getElementById('filterStatus').addEventListener('change', () => loadRequests(1));
    document.getElementById('exportBtn').addEventListener('click', exportData);
    document.getElementById('myAssignedToggle').addEventListener('click', toggleMyAssigned);
});
</script>
@endsection
