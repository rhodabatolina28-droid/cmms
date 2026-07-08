@extends('layouts.app')

@section('title', 'User Management | Super Admin')
@section('page-title', 'User Management')

@section('styles')
    <style nonce="{{ $cspNonce }}">
        .user-mgmt-container {
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
            padding: 16px 20px;
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
            max-width: 500px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            overflow: hidden;
            animation: modalPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .modal-header {
            padding: 20px 25px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        .close-btn { background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer; }
        .form-group { margin-bottom: 12px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        .form-group-sm { margin-bottom: 4px; }
        .form-help { margin: 2px 0 0; font-size: 11px; color: #64748b; line-height: 1.3; }
        .modal-body { padding: 20px 25px; }
        .modal-foot { padding: 12px 25px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px; }
        .btn-cancel { padding: 10px 20px; }
        .btn-submit { padding: 10px 25px; }
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
            .modal-overlay { align-items: flex-start !important; padding: 0 !important; overflow-y: auto !important; }
            .modal-card { width: 100% !important; max-width: 100% !important; margin: 0 !important; border-radius: 0 !important; min-height: auto !important; overflow: visible !important; }
            .modal-body { padding: 16px !important; }
            form { min-height: auto !important; display: block !important; }
            .form-grid { grid-template-columns: 1fr !important; gap: 10px !important; }
            .modal-foot { flex-direction: column !important; gap: 8px !important; padding: 14px 16px !important; margin-top: 0 !important; }
            .modal-foot button { width: 100% !important; justify-content: center !important; }
            .btn-gov-primary { min-height: 48px !important; justify-content: center !important; }
            .close-btn { width: 44px !important; height: 44px !important; font-size: 24px !important; display: flex !important; align-items: center !important; justify-content: center !important; border-radius: 8px !important; }
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
                        <h4>{{ $users->total() }}</h4>
                    </div>
                </div>
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>Active</p>
                        <h4>{{ \App\Models\User::where('branch', auth()->user()->branch)->where('is_active', true)->count() }}</h4>
                    </div>
                </div>
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>Inactive</p>
                        <h4>{{ \App\Models\User::where('branch', auth()->user()->branch)->where('is_active', false)->count() }}</h4>
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
                    <option value="RESEARCH AND INFORMATION DIVISION">Research & Information Division</option>
                    <option value="ADMINISTRATIVE DIVISION">Administrative Division</option>
                    <option value="FINANCIAL AND MANAGEMENT DIVISION">Financial & Management Division</option>
                    <option value="COMMISSION ON AUDIT">Commission on Audit</option>
                    <option value="CONCILIATION AND MEDIATION DIVISION">Conciliation & Mediation Division</option>
                    <option value="VOLUNTARY ARBITRATION DIVISION">Voluntary Arbitration Division</option>
                    <option value="WORKPLACE RELATIONS ENHANCEMENT DIVISION">Workplace Relations Enhancement Division</option>
                    <option value="OFFICE OF THE EXECUTIVE DIRECTOR">Office of the Executive Director</option>
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
                    <option value="Active">Active Only</option>
                    <option value="Inactive">Inactive Only</option>
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
                        @foreach($users as $user)
                        <tr class="tr-hover-row" data-division="{{ strtoupper($user->office ?? '') }}"
                            data-department="{{ strtoupper($user->department ?? '') }}"
                            data-role="{{ strtoupper($user->role ?? '') }}"
                            data-status="{{ $user->is_active ? 'ACTIVE' : 'INACTIVE' }}">
                            <td>
                                <div class="name-bold">{{ $user->full_name }}</div>
                                <div class="email-mono">{{ $user->email }}</div>
                            </td>
                            <td>
                                <span class="role-pill">
                                    {{ str_replace('_', ' ', $user->role) }}
                                </span>
                            </td>
                            <td class="td-bold">
                                {{ $user->office ?: 'No Office Assigned' }}
                                @if($user->department)
                                    <div class="dept-sub">
                                        {{ $user->department }}
                                    </div>
                                @endif
                            </td>
                            <td class="td-center">
                                <span class="status-pill {{ $user->is_active ? 'sp-active' : 'sp-inactive' }}">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="td-right">
                                <div class="action-group">
                                    <button data-action="reset-password" data-user-id="{{ $user->id }}" class="btn-action-modern" title="Reset Password to Default">
                                        <i class="fa-solid fa-key"></i>
                                    </button>
                                    <button data-action="toggle-status" data-user-id="{{ $user->id }}" class="btn-action-modern" title="Toggle Access">
                                        <i class="fa-solid fa-power-off"></i>
                                    </button>
                                    <button data-action="edit-user" data-user-id="{{ $user->id }}" class="btn-action-modern" title="Edit User">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="pagination-wrap">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal-card">
        <div class="modal-header">
            <div>
                <h4 class="modal-title">Edit System Account</h4>
                <p style="margin: 4px 0 0; font-size: 12px; color: #64748b;">Update user information and role assignments</p>
            </div>
            <button type="button" class="close-btn" onclick="document.getElementById('editUserModal').style.display='none'">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form id="editUserForm">
            <div class="modal-body">
                <input type="hidden" name="user_id" id="editUserId">

                {{-- Personnel Information Section --}}
                <div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">
                    <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px;">
                        <i class="fa-solid fa-user" style="color: #0038A8; margin-right: 6px;"></i>Personnel Information
                    </div>
                    
                    {{-- Full Name --}}
                    <div class="form-group">
                        <label class="form-label-gov">Full Name <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="full_name" id="editFullName" class="form-input-gov" required placeholder="Enter complete name" style="font-size: 14px;">
                    </div>

                    {{-- Email --}}
                    <div class="form-group">
                        <label class="form-label-gov">Email Address <span style="color:#ef4444;">*</span></label>
                        <input type="email" name="email" id="editEmail" class="form-input-gov" required placeholder="e.g. name@ncmb.gov.ph" style="font-size: 14px;">
                    </div>
                </div>

                {{-- Role & Assignment Section --}}
                <div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #e2e8f0;">
                    <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px;">
                        <i class="fa-solid fa-shield-halved" style="color: #0038A8; margin-right: 6px;"></i>Role & Assignment
                    </div>

                    {{-- Role --}}
                    <div class="form-group">
                        <label class="form-label-gov">System Role <span style="color:#ef4444;">*</span></label>
                        <select name="role" id="editUserRole" class="form-input-gov" required style="font-size: 14px;">
                            <option value="user">User</option>
                            <option value="admin">Division Admin</option>
                            <option value="supply_officer">Supply Officer / Admin (Administrative Div.)</option>
                            <option value="it">IT Personnel</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                        <p class="form-help" style="margin-top: 6px;">Supply Officer role is restricted to Administrative Division only.</p>
                    </div>

                    {{-- Region | Branch --}}
                    <div class="form-grid">
                        <div>
                            <label class="form-label-gov">Region</label>
                            <input type="text" name="region" id="editRegion" class="form-input-gov" readonly style="background: #f8fafc; font-size: 13px;">
                        </div>
                        <div>
                            <label class="form-label-gov">Branch</label>
                            <input type="text" name="branch" id="editBranch" class="form-input-gov" readonly style="background: #f8fafc; font-size: 13px;">
                        </div>
                    </div>
                </div>

                {{-- Department & Division Section --}}
                <div>
                    <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px;">
                        <i class="fa-solid fa-building" style="color: #0038A8; margin-right: 6px;"></i>Department & Division
                    </div>

                    {{-- Department | Division --}}
                    <div class="form-grid">
                        <div>
                            <label class="form-label-gov">Department</label>
                            <select name="department" id="editUserDepartment" class="form-input-gov" style="font-size: 14px;">
                                <option value="">None / Not Applicable</option>
                                <option value="INTERNAL SERVICES DEPARTMENT">Internal Services Dept.</option>
                                <option value="TECHNICAL SERVICES DEPARTMENT">Technical Services Dept.</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label-gov">Division / Office <span style="color:#ef4444;">*</span></label>
                            <select name="office" id="editUserOffice" class="form-input-gov" required style="font-size: 14px;">
                                <option value="">— Select Division / Office —</option>
                                <option value="RESEARCH AND INFORMATION DIVISION" data-dept="INTERNAL">Research & Information Div. (RID)</option>
                                <option value="ADMINISTRATIVE DIVISION" data-dept="INTERNAL">Administrative Division (AD)</option>
                                <option value="FINANCIAL AND MANAGEMENT DIVISION" data-dept="INTERNAL">Financial & Management Div. (FMD)</option>
                                <option value="COMMISSION ON AUDIT" data-dept="INTERNAL">Commission on Audit (COA)</option>
                                <option value="CONCILIATION AND MEDIATION DIVISION" data-dept="TECHNICAL">Conciliation & Mediation Div. (CMD)</option>
                                <option value="VOLUNTARY ARBITRATION DIVISION" data-dept="TECHNICAL">Voluntary Arbitration Div. (VAD)</option>
                                <option value="WORKPLACE RELATIONS ENHANCEMENT DIVISION" data-dept="TECHNICAL">Workplace Relations Enhancement Div. (WRED)</option>
                                <option value="OFFICE OF THE EXECUTIVE DIRECTOR" data-dept="TECHNICAL">Office of the Exec. Director (OED)</option>
                            </select>
                            <p class="form-help" style="margin-top: 6px;">Required — determines the user's scope in the system.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-action-modern" onclick="document.getElementById('editUserModal').style.display='none'">
                    <i class="fa-solid fa-xmark" style="margin-right: 4px;"></i> Discard
                </button>
                <button type="submit" class="btn-gov-primary btn-submit">
                    <i class="fa-solid fa-save"></i> Update Account
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ADD USER MODAL -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal-card">
        <div class="modal-header">
            <h4 class="modal-title">Create New System Account</h4>
        </div>
        <form id="addUserForm">
            <div class="modal-body">
                {{-- Row 1: Full Name --}}
                <div class="form-group">
                    <label class="form-label-gov">Personnel Full Name</label>
                    <input type="text" name="full_name" class="form-input-gov" required placeholder="Enter complete name">
                </div>

                {{-- Row 2: Email --}}
                <div class="form-group">
                    <label class="form-label-gov">Official Email Address</label>
                    <input type="email" name="email" class="form-input-gov" required placeholder="e.g. name@ncmb.gov.ph">
                </div>

                {{-- Row 3: Role --}}
                <div class="form-group">
                    <label class="form-label-gov">System Role</label>
                    <select name="role" id="newUserRole" class="form-input-gov" required>
                        <option value="user">User</option>
                        <option value="admin">Division Admin</option>
                        <option value="supply_officer">Supply Officer / Admin (Administrative Div.)</option>
                        <option value="it">IT Personnel</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>

                {{-- Row 4: Region | Branch --}}
                <div class="form-grid">
                    <div>
                        <label class="form-label-gov">Region</label>
                        <input type="text" name="region" class="form-input-gov" placeholder="e.g. NCR, Region I, CAR" value="{{ Auth::user()->region }}" readonly>
                    </div>
                    <div>
                        <label class="form-label-gov">Branch</label>
                        <input type="text" name="branch" class="form-input-gov" placeholder="e.g. NCR Central Office" value="{{ Auth::user()->branch }}" readonly>
                    </div>
                </div>

                {{-- Row 5: Department | Division --}}
                <div class="form-grid">
                    <div>
                        <label class="form-label-gov">Department</label>
                        <select name="department" id="newUserDepartment" class="form-input-gov">
                            <option value="">None / Not Applicable</option>
                            <option value="INTERNAL SERVICES DEPARTMENT">Internal Services Dept.</option>
                            <option value="TECHNICAL SERVICES DEPARTMENT">Technical Services Dept.</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-gov">Division / Office <span style="color:#ef4444;">*</span></label>
                        <select name="office" id="newUserOffice" class="form-input-gov" required>
                            <option value="">— Select Division / Office —</option>
                            <option value="RESEARCH AND INFORMATION DIVISION" data-dept="INTERNAL">Research & Information Div. (RID)</option>
                            <option value="ADMINISTRATIVE DIVISION" data-dept="INTERNAL">Administrative Division (AD)</option>
                            <option value="FINANCIAL AND MANAGEMENT DIVISION" data-dept="INTERNAL">Financial & Management Div. (FMD)</option>
                            <option value="COMMISSION ON AUDIT" data-dept="INTERNAL">Commission on Audit (COA)</option>
                            <option value="CONCILIATION AND MEDIATION DIVISION" data-dept="TECHNICAL">Conciliation & Mediation Div. (CMD)</option>
                            <option value="VOLUNTARY ARBITRATION DIVISION" data-dept="TECHNICAL">Voluntary Arbitration Div. (VAD)</option>
                            <option value="WORKPLACE RELATIONS ENHANCEMENT DIVISION" data-dept="TECHNICAL">Workplace Relations Enhancement Div. (WRED)</option>
                            <option value="OFFICE OF THE EXECUTIVE DIRECTOR" data-dept="TECHNICAL">Office of the Exec. Director (OED)</option>
                        </select>
                        <p class="form-help">Required — determines the user's scope in the system.</p>
                    </div>
                </div>

                {{-- Row 6: Password --}}
                <div class="form-group-sm" style="position:relative;">
                    <label class="form-label-gov">Initial Access Password</label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="password" name="password" id="newUserPassword" class="form-input-gov" required placeholder="••••••••" style="padding-right:40px;">
                        <button type="button" id="toggleNewUserPassword" class="btn-action-modern" style="position:absolute;right:22px;top:32px;border:none;background:none;padding:8px;cursor:pointer;font-size:16px;color:#64748b;" tabindex="-1">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <p class="form-help" style="margin-top:6px;">Password must be at least 8 characters, 1 uppercase letter, and 1 number.</p>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-action-modern close-modal-btn btn-cancel">Discard</button>
                <button type="submit" class="btn-gov-primary btn-submit">
                    <i class="fa-solid fa-save"></i> Save Account
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
function filterUsers() {
    const search = document.getElementById('searchUser').value.toLowerCase();
    const department = document.getElementById('filterDepartment').value.toUpperCase();
    const division = document.getElementById('filterDivision').value.toUpperCase();
    const role = document.getElementById('filterRole').value.toUpperCase();
    const status = document.getElementById('filterStatus').value.toUpperCase();
    const rows = document.querySelectorAll('#userTable tr');

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
        if (row.dataset.division === undefined) return;

        const text = row.textContent.toLowerCase();
        const rowDivision = row.dataset.division || '';
        const rowDepartment = row.dataset.department || '';
        const rowRole = row.dataset.role;
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

        const matchesRole = role === "" || rowRole === role;
        const matchesStatus = status === "" || (status === "ACTIVE ONLY" && rowStatus === "ACTIVE") || (status === "INACTIVE ONLY" && rowStatus === "INACTIVE") || rowStatus === status;

        row.style.display = (matchesSearch && matchesDiv && matchesDept && matchesRole && matchesStatus) ? "" : "none";
    });
}

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
            await Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.message,
                confirmButtonColor: '#0038A8',
                timer: 2000,
                showConfirmButton: false
            });
            location.reload();
        } else if (result.errors) {
            // Validation errors from server
            const errorMessages = Object.values(result.errors).flat().join('<br>');
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                html: errorMessages,
                confirmButtonColor: '#0038A8'
            });
        } else if (result.message) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message,
                confirmButtonColor: '#0038A8'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'An unexpected error occurred. Please check the password requirements: at least 8 characters, 1 uppercase letter, and 1 number.',
                confirmButtonColor: '#0038A8'
            });
        }
    } catch (error) {
        Swal.fire('Error!', 'An unexpected error occurred.', 'error');
    }
});

