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
            /* D9.9: 6 cards sa ISANG row sa desktop — hindi na bumababa ang
               CSM Satisfaction sa ikalawang row. minmax(0,1fr) = pantay na
               hati, walang overflow. */
            grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
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
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
        @media screen and (max-width: 1000px) {
            .admin-workspace-grid { grid-template-columns: 1fr !important; }
            .analytics-grid { grid-template-columns: 1fr !important; }
            /* D9.9: sa mas maliit na screen, 3 columns x 2 rows ang stats —
               readable pa rin, hindi siksik */
            .stats-grid { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
        }
        @media screen and (max-width: 767px) {
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
            .analytics-grid {
                min-width: 0 !important;
            }
            .analytics-box {
                min-width: 0 !important;
                overflow: hidden !important;
            }
            .analytics-box .chart-box-bar,
            .analytics-box .chart-box-doughnut {
                height: 360px !important;
                max-width: 100% !important;
                overflow: hidden !important;
            }
            .analytics-box canvas {
                max-width: 100% !important;
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

        /* D9 polish: latest-month value chip sa trend card headers */
        .kpi-trend-chip {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.3px;
            padding: 3px 10px;
            border-radius: 12px;
            text-transform: none;
            white-space: nowrap;
        }

        /* D9: responsive caps - 4 analytics boxes lock to 2 columns (no 3+1 wrap) */
        .analytics-gov-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        /* D9: KPI cards - 2 columns desktop, stack on mobile */
        .kpi-card-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        @media screen and (max-width: 767px) {
            .kpi-card-grid { grid-template-columns: 1fr !important; }
            /* D9 polish: trend charts STACK full-width sa phones — ang 2-col lock
               ay para sa desktop lang; sa 375px screen ang ~160px na chart columns
               ay hindi mababasa. */
            .analytics-gov-grid { grid-template-columns: 1fr !important; }
            /* Trend line charts: 260px ang sapat kapag full-width (ang generic
               360px rule ay para sa bar chart na may mahahabang office labels). */
            .analytics-box .chart-box-trend { height: 260px !important; }
        }

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
        </div>
        <div class="stat-card-premium stat-overdue {{ $stats['overdue_tickets'] > 0 ? 'stat-overdue-alert' : '' }}">
            <i class="fa-solid fa-clock stat-bg-icon"></i>
            <span class="stat-label">Overdue Tickets</span>
            <div class="stat-value">{{ $stats['overdue_tickets'] }}</div>
            @if(($stats['overdue_pms'] ?? 0) > 0)
                <div style="font-size:10px; color:#b45309; font-weight:700; margin-top:4px;">
                    <i class="fa-solid fa-calendar-check" style="margin-right:3px;"></i>{{ $stats['overdue_pms'] }} PM overdue
                </div>
            @endif
        </div>
        <div class="stat-card-premium stat-assets">
            <i class="fa-solid fa-face-smile stat-bg-icon"></i>
            <span class="stat-label">CSM Satisfaction</span>
            <div class="stat-value">
                @if($csmAverage > 0){{ number_format($csmAverage, 1) }}<span style="font-size: 12px; font-weight: 700; color: #64748b;">/5.0</span>@else <span style="color: #94a3b8;">&mdash;</span> @endif
            </div>
            <div style="font-size: 10px; font-weight: 700; color: #64748b; margin-top: 4px;">{{ $csmResponses }}/{{ $completedIctCount }} completed ICT responded &middot; {{ $csmResponseRate }}%</div>
        </div>
    </div>

    <!-- D9: MAINTENANCE KPI - Monthly (Avg. Downtime / Days Between Failures) -->
    <div class="analytics-box" style="padding: 20px 24px; margin-bottom: 18px;">
        <div class="analytics-title" style="margin-bottom: 4px;">
            <i class="fa-solid fa-chart-simple icon-blue"></i>
            Maintenance KPI
            <form method="GET" action="{{ route("dashboard.super-admin") }}" style="margin-left: auto;">
                <select name="kpi_month" onchange="this.form.submit()" style="padding: 4px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 12px; font-weight: 700; color: #1e293b; background: white;">
                    @foreach($kpi["months"] as $key => $label)
                        <option value="{{ $key }}" @if($key === $kpi["selected"]) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <p style="font-size: 12px; color: #64748b; margin: 0 0 14px 0;">How long a failed asset stays down, and how often breakdowns occur.</p>
        <div class="kpi-card-grid">
            <div class="stat-card-premium stat-total">
                <i class="fa-regular fa-clock stat-bg-icon"></i>
                <span class="stat-label">Avg. Downtime</span>
                <div class="stat-value">
                    @if($kpi["mttr_days"] !== null){{ number_format($kpi["mttr_days"], 1) }}<span style="font-size: 12px; font-weight: 700; color: #64748b;"> days</span>@else <span style="color: #94a3b8;">&mdash;</span> @endif
                </div>
                <div style="font-size: 11px; font-weight: 700; color: #64748b; margin-top: 2px;">Mean time to repair (MTTR) &mdash; lower is better</div>
                @if($kpi["mttr_days"] !== null && $kpi["mttr_prev"] !== null && $kpi["mttr_prev"] > 0)
                    @php $mttrDiff = round($kpi["mttr_prev"] - $kpi["mttr_days"], 1); @endphp
                    @if($mttrDiff > 0)
                        <div style="font-size: 11px; font-weight: 700; color: #047857; margin-top: 4px;">&#9660; {{ abs($mttrDiff) }} days faster than last month</div>
                    @elseif($mttrDiff < 0)
                        <div style="font-size: 11px; font-weight: 700; color: #b91c1c; margin-top: 4px;">&#9650; {{ abs($mttrDiff) }} days slower than last month</div>
                    @else
                        <div style="font-size: 11px; font-weight: 700; color: #64748b; margin-top: 4px;">No change from last month</div>
                    @endif
                @endif
            </div>
            <div class="stat-card-premium stat-assets">
                <i class="fa-solid fa-infinity stat-bg-icon"></i>
                <span class="stat-label">Days Between Failures</span>
                <div class="stat-value">
                    @if($kpi["mtbf_days"] !== null){{ number_format($kpi["mtbf_days"], 1) }}<span style="font-size: 12px; font-weight: 700; color: #64748b;"> days</span>@else <span style="color: #10b981;">No failures this month</span> @endif
                </div>
                <div style="font-size: 11px; font-weight: 700; color: #64748b; margin-top: 2px;">Mean time between failures (MTBF) &mdash; higher is better</div>
                @if($kpi["mtbf_days"] !== null && $kpi["mtbf_prev"] !== null && $kpi["mtbf_prev"] > 0)
                    @php $mtbfDiff = round($kpi["mtbf_days"] - $kpi["mtbf_prev"], 1); @endphp
                    @if($mtbfDiff > 0)
                        <div style="font-size: 11px; font-weight: 700; color: #047857; margin-top: 4px;">&#9650; {{ abs($mtbfDiff) }} days longer between breakdowns (improved)</div>
                    @elseif($mtbfDiff < 0)
                        <div style="font-size: 11px; font-weight: 700; color: #b91c1c; margin-top: 4px;">&#9660; {{ abs($mtbfDiff) }} days shorter (more frequent breakdowns)</div>
                    @else
                        <div style="font-size: 11px; font-weight: 700; color: #64748b; margin-top: 4px;">No change from last month</div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <!-- WORKSPACE GRID -->
    <div class="admin-workspace-grid">
        
        <!-- LEFT: ANALYTICS AND TABLE -->
        <div>
            <!-- ANALYTICS GRID -->
            <div class="analytics-grid analytics-gov-grid">
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
                            <div class="analytics-box" style="padding: 24px 26px;">
                    <div class="analytics-title" style="margin-bottom: 4px; flex-wrap: nowrap;">
                        <i class="fa-solid fa-arrow-trend-up icon-blue"></i>
                        <span style="white-space: nowrap;">MTTR</span>
                        <span style="font-size: 10px; color: #94a3b8; font-weight: 600; letter-spacing: 0; text-transform: none; flex-shrink: 0; white-space: nowrap;">6 months</span>
                        <span class="kpi-trend-chip" id="mttrLatestChip" style="display: none; margin-left: auto; flex-shrink: 0; white-space: nowrap;"></span>
                    </div>
                    <p style="font-size: 12px; color: #64748b; margin: 0 0 16px 0;">Average downtime each month, in days.</p>
                    <div class="chart-box-bar chart-box-trend" style="height: 200px; width: 100%; position: relative;">
                        <canvas id="mttrChart"></canvas>
                    </div>
                </div>

                <div class="analytics-box" style="padding: 24px 26px;">
                    <div class="analytics-title" style="margin-bottom: 4px; flex-wrap: nowrap;">
                        <i class="fa-solid fa-arrow-trend-down icon-blue"></i>
                        <span style="white-space: nowrap;">MTBF</span>
                        <span style="font-size: 10px; color: #94a3b8; font-weight: 600; letter-spacing: 0; text-transform: none; flex-shrink: 0; white-space: nowrap;">6 months</span>
                        <span class="kpi-trend-chip" id="mtbfLatestChip" style="display: none; margin-left: auto; flex-shrink: 0; white-space: nowrap;"></span>
                    </div>
                    <p style="font-size: 12px; color: #64748b; margin: 0 0 16px 0;">Days between breakdowns (hollow = no breakdowns that month).</p>
                    <div class="chart-box-bar chart-box-trend" style="height: 200px; width: 100%; position: relative;">
                        <canvas id="mtbfChart"></canvas>
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

    // D9: 6-month trends (null = gap; hollow marker = censored no-breakdown month)
    const kpiTrend = @json($kpi["trend"]);
    const kpiTooltipBase = { backgroundColor: "#0f172a", titleColor: "#94a3b8", bodyColor: "#ffffff", padding: 12, cornerRadius: 8, displayColors: false };
    const kpiTrendScales = {
        y: {
            beginAtZero: true,
            /* grace: binibigyan ng headroom ang tuktok — hindi na dumidikit ang
               line sa pinakamataas na gridline kapag buwanang value ang max. */
            grace: "25%",
            ticks: {
                color: "#94a3b8",
                font: { size: 10, weight: "600" },
                callback: v => v + "d",
                maxTicksLimit: 6
            },
            grid: { color: "#e2e8f0", borderDash: [4, 4], drawTicks: false },
            border: { display: false }
        },
        x: {
            ticks: { color: "#64748b", font: { size: 10, weight: "600" }, maxRotation: 0, autoSkip: true, maxTicksLimit: 6 },
            grid: { display: false },
            border: { color: "#e2e8f0" }
        }
    };
    const kpiInteraction = { mode: "index", intersect: false };
    // D9.8: hover crosshair — patayong dashed guide line sa aktwal na buwan
    // na hinihover, para madaling i-align ang tooltip sa trend (shared ng
    // MTTR at MTBF charts).
    const kpiHoverLine = {
        id: "kpiHoverLine",
        afterDatasetsDraw(chart) {
            const active = chart.tooltip ? chart.tooltip.getActiveElements() : [];
            if (!active || !active.length) return;
            const { ctx, chartArea } = chart;
            const x = active[0].element.x;
            ctx.save();
            ctx.beginPath();
            ctx.setLineDash([4, 4]);
            ctx.strokeStyle = "rgba(15, 23, 42, 0.25)";
            ctx.lineWidth = 1;
            ctx.moveTo(x, chartArea.top);
            ctx.lineTo(x, chartArea.bottom);
            ctx.stroke();
            ctx.restore();
        }
    };
    // D9.8b: BASELINE — dashed horizontal line sa mean ng mga VALID na buwan
    // (MTTR: buwang may breakdown; MTBF: hindi censored — dahil ang censored
    // value ay ">= X days" lower bound, bias ang pagkakasama). Instant na
    // "mas maganda/mas masahol kaysa sa average" na reference. Walang valid
    // na buwan = walang baseline (walang ipapakitang maling reference).
    const kpiMeanOf = (series, excludeCensored) => {
        const vals = [];
        (series || []).forEach((v, i) => {
            if (v === null || v === undefined) return;
            if (excludeCensored && kpiTrend.censored[i]) return;
            vals.push(v);
        });
        return vals.length ? vals.reduce((a, b) => a + b, 0) / vals.length : null;
    };
    const kpiMttrBaseline = kpiMeanOf(kpiTrend.mttr, false);
    const kpiMtbfBaseline = kpiMeanOf(kpiTrend.mtbf, true);
    const kpiBaselinePlugin = (baseline, color) => ({
        id: "kpiBaselineLine",
        afterDatasetsDraw(chart) {
            if (baseline === null || baseline === undefined) return;
            const yScale = chart.scales.y;
            if (baseline < yScale.min || baseline > yScale.max) return;
            const { ctx, chartArea } = chart;
            const y = yScale.getPixelForValue(baseline);
            ctx.save();
            ctx.beginPath();
            ctx.setLineDash([6, 4]);
            ctx.strokeStyle = color;
            ctx.lineWidth = 1.5;
            ctx.moveTo(chartArea.left, y);
            ctx.lineTo(chartArea.right, y);
            ctx.stroke();
            ctx.fillStyle = color;
            ctx.font = "600 10px Arial, Helvetica, sans-serif";
            ctx.textAlign = "right";
            ctx.textBaseline = "bottom";
            ctx.fillText("avg " + baseline.toFixed(1) + "d", chartArea.right - 4, y - 3);
            ctx.restore();
        }
    });
    // Fix 3: kapag BUMABA ang MTBF kumpara sa nakaraang buwan (mas madalas na
    // breakdown = negative outcome), ang line graph at chip ay magiging RED —
    // dati laging green sila kahit nag-c-crash ang MTBF (hal. 30+ → 2.3 days).
    const kpiMtbfWorsened = @json($kpi["mtbf_days"] !== null && $kpi["mtbf_prev"] !== null && $kpi["mtbf_prev"] > 0 && $kpi["mtbf_days"] < $kpi["mtbf_prev"]);
    const kpiMtbfColor = kpiMtbfWorsened ? "#dc2626" : "#10b981";
    const kpiMtbfDark = kpiMtbfWorsened ? "#b91c1c" : "#059669";
    const kpiMtbfSoft = kpiMtbfWorsened ? "rgba(220, 38, 38, 0.08)" : "rgba(16, 185, 129, 0.08)";
    const kpiMtbfGradTop = kpiMtbfWorsened ? "rgba(220, 38, 38, 0.20)" : "rgba(16, 185, 129, 0.20)";
    const ctxMttr = document.getElementById("mttrChart");
    if (ctxMttr) {
        new Chart(ctxMttr, {
            type: "line",
            data: {
                labels: kpiTrend.months,
                datasets: [{
                    label: "Avg. downtime",
                    data: kpiTrend.mttr,
                    borderColor: "#0038A8",
                    borderWidth: 3,
                    borderCapStyle: "round",
                    backgroundColor: (ctx) => {
                        const { chartArea, ctx: c } = ctx.chart;
                        if (!chartArea) return "rgba(0, 56, 168, 0.08)";
                        const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        g.addColorStop(0, "rgba(0, 56, 168, 0.20)");
                        g.addColorStop(1, "rgba(0, 56, 168, 0)");
                        return g;
                    },
                    fill: true,
                    tension: 0,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: "#0038A8",
                    pointBorderColor: "#ffffff",
                    pointBorderWidth: 2,
                    pointHoverBorderWidth: 3,
                    pointHoverBorderColor: "#ffffff",
                    pointStyle: "circle",
                    borderJoinStyle: "round",
                    spanGaps: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 700, easing: "easeOutQuart" },
                interaction: kpiInteraction,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...kpiTooltipBase,
                        callbacks: {
                            title: items => items[0]?.label ?? "",
                            label: ctx => {
                                const i = ctx.dataIndex;
                                if (kpiTrend.mttr[i] === null || kpiTrend.mttr[i] === undefined) {
                                    return " No breakdowns recorded this month";
                                }
                                return " Average downtime: " + ctx.parsed.y + " days";
                            }
                        }
                    }
                },
                scales: {
                    ...kpiTrendScales,
                    y: { ...kpiTrendScales.y, suggestedMax: kpiMttrBaseline !== null ? kpiMttrBaseline : undefined }
                }
            },
            plugins: [kpiHoverLine, kpiBaselinePlugin(kpiMttrBaseline, "rgba(0, 56, 168, 0.55)")]
        });
    }
    const ctxMtbf = document.getElementById("mtbfChart");
    if (ctxMtbf) {
        new Chart(ctxMtbf, {
            type: "line",
            data: {
                labels: kpiTrend.months,
                datasets: [{
                    label: "Days between failures",
                    data: kpiTrend.mtbf,
                    borderColor: kpiMtbfColor,
                    borderWidth: 3,
                    borderCapStyle: "round",
                    backgroundColor: (ctx) => {
                        const { chartArea, ctx: c } = ctx.chart;
                        if (!chartArea) return kpiMtbfSoft;
                        const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        g.addColorStop(0, kpiMtbfGradTop);
                        g.addColorStop(1, "rgba(0, 0, 0, 0)");
                        return g;
                    },
                    fill: true,
                    tension: 0,
                    spanGaps: false,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: kpiTrend.censored.map(c => c ? "rgba(0,0,0,0)" : kpiMtbfColor),
                    pointHoverBackgroundColor: kpiTrend.censored.map(c => c ? "rgba(0,0,0,0)" : kpiMtbfDark),
                    pointBorderColor: kpiTrend.censored.map(c => c ? kpiMtbfColor : "#ffffff"),
                    pointBorderWidth: kpiTrend.censored.map(c => c ? 2 : 2),
                    pointHoverBorderWidth: 3,
                    pointHoverBorderColor: "#ffffff",
                    pointStyle: "circle",
                    borderJoinStyle: "round",
                    // D9.8: DASHED segment papunta/paglabang censored month —
                    // tama lang, dahil ">= X days" ang value (lower bound),
                    // hindi eksaktong bilang. Solid ang normal na segments.
                    segment: {
                        borderDash: ctx => (kpiTrend.censored[ctx.p0DataIndex] || kpiTrend.censored[ctx.p1DataIndex]) ? [5, 5] : undefined
                    }
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 700, easing: "easeOutQuart" },
                interaction: kpiInteraction,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...kpiTooltipBase,
                        callbacks: {
                            title: items => items[0]?.label ?? "",
                            label: ctx => {
                                const i = ctx.dataIndex;
                                if (i !== undefined && kpiTrend.censored[i]) {
                                    return " No breakdowns (>= " + ctx.parsed.y + " days between failures)";
                                }
                                return " " + ctx.parsed.y + " days between breakdowns";
                            }
                        }
                    }
                },
                scales: {
                    ...kpiTrendScales,
                    y: { ...kpiTrendScales.y, suggestedMax: kpiMtbfBaseline !== null ? kpiMtbfBaseline : undefined }
                }
            },
            plugins: [kpiHoverLine, kpiBaselinePlugin(kpiMtbfBaseline, kpiMtbfWorsened ? "rgba(220, 38, 38, 0.55)" : "rgba(16, 185, 129, 0.55)")]
        });
    }

    // D9: empty-state overlays — PER-CHART: kapag puro null ang sariling series
    // ng isang chart (hal. walang breakdown buong 6-month window), magpakita ng
    // malinaw na "No breakdowns recorded" imbes na blangkong canvas. Dati
    // all-or-nothing ito — kung may MTTR data pero wala ang MTBF, nagiging
    // blangkong canvas ang MTBF na parang nabali ang chart.
    (function mountKpiEmptyStates() {
        const hasBreakdown = (arr) => (arr || []).some(v => v !== null && v !== undefined);
        [["mttrChart", "downtime trend", kpiTrend.mttr], ["mtbfChart", "failure trend", kpiTrend.mtbf]].forEach(([id, name, series]) => {
            if (hasBreakdown(series)) return; // may data — walang kailangan
            const cv = document.getElementById(id);
            if (!cv) return;
            cv.style.display = "none";
            const holder = cv.parentElement;
            holder.style.position = "relative";
            const empty = document.createElement("div");
            empty.style.cssText = "position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:#94a3b8;font-size:13px;font-weight:600;text-align:center;";
            empty.innerHTML = '<i class="fa-solid fa-shield-heart" style="font-size:32px;color:#e2e8f0;"></i><div>No breakdowns in the last 6 months.</div><div style="font-size:11px;font-weight:500;">The ' + name + ' will appear here once failures are recorded.</div>';
            holder.appendChild(empty);
        });
    })();

    // D9 polish: latest-month value chip sa header ng bawat trend card.
    // Kunin ang pinakahuling buwan na may value (huling non-null); kung puro
    // null ang series, nananatiling nakatago ang chip (ang empty-state overlay
    // na ang bahala sa blangkong canvas).
    (function mountKpiChips() {
        const fmt = (v) => (v === null || v === undefined) ? null : (Number.isInteger(v) ? v + "d" : Number(v).toFixed(1) + "d");
        [
            ["mttrLatestChip", kpiTrend.mttr, kpiTrend.censored, "rgba(0, 56, 168, 0.08)", "#0038A8", false],
            ["mtbfLatestChip", kpiTrend.mtbf, kpiTrend.censored, kpiMtbfWorsened ? "rgba(220, 38, 38, 0.10)" : "rgba(16, 185, 129, 0.10)", kpiMtbfWorsened ? "#b91c1c" : "#059669", true]
        ].forEach(([id, series, censored, bg, fg, showGe]) => {
            const el = document.getElementById(id);
            if (!el) return;
            let idx = -1;
            (series || []).forEach((v, i) => { if (v !== null && v !== undefined) idx = i; });
            if (idx < 0) return;
            const prefix = (showGe && censored && censored[idx]) ? "\u2265 " : "";
            const val = fmt(series[idx]);
            if (val === null) return;
            // compact chip: "Sep · 3.1d" — iisang line lang. Ang acronym ay nasa
            // title na mismo ("MTTR"), kaya hindi na kailangang ulitin sa chip.
            const shortMonth = (kpiTrend.months[idx] || "----").substring(0, 3);
            el.textContent = shortMonth + " \u00b7 " + prefix + val;
            el.style.background = bg;
            el.style.color = fg;
            el.style.display = "";
        });
    })();

}); // end DOMContentLoaded

</script>
@endsection
