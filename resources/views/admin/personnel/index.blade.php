@extends('layouts.app')

@section('title', 'Manage Personnel | NCMB ICT')
@section('page-title', 'Personnel Management')

@section('styles')
    <style nonce="{{ $cspNonce }}">
        .personnel-container {
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

        .card-body-content {
            padding: 25px 30px;
        }

        .filter-ribbon {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }

        .ribbon-input {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
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
            padding: 15px;
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

        .sp-active { background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.15); }
        .sp-inactive { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.15); }

        .btn-view-modern {
            padding: 8px 16px;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-view-modern:hover {
            background: #0038A8;
            border-color: #0038A8;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 56, 168, 0.2);
        }
        
        .btn-view-modern:active {
            transform: scale(0.97);
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-card {
            background: white;
            width: 90%;
            max-width: 600px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: modalPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes modalPop {
            0% { transform: scale(0.95); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .modal-header {
            padding: 20px 25px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 25px;
        }

        .modal-footer {
            padding: 15px 25px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-label-gov {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .form-input-gov {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
            transition: all 0.2s;
        }

        .form-input-gov:focus {
            border-color: #0038A8;
            box-shadow: 0 0 0 3px rgba(0, 56, 168, 0.1);
        }

        /* --- Inline style replacement classes --- */
        .h3-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; }
        .p-subtitle { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .btn-primary-solid { background: #0038A8; color: white; border: none; padding: 10px 20px; border-radius: 4px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .search-wrapper { position: relative; flex: 1; min-width: 250px; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; }
        .search-input { width: 100%; padding-left: 35px; }
        .w-150 { width: 150px; }
        .d-none { display: none; }
        .w-180 { width: 180px; }
        .table-wrap { overflow-x: auto; }
        .th-center { text-align: center !important; }
        .name-cell-bold { font-weight: 700; color: #1e293b; }
        .email-cell-color { color: #64748b; }
        .td-muted-sm { color: #64748b; font-size: 12px; }
        .td-center { text-align: center !important; vertical-align: middle; }
        /* Fixed widths for Status and Action columns */
        .th-status { width: 100px; }
        .th-actions { width: 120px; }
        .pagination-wrap { margin-top: 20px; }
        .modal-title { margin: 0; font-size: 16px; font-weight: 800; color: #1e293b; }
        .close-icon-btn { background: none; border: none; cursor: pointer; color: #94a3b8; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-grid-full { grid-column: span 2; }
        .btn-cancel-lg { padding: 10px 20px; }
        .btn-save-solid { background: #0038A8; color: white; border: none; padding: 10px 24px; border-radius: 6px; font-weight: 700; cursor: pointer; }
        .modal-card-lg { max-width: 750px; }
        .loading-spinner { padding: 40px; text-align: center; }
        .spinner-icon { font-size: 30px; color: #0038A8; }
        .loading-text { margin-top: 10px; color: #64748b; }
        .det-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; background: #f8fafc; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .det-value { margin: 0; font-size: 15px; font-weight: 700; color: #1e293b; }
        .det-value-sm { margin: 0; font-size: 14px; color: #1e293b; }
        .det-hide { display: none; }
        .dept-select { padding: 2px 6px; font-size: 12px; height: auto; width: auto; border: 1px solid #cbd5e1; border-radius: 4px; }
        .status-toggle-group { display: flex; align-items: center; gap: 10px; }
        .toggle-btn-sm { padding: 2px 8px; font-size: 10px; }
        .section-title { margin: 0 0 12px; font-size: 13px; color: #1e293b; font-weight: 800; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px; text-transform: uppercase; }
        .section-icon { margin-right: 8px; color: #0038A8; }
        .assets-scroll { max-height: 150px; overflow-y: auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 15px; }
        .requests-scroll { max-height: 200px; overflow-y: auto; }
        .btn-close-lg { padding: 10px 20px; }
        .stat-card { background: #f8fafc; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #e2e8f0; }
        .stat-label { margin: 0; font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; }
        .stat-value { margin: 4px 0 0; font-size: 18px; font-weight: 800; color: #1e293b; }
        .stat-card-done { background: #ecfdf5; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #d1fae5; }
        .stat-label-green { margin: 0; font-size: 10px; font-weight: 800; color: #059669; text-transform: uppercase; }
        .stat-value-green { margin: 4px 0 0; font-size: 18px; font-weight: 800; color: #059669; }
        .stat-card-pending { background: #fffbeb; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #fef3c7; }
        .stat-label-yellow { margin: 0; font-size: 10px; font-weight: 800; color: #d97706; text-transform: uppercase; }
        .stat-value-yellow { margin: 4px 0 0; font-size: 18px; font-weight: 800; color: #d97706; }
        .stat-card-rejected { background: #fef2f2; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #fee2e2; }
        .stat-label-red { margin: 0; font-size: 10px; font-weight: 800; color: #dc2626; text-transform: uppercase; }
        .stat-value-red { margin: 4px 0 0; font-size: 18px; font-weight: 800; color: #dc2626; }
        .empty-text { text-align: center; color: #94a3b8; padding: 20px; font-size: 13px; background: #f8fafc; border-radius: 8px; }
        .empty-text-sm { text-align: center; color: #94a3b8; padding: 15px; font-size: 12px; }
        .filter-ribbon-wrap { display: flex; gap: 12px; flex-wrap: wrap; }
        .fs-11 { font-size: 11px; }
        .fs-12 { font-size: 12px; }
        .fs-13 { font-size: 13px; }
        .td-right { text-align: right; }
        .td-request-num { font-weight: 700; color: #0038A8; }
        .det-flex-group { display: flex; gap: 5px; }
        .form-group-lg { margin-bottom: 25px; }
        /* ── SweetAlert on TOP of all modals ── */
        .swal2-container { z-index: 100000 !important; }
        @media (max-width: 767px) {
            .det-grid { grid-template-columns: 1fr !important; }
            .stats-grid { grid-template-columns: repeat(2, 1fr) !important; }
            .form-grid { grid-template-columns: 1fr !important; }
            .form-grid-full { grid-column: span 1 !important; }
            .card-header-accent { flex-direction: column !important; align-items: flex-start !important; gap: 10px !important; }
            .card-header-accent button { width: 100% !important; justify-content: center !important; }
            .filter-ribbon { flex-direction: column !important; }
            .filter-ribbon .ribbon-input,
            .filter-ribbon .search-wrapper { width: 100% !important; min-width: 0 !important; }
            .ribbon-input, .form-input-gov, .search-input { 
                min-height: 48px !important; 
                font-size: 15px !important; 
                padding: 12px !important; 
            }
            .search-input { padding-left: 38px !important; }
            .search-icon { font-size: 14px !important; left: 14px !important; }
            .btn-primary-solid { 
                min-height: 48px !important; 
                justify-content: center !important;
                width: 100% !important;
            }
            .btn-view-modern { 
                min-height: 44px !important; 
                padding: 10px 16px !important;
                font-size: 13px !important;
                width: 100% !important;
                justify-content: center !important;
            }
            .btn-save-solid { 
                min-height: 48px !important; 
                width: 100% !important; 
                justify-content: center !important;
            }
            .modal-card { width: 95vw !important; max-width: 95vw !important; }
            .modal-footer { flex-direction: column !important; }
            .modal-footer button { width: 100% !important; }
            .modal-body { padding: 20px !important; }
            .gov-table-premium td, 
            .gov-table-premium th { padding: 8px 10px !important; font-size: 12px !important; }
            /* ── X button touch-friendly ── */
            .close-icon-btn { width: 44px !important; height: 44px !important; font-size: 22px !important; display: flex !important; align-items: center !important; justify-content: center !important; border-radius: 8px !important; }
            .close-icon-btn:hover { background: rgba(0,0,0,0.05) !important; }
            /* ── Swal/Toggle mobile fix ── */
            .swal2-popup { width: 85vw !important; max-width: 300px !important; margin: 0 auto !important; box-sizing: border-box !important; padding: 16px 20px !important; }
            .swal2-actions { flex-direction: column !important; width: 100% !important; gap: 6px !important; margin-top: 10px !important; }
            .swal2-actions button { width: 100% !important; margin: 0 !important; box-sizing: border-box !important; padding: 8px 12px !important; font-size: 13px !important; border-radius: 6px !important; }
            .swal2-container { padding: 10px !important; box-sizing: border-box !important; }
            .swal2-title { font-size: 15px !important; padding: 0 !important; }
            .swal2-html-container { font-size: 13px !important; margin: 8px 0 0 !important; }
            /* ── Modal overlay scroll fix ── */
            .modal-overlay { align-items: flex-start !important; padding-top: 20px !important; overflow-y: auto !important; }
            .modal-body { max-height: 70vh !important; overflow-y: auto !important; }
        }
    </style>
@endsection

@section('content')
<div class="personnel-container">
    
    <div class="polish-card">
        <!-- HEADER STRIP -->
        <div class="card-header-accent">
            <div>
                <h3 class="h3-title">{{ Auth::user()->department ? 'Department' : (Auth::user()->office ? 'Office' : 'Division') }} Personnel Registry</h3>
                <p class="p-subtitle">Manage user access, roles, and account status within your jurisdiction.</p>
            </div>
            @if(Auth::user()->role === 'super_admin' || Auth::user()->role === 'admin')
            <button id="addPersonnelBtn" class="btn-primary-solid">
                <i class="fa-solid fa-user-plus"></i> Add New Personnel
            </button>
            @endif
        </div>

        <div class="card-body-content">
            <!-- FILTERS -->
            <div class="filter-ribbon">
                <div class="search-wrapper">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="searchPersonnel" placeholder="Search by name, email, or position..." class="ribbon-input search-input">
                </div>

                @if(!Auth::user()->canProcessSupply())
                    @if(Auth::user()->department)
                    <input type="text" id="filterDivision" placeholder="Filter by Branch..." class="ribbon-input w-150">
                    @else
                    <input type="text" id="filterDivision" placeholder="Filter by Branch / Division..." class="ribbon-input w-150">
                    @endif
                @endif

                @if(Auth::user()->department)
                <select id="filterDepartment" class="ribbon-input d-none">
                    <option value="{{ Auth::user()->department }}">{{ Auth::user()->department }}</option>
                </select>
                @else
                <select id="filterDepartment" class="ribbon-input w-180">
                    <option value="">All Departments</option>
                    <option value="INTERNAL SERVICES DEPARTMENT">Internal Services Department</option>
                    <option value="TECHNICAL SERVICES DEPARTMENT">Technical Services Department</option>
                    <option value="COMMISSION ON AUDIT">Commission on Audit</option>
                </select>
                @endif

                <select id="filterStatus" class="ribbon-input w-150">
                    <option value="">All Status</option>
                    <option value="active">Active Accounts</option>
                    <option value="inactive">Inactive Accounts</option>
                </select>
            </div>

            <div class="table-wrap">
                <table class="gov-table-premium" id="personnelTable">
                    <thead>
                        <tr>
                            <th>Personnel Name</th>
                            <th>Email Address</th>
                            <th>Position</th>
                            <th>Division</th>
                            <th class="th-center th-status">Status</th>
                            <th class="th-center th-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($personnel as $person)
                        <tr class="person-row tr-hover-row" 
                            data-status="{{ $person->is_active ? 'active' : 'inactive' }}"
                            data-division="{{ strtoupper($person->office ?? '') }}"
                            data-department="{{ strtoupper($person->department ?? '') }}">
                            <td class="name-cell name-cell-bold">{{ $person->full_name }}</td>
                            <td class="email-cell email-cell-color">{{ $person->email }}</td>
                            <td class="td-muted-sm">{{ $person->position ?: 'Not Specified' }}</td>
                            <td class="td-muted-sm">{{ $person->office ?: 'N/A' }}</td>
                            <td class="td-center">
                                <span class="status-pill sp-{{ $person->is_active ? 'active' : 'inactive' }}">
                                    {{ $person->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="td-center">
                                <button data-action="view-personnel" data-id="{{ $person->id }}" class="btn-view-modern">
                                    <i class="fa-solid fa-id-card"></i> Manage
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="pagination-wrap">
                    {{ $personnel->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Personnel Modal -->
<div class="modal-overlay" id="addPersonnelModal">
    <div class="modal-card">
        <div class="modal-header">
            <h4 class="modal-title">Register New Personnel</h4>
        </div>
        <form id="addPersonnelForm">
            @csrf
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-grid-full">
                        <label class="form-label-gov">Full Legal Name</label>
                        <input type="text" name="full_name" required class="form-input-gov" placeholder="Juan Dela Cruz">
                    </div>
                    <div class="form-grid-full">
                        <label class="form-label-gov">Work Email Address</label>
                        <input type="email" name="email" required class="form-input-gov" placeholder="juan@ncmb.gov.ph">
                    </div>
                    <div>
                        <label class="form-label-gov">Position / Rank</label>
                        <input type="text" name="position" class="form-input-gov" placeholder="ICT Officer I">
                    </div>
                    @if(Auth::user()->office)
                    <div>
                        <label class="form-label-gov">Division / Office</label>
                        <input type="text" value="{{ Auth::user()->office }}" class="form-input-gov" disabled>
                        <input type="hidden" name="office" value="{{ Auth::user()->office }}">
                    </div>
                    @else
                    <div>
                        <label class="form-label-gov">Division / Office</label>
                        <select name="office" class="form-input-gov">
                            <option value="">None / Not Applicable</option>
                            <option value="RESEARCH AND INFORMATION DIVISION">RID</option>
                            <option value="ADMINISTRATIVE DIVISION">AD</option>
                            <option value="FINANCIAL AND MANAGEMENT DIVISION">FMD</option>
                            <option value="COMMISSION ON AUDIT">COA</option>
                            <option value="CONCILIATION AND MEDIATION DIVISION">CMD</option>
                            <option value="VOLUNTARY ARBITRATION DIVISION">VAD</option>
                            <option value="WORKPLACE RELATIONS ENHANCEMENT DIVISION">WRED</option>
                            <option value="OFFICE OF THE EXECUTIVE DIRECTOR">OED</option>
                        </select>
                    </div>
                    @endif
                    @if(Auth::user()->department)
                    <input type="hidden" name="department" value="{{ Auth::user()->department }}">
                    @else
                    <div>
                        <label class="form-label-gov">Department</label>
                        <select name="department" class="form-input-gov">
                            <option value="">None</option>
                            <option value="INTERNAL SERVICES DEPARTMENT">Internal Services</option>
                            <option value="TECHNICAL SERVICES DEPARTMENT">Technical Services</option>
                            <option value="COMMISSION ON AUDIT">COA</option>
                        </select>
                    </div>
                    @endif
                    <div>
                        <label class="form-label-gov">System Role</label>
                        <select name="role" required class="form-input-gov">
                            <option value="user">Regular User</option>
                            <option value="admin">Division Admin</option>
                            <option value="it">IT Personnel</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-gov">Temporary Password</label>
                        <input type="password" name="password" required class="form-input-gov" placeholder="Minimum 6 characters" minlength="6">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-view-modern close-add-personnel-btn btn-cancel-lg">Cancel</button>
                <button type="submit" class="btn-save-solid">Create Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Personnel Details Modal -->
<div class="modal-overlay" id="personnelModal">
    <div class="modal-card modal-card-lg">
        <div class="modal-header">
            <h4 class="modal-title">Personnel Profile & Activity</h4>
        </div>
        <div class="modal-body">
            <div id="modalLoading" class="loading-spinner">
                <i class="fa-solid fa-circle-notch fa-spin spinner-icon"></i>
                <p class="loading-text">Loading profile data...</p>
            </div>

            <div id="modalContent" class="d-none">
                <!-- Basic Info Grid -->
                <div class="det-grid">
                    <div>
                        <label class="form-label-gov">Full Name</label>
                        <p id="detName" class="det-value">-</p>
                    </div>
                    <div>
                        <label class="form-label-gov">Email Address</label>
                        <p id="detEmail" class="det-value-sm">-</p>
                    </div>
 
                    <div>
                        <label class="form-label-gov">Position</label>
                        <p id="detPosition" class="det-value-sm">-</p>
                    </div>
                    <div>
                        <label class="form-label-gov">Office</label>
                        <p id="detOffice" class="det-value-sm">-</p>
                    </div>
                    @if(Auth::user()->department)
                    <div class="det-hide">
                        <label class="form-label-gov">Department</label>
                        <select id="detDepartmentSelect" class="form-input-gov">
                            <option value="{{ Auth::user()->department }}">{{ Auth::user()->department }}</option>
                        </select>
                    </div>
                    @else
                    <div>
                        <label class="form-label-gov">Department</label>
                        <div class="det-flex-group">
                            <select id="detDepartmentSelect" class="form-input-gov dept-select" disabled>
                                <option value="">None</option>
                                <option value="INTERNAL SERVICES DEPARTMENT">INTERNAL SERVICES</option>
                                <option value="TECHNICAL SERVICES DEPARTMENT">TECHNICAL SERVICES</option>
                                <option value="COMMISSION ON AUDIT">COA</option>
                            </select>
                        </div>
                    </div>
                    @endif
                    <div>
                        <label class="form-label-gov">Account Status</label>
                        <div class="status-toggle-group">
                            <span id="detStatusBadge" class="status-pill">-</span>
                            <button id="btnToggleStatus" class="btn-view-modern toggle-btn-sm">Toggle</button>
                        </div>
                    </div>
                </div>

                <!-- Assigned Assets -->
                <div class="form-group-lg">
                    <h5 class="section-title">
                        <i class="fa-solid fa-laptop section-icon"></i> Currently Assigned Assets
                    </h5>
                    <div id="detAssets" class="assets-scroll">
                    </div>
                </div>

                <!-- Stats and History -->
                <div>
                    <h5 class="section-title">
                        <i class="fa-solid fa-clipboard-list section-icon"></i> ICT Request Overview
                    </h5>
                    <div id="detStats" class="stats-grid">
                    </div>
                    <div id="detRequests" class="requests-scroll">
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-view-modern close-view-modal-btn btn-close-lg">Close Window</button>
        </div>
    </div>
</div>

@endsection

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

    function renderAssets(assets) {
        const container = document.getElementById('detAssets');
        if (assets.length > 0) {
            let html = '<table class="gov-table-premium fs-12">';
            html += '<thead><tr><th>Asset Name</th><th>Serial No</th><th>Status</th></tr></thead><tbody>';
            assets.forEach(a => {
                html += `<tr><td>${a.item_name}</td><td>${a.serial_number || '-'}</td><td>${a.status}</td></tr>`;
            });
            container.innerHTML = html + '</tbody></table>';
        } else {
            container.innerHTML = '<p class="empty-text">No assets currently assigned to this personnel.</p>';
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
            let html = '<table class="gov-table-premium fs-11">';
            html += '<tbody>';
            requests.forEach(r => {
                html += `<tr><td class="td-request-num">${r.display_number || r.request_number}</td><td>${r.type}</td><td class="td-right">${r.status}</td></tr>`;
            });
            container.innerHTML = html + '</tbody></table>';
        } else {
            container.innerHTML = '<p class="empty-text-sm">No request history found.</p>';
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
