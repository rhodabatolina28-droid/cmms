@extends('layouts.app')

@section('title', 'User Management | Super Admin')
@section('page-title', 'User Management')

@section('styles')
    <style nonce="{{ $cspNonce }}">
        .user-mgmt-container {
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

        /* Summary Stats Ribbon — inside card body */
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

        .sa-stat-icon-bg-orange { background: #fff7ed; color: #c2410c; }
        .sa-stat-icon-bg-blue { background: #eff6ff; color: #1d4ed8; }
        .sa-stat-icon-bg-green { background: #ecfdf5; color: #059669; }
        .sa-stat-icon-bg-gray { background: #f8fafc; color: #1e293b; }
        .sa-stat-card-accent { border-right: 4px solid #0038A8; }

        /* Status & Role Badges */
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

        .role-pill {
            font-size: 10px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 20px;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            text-transform: uppercase;
        }

        .btn-action-modern {
            padding: 6px 10px;
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
            cursor: pointer;
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

        /* Modal Overlays */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-card {
            background: white;
            border-radius: 15px;
            width: 100%;
            max-width: 560px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            overflow: hidden;
            animation: modalPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .modal-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .modal-body { padding: 25px; }

        .form-label-gov {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .form-input-gov {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
            outline: none;
        }

        .form-input-gov:focus {
            border-color: #0038A8;
            box-shadow: 0 0 0 3px rgba(0, 56, 168, 0.05);
        }

        .btn-gov-primary {
            background: #0038A8;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-gov-primary:hover {
            background: #002d87;
            transform: translateY(-1px);
        }

        /* --- Inline style replacement classes --- */
        .h3-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; }
        .icon-blue { margin-right: 10px; color: #0038A8; }
        .p-subtitle { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .content-padding { padding: 25px 30px; }
        .search-wrapper { position: relative; flex: 1; min-width: 250px; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; }
        .search-input { width: 100%; padding-left: 35px; }
        .w-180 { width: 180px; }
        .w-220 { width: 220px; }
        .w-120 { width: 120px; }
        .table-wrap { overflow-x: auto; }
        .gov-table-premium tbody { transition: none; }
        .gov-table-premium tbody.fading { opacity: 1; }
        .th-center { text-align: center !important; }
        .th-right { text-align: center !important; }
        .name-bold { font-weight: 800; color: #1e293b; }
        .email-mono { font-size: 11px; color: #64748b; font-family: monospace; }
        .td-bold { font-weight: 700; color: #475569; }
        .dept-sub { font-size: 11px; color: #64748b; font-weight: normal; margin-top: 2px; }
        .td-center { text-align: center !important; vertical-align: middle; }
        .td-right { text-align: center !important; vertical-align: middle; }
        .action-group { display: flex; gap: 6px; justify-content: center; align-items: center; }
        .btn-disabled { color: #94a3b8; border-color: #e2e8f0; cursor: not-allowed; pointer-events: none; }
        .pagination-wrap { margin-top: 20px; }
        .modal-title { margin: 0; font-size: 16px; font-weight: 800; color: #1e293b; }
        .close-btn { background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer; padding: 4px 8px; border-radius: 6px; transition: all 0.2s; }
        .close-btn:hover { background: #f1f5f9; color: #475569; }
        .form-group { margin-bottom: 14px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
        .form-group-sm { margin-bottom: 4px; }
        .form-help { margin: 4px 0 0; font-size: 11px; color: #64748b; line-height: 1.4; }
        .modal-body { padding: 20px 24px; overflow-y: auto; flex: 1; }
        .modal-foot { padding: 14px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px; flex-shrink: 0; }
        .btn-cancel { padding: 10px 20px; }
        .btn-submit { padding: 10px 25px; }
        .section-divider { font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #f1f5f9; }
        /* MOBILE RESPONSIVE */
        @media (max-width: 767px) {
            .card-header-accent { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 16px 20px !important; }
            .card-header-accent .btn-gov-primary { width: 100% !important; justify-content: center !important; }
            .h3-title { font-size: 16px !important; }
            .content-padding { padding: 16px !important; }
            .filter-ribbon { flex-direction: column !important; gap: 10px !important; padding: 12px !important; }
            .search-wrapper { width: 100% !important; min-width: 0 !important; }
            .ribbon-input, .form-input-gov { 
                min-height: 48px !important; 
                font-size: 15px !important; 
                padding: 12px !important; 
                width: 100% !important;
            }
            .search-input { padding-left: 38px !important; }
            .search-icon { font-size: 14px !important; left: 14px !important; }
            .w-180, .w-220, .w-120 { width: 100% !important; }
            .table-wrap { overflow-x: auto !important; }
            .gov-table-premium th, .gov-table-premium td { padding: 10px 8px !important; font-size: 12px !important; }
            .gov-table-premium th { font-size: 10px !important; letter-spacing: 0.3px !important; }
            .gov-table-premium tr:active { background: #f1f5f9 !important; }
            .name-bold { font-size: 13px !important; }
            .email-mono { font-size: 11px !important; }
            .role-pill { font-size: 10px !important; padding: 3px 8px !important; }
            .status-pill { font-size: 10px !important; padding: 4px 10px !important; }
            .action-group { flex-wrap: nowrap !important; gap: 6px !important; }
            .btn-action-modern { 
                min-height: 44px !important; 
                min-width: 44px !important; 
                padding: 8px 12px !important;
                justify-content: center !important;
                font-size: 12px !important;
            }
            /* Modal: scroll inside, no full-height stretch that creates gap */
            .modal-overlay { align-items: flex-end !important; padding: 0 !important; overflow-y: auto !important; }
            .modal-card { 
                width: 100% !important; 
                max-width: 100% !important; 
                max-height: 92vh !important;
                margin: 0 !important; 
                border-radius: 16px 16px 0 0 !important;
                overflow: hidden !important;
            }
            .modal-body { padding: 16px !important; overflow-y: auto !important; }
            .form-grid { grid-template-columns: 1fr !important; gap: 10px !important; }
            .modal-foot { flex-direction: column !important; gap: 8px !important; padding: 14px 16px !important; }
            .modal-foot button { width: 100% !important; justify-content: center !important; min-height: 48px !important; }
            .btn-gov-primary { min-height: 48px !important; justify-content: center !important; }
            .close-btn { width: 44px !important; height: 44px !important; font-size: 22px !important; display: flex !important; align-items: center !important; justify-content: center !important; }
            .form-input-gov { min-height: 46px !important; font-size: 16px !important; }
            .section-divider { font-size: 10px !important; }
            .ribbon-input { min-height: 46px !important; font-size: 15px !important; }
        }
        /* Hide SweetAlert2 checkbox element forcefully */
        .swal2-checkbox,
        #swal2-checkbox,
        label[for="swal2-checkbox"],
        .swal2-checkbox input[type="checkbox"],
        div.swal2-checkbox {
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
            width: 0 !important;
            height: 0 !important;
            position: absolute !important;
            pointer-events: none !important;
        }
        .swal2-input, .swal2-select, .swal2-textarea, .swal2-radio { min-height: auto !important; width: auto !important; }
        /* Hide SweetAlert2 checkbox with extreme specificity */
        div.swal2-checkbox,
        .swal2-modal .swal2-checkbox,
        .swal2-container .swal2-checkbox {
            all: unset !important;
            display: none !important;
        }
        /* Action icon buttons - improve touch UX */
        .action-group .btn-action-modern { 
            display: inline-flex !important; 
            align-items: center !important; 
            gap: 6px !important; 
        }
        .action-group .btn-action-modern i { 
            font-size: 14px !important; 
            width: 16px !important; 
            text-align: center !important; 
        }
    </style>
@endsection

@section('content')
<div class="user-mgmt-container">
    <div class="polish-card">
        <!-- HEADER STRIP -->
        <div class="card-header-accent">
            <div>
                <h3 class="h3-title">
                    <i class="fa-solid fa-users-gear icon-blue"></i>
                    System User Accounts
                </h3>
                <p class="p-subtitle">Manage system access, roles, and office assignments for all personnel.</p>
            </div>
            <button id="addUserBtn" class="btn-gov-primary">
                <i class="fa-solid fa-user-plus"></i> Create System Account
            </button>
        </div>

        <div class="content-padding">
            <!-- STATS RIBBON -->
            <div class="stats-ribbon">
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>Total Users</p>
                        <h4 id="statTotal">--</h4>
                    </div>
                </div>
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>Active</p>
                        <h4 id="statActive">--</h4>
                    </div>
                </div>
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>Inactive</p>
                        <h4 id="statInactive">--</h4>
                    </div>
                </div>
            </div>

            <!-- FILTER RIBBON -->
            <div class="filter-ribbon">
                <div class="search-wrapper">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="searchUser" placeholder="Search by name or email address..." class="ribbon-input search-input">
                </div>

                <select id="filterDepartment" class="ribbon-input w-180">
                    <option value="">All Departments</option>
                    <option value="INTERNAL SERVICES DEPARTMENT">Internal Services Department</option>
                    <option value="TECHNICAL SERVICES DEPARTMENT">Technical Services Department</option>
                </select>

                <select id="filterDivision" class="ribbon-input w-220">
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

                <select id="filterRole" class="ribbon-input w-120">
                    <option value="">All Roles</option>
                    <option value="user">User</option>
                    <option value="admin">Division Admin</option>
                    <option value="supply_officer">Supply Officer</option>
                    <option value="it">IT Personnel</option>
                    <option value="super_admin">Super Admin</option>
                </select>

                <select id="filterStatus" class="ribbon-input w-120">
                    <option value="">All Status</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>
            </div>

            <div class="table-wrap">
                <table class="gov-table-premium">
                    <thead>
                        <tr>
                            <th>Personnel Information</th>
                            <th>Assigned Role</th>
                            <th>Office / Division</th>
                            <th class="th-center">Account Status</th>
                            <th class="th-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userTable">
                        <tr><td colspan="5" style="text-align:center;padding:30px;color:#94a3b8;">Loading...</td></tr>
                    </tbody>
                </table>
                <div id="usersPagination" class="pagination-wrap"></div>
            </div>
        </div>
    </div>
</div>
@include('partials.super-admin._user_modals')
@endsection

@include('partials.super-admin._user_scripts')