@extends('layouts.app')

@section('title', 'IT Dashboard | NCMB CMMS')
@section('page-title', 'IT Dashboard')

@section('styles')
    <style nonce="{{ $cspNonce }}">
        .dashboard-container { animation: fadeInSlide 0.5s ease-out; }
        @keyframes fadeInSlide {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
    .welcome-hero {
        background: linear-gradient(135deg, #0038A8 0%, #002366 100%);
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
        top: -20px;
        right: -20px;
        width: clamp(100px, 10vw, 150px);
        height: clamp(100px, 10vw, 150px);
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
    }
    .flex-sb { display: flex; justify-content: space-between; align-items: center; }
    .hero-role { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; opacity: 0.7; }
    .hero-name { font-size: 32px; font-weight: 800; margin: 0; }
    .hero-desc { margin: 10px 0 0; opacity: 0.8; font-size: 15px; }
    .hero-stats-box { text-align: right; background: rgba(255,255,255,0.1); padding: 15px 25px; border-radius: 12px; backdrop-filter: blur(10px); }
    .hero-stats-label { font-size: 11px; font-weight: 800; text-transform: uppercase; margin-bottom: 5px; }
    .hero-stats-value { font-size: 18px; font-weight: 800; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: clamp(10px, 1vw, 14px);
            margin-bottom: clamp(16px, 2vw, 24px);
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
            display: block;
            font-size: clamp(10px, 0.8vw, 11px);
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: clamp(22px, 2.2vw, 28px);
            font-weight: 800;
            color: #1e293b;
        }
        .workbench-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(280px, 320px);
            gap: clamp(16px, 2vw, 24px);
        }
        .panel {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 15px 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .panel-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
        }
        .panel-title h3 {
            margin: 0;
            font-size: 13px;
            font-weight: 900;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .panel-link {
            color: #0038A8;
            font-size: 12px;
            font-weight: 900;
            text-decoration: none;
        }
        .job-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .job-row:last-child { border-bottom: 0; }
        .job-number {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #0038A8;
            font-size: 13px;
            font-weight: 900;
            text-decoration: none;
        }
        .job-type {
            display: inline-flex;
            align-items: center;
            min-width: 34px;
            justify-content: center;
            border-radius: 4px;
            padding: 3px 7px;
            background: #f1f5f9;
            color: #334155;
            font-size: 10px;
            font-weight: 900;
            margin-right: 8px;
        }
        .job-meta {
            margin-top: 2px;
            font-size: 12px;
            color: #64748b;
            line-height: 1.35;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            white-space: nowrap;
            border: 1px solid transparent;
        }
        .status-pending { background: #fffbeb; color: #92400e; border-color: rgba(245, 158, 11, 0.2); box-shadow: 0 2px 4px rgba(245, 158, 11, 0.15); }
        .status-ongoing { background: #eff6ff; color: #1e40af; border-color: rgba(59, 130, 246, 0.2); box-shadow: 0 2px 4px rgba(59, 130, 246, 0.15); }
        .status-completed { background: #ecfdf5; color: #065f46; border-color: rgba(16, 185, 129, 0.2); box-shadow: 0 2px 4px rgba(16, 185, 129, 0.15); }
        .status-awaiting { background: #faf5ff; color: #7e22ce; border-color: rgba(126, 34, 206, 0.2); box-shadow: 0 2px 4px rgba(126, 34, 206, 0.15); }
        .status-referred { background: #ccfbf1; color: #115e59; border-color: rgba(17, 94, 89, 0.2); box-shadow: 0 2px 4px rgba(17, 94, 89, 0.15); }
        .action-button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-width: 72px;
            justify-content: center;
            background: #0038A8;
            color: white;
            border-radius: 7px;
            padding: 6px 10px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 900;
            min-height: 32px;
            transition: all 0.2s;
        }
        .action-button:hover { background: #002366; color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0, 35, 102, 0.3); }
        .empty-state {
            padding: 18px 12px;
            text-align: center;
            color: #64748b;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
        }
        @media screen and (max-width: 800px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 10px !important; }
            .workbench-grid { grid-template-columns: 1fr !important; }
            .job-row { grid-template-columns: 1fr !important; }
            .action-button { width: 100% !important; }
            .welcome-hero { padding: 18px 16px !important; border-radius: 12px !important; }
            .flex-sb { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; }
            .hero-name { font-size: 20px !important; }
            .hero-desc { font-size: 13px !important; }
            .hero-stats-box { min-width: 0 !important; width: 100% !important; }
            .panel { padding: 12px 14px !important; }
            .job-number { font-size: 14px !important; }
            .action-button { min-height: 40px !important; padding: 8px 12px !important; font-size: 12px !important; }
        }
        .stat-value-amber { color: #f59e0b; }
        .stat-value-dark-amber { color: #92400e; }
        .stat-value-blue { color: #2563eb; }
        .action-bar { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .btn-pm-tasks { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: #059669; color: white; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; }
        .job-status-mt { margin-top: 8px; }
        .empty-state-icon { font-size: 28px; color: #94a3b8; margin-bottom: 10px; }
        .empty-state-title { font-weight: 900; color: #334155; }
        .empty-state-subtitle { margin-top: 5px; }
        .job-row-single { grid-template-columns: 1fr; }
        .job-action-full { width: 100%; }
        @media screen and (max-width: 767px) {
            .workbench-grid { grid-template-columns: 1fr !important; }
            .stats-grid { grid-template-columns: repeat(2,1fr) !important; }
            .card-header-accent { flex-direction: column !important; gap: 10px !important; }
            .action-bar { flex-direction: column !important; gap: 10px !important; }
            .btn-pm-tasks { width: 100% !important; justify-content: center !important; min-height: 44px !important; font-size: 13px !important; }
            input, select, textarea { min-height: 44px !important; font-size: 15px !important; }
            .btn, button:not(#sidebarToggle):not(#notifBell) { min-height: 44px !important; font-size: 13px !important; }

            /* Hero stats box — left-aligned row (was right-hugging full-width) */
            .hero-stats-box {
                text-align: left !important;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 10px !important;
                padding: 12px 16px !important;
            }
            /* View All — comfortable tap target */
            .panel-link {
                display: inline-flex !important;
                align-items: center !important;
                min-height: 36px !important;
                padding: 6px 4px !important;
                font-size: 13px !important;
            }
            .job-row { padding: 12px 0 !important; }
        }
    </style>
@endsection

@section('content')
@php
    $jobUrl = function ($job) {
        if ($job->type === 'ICT' && $job->status === \App\Models\Request::STATUS_AWAITING_PARTS) {
            return route('ict.ticket', $job->id);
        }

        return route($job->type === 'ICT' ? 'ict.edit' : 'maintenance.edit', $job->id);
    };
    $jobAction = function ($job) {
        if ($job->status === \App\Models\Request::STATUS_PENDING) {
            return 'Diagnose';
        }
        if ($job->status === \App\Models\Request::STATUS_AWAITING_PARTS) {
            return 'Parts Status';
        }
        if ($job->status === \App\Models\Request::STATUS_REFERRED_EXTERNAL) {
            return 'Follow Up';
        }
        return $job->type === 'ICT' ? 'Continue' : 'Update';
    };
    $pillClass = function ($status) {
        return match ($status) {
            \App\Models\Request::STATUS_AWAITING_PARTS => 'status-awaiting',
            \App\Models\Request::STATUS_REFERRED_EXTERNAL => 'status-referred',
            \App\Models\Request::STATUS_ONGOING => 'status-ongoing',
            \App\Models\Request::STATUS_COMPLETED => 'status-completed',
            default => 'status-pending',
        };
    };
@endphp

<div class="dashboard-container">
    <div class="welcome-hero">
        <div class="flex-sb">
            <div>
                <div class="hero-role">IT Personnel</div>
                <h1 class="hero-name">{{ Auth::user()->full_name }}</h1>
                <p class="hero-desc">
                    {{ Auth::user()->office ?: 'Central Office' }} @if(Auth::user()->department) &middot; {{ Auth::user()->department }} @endif
                </p>
            </div>
            <div class="hero-stats-box">
                <div class="hero-stats-label">Active Workload</div>
                <div class="hero-stats-value">{{ $stats['pending'] + $stats['ongoing'] + $stats['awaiting_parts'] }}</div>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card-premium stat-assigned">
            <i class="fa-solid fa-briefcase stat-bg-icon"></i>
            <span class="stat-label">Assigned JOs</span>
            <div class="stat-value">{{ $stats['assigned'] }}</div>
        </div>
        <div class="stat-card-premium stat-pending">
            <i class="fa-regular fa-hourglass-half stat-bg-icon"></i>
            <span class="stat-label">Pending</span>
            <div class="stat-value stat-value-amber">{{ $stats['pending'] }}</div>
        </div>
        <div class="stat-card-premium stat-card-scheduled">
            <i class="fa-solid fa-calendar stat-bg-icon"></i>
            <span class="stat-label">Scheduled</span>
            <div class="stat-value stat-value-dark-amber">{{ $stats['scheduled'] ?? 0 }}</div>
        </div>
        <div class="stat-card-premium stat-ongoing">
            <i class="fa-solid fa-spinner stat-bg-icon"></i>
            <span class="stat-label">In Progress</span>
            <div class="stat-value">{{ $stats['ongoing'] }}</div>
        </div>
    </div>

    <div class="workbench-grid">
        <div>
            <div class="panel">
                <div class="panel-title">
                    <h3>Assigned Job Orders</h3>
                    <a href="{{ route('ict.index') }}" class="panel-link">View All</a>
                </div>

                @forelse($requests as $job)
                    <div class="job-row">
                        <div>
                            <a href="{{ $jobUrl($job) }}" class="job-number">
                                <span class="job-type">{{ $job->type === 'ICT' ? 'ICT' : 'PM' }}</span>
                                {{ $job->display_number ?? $job->request_number }}
                            </a>
                            <div class="job-meta">
                                {{ $job->requestor_name }}
                            </div>
                            <div class="job-status-mt">
                                <span class="status-pill {{ $pillClass($job->status) }}">{{ $job->status }}</span>
                            </div>
                        </div>
                        <a href="{{ $jobUrl($job) }}" class="action-button">
                            <i class="fa-solid fa-arrow-right"></i> {{ $jobAction($job) }}
                        </a>
                    </div>
                @empty
                    <div class="empty-state">
                        <i class="fa-solid fa-clipboard-check empty-state-icon"></i>
                        <div class="empty-state-title">No active job orders assigned</div>
                        <div class="empty-state-subtitle">Assigned ICT and PM work will appear here.</div>
                    </div>
                @endforelse
            </div>

        </div>

        <div>
            <div class="panel">
                <div class="panel-title">
                    <h3>For Completion</h3>
                </div>
                @forelse($needsCompletion as $job)
                    <div class="job-row job-row-single">
                        <div>
                            <a href="{{ $jobUrl($job) }}" class="job-number">{{ $job->display_number ?? $job->request_number }}</a>
                            <div class="job-meta">
                                {{ $job->type === 'ICT' ? 'Section 5 / IT signature' : 'Technician signature' }}
                            </div>
                        </div>
                        <a href="{{ $jobUrl($job) }}" class="action-button job-action-full">Complete</a>
                    </div>
                @empty
                    <div class="empty-state">No jobs waiting for completion details.</div>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endsection