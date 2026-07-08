@extends('layouts.app')

@section('title', 'Admin Dashboard | NCMB ICT System')
@section('page-title', 'Dashboard')

@section('styles')
    <style nonce="{{ $cspNonce }}">
        /* PREMIUM DASHBOARD ANIMATIONS */
        @keyframes fadeInSlide {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dashboard-container {
            animation: fadeInSlide 0.5s ease-out;
        }

        /* PREMIUM WELCOME HERO — FLUID */
        .welcome-hero {
            background: linear-gradient(135deg, #0f172a 0%, #0038A8 100%);
            border-radius: clamp(10px, 1.2vw, 15px);
            padding: clamp(20px, 3vw, 35px);
            color: white;
            position: relative;
            overflow: hidden;
            margin-bottom: clamp(20px, 2.5vw, 30px);
            box-shadow: 0 10px 25px rgba(0, 56, 168, 0.2);
        }

        .welcome-hero::after {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: clamp(120px, 12vw, 180px);
            height: clamp(120px, 12vw, 180px);
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }

        /* STATS GRID MODERN — FLUID: adapts to any monitor size */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: clamp(12px, 1.5vw, 20px);
            margin-bottom: clamp(20px, 2.5vw, 30px);
        }

        .stat-card-premium {
            background: white;
            border-radius: 12px;
            padding: clamp(16px, 1.5vw, 20px);
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card-premium:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px -8px rgba(0, 56, 168, 0.15);
            border-color: rgba(0, 56, 168, 0.2);
        }

        .stat-bg-icon {
            position: absolute;
            right: -10px;
            bottom: -15px;
            font-size: 80px;
            opacity: 0.03;
            transform: rotate(-15deg);
            transition: transform 0.3s;
        }

        .stat-card-premium:hover .stat-bg-icon {
            transform: rotate(0) scale(1.1);
            opacity: 0.06;
        }

        .stat-card-premium::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .stat-label {
            font-size: clamp(10px, 0.8vw, 11px);
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            display: block;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: clamp(24px, 2.5vw, 32px);
            font-weight: 800;
            color: #1e293b;
        }

        /* TABLE THEME */
        .premium-table-box {
            background: white;
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .status-pill {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .status-pending { background: #fffbeb; color: #92400e; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.2); }
        .status-ongoing { background: #eff6ff; color: #1e40af; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.2); }
        .status-completed { background: #ecfdf5; color: #065f46; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.2); }

        /* QUICK ACTION BUTTONS */
        .btn-action-premium {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 16px;
            border-radius: 10px;
            text-decoration: none;
            color: #1e293b;
            font-weight: 700;
            font-size: 14px;
            transition: all 0.2s;
        }

        .btn-action-premium:hover {
            background: #0038A8;
            color: white;
            border-color: #0038A8;
            transform: translateY(-2px);
        }

        .queue-panel {
            background: white;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .queue-item {
            display: block;
            text-decoration: none;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .queue-item:last-child {
            border-bottom: 0;
        }

        @media (max-width: 800px) {
            .admin-workspace-grid {
                grid-template-columns: 1fr !important;
            }
        }
        .tr-hover-row { transition: all 0.2s; position: relative; }
        .tr-hover-row:hover { background: #f8fafc !important; transform: scale(1.002); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .tr-hover-row:hover td:first-child { box-shadow: inset 4px 0 0 #0038A8; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }

        /* Utility classes */
        .flex-sb { display: flex; justify-content: space-between; align-items: center; }
        .flex-sb-wrap { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        .flex-center { display: flex; align-items: center; }
        .flex-start-gap { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
        .flex-center-sb { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .text-muted { font-size: 13px; color: #64748b; font-weight: 600; }
        .text-bold-dark { font-weight: 800; color: #1e293b; }
        .text-red { color: #dc2626; }
        .text-green { color: #10b981; }
        .text-dark { color: #1e293b; }
        .table-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 1px; }
        .status-pending   { background: #fffbeb; color: #92400e; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.2); }
        .status-ongoing   { background: #eff6ff; color: #1e40af; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.2); }
        .status-completed { background: #ecfdf5; color: #065f46; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.2); }
        .status-rejected  { background: #fef2f2; color: #991b1b; border: 1px solid #fee2e2; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.15); }
        .status-cancelled { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
        .status-other     { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
        .link-master { text-decoration: none; color: #0038A8; font-weight: 700; font-size: 13px; }
        .scroll-x { overflow-x: auto; }
        .table-full { width: 100%; border-collapse: collapse; }
        .table-header { padding: 12px 10px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .table-header th { padding: 12px 10px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .table-header th.center { text-align: center; }
        .table-header th.right { text-align: right; }
        .table-row-header { text-align: left; border-bottom: 2px solid #f1f5f9; }
        .table-row-border { border-bottom: 1px solid #f8fafc; transition: background 0.2s; }
        .table-cell { padding: 16px 10px; }
        .table-cell-bold { padding: 16px 10px; font-weight: 800; color: #0038A8; }
        .table-cell-gray { padding: 16px 10px; color: #64748b; font-size: 13px; }
        .table-cell-dark { padding: 16px 10px; color: #1e293b; font-weight: 600; }
        .table-cell-center { padding: 16px 10px; text-align: center; }
        .table-cell-right { padding: 16px 10px; text-align: right; }
        .empty-cell { padding: 40px; text-align: center; color: #94a3b8; }
        .icon-link { color: #94a3b8; }
        .ribbon-label { margin-bottom: 15px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
        .section-label { margin: 30px 0 15px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
        .hero-role { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; opacity: 0.7; }
        .hero-name { font-size: 32px; font-weight: 800; margin: 0; }
        .hero-desc { margin: 10px 0 0; opacity: 0.8; font-size: 15px; }
        .hero-stats-box { text-align: right; background: rgba(255,255,255,0.1); padding: 15px 25px; border-radius: 12px; backdrop-filter: blur(10px); }
        .hero-stats-label { font-size: 11px; font-weight: 800; text-transform: uppercase; margin-bottom: 5px; }
        .hero-stats-value { font-size: 18px; font-weight: 800; }
        .mb-0 { margin-bottom: 0; }
        .mb-10 { margin-bottom: 10px; }
        .mb-25 { margin-bottom: 25px; }
        .sub-label { font-size: 11px; color: #64748b; margin-top: 4px; }
        .badge-sm { border-radius: 999px; padding: 3px 8px; font-size: 11px; font-weight: 800; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-green { background: #dcfce7; color: #166534; }
        .admin-workspace-grid { display: grid; gap: 25px; }
        .job-number { font-size: 13px; font-weight: 800; color: #1e293b; }
        .job-meta { font-size: 12px; color: #64748b; margin-top: 2px; }
        .assign-link { color: #0038A8; font-size: 12px; font-weight: 800; }
        .empty-queue { padding: 16px 0 6px; color: #64748b; font-size: 13px; line-height: 1.5; }
        .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; font-size: 12px; color: #475569; }
        .info-min { min-width: 140px; }
        .info-label { color: #64748b; }
        @media (max-width: 767px) {
            .admin-workspace-grid { grid-template-columns: 1fr !important; }
            .stats-grid { grid-template-columns: repeat(2,1fr) !important; }
            .workbench-grid { grid-template-columns: 1fr !important; }
            .card-header-accent { flex-direction: column !important; gap: 10px !important; }
            .info-box { padding: 12px !important; font-size: 13px !important; }
            .assign-link { font-size: 13px !important; min-height: 44px !important; display: inline-flex !important; align-items: center !important; }
            input, select, textarea { min-height: 48px !important; font-size: 15px !important; }
            .btn, button:not(#sidebarToggle):not(#notifBell) { min-height: 48px !important; font-size: 14px !important; }
        }
    </style>
@endsection

@section('content')
<div class="dashboard-container">
    
    <!-- WELCOME HERO (ADMIN) -->
    <div class="welcome-hero">
        <div class="flex-sb">
            <div>
                <div class="hero-role">Division Administrator</div>
                <h1 class="hero-name">{{ Auth::user()->full_name }}</h1>
                <p class="hero-desc">
                    Overseeing ICT operations for <strong>{{ Auth::user()->office ?: (Auth::user()->department ?: 'Central Office') }}</strong>
                </p>
            </div>
            <div class="hero-stats-box">
                <div class="hero-stats-label">Managed Office</div>
                <div class="hero-stats-value">{{ Auth::user()->office ?: (Auth::user()->department ?: 'Central Office') }}</div>
            </div>
        </div>
    </div>

    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card-premium stat-pending">
            <i class="fa-regular fa-hourglass-half stat-bg-icon"></i>
            <span class="stat-label">Pending</span>
            <div class="stat-value">{{ $stats['pending'] }}</div>
        </div>
        <div class="stat-card-premium stat-ongoing">
            <i class="fa-solid fa-spinner stat-bg-icon"></i>
            <span class="stat-label">Ongoing</span>
            <div class="stat-value">{{ $stats['ongoing'] }}</div>
        </div>
        <div class="stat-card-premium stat-completed">
            <i class="fa-solid fa-check-double stat-bg-icon"></i>
            <span class="stat-label">Completed</span>
            <div class="stat-value">{{ $stats['completed'] }}</div>
        </div>
        <div class="stat-card-premium stat-total">
            <i class="fa-solid fa-users-gear stat-bg-icon"></i>
            <span class="stat-label">Jobs - Unassigned</span>
            <div class="stat-value {{ ($stats['unassigned_jobs'] ?? 0) > 0 ? 'text-red' : 'text-green' }}">{{ $stats['unassigned_jobs'] ?? 0 }}</div>
            <div class="sub-label">Assign via Manage Requests</div>
        </div>
    </div>

    <div class="admin-workspace-grid">
        
        <!-- LEFT: DEPARTMENT/OFFICE ACTIVITY -->
        <div class="premium-table-box">
            <div class="flex-sb mb-25">
                <h3 class="table-title">Recent {{ Auth::user()->department ? 'Department' : (Auth::user()->office ? 'Office' : 'Division') }} Requests</h3>
                <a href="{{ route('ict.index') }}" class="link-master">View All</a>
            </div>
            <div class="scroll-x">
                <table class="table-full">
                    <thead>
                        <tr class="table-row-header">
                            <th class="table-header">Tracking #</th>
                            <th class="table-header">Requestor</th>
                            <th class="table-header center">Status</th>
                            <th class="table-header right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            @php
                                $statusKey = strtolower(str_replace([' ', '-'], '', $req->status));
                                $safeStatus = in_array($statusKey, ['pending','ongoing','completed','rejected','cancelled'])
                                    ? $statusKey : 'other';
                            @endphp
                            <tr class="tr-hover-row table-row-border">
                                <td class="table-cell-bold">{{ $req->display_number ?? $req->request_number }}</td>
                                <td class="table-cell-dark">{{ $req->requestor_name }}</td>
                                <td class="table-cell-center">
                                    <span class="status-pill status-{{ $safeStatus }}">{{ $req->status }}</span>
                                </td>
                                <td class="table-cell-right">
                                    <a href="{{ route($req->getRoutePrefix() . '.show', $req->id) }}" class="icon-link"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-cell" style="padding: 50px 20px;">
                                <i class="fa-solid fa-inbox" style="font-size: 42px; color: #e2e8f0; margin-bottom: 15px; display: block;"></i>
                                <div style="font-weight: 800; color: #64748b; font-size: 15px;">No Requests Yet</div>
                                <div style="font-size: 12px; color: #94a3b8; margin-top: 5px;">Your office hasn't received any requests at the moment.</div>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RIGHT: QUICK ACTIONS & TOOLS -->
        <div>
            <div class="queue-panel">
                <div class="flex-center-sb">
                    <div class="ribbon-label mb-0">Needs IT Assignment</div>
                    <span class="badge-sm {{ ($stats['unassigned_jobs'] ?? 0) > 0 ? 'badge-red' : 'badge-green' }}">{{ $stats['unassigned_jobs'] ?? 0 }}</span>
                </div>

                @forelse(($unassignedRequests ?? collect()) as $job)
                    <a href="{{ route($job->type === 'ICT' ? 'ict.show' : 'maintenance.show', $job->id) }}" class="queue-item">
                        <div class="flex-start-gap">
                            <div>
                                <div class="job-number">{{ $job->display_number ?? $job->request_number }}</div>
                                <div class="job-meta">{{ $job->requestor_name }}</div>
                            </div>
                            <span class="assign-link">Assign</span>
                        </div>
                    </a>
                @empty
                    <div class="empty-queue">All ICT and PM jobs in your scope already have assigned IT personnel.</div>
                @endforelse
            </div>

            <div class="ribbon-label">Management Tools</div>
            <a href="{{ route('ict.index') }}" class="btn-action-premium mb-10">
                <i class="fa-solid fa-list-check"></i> Manage Requests
            </a>
            @if(Auth::user()->canProcessSupply())
                <a href="{{ route('inventory.index') }}" class="btn-action-premium mb-10">
                    <i class="fa-solid fa-boxes-stacked"></i> Inventory & Assets
                </a>
            @endif
            <a href="{{ route('personnel.index') }}" class="btn-action-premium">
                <i class="fa-solid fa-users-gear"></i> Manage Personnel
            </a>

            @php
                $dashboardUser = Auth::user();
                $roleLabel = $dashboardUser->role === 'super_admin' ? 'Super Admin'
                    : ($dashboardUser->role === 'it' ? 'IT Personnel'
                    : ($dashboardUser->canProcessSupply() ? 'Supply Admin' : 'Division Admin'));
                $scopeLabel = $dashboardUser->department ? 'Department'
                    : ($dashboardUser->office ? 'Office' : ($dashboardUser->branch ? 'Branch' : 'Scope'));
                $scopeValue = $dashboardUser->department ?: $dashboardUser->office ?: $dashboardUser->branch ?: 'N/A';
            @endphp

            <div class="section-label">Division Info</div>
            <div class="info-box">
                <div class="flex-sb-wrap">
                    <div class="info-min">
                        <span>System Role:</span><br>
                        <strong class="text-dark">{{ $roleLabel }}</strong>
                    </div>
                    <div class="info-min">
                        <span class="info-label">{{ $scopeLabel }}:</span><br>
                        <strong class="text-dark">{{ $scopeValue }}</strong>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>
@endsection