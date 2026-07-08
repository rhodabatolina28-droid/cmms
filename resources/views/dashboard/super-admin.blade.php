@extends('layouts.app')

@section('title', 'Super Admin Dashboard | NCMB ICT System')
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
            background: linear-gradient(135deg, #1e1b4b 0%, #0038A8 100%);
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

        /* STATS GRID MODERN — FLUID */
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

        .stat-total::before { background: #0038A8; }
        .stat-pending::before { background: #f59e0b; }
        .stat-ongoing::before { background: #3b82f6; }
        .stat-completed::before { background: #10b981; }

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

        /* ANALYTICS GRID — FLUID */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: clamp(15px, 2vw, 25px);
            margin-bottom: clamp(20px, 2.5vw, 30px);
        }

        .analytics-box {
            background: #f8fafc;
            border-radius: 10px;
            padding: clamp(16px, 1.5vw, 20px);
            border: 1px solid #e2e8f0;
        }

        .analytics-title {
            font-size: clamp(11px, 1vw, 13px);
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .analytics-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .analytics-row:last-child { border-bottom: none; }

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
            box-shadow: 0 4px 6px -1px rgba(0, 56, 168, 0.2);
        }

        @media (max-width: 1000px) {
            .stats-grid,
            .admin-workspace-grid {
                grid-template-columns: 1fr !important;
            }
            .analytics-grid {
                grid-template-columns: 1fr !important;
            }
        }
        @media (max-width: 767px) {
            .flex-sb { flex-direction: column !important; align-items: flex-start !important; gap: 16px !important; }
            .welcome-hero { padding: 18px 16px !important; border-radius: 12px !important; }
            .hero-name { font-size: 22px !important; }
            .hero-desc { font-size: 13px !important; max-width: 100% !important; }
            .hero-stats-box { width: 100% !important; text-align: left !important; padding: 12px 16px !important; border-radius: 10px !important; }
            .hero-stats-value { font-size: 20px !important; }
            .hero-role { font-size: 10px !important; letter-spacing: 1.5px !important; }
            .stats-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 10px !important; }
            .stat-card-premium { padding: 14px 12px !important; border-radius: 12px !important; }
            .stat-value { font-size: 20px !important; }
            .stat-label { font-size: 9px !important; margin-bottom: 4px !important; }
            .btn-action-premium { 
                padding: 14px 16px !important; 
                min-height: 48px !important; 
                align-items: center !important;
                border-radius: 12px !important;
            }
            .btn-action-premium > div:last-child {
                flex: 1 !important;
                min-width: 0 !important;
            }
            .action-title { font-size: 13px !important; }
            .action-subtitle { font-size: 10px !important; }
            .icon-circle { 
                width: 36px !important; 
                height: 36px !important; 
                font-size: 14px !important;
                flex-shrink: 0 !important;
            }
            .premium-table-box { padding: 16px !important; border-radius: 12px !important; }
            .table-title { font-size: 14px !important; }
            .link-master { font-size: 11px !important; }
            .table-header th { padding: 8px !important; font-size: 9px !important; }
            .table-cell, .table-cell-bold, .table-cell-dept, .table-cell-gray, .table-cell-dark { padding: 10px 8px !important; font-size: 11px !important; }
            .table-cell-center { padding: 10px 8px !important; }
            .ribbon-label { font-size: 10px !important; margin-bottom: 12px !important; }
            .section-label { font-size: 10px !important; margin: 20px 0 12px !important; }
            .stat-padded { padding: 14px !important; border-radius: 12px !important; }
            .analytics-box { border-radius: 12px !important; }
        }
        .tr-hover-row { transition: all 0.2s; position: relative; }
        .tr-hover-row:hover { background: #f8fafc !important; transform: scale(1.002); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .tr-hover-row:hover td:first-child { box-shadow: inset 4px 0 0 #0038A8; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }

        /* Utility classes */
        .flex-sb { display: flex; justify-content: space-between; align-items: center; }
        .flex-center { display: flex; align-items: center; }
        .flex-center-gap { display: flex; align-items: center; gap: 10px; }
        .text-uppercase { text-transform: uppercase; }
        .text-muted { font-size: 13px; color: #64748b; font-weight: 600; }
        .text-bold-dark { font-weight: 800; color: #1e293b; }
        .text-small-muted { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
        .stat-value-warn { color: #f59e0b; }
        .stat-value-blue { color: #3b82f6; }
        .stat-value-green { color: #10b981; }
        .icon-circle { background: white; border-radius: 8px; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .icon-blue { color: #0038A8; }
        .action-title { font-size: 14px; font-weight: 800; color: #1e293b; }
        .action-subtitle { font-size: 11px; color: #64748b; font-weight: 600; }
        .section-label { margin: 30px 0 15px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
        .table-header th { padding: 12px 10px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .table-header th.center { text-align: center; }
        .table-cell { padding: 16px 10px; }
        .table-cell-bold { padding: 16px 10px; font-weight: 800; color: #0038A8; }
        .table-cell-dept { padding: 16px 10px; font-weight: 700; color: #334155; }
        .table-cell-gray { padding: 16px 10px; color: #64748b; font-size: 13px; }
        .table-cell-dark { padding: 16px 10px; color: #1e293b; font-weight: 600; }
        .table-cell-center { padding: 16px 10px; text-align: center; }
        .empty-cell { padding: 40px; text-align: center; color: #94a3b8; }
        .ribbon-label { margin-bottom: 15px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
        .hero-role { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; opacity: 0.7; }
        .hero-name { font-size: 32px; font-weight: 800; margin: 0; }
        .hero-desc { margin: 10px 0 0; opacity: 0.8; font-size: 15px; max-width: 500px; }
        .hero-stats-box { text-align: right; background: rgba(255,255,255,0.1); padding: 15px 25px; border-radius: 12px; backdrop-filter: blur(10px); }
        .hero-stats-label { font-size: 11px; font-weight: 800; text-transform: uppercase; margin-bottom: 5px; }
        .hero-stats-value { font-size: 24px; font-weight: 800; }
        .mb-12 { margin-bottom: 12px; }
        .table-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 1px; }
        .link-master { text-decoration: none; color: #0038A8; font-weight: 700; font-size: 13px; }
        .text-system { font-size: 12px; color: #64748b; line-height: 1.5; }
        .strong-system { color: #1e293b; font-size: 13px; }
        .icon-ok { color: #10b981; font-size: 18px; }
        .admin-workspace-grid { display: grid; gap: 25px; }
        .pad-y-sm { padding: 8px 0; }
        .mb-25 { margin-bottom: 25px; }
        .scroll-x { overflow-x: auto; }
        .table-full { width: 100%; border-collapse: collapse; }
        .tr-header-bottom { text-align: left; border-bottom: 2px solid #f1f5f9; }
        .table-row-border { border-bottom: 1px solid #f8fafc; transition: background 0.2s; }
        .link-inherit { text-decoration: none; color: inherit; }
        .stat-padded { padding: 18px; background: #f8fafc; }
    </style>
@endsection

@section('content')
<div class="dashboard-container">
    
    <!-- WELCOME HERO (SUPER ADMIN) -->
    <div class="welcome-hero">
        <div class="flex-sb">
            <div>
                <div class="hero-role">System Administrator</div>
                <h1 class="hero-name">{{ Auth::user()->full_name }}</h1>
                <p class="hero-desc">
                    Managing ICT operations for the <strong>National Conciliation and Mediation Board</strong> Central Office.
                </p>
            </div>
            <div class="hero-stats-box">
                <div class="hero-stats-label">Total Users</div>
                <div class="hero-stats-value">{{ $stats['total_users'] }}</div>
            </div>
        </div>
    </div>

    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card-premium stat-total">
            <i class="fa-solid fa-layer-group stat-bg-icon"></i>
            <span class="stat-label">Total Tickets</span>
            <div class="stat-value">{{ $stats['total'] }}</div>
        </div>
        <div class="stat-card-premium stat-pending">
            <i class="fa-regular fa-hourglass-half stat-bg-icon"></i>
            <span class="stat-label">Pending</span>
            <div class="stat-value stat-value-warn">{{ $stats['pending'] }}</div>
        </div>
        <div class="stat-card-premium stat-ongoing">
            <i class="fa-solid fa-spinner stat-bg-icon"></i>
            <span class="stat-label">Ongoing</span>
            <div class="stat-value stat-value-blue">{{ $stats['ongoing'] }}</div>
        </div>
        <div class="stat-card-premium stat-completed">
            <i class="fa-solid fa-check-double stat-bg-icon"></i>
            <span class="stat-label">Completed</span>
            <div class="stat-value stat-value-green">{{ $stats['completed'] }}</div>
        </div>
    </div>

    <!-- WORKSPACE GRID -->
    <div class="admin-workspace-grid">
        
        <!-- LEFT: TABLE AND ANALYTICS -->
        <div>
            <!-- ANALYTICS GRID -->
            <div class="analytics-grid">
                <div class="analytics-box">
                    <div class="analytics-title">
                        <i class="fa-solid fa-chart-pie icon-blue"></i>
                        Service Distribution
                    </div>
                    <div class="analytics-row">
                        <span class="text-muted">ICT Tickets</span>
                        <span class="text-bold-dark">{{ $stats['ict'] }}</span>
                    </div>
                    <div class="analytics-row">
                        <span class="text-muted">PM Work Orders</span>
                        <span class="text-bold-dark">{{ $stats['maintenance'] }}</span>
                    </div>
                </div>

                  <div class="analytics-box">
                      <div class="analytics-title">
                          <i class="fa-solid fa-sitemap icon-blue"></i>
                          Department Coverage
                      </div>
                      @forelse($departmentStats as $branch => $count)
                      <div class="analytics-row">
                          <span class="text-muted">{{ $branch ?: 'Unassigned' }}</span>
                          <span class="text-bold-dark">{{ $count }} Requests</span>
                      </div>
                      @empty
                    <div class="text-muted pad-y-sm">
                        No requests found.
                    </div>
                      @endforelse
                  </div>
            </div>

            <!-- SYSTEM WIDE TABLE -->
            <div class="premium-table-box">
                <div class="flex-sb mb-25">
                    <h3 class="table-title">Recent Office Activity</h3>
                    <a href="{{ route('ict.index') }}" class="link-master">View Master List</a>
                </div>
                <div class="scroll-x">
                    <table class="table-full">
                        <thead>
                            <tr class="table-header tr-header-bottom">
                                <th>Tracking #</th>
                                <th>Division / Office</th>
                                <th>Type</th>
                                <th>Requestor</th>
                                <th class="center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                            $sortedRecent = $recentRequests->take(8)->sortBy(function($r) {
                                $map = ['Pending' => 0, 'Scheduled' => 0, 'Ongoing' => 1, 'Completed' => 2];
                                return $map[$r->status] ?? 99;
                            });
                        @endphp
                        @forelse($sortedRecent as $req)
                                <tr class="tr-hover-row table-row-border">
                                    <td class="table-cell-bold">
                                        <a href="{{ route($req->type === 'ICT' ? 'ict.show' : 'maintenance.show', $req->id) }}" class="link-inherit">
                                            {{ $req->display_number ?? $req->request_number }}
                                        </a>
                                    </td>
                                    <td class="table-cell-dept">
                                        @php
                                            $divisionLabel = $req->user->office ?? $req->user->department ?? $req->office ?? 'Central Office';
                                        @endphp
                                        {{ $divisionLabel }}
                                    </td>
                                    <td class="table-cell-gray">{{ $req->type }}</td>
                                    <td class="table-cell-dark">{{ $req->requestor_name }}</td>
                                    <td class="table-cell-center">
                                        <span class="status-pill status-{{ strtolower($req->status) }}">{{ $req->status }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="empty-cell" style="padding: 50px 20px;">
                                    <i class="fa-solid fa-inbox" style="font-size: 42px; color: #e2e8f0; margin-bottom: 15px; display: block;"></i>
                                    <div style="font-weight: 800; color: #64748b; font-size: 15px;">No System Activity</div>
                                    <div style="font-size: 12px; color: #94a3b8; margin-top: 5px;">No requests have been submitted across the system yet.</div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT: QUICK ACTIONS & TOOLS -->
        <div>
            <div class="ribbon-label">Management Tools</div>
            
            <a href="{{ route('ict.index') }}" class="btn-action-premium mb-12">
                <div class="icon-circle">
                    <i class="fa-solid fa-clipboard-list icon-blue"></i>
                </div>
                <div>
                    <div class="action-title">Master List</div>
                    <div class="action-subtitle">All office requests</div>
                </div>
            </a>

            <a href="{{ route('super_admin.users') }}" class="btn-action-premium mb-12">
                <div class="icon-circle">
                    <i class="fa-solid fa-users-gear icon-blue"></i>
                </div>
                <div>
                    <div class="action-title">Manage Users</div>
                    <div class="action-subtitle">System access & roles</div>
                </div>
            </a>

            <a href="{{ route('pm-schedules.index') }}" class="btn-action-premium mb-12">
                <div class="icon-circle">
                    <i class="fa-solid fa-calendar-check icon-blue"></i>
                </div>
                <div>
                    <div class="action-title">PM Schedules</div>
                    <div class="action-subtitle">Preventive maintenance schedules</div>
                </div>
            </a>

            <div class="section-label">System Status</div>
            <div class="stat-card-premium stat-padded">
                <div class="flex-center-gap mb-12">
                    <i class="fa-solid fa-circle-check icon-ok"></i>
                    <strong class="strong-system">All Systems Operational</strong>
                </div>
                <div class="text-system">
                    Database connection stable. Background jobs are processing normally.
                </div>
            </div>
        </div>

    </div>

</div>
@endsection