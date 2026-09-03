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
            font-family: Arial, Helvetica, sans-serif;
        }

        .dashboard-container button,
        .dashboard-container input,
        .dashboard-container select,
        .dashboard-container textarea {
            font-family: Arial, Helvetica, sans-serif;
        }

        /* ALERTS AND PULSE EFFECTS */
        @keyframes pulseRed {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        .stat-overdue-alert {
            animation: pulseRed 2s infinite;
        }
        
        /* CSS BAR CHART */
        .css-bar-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 12px;
        }
        .css-bar-label {
            width: 120px;
            font-size: 11px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            text-align: right;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .css-bar-track {
            flex: 1;
            background: #f1f5f9;
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
        }
        .css-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6 0%, #8b5cf6 100%);
            border-radius: 5px;
            transition: width 1s ease-out;
        }
        .css-bar-value {
            width: 30px;
            font-size: 13px;
            font-weight: 800;
            color: #1e293b;
        }

        /* PREMIUM WELCOME HERO — DEEP NAVY / NCMB BLUE */
        .welcome-hero {
            background: linear-gradient(135deg, #0f172a 0%, #002878 100%);
            border-radius: clamp(10px, 1.2vw, 15px);
            padding: clamp(20px, 3vw, 35px);
            color: white;
            position: relative;
            overflow: hidden;
            margin-bottom: clamp(20px, 2.5vw, 30px);
            box-shadow: 0 10px 25px rgba(0, 40, 120, 0.25);
        }

        .hero-role {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            opacity: 0.84;
        }
        .hero-name {
            margin: 6px 0;
            font-size: clamp(26px, 2.1vw, 34px);
            line-height: 1.2;
        }
        .hero-desc {
            max-width: 720px;
            margin: 0;
            font-size: 14px;
            line-height: 1.55;
        }
        .hero-stats-label {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }
        .hero-stats-value {
            font-size: 28px;
            font-weight: 800;
            line-height: 1.15;
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
        
        .welcome-hero::before {
            content: '';
            position: absolute;
            bottom: -50px;
            right: 15%;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 70%);
            border-radius: 50%;
        }

        /* STATS GRID MODERN */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
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
            content: none;
        }

        /* Stat Colors */
        .stat-total .stat-bg-icon { color: #0038A8; }
        .stat-pending .stat-bg-icon { color: #f59e0b; }
        .stat-ongoing .stat-bg-icon { color: #3b82f6; }
        .stat-assets .stat-bg-icon { color: #10b981; }
        .stat-overdue .stat-bg-icon { color: #ef4444; }

        .stat-label {
            font-size: clamp(11px, 0.85vw, 12px);
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

        /* ANALYTICS GRID */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: clamp(15px, 2vw, 25px);
            margin-bottom: clamp(20px, 2.5vw, 30px);
        }

        .analytics-box {
            background: white;
            border-radius: 15px;
            padding: clamp(20px, 1.5vw, 25px);
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .analytics-title {
            font-size: 15px;
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .analytics-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
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
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .status-pending { background: #fffbeb; color: #92400e; border: 1px solid rgba(245, 158, 11, 0.2); }
        .status-ongoing { background: #eff6ff; color: #1e40af; border: 1px solid rgba(59, 130, 246, 0.2); }
        .status-completed { background: #ecfdf5; color: #065f46; border: 1px solid rgba(16, 185, 129, 0.2); }
        .status-scheduled { background: #fef3c7; color: #b45309; border: 1px solid rgba(245, 158, 11, 0.2); }

        /* ACTION CENTER WIDGET */
        .action-center-card {
            background: linear-gradient(to right bottom, #fff1f2, #ffe4e6);
            border-radius: 12px;
            border: 1px solid #fecdd3;
            padding: 16px;
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        .action-center-icon {
            background: white;
            color: #e11d48;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 2px 4px rgba(225, 29, 72, 0.1);
        }
        .action-center-content h4 {
            margin: 0 0 5px;
            font-size: 14px;
            font-weight: 800;
            color: #9f1239;
        }
        .action-center-content p {
            margin: 0;
            font-size: 13px;
            color: #be123c;
            line-height: 1.4;
        }

        .action-center-warning {
            background: linear-gradient(to right bottom, #fffbeb, #fef3c7);
            border-color: #fde68a;
        }
        .action-center-warning .action-center-icon {
            color: #d97706;
            box-shadow: 0 2px 4px rgba(217, 119, 6, 0.1);
        }
        .action-center-warning h4 { color: #92400e; }
        .action-center-warning p { color: #b45309; }
        .action-center-info { background: linear-gradient(to right bottom, #eff6ff, #dbeafe); border-color: #bfdbfe; }
        .action-center-info .action-center-icon { color: #1d4ed8; box-shadow: 0 2px 4px rgba(29, 78, 216, .1); }
        .action-center-info h4 { color: #1e40af; }
        .action-center-info p { color: #1d4ed8; }
        .action-center-danger { background: linear-gradient(to right bottom, #fef2f2, #fee2e2); border-color: #fecaca; }
        .action-center-danger .action-center-icon { color: #b91c1c; box-shadow: 0 2px 4px rgba(185, 28, 28, .1); }
        .action-center-danger h4 { color: #991b1b; }
        .action-center-danger p { color: #b91c1c; }

        /* LAYOUT & UTILITIES */
        @media (max-width: 1000px) {
            .admin-workspace-grid { grid-template-columns: 1fr !important; }
            .analytics-grid { grid-template-columns: 1fr !important; }
        }
        @media (max-width: 767px) {
            .flex-sb { flex-direction: column !important; align-items: flex-start !important; gap: 16px !important; }
            .welcome-hero { padding: 18px 16px !important; border-radius: 12px !important; }
            .hero-name { font-size: 22px !important; }
            .hero-desc { font-size: 14px !important; max-width: 100% !important; }
            .hero-stats-box { width: 100% !important; text-align: left !important; padding: 12px 16px !important; border-radius: 10px !important; }
            .hero-stats-value { font-size: 20px !important; }
            .hero-role { font-size: 11px !important; letter-spacing: 1px !important; }
            .stats-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 10px !important; }
            .stat-card-premium { padding: 14px 12px !important; border-radius: 12px !important; }
            .stat-value { font-size: 20px !important; }
            .stat-label { font-size: 10px !important; margin-bottom: 4px !important; }
            .premium-table-box { padding: 16px !important; border-radius: 12px !important; }
            .table-title { font-size: 14px !important; }
            .link-master { font-size: 11px !important; }
            .table-header th { padding: 8px !important; font-size: 10px !important; }
            .table-cell, .table-cell-bold, .table-cell-dept, .table-cell-gray, .table-cell-dark { padding: 10px 8px !important; font-size: 12px !important; }

            /* ═══ Recent Office Requests — DELIBERATELY NOT compressed ═══
               Keep natural column widths & breathing room so it reads well;
               the .scroll-x wrapper handles overflow instead of squishing. */
            .premium-table-box .table-full {
                min-width: 700px !important;
            }
            .premium-table-box .table-header th {
                padding: 12px 10px !important;
                font-size: 12px !important;
                white-space: nowrap;
            }
            .premium-table-box .table-cell,
            .premium-table-box .table-cell-bold,
            .premium-table-box .table-cell-dept,
            .premium-table-box .table-cell-gray,
            .premium-table-box .table-cell-dark {
                padding: 14px 10px !important;
                font-size: 13px !important;
                white-space: nowrap;
            }
            .ribbon-label { font-size: 10px !important; margin-bottom: 12px !important; }
            .analytics-box { border-radius: 12px !important; padding: 16px !important; }

            /* ═══ Charts — decompressed on mobile ═══
               Taller canvas so office labels + doughnut legend breathe;
               Chart.js is responsive so it reflows to the new height. */
            .analytics-box .chart-box-bar,
            .analytics-box .chart-box-doughnut {
                height: 360px !important;
            }
        }
        .tr-hover-row { transition: all 0.2s; position: relative; }
        .tr-hover-row:hover { background: #f8fafc !important; transform: scale(1.002); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .tr-hover-row:hover td:first-child { box-shadow: inset 4px 0 0 #0f172a; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }
        .admin-workspace-grid { display: grid; gap: 25px; grid-template-columns: 2fr 1fr; }
        .flex-sb { display: flex; justify-content: space-between; align-items: center; }
        .text-muted { font-size: 13px; color: #64748b; font-weight: 600; }
        .text-bold-dark { font-weight: 800; color: #1e293b; }
        .icon-blue { color: #0038A8; }
        .table-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 1px; }
        .link-master { text-decoration: none; color: #0038A8; font-weight: 700; font-size: 14px; }
        .scroll-x { overflow-x: auto; }
        .table-full { width: 100%; border-collapse: collapse; }
        .tr-header-bottom { text-align: left; border-bottom: 2px solid #f1f5f9; }
        .table-row-border { border-bottom: 1px solid #f8fafc; transition: background 0.2s; }
        .link-inherit { text-decoration: none; color: inherit; }
        .mb-25 { margin-bottom: 25px; }
        .empty-cell { padding: 40px; text-align: center; color: #94a3b8; }
        .table-header th { padding: 12px 10px; font-size: 12px; letter-spacing: 0.4px; }
        .table-cell, .table-cell-bold, .table-cell-dept, .table-cell-gray, .table-cell-dark, .table-cell-center { padding: 13px 10px; font-size: 13px; }
        .progress-bar-bg { background: #e2e8f0; height: 6px; border-radius: 3px; width: 100%; margin-top: 6px; overflow: hidden; }
        .progress-bar-fill { background: #0038A8; height: 100%; border-radius: 3px; }
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
                    Managing ICT and Maintenance operations for the <strong>National Conciliation and Mediation Board</strong>.
                </p>
            </div>
            <div class="hero-stats-box">
                <div class="hero-stats-label">Total Users</div>
                <div class="hero-stats-value">{{ $stats['total_users'] }}</div>
            </div>
        </div>
    </div>

    <!-- STATS GRID - CMMS FOCUSED -->
    <div class="stats-grid">
        <div class="stat-card-premium stat-total">
            <i class="fa-solid fa-layer-group stat-bg-icon"></i>
            <span class="stat-label">Total User Requests</span>
            <div class="stat-value">{{ $stats['total'] }}</div>
        </div>
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
        <div class="stat-card-premium stat-assets">
            <i class="fa-solid fa-server stat-bg-icon"></i>
            <span class="stat-label">Active Assets</span>
            <div class="stat-value">{{ $stats['total_assets'] }}</div>
            @if(($assetBreakdown['under_repair'] ?? 0) > 0)
                <div style="font-size:10px; color:#c2410c; font-weight:700; margin-top:4px;">
                    <i class="fa-solid fa-wrench" style="margin-right:3px;"></i>{{ $assetBreakdown['under_repair'] }} under repair
                </div>
            @endif
        </div>
        <div class="stat-card-premium stat-overdue {{ $stats['overdue_pms'] > 0 ? 'stat-overdue-alert' : '' }}">
            <i class="fa-solid fa-clock stat-bg-icon"></i>
            <span class="stat-label">Overdue PMs</span>
            <div class="stat-value">{{ $stats['overdue_pms'] }}</div>
        </div>
    </div>

    <!-- WORKSPACE GRID -->
    <div class="admin-workspace-grid">
        
        <!-- LEFT: ANALYTICS AND TABLE -->
        <div>
            <!-- ANALYTICS GRID -->
            <div class="analytics-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
                <div class="analytics-box" style="padding: 24px 26px;">
                    <div class="analytics-title" style="margin-bottom: 4px;">
                        <i class="fa-solid fa-chart-bar icon-blue"></i>
                        Request Volume by Office
                        <span style="margin-left: auto; font-size: 10px; color: #94a3b8; font-weight: 600; letter-spacing: 0; text-transform: none;">ICT &amp; Repair Only</span>
                    </div>
                    <p style="font-size: 12px; color: #64748b; margin: 0 0 16px 0;">Top offices by request volume.</p>
                    <div class="chart-box-bar" style="height: 280px; width: 100%; position: relative;">
                        <canvas id="officeChart"></canvas>
                    </div>
                </div>

                <div class="analytics-box" style="padding: 24px 26px;">
                    <div class="analytics-title" style="margin-bottom: 4px;">
                        <i class="fa-solid fa-chart-pie icon-blue"></i>
                        Asset Status Overview
                    </div>
                    <p style="font-size: 12px; color: #64748b; margin: 0 0 16px 0;">Real inventory status &mdash; only <strong>Active</strong> counts as active.</p>
                    <div class="chart-box-doughnut" style="height: 280px; width: 100%; position: relative; display: flex; justify-content: center;">
                        <canvas id="workloadChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- SYSTEM WIDE TABLE -->
            <div class="premium-table-box">
                <div class="flex-sb mb-25">
                    <h3 class="table-title">Recent Office Requests</h3>
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
                                <tr><td colspan="5" class="empty-cell">
                                    <i class="fa-solid fa-inbox" style="font-size: 42px; color: #e2e8f0; margin-bottom: 15px; display: block;"></i>
                                    <div style="font-weight: 800; color: #64748b; font-size: 15px;">No User Requests</div>
                                    <div style="font-size: 12px; color: #94a3b8; margin-top: 5px;">No ICT or Repair requests have been submitted across the offices yet.</div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT: MANAGEMENT TOOLS -->
        <div>
            <div style="background: white; border-radius: 15px; padding: 20px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <h3 class="table-title" style="margin-bottom: 15px; font-size: 12px; color:#64748b;">Management Tools</h3>
                
                <a href="{{ route('ict.index') }}" style="display:flex; align-items:center; gap:12px; padding:12px; border-radius:8px; text-decoration:none; color:#1e293b; transition:all 0.2s; border:1px solid transparent;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#e2e8f0';" onmouseout="this.style.background='transparent'; this.style.borderColor='transparent';">
                    <div style="background:#eff6ff; color:#3b82f6; width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-clipboard-list"></i></div>
                    <div style="flex:1;"><div style="font-size:13px; font-weight:700;">Master List</div><div style="font-size:10px; color:#64748b;">All system requests</div></div>
                </a>

                <a href="{{ route('super_admin.users') }}" style="display:flex; align-items:center; gap:12px; padding:12px; border-radius:8px; text-decoration:none; color:#1e293b; transition:all 0.2s; border:1px solid transparent;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#e2e8f0';" onmouseout="this.style.background='transparent'; this.style.borderColor='transparent';">
                    <div style="background:#eff6ff; color:#3b82f6; width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-users-gear"></i></div>
                    <div style="flex:1;"><div style="font-size:13px; font-weight:700;">Manage Users</div><div style="font-size:10px; color:#64748b;">System access control</div></div>
                </a>

                <a href="{{ route('pm-schedules.index') }}" style="display:flex; align-items:center; gap:12px; padding:12px; border-radius:8px; text-decoration:none; color:#1e293b; transition:all 0.2s; border:1px solid transparent;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#e2e8f0';" onmouseout="this.style.background='transparent'; this.style.borderColor='transparent';">
                    <div style="background:#eff6ff; color:#3b82f6; width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-calendar-check"></i></div>
                    <div style="flex:1;"><div style="font-size:13px; font-weight:700;">PM Schedules</div><div style="font-size:10px; color:#64748b;">Preventive maintenance</div></div>
                </a>

                <a href="{{ route('pm-schedules.calendar') }}" style="display:flex; align-items:center; gap:12px; padding:12px; border-radius:8px; text-decoration:none; color:#1e293b; transition:all 0.2s; border:1px solid transparent;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#e2e8f0';" onmouseout="this.style.background='transparent'; this.style.borderColor='transparent';">
                    <div style="background:#eff6ff; color:#3b82f6; width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center;"><i class="fa-solid fa-calendar-days"></i></div>
                    <div style="flex:1;"><div style="font-size:13px; font-weight:700;">Maintenance Calendar</div><div style="font-size:10px; color:#64748b;">View schedules timeline</div></div>
                </a>
            </div>

            <!-- CSM Snapshot (Phase 1) -->
            <div style="background: white; border-radius: 15px; padding: 20px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-top: 20px;">
                <h3 class="table-title" style="margin-bottom: 15px; font-size: 12px; font-weight: 800; color:#64748b; text-transform:uppercase;">Service Quality (CSM)</h3>
                <div style="display:flex; align-items:center; gap:15px;">
                    <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; display:flex; align-items:center; justify-content:center; font-size: 18px; font-weight: 800; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);">
                        {{ $csmAverage }}
                    </div>
                    <div style="flex:1;">
                        <div style="display:flex; color:#f59e0b; font-size:12px; margin-bottom:4px; gap: 2px;">
                            @for($i=1; $i<=5; $i++)
                                <i class="fa-solid fa-star" style="{{ $i <= round($csmAverage) ? '' : 'color:#e2e8f0;' }}"></i>
                            @endfor
                        </div>
                        <div style="font-size:13px; font-weight:700; color:#1e293b;">Overall Satisfaction</div>
                        <div style="font-size:12px; color:#64748b;">{{ $csmResponses }}/{{ $completedIctCount }} completed ICT tickets responded ({{ $csmResponseRate }}%)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js" nonce="{{ $cspNonce }}"></script>
<script nonce="{{ $cspNonce }}">
    document.addEventListener('DOMContentLoaded', function() {
        // ─── Prepare Data for Bar Chart ──────────────────────────────────────────
        const departmentData = @json($departmentStats);
        const rawLabels = Object.keys(departmentData);
        const data      = Object.values(departmentData);
        const officeTotal = data.reduce((a, b) => a + b, 0);

        // ─── Horizontal Bar Chart — Office Request Volume ─────────────────────────
        // Horizontal axis is used so long office names fit without truncation.
        const ctxOffice = document.getElementById('officeChart');
        if (ctxOffice) {
            // Sort: highest requests first
            const sorted = rawLabels.map((l, i) => ({ label: l || 'Unassigned', val: data[i] }))
                                    .sort((a, b) => b.val - a.val);
            const sortedLabels = sorted.map(x => x.label);
            const sortedData   = sorted.map(x => x.val);
            const maxVal       = Math.max(...sortedData, 1);

            // Top office highlighted in deep blue; the rest in light blue for scanning
            const barColors = sortedData.map((_, i) =>
                i === 0 ? 'rgba(0, 56, 168, 1)' : 'rgba(147, 197, 253, 0.75)'
            );

            new Chart(ctxOffice, {
                type: 'bar',
                data: {
                    labels: sortedLabels,
                    datasets: [{
                        label: 'No. of Requests',
                        data: sortedData,
                        backgroundColor: barColors,
                        hoverBackgroundColor: barColors.map(c => c.replace(/[\d.]+\)$/, '1)')),
                        borderRadius: 5,
                        borderSkipped: false,
                        maxBarThickness: 26,   // thin horizontal bars
                        barPercentage: 0.72,
                        categoryPercentage: 0.85
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 10, right: 24, bottom: 5, left: 5 } },
                    animation: { duration: 800, easing: 'easeOutQuart' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#94a3b8',
                            bodyColor: '#ffffff',
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                title: ctx => ctx[0].label,
                                label: ctx => {
                                    const pct = officeTotal > 0 ? ((ctx.parsed.x / officeTotal) * 100).toFixed(1) : 0;
                                    return ` ${ctx.parsed.x} request${ctx.parsed.x !== 1 ? 's' : ''} (${pct}%)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: maxVal + Math.ceil(maxVal * 0.15),
                            grid: { color: '#f1f5f9', drawTicks: false },
                            border: { dash: [4, 4], color: 'transparent' },
                            ticks: {
                                stepSize: 1,
                                precision: 0,
                                font: { size: 12, family: 'Arial, Helvetica, sans-serif' },
                                color: '#94a3b8',
                                padding: 6
                            }
                        },
                        y: {
                            grid: { display: false },
                            border: { color: '#e2e8f0' },
                            ticks: {
                                font: { size: 12, weight: '600', family: 'Arial, Helvetica, sans-serif' },
                                color: '#334155'
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'barValueLabels',
                    afterDatasetsDraw(chart) {
                        const { ctx } = chart;
                        chart.data.datasets.forEach((dataset, i) => {
                            const meta = chart.getDatasetMeta(i);
                            meta.data.forEach((bar, index) => {
                                const value = dataset.data[index];
                                if (value === 0) return;
                                ctx.save();
                                ctx.fillStyle = '#0038A8';
                                ctx.font = 'bold 12px Arial, Helvetica, sans-serif';
                                ctx.textAlign = 'left';
                                ctx.textBaseline = 'middle';
                                ctx.fillText(value, bar.x + 6, bar.y);
                                ctx.restore();
                            });
                        });
                    }
                }]
            });

            // Empty state: friendly message instead of a blank chart
            if (officeTotal === 0) {
                ctxOffice.style.display = 'none';
                const holder = ctxOffice.parentElement;
                const empty = document.createElement('div');
                empty.style.cssText = 'position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:#94a3b8;font-size:13px;font-weight:600;text-align:center;';
                empty.innerHTML = '<i class="fa-solid fa-chart-column" style="font-size:32px;color:#e2e8f0;"></i><div>No requests recorded yet.</div><div style="font-size:11px;font-weight:500;">Request volume by office will appear here.</div>';
                holder.appendChild(empty);
            }
        }

        // ─── Doughnut Chart — Asset Status Overview (real inventory statuses) ────
        const assetBreakdown = @json($assetBreakdown);
        const doughnutValues = [
            assetBreakdown.active,
            assetBreakdown.spare,
            assetBreakdown.under_repair,
            assetBreakdown.for_disposal
        ];
        const doughnutTotal = assetBreakdown.total || 0;

        const ctxWorkload = document.getElementById('workloadChart');
        if (ctxWorkload) {
            const centerText = {
                id: 'doughnutCenterText',
                afterDraw(chart, args, opts) {
                    const { ctx } = chart;
                    const meta = chart.getDatasetMeta(0);
                    if (!meta.data.length) return;
                    const first = meta.data[0];
                    const x = first.x, y = first.y;
                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillStyle = '#1e293b';
                    ctx.font = 'bold 24px Arial, Helvetica, sans-serif';
                    ctx.fillText(doughnutTotal, x, y - 6);
                    ctx.fillStyle = '#94a3b8';
                    ctx.font = '600 10px Arial, Helvetica, sans-serif';
                    ctx.fillText('TOTAL ASSETS', x, y + 16);
                    ctx.restore();
                }
            };
            new Chart(ctxWorkload, {
                type: 'doughnut',
                data: {
                    labels: ['Active', 'Spare', 'Under Repair', 'For Disposal'],
                    datasets: [{
                        data: doughnutValues,
                        backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6'],
                        borderWidth: 3,
                        borderColor: '#ffffff',
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 8,
                                padding: 14,
                                font: { size: 12, family: 'Arial, Helvetica, sans-serif', weight: '700' },
                                color: '#475569',
                                generateLabels(chart) {
                                    const cd = chart.data;
                                    const bg = cd.datasets[0];
                                    return cd.labels.map((label, i) => {
                                        const val = bg.data[i];
                                        const pct = doughnutTotal > 0 ? Math.round((val / doughnutTotal) * 100) : 0;
                                        return {
                                            text: `${label} · ${val} · ${pct}%`,
                                            fillStyle: bg.backgroundColor[i],
                                            strokeStyle: bg.backgroundColor[i],
                                            hidden: !chart.getDataVisibility(i),
                                            index: i,
                                            pointStyle: 'circle'
                                        };
                                    });
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#94a3b8',
                            bodyColor: '#ffffff',
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: ctx => {
                                    const pct = doughnutTotal > 0 ? ((ctx.parsed / doughnutTotal) * 100).toFixed(1) : 0;
                                    return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                                }
                            }
                        }
                    }
                },
                plugins: [centerText]
            });

            // Zero-state: show "No Data" instead of a broken ring
            if (doughnutTotal === 0) {
                ctxWorkload.style.display = 'none';
                const holder = ctxWorkload.parentElement;
                const empty = document.createElement('div');
                empty.style.cssText = 'position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:#94a3b8;font-size:13px;font-weight:600;text-align:center;';
                empty.innerHTML = '<i class="fa-solid fa-box-open" style="font-size:32px;color:#e2e8f0;"></i><div>No inventory assets recorded yet.</div><div style="font-size:11px;font-weight:500;">Asset status will appear here.</div>';
                holder.appendChild(empty);
            }
        }

    });
</script>
@endsection
