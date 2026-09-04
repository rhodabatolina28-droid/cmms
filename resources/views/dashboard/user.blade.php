@extends('layouts.app')

@section('title', 'Dashboard | NCMB ICT System')
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
            background: linear-gradient(135deg, #0038A8 0%, #002366 100%);
            border-radius: clamp(10px, 1.2vw, 15px);
            padding: clamp(20px, 3vw, 35px);
            color: white;
            position: relative;
            overflow: hidden;
            margin-bottom: clamp(20px, 2.5vw, 30px);
            box-shadow: 0 10px 20px rgba(0, 56, 168, 0.15);
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

        /* STATS GRID MODERN — FLUID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
            font-size: clamp(10px, 0.8vw, 12px);
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: clamp(22px, 2.2vw, 28px);
            font-weight: 800;
            color: #1e293b;
        }

        /* QUICK ACTIONS */
        .action-button-premium {
            display: flex;
            align-items: center;
            gap: 15px;
            background: white;
            border-radius: 12px;
            padding: 18px;
            text-decoration: none;
            color: #1e293b;
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .action-button-premium:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
            border-color: #0038A8;
        }

        .action-icon-circle {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
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
        .tr-hover-row { transition: all 0.2s; position: relative; }
        .tr-hover-row:hover { background: #f8fafc !important; transform: scale(1.002); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .tr-hover-row:hover td:first-child { box-shadow: inset 4px 0 0 #0038A8; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }
        .btn-hover-primary:hover { background: #002366 !important; }
        .link-hover-survey:hover { background: #0038A8 !important; color: white !important; border-color: #0038A8 !important; }

        /* Utility classes */
        .flex-sb { display: flex; justify-content: space-between; align-items: center; }
        .flex-center { display: flex; align-items: center; }
        .flex-center-gap { display: flex; align-items: center; gap: 10px; }
        .flex-wrap-gap { display: flex; gap: 6px; flex-wrap: wrap; }
        .hero-name { font-size: 32px; font-weight: 800; margin: 0; }
        .hero-desc { margin: 8px 0 0; opacity: 0.8; font-size: 15px; }
        .hero-status-box { display: flex; flex-direction: column; align-items: flex-end; }
        .hero-status-label { font-size: 12px; opacity: 0.7; font-weight: 700; text-transform: uppercase; }
        .hero-status-value { font-size: 14px; font-weight: 800; color: #1e293b; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 56, 168, 0.2); backdrop-filter: blur(6px); display: flex; align-items: center; justify-content: center; z-index: 9999; animation: fadeIn 0.3s ease-out; }
        .modal-box { background: white; border-radius: 20px; padding: 40px; text-align: center; max-width: 450px; width: 90%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); transform: scale(0.9); animation: scaleUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
        .modal-icon-circle { background: #ecfdf5; color: #10b981; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 20px; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2); }
        .modal-title { color: #1e293b; font-weight: 900; margin: 0 0 10px; font-size: 24px; }
        .modal-text { color: #64748b; font-size: 14px; line-height: 1.6; margin: 0 0 25px; }
        .modal-btn { background: #0038A8; color: white; border: none; padding: 12px 35px; border-radius: 8px; font-weight: 800; font-size: 14px; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 6px rgba(0, 56, 168, 0.2); }
        .banner-alert { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 15px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        .banner-text { display: flex; align-items: center; gap: 10px; }
        .banner-icon { color: #0038A8; font-size: 16px; }
        .banner-msg { font-size: 13px; font-weight: 700; color: #334155; }
        .survey-link { background: white; border: 1px solid #e2e8f0; color: #0038A8; padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); transition: all 0.2s; }
        .star-icon { font-size: 9px; color: #fbbf24; }
        .table-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; }
        .link-master { text-decoration: none; color: #0038A8; font-weight: 700; font-size: 13px; }
        .scroll-x { overflow-x: auto; }
        .table-full { width: 100%; border-collapse: collapse; }
        .table-row-header { text-align: left; border-bottom: 2px solid #f1f5f9; }
        .table-row-border { border-bottom: 1px solid #f8fafc; transition: background 0.2s; }
        .table-header { padding: 12px 10px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .table-header.center { text-align: center; }
        .table-header.right { text-align: right; }
        .table-cell { padding: 14px 10px; }
        .table-cell-bold { padding: 14px 10px; font-weight: 800; color: #0038A8; }
        .table-cell-dept { padding: 14px 10px; color: #334155; font-weight: 600; }
        .table-cell-center { padding: 14px 10px; text-align: center; }
        .table-cell-right { padding: 14px 10px; text-align: right; }
        .empty-cell { padding: 30px; text-align: center; color: #94a3b8; }
        .rate-badge { background: #fbbf24; color: #92400e; padding: 5px 12px; border-radius: 6px; font-size: 11px; font-weight: 800; text-decoration: none; margin-right: 10px; box-shadow: 0 2px 4px rgba(251, 191, 36, 0.2); }
        .icon-chevron { color: #94a3b8; }
        .ribbon-label { margin-bottom: 15px; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
        .action-icon-blue { background: #e0e7ff; color: #4338ca; }
        .action-icon-dark { background: #e0e7ff; color: #3730a3; }
        .action-icon-red { background: #fee2e2; color: #b91c1c; }
        .action-title { font-weight: 800; font-size: 15px; }
        .action-subtitle { font-size: 12px; color: #64748b; }
        .action-subtitle-sm { font-size: 12px; color: #64748b; line-height: 1.4; }
        .user-grid { display: grid; gap: 25px; }
        .action-disabled { opacity: 0.75; cursor: default; border-color: #e2e8f0; background: #f8fafc; }
        .action-restricted { opacity: 0.65; cursor: not-allowed; pointer-events: none; border-color: #fecaca; background: #fff5f5; }
        .action-title-muted { font-weight: 800; font-size: 15px; color: #475569; }
        .action-title-red { font-weight: 800; font-size: 15px; color: #991b1b; }
        .action-subtitle-warn { font-size: 12px; color: #b45309; line-height: 1.4; }
        .mt-12 { margin-top: 12px; }
        .mb-20 { margin-bottom: 20px; }
        @media screen and (max-width: 767px) {
            .user-grid { grid-template-columns: 1fr !important; }
            .stats-grid { grid-template-columns: repeat(2,1fr) !important; }
            .welcome-hero { padding: 20px !important; }
            .hero-name { font-size: 18px !important; }
            .hero-desc { font-size: 12px !important; }
            .hero-status-box { align-items: flex-start !important; margin-top: 8px !important; }
            .flex-sb { flex-direction: column !important; gap: 6px !important; }
            .banner-alert { flex-direction: column !important; align-items: flex-start !important; }
            .survey-link { width: 100% !important; justify-content: center !important; min-height: 44px !important; }
            .scroll-x { overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; }
            .table-header { font-size: 10px !important; padding: 8px !important; }
            .table-cell, .table-cell-bold, .table-cell-dept, .table-cell-center, .table-cell-right { padding: 10px 8px !important; font-size: 12px !important; }
            .rate-badge { min-height: 36px !important; display: inline-flex !important; align-items: center !important; }
            .modal-box { padding: 24px 16px !important; }
            .modal-title { font-size: 20px !important; }
            .modal-btn { width: 100% !important; min-height: 48px !important; font-size: 15px !important; }
        }
    </style>
@endsection

@section('content')
<div class="dashboard-container">
    
    <!-- WELCOME HERO -->
    <div class="welcome-hero">
        <div class="flex-sb">
            <div>
                <h1 class="hero-name">{{ Auth::user()->full_name }}</h1>
                <p class="hero-desc">{{ Auth::user()->office ?: 'Central Office' }} @if(Auth::user()->department) | {{ Auth::user()->department }} @endif</p>
            </div>
            <div class="hero-status-box">
                <div class="hero-status-label">Current Status</div>
                <div class="hero-status-value">{{ $stats['pending'] }} Requests Pending</div>
            </div>
        </div>
    </div>

    <!-- THANK YOU POPUP MODAL -->
    @if(session('success') && str_contains(session('success'), 'Thank you for completing the survey'))
        <div id="thankYouModal" class="modal-overlay">
            <div class="modal-box">
                <div class="modal-icon-circle">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h2 class="modal-title">Thank You!</h2>
                <p class="modal-text">
                    Your feedback has been successfully submitted. It will help NCMB continuously improve its services for everyone.
                </p>
                <button id="thankYouDoneBtn" class="modal-btn btn-hover-primary">
                    Done
                </button>
            </div>
        </div>
        
        <style nonce="{{ $cspNonce }}">
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes scaleUp {
                from { transform: scale(0.95); opacity: 0; }
                to { transform: scale(1); opacity: 1; }
            }
        </style>
    @endif

    <!-- STATS GRID -->
    <div class="stats-grid">
        <div class="stat-card-premium stat-total">
            <i class="fa-solid fa-list-ul stat-bg-icon"></i>
            <span class="stat-label">Total Requests</span>
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
        <div class="stat-card-premium stat-completed">
            <i class="fa-solid fa-check-double stat-bg-icon"></i>
            <span class="stat-label">Completed</span>
            <div class="stat-value">{{ $stats['completed'] }}</div>
        </div>
    </div>

    <div class="user-grid">
        
        <!-- LEFT: RECENT ACTIVITY -->
        <div class="premium-table-box">
            <div class="flex-sb mb-20">
                <h3 class="table-title">Recent Activity</h3>
                <a href="{{ route('ict.index') }}" class="link-master">View All</a>
            </div>
            <div class="scroll-x">
                <table class="table-full">
                    <thead>
                        <tr class="table-row-header">
                            <th class="table-header">Ticket #</th>
                            <th class="table-header center">Status</th>
                            <th class="table-header right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            <tr class="tr-hover-row table-row-border">
                                <td class="table-cell-bold">{{ $req->display_number ?? $req->request_number }}</td>
                                <td class="table-cell-center">
                                    <span class="status-pill status-{{ strtolower($req->status) }}">{{ $req->status }}</span>
                                </td>
                                <td class="table-cell-right">
                                    @if($req->status === 'Completed' && !$req->csmSurvey()->exists())
                                        <a href="{{ route('csm.create', $req->id) }}" class="rate-badge"><i class="fa-solid fa-star"></i> Rate Us</a>
                                    @endif
                                    <a href="{{ $req->type == 'ICT' ? route('ict.show', $req->id) : route('maintenance.show', $req->id) }}" class="icon-chevron"><i class="fa-solid fa-chevron-right"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-cell" style="padding: 40px 20px;">
                                <i class="fa-solid fa-folder-open" style="font-size: 45px; color: #e2e8f0; margin-bottom: 15px; display: block;"></i>
                                <div style="font-weight: 800; color: #64748b; font-size: 15px;">No Recent Requests</div>
                                <div style="font-size: 12px; color: #94a3b8; margin-top: 5px;">You don't have any active or recent requests at the moment.</div>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RIGHT: ACTIONS & ASSETS -->
        <div>
            <div class="ribbon-label">Quick Actions</div>
            @if(!empty($hasAssignedAssets))
                <a href="{{ route('ict.create') }}" class="action-button-premium">
                    <div class="action-icon-circle action-icon-blue">
                        <i class="fa-solid fa-desktop"></i>
                    </div>
                    <div>
                        <div class="action-title">New Request</div>
                        <div class="action-subtitle">ICT Technical Support</div>
                    </div>
                </a>
                <div class="action-button-premium action-disabled mt-12">
                    <div class="action-icon-circle action-icon-dark">
                        <i class="fa-solid fa-calendar-clock"></i>
                    </div>
                    <div>
                        <div class="action-title-muted">Preventive Maintenance</div>
                        <div class="action-subtitle-sm">
                            PM is now scheduled by your ICT Unit. Contact your Super Admin for concerns.
                        </div>
                    </div>
                </div>
            @else
                <div class="action-button-premium action-restricted">
                    <div class="action-icon-circle action-icon-red">
                        <i class="fa-solid fa-ban"></i>
                    </div>
                    <div>
                        <div class="action-title-red">Requests Unavailable</div>
                        <div class="action-subtitle-warn">
                            Walang assigned asset. Makipag-ugnayan sa Administrative supply admin.
                        </div>
                    </div>
                </div>
            @endif

        </div>

    </div>

</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
const thankYouBtn = document.getElementById('thankYouDoneBtn');
if (thankYouBtn) {
    thankYouBtn.addEventListener('click', function() {
        document.getElementById('thankYouModal').remove();
    });
}
</script>
@endsection
