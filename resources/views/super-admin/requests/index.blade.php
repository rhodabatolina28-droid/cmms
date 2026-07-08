@extends('layouts.app')

@section('title', 'Master System Requests | Super Admin')
@section('page-title', 'Master List of Requests')

@section('styles')
    <style nonce="{{ $cspNonce }}">
        .master-container {
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

        .card-body-content { padding: 25px 30px; }
        .h3-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; }
        .p-subtitle { margin: 2px 0 0; font-size: 12px; color: #64748b; }

        /* Summary Stats Ribbon — inside card body */
        .stats-ribbon {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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
        @media (max-width: 767px) {
            .stats-ribbon { grid-template-columns: repeat(2, 1fr) !important; gap: 10px !important; }
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
            .gov-table-premium th, .gov-table-premium td { padding: 8px 10px !important; font-size: 11px !important; }
            .btn-action-modern { min-height: 44px !important; min-width: 44px !important; }
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
            {{-- STATS RIBBON — inside the card --}}
            <div class="stats-ribbon">
                <div class="stat-item-premium" data-stat-filter="Pending">
                    <div class="stat-info">
                        <p>Total Pending</p>
                        <h4 id="statPending">{{ $requests->where('status', 'Pending')->count() }}</h4>
                    </div>
                </div>
                <div class="stat-item-premium" data-stat-filter="Ongoing">
                    <div class="stat-info">
                        <p>Ongoing Repairs</p>
                        <h4 id="statOngoing">{{ $requests->where('status', 'Ongoing')->count() }}</h4>
                    </div>
                </div>
                <div class="stat-item-premium" data-stat-filter="Completed">
                    <div class="stat-info">
                        <p>Completed</p>
                        <h4 id="statCompleted">{{ $requests->where('status', 'Completed')->count() }}</h4>
                    </div>
                </div>
                <div class="stat-item-premium sa-stat-card-accent" data-stat-filter="">
                    <div class="stat-info">
                        <p>Total Filed</p>
                        <h4 id="statTotal">{{ $requests->count() }}</h4>
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
                    <option value="RESEARCH AND INFORMATION DIVISION">Research & Information Division</option>
                    <option value="ADMINISTRATIVE DIVISION">Administrative Division</option>
                    <option value="FINANCIAL AND MANAGEMENT DIVISION">Financial & Management Division</option>
                    <option value="COMMISSION ON AUDIT">Commission on Audit</option>
                    <option value="CONCILIATION AND MEDIATION DIVISION">Conciliation & Mediation Division</option>
                    <option value="VOLUNTARY ARBITRATION DIVISION">Voluntary Arbitration Division</option>
                    <option value="WORKPLACE RELATIONS ENHANCEMENT DIVISION">Workplace Relations Enhancement Division</option>
                    <option value="OFFICE OF THE EXECUTIVE DIRECTOR">Office of the Executive Director</option>
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
            <div class="sa-table-wrap">
                <table class="gov-table-premium">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Office/Division</th>
                            <th class="sa-td-center">Type</th>
                            <th>Requestor</th>
                            <th>Assigned IT</th>
                            <th>Date Filed</th>
                            <th class="sa-td-center" title="Division Admin Review Status"><i class="fa-solid fa-clipboard-check"></i> Div Review</th>
                            <th class="sa-td-center">Status</th>
                            <th class="sa-td-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="masterRequestTable">
                        @forelse($requests as $req)
                        @php $isAssignedToMe = (int)($req->assigned_to ?? 0) === Auth::id(); @endphp
                        <tr class="tr-hover-row {{ $isAssignedToMe ? 'sa-row-assigned' : '' }}" data-region="{{ strtoupper($req->region ?? '') }}" 
                            data-division="{{ strtoupper($req->office ?? '') }}"
                            data-department="{{ strtoupper($req->user->department ?? '') }}"
                            data-type="{{ strtoupper($req->type ?? '') }}" 
                            data-status="{{ strtoupper($req->status ?? '') }}"
                            data-assigned-me="{{ $isAssignedToMe ? '1' : '0' }}">
                            <td>
                                <div class="sa-td-id">
                                    {{ $req->display_number ?? $req->request_number }}
                                </div>
                                <div class="sa-td-desc">{{ $req->description }}</div>
                            </td>
                            <td class="sa-td-office">{{ $req->office ?: 'N/A' }}</td>
                            <td class="sa-td-center">
                                <span class="type-pill sa-type-pill sa-type-{{ strtolower($req->type === 'ICT' ? 'ict' : 'pm') }}">
                                    {{ $req->type }}
                                </span>
                            </td>
                            <td class="sa-td-requestor">
                                {{ $req->requestor_name }}
                                <div class="sa-td-requestor-sub">
                                    {{ $req->office ?: 'N/A' }} 
                                    @if($req->user && $req->user->department)
                                        | {{ $req->user->department }}
                                    @endif
                                </div>
                            </td>
                            <td class="sa-td-assigned">
                                @if($req->assignedTo)
                                    <span class="{{ $isAssignedToMe ? 'sa-assigned-highlight' : 'sa-assigned-normal' }}">
                                        {{ $isAssignedToMe ? '★ ' : '' }}{{ $req->assignedTo->full_name }}
                                    </span>
                                @else
                                    <span class="sa-assigned-none">Unassigned</span>
                                @endif
                            </td>
                            <td class="sa-td-date">{{ $req->created_at->format('M d, Y | h:i A') }}</td>
                            <td class="sa-td-center">
                                @if($req->division_admin_review_status === 'Approved')
                                    <span class="sa-review-icon-green" title="Approved by Division Admin"><i class="fa-solid fa-circle-check"></i></span>
                                @elseif($req->division_admin_review_status === 'Rejected')
                                    <span class="sa-review-icon-red" title="Rejected by Division Admin"><i class="fa-solid fa-circle-xmark"></i></span>
                                @else
                                    <span class="sa-review-icon-gray" title="Pending Division Review"><i class="fa-regular fa-clock"></i></span>
                                @endif
                            </td>
                            <td class="sa-td-center">
                                <span class="status-pill @if($req->status === 'Pending') sp-pending @elseif($req->status === 'Ongoing') sp-ongoing @elseif($req->status === 'Completed') sp-completed @endif">
                                    {{ $req->status }}
                                </span>
                            </td>
                            <td class="sa-td-center">
                                <a href="{{ route($req->type === 'ICT' ? 'ict.show' : 'maintenance.show', $req->id) }}" class="btn-action-modern sa-btn-row-inline">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Details
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="sa-empty">
                                <i class="fa-solid fa-inbox sa-empty-icon"></i>
                                <span class="sa-empty-text">No office requests recorded yet.</span>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="sa-pagination">
                    {{ $requests->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
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
    filterRequests();
}

function filterRequests() {
    const search = document.getElementById('masterSearch').value.toLowerCase();
    const department = document.getElementById('filterDepartment').value.toUpperCase();
    const division = document.getElementById('filterDivision').value.toUpperCase();
    const status = document.getElementById('filterStatus').value.toUpperCase();
    
    const rows = document.querySelectorAll('#masterRequestTable tr');

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

    rows.forEach(row => {
        if (row.dataset.region === undefined) return;

        const text = row.textContent.toLowerCase();
        const rowDivision = row.dataset.division || '';
        const rowDepartment = row.dataset.department || '';
        const rowStatus = row.dataset.status;

        const matchesSearch = text.includes(search);

        const normDivision = normalizeOfficeDept(rowDivision);
        const normDepartment = normalizeOfficeDept(rowDepartment);

        const internalOffices = ['ADMINISTRATIVEDIVISION', 'INTERNALSERVICESDEPARTMENT', 'COMMISSIONONAUDIT', 'FINANCIALANDMANAGEMENTDIVISION', 'RESEARCHANDINFORMATIONDIVISION'];
        const technicalOffices = ['CONCILIATIONANDMEDIATIONDIVISION', 'TECHNICALSERVICESDEPARTMENT', 'OFFICEOFTHEEXECUTIVEDIRECTOR', 'VOLUNTARYARBITRATIONDIVISION', 'WORKPLACERELATIONSENHANCEMENTDIVISION'];

        let matchesDept = department === "";
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

        let matchesDiv = division === "";
        if (!matchesDiv) {
            const cleanSearch = division.replace(/[^A-Z0-9]/g, '').trim();
            matchesDiv = (normDivision === cleanSearch) || normDivision.includes(cleanSearch);
        }

        const matchesStatus = status === "" || rowStatus === status;
        const matchesMyAssigned = !myAssignedOnly || row.dataset.assignedMe === '1';

        row.style.display = (matchesSearch && matchesDiv && matchesDept && matchesStatus && matchesMyAssigned) ? "" : "none";
    });
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
    document.getElementById('masterSearch').addEventListener('keyup', filterRequests);
    document.getElementById('filterDepartment').addEventListener('change', function() {
        filterRequests();
    });
    document.getElementById('filterDivision').addEventListener('change', filterRequests);
    document.getElementById('filterStatus').addEventListener('change', filterRequests);
    document.getElementById('exportBtn').addEventListener('click', exportData);
    document.getElementById('myAssignedToggle').addEventListener('click', toggleMyAssigned);
    
    // Clickable stats cards - filter by status when clicked
    document.querySelectorAll('.stat-item-premium[data-stat-filter]').forEach(function(card) {
        card.style.cursor = 'pointer';
        card.addEventListener('click', function() {
            const status = this.dataset.statFilter;
            document.getElementById('filterStatus').value = status;
            filterRequests();
        });
    });
});
</script>
@endsection