async function toggleUserStatus(id) {
    const confirmResult = await Swal.fire({
        title: 'Are you sure?',
        text: "You are about to toggle this account's status.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0038A8',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, toggle it!'
    });

    if (!confirmResult.isConfirmed) return;

    try {
        const response = await fetch("{{ route('super_admin.users.toggle', ':id') }}".replace(':id', id), {
            method: 'POST',
            credentials: 'include',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            Swal.fire('Error!', result.message, 'error');
        }
    } catch (error) {
        Swal.fire('Error!', 'An error occurred while toggling status.', 'error');
    }
}

async function resetPassword(id) {
    const confirmResult = await Swal.fire({
        title: 'Reset Password?',
        text: "Are you sure you want to reset this user's password to the system default?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0038A8',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, reset it!'
    });

    if (!confirmResult.isConfirmed) return;

    try {
        const response = await fetch("{{ route('super_admin.users.reset_password', ':id') }}".replace(':id', id), {
            method: 'POST',
            credentials: 'include',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await response.json();
        if (result.success) {
            Swal.fire('Password Reset!', result.message, 'success');
        } else {
            Swal.fire('Error!', result.message, 'error');
        }
    } catch (error) {
        Swal.fire('Error!', 'An error occurred while resetting the password.', 'error');
    }
}

// Close modal on outside click
window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.style.display = 'none';
    }
}

async function editUser(id) {
    try {
        const response = await fetch("{{ route('super_admin.users') }}?get_user=" + id);
        const result = await response.json();
        
        if (result.success) {
            const user = result.user;
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editFullName').value = user.full_name;
            document.getElementById('editEmail').value = user.email;
            document.getElementById('editUserRole').value = user.role;
            document.getElementById('editRegion').value = user.region || '';
            document.getElementById('editBranch').value = user.branch || '';
            document.getElementById('editUserDepartment').value = user.department || '';
            document.getElementById('editUserOffice').value = user.office || '';
            
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
            method: 'PUT',
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
            await Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: result.message,
                confirmButtonColor: '#0038A8',
                timer: 2000,
                showConfirmButton: false
            });
            document.getElementById('editUserModal').style.display = 'none';
            location.reload();
        } else if (result.errors) {
            const errorMessages = Object.values(result.errors).flat().join('<br>');
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                html: errorMessages,
                confirmButtonColor: '#0038A8'
            });
        } else if (result.message) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: result.message,
                confirmButtonColor: '#0038A8'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'An unexpected error occurred.',
                confirmButtonColor: '#0038A8'
            });
        }
    } catch (error) {
        Swal.fire('Error!', 'An unexpected error occurred.', 'error');
    }
});
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('searchUser').addEventListener('keyup', filterUsers);
    document.getElementById('filterDepartment').addEventListener('change', function() {
        updateDepartmentDropdown('filterDepartment', 'filterDivision');
        filterUsers();
    });
    document.getElementById('filterDivision').addEventListener('change', filterUsers);
    document.getElementById('filterRole').addEventListener('change', filterUsers);
    document.getElementById('filterStatus').addEventListener('change', filterUsers);
    document.getElementById('addUserBtn').addEventListener('click', openAddUserModal);
    document.querySelectorAll('.close-modal-btn').forEach(function(el) {
        el.addEventListener('click', closeAddUserModal);
    });

    // Password toggle show/hide
    document.getElementById('toggleNewUserPassword').addEventListener('click', function() {
        const pwInput = document.getElementById('newUserPassword');
        const icon = this.querySelector('i');
        if (pwInput.type === 'password') {
            pwInput.type = 'text';
            icon.className = 'fa-solid fa-eye-slash';
        } else {
            pwInput.type = 'password';
            icon.className = 'fa-solid fa-eye';
        }
    });

    // Filter division options based on selected department
    document.getElementById('newUserDepartment').addEventListener('change', function() {
        const dept = this.value;
        const officeSelect = document.getElementById('newUserOffice');
        const options = officeSelect.querySelectorAll('option');
        
        options.forEach(opt => {
            if (!opt.value) return; // Skip placeholder
            const dataDept = opt.getAttribute('data-dept');
            if (!dept || !dataDept) {
                opt.style.display = '';
            } else if (dept === 'INTERNAL SERVICES DEPARTMENT' && dataDept === 'INTERNAL') {
                opt.style.display = '';
            } else if (dept === 'TECHNICAL SERVICES DEPARTMENT' && dataDept === 'TECHNICAL') {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
        officeSelect.value = '';
    });

    // Auto-set department when office is selected
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
        const deptSelect = document.getElementById('newUserDepartment');
        deptSelect.value = deptMap[this.value] || '';
    });
    document.querySelectorAll('[data-action]').forEach(function(el) {
        el.addEventListener('click', function() {
            var id = this.dataset.userId;
            switch (this.dataset.action) {
                case 'reset-password': resetPassword(id); break;
                case 'toggle-status': toggleUserStatus(id); break;
                case 'edit-user': editUser(id); break;
            }
        });
    });
});
</script>
@endsection


