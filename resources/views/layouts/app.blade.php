<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CMMS - Repair & Maintenance')</title>
    
    <!-- Favicon - NCMB Logo -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/ncmb-logo.svg') }}">
    
    <!-- Fonts & Icons -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Core CSS -->
    @vite(['resources/css/admin.css', 'resources/css/mobile-responsive.css'])
    
    <!-- CSS for Pagination Fix (Final Clean) -->
    <style nonce="{{ $cspNonce }}">
        /* Pagination */
        nav[role="navigation"] { margin-top: 20px; }
        .pagination, nav[role="navigation"] ul { 
            display: flex !important; 
            list-style: none !important; 
            padding: 0 !important; 
            gap: 5px !important; 
            justify-content: flex-end !important;
            margin: 0 !important;
        }
        nav[role="navigation"] li { display: inline-block !important; }
        nav[role="navigation"] a, nav[role="navigation"] span { 
            padding: 8px 14px !important; 
            border: 1px solid #dee2e6 !important; 
            color: #0038A8 !important; 
            text-decoration: none !important; 
            border-radius: 4px !important;
            font-size: 14px !important;
            background: white !important;
            line-height: 1 !important;
        }
        nav[role="navigation"] .active span, nav[role="navigation"] li[aria-current="page"] span { 
            background-color: #0038A8 !important; 
            color: white !important; 
            border-color: #0038A8 !important; 
        }
        nav[role="navigation"] svg { width: 14px !important; height: 14px !important; display: inline-block !important; }
        nav[role="navigation"] > div:first-child { display: none !important; }
        nav[role="navigation"] > div:last-child { display: flex !important; width: 100% !important; justify-content: flex-end !important; }
        .hidden.sm\:flex-1.sm\:flex.sm\:items-center.sm\:justify-between { display: flex !important; flex-direction: row-reverse !important; }


        /* Sidebar overrides (only keep essential overrides, let admin.css handle the rest to prevent jumping) */
        #sidebar { background-color: #0038A8 !important; }
        #logout-form { display: none; }
        #logoutLink { color: #ffcccc; margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); }

        /* Backdrop - smooth fade transition */
        #sidebarBackdrop { position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:99998; -webkit-tap-highlight-color:transparent; opacity:0; visibility:hidden; transition:opacity 0.35s ease, visibility 0.35s ease; }
        #sidebarBackdrop.active { opacity:1; visibility:visible; }

        /* Topbar */
        .topbar { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(0,0,0,0.05); padding: 10px 30px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; position: sticky; top: 0; z-index: 90; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.01); }
        .topbar-left { display: flex; align-items: center; gap: 20px; }
        .topbar-right { display: flex; align-items: center; gap: 30px; }
        .topbar-title { margin: 0; font-size: 18px; color: #111827; font-weight: 800; letter-spacing: -0.5px; }
        #sidebarToggle { background: #f3f4f6; border: none; width: 38px; height: 38px; border-radius: 10px; font-size: 18px; cursor: pointer; color: #374151; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        #timeContainer { display: flex; align-items: center; gap: 15px; background: white; padding: 6px 20px 6px 6px; border-radius: 30px; border: 1px solid rgba(0, 56, 168, 0.1); box-shadow: 0 4px 10px rgba(0, 56, 168, 0.06); transition: transform 0.2s, box-shadow 0.2s; }
        #timeContainer:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0, 56, 168, 0.1); }
        #realTimeClock { font-size: 16px; color: #0038A8; font-weight: 900; font-family: Arial, sans-serif; line-height: 1; letter-spacing: 0.5px; }
        #realTimeDate { font-size: 10px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }
        .time-text-wrap { text-align: right; }
        .time-clock-icon { width: 34px; height: 34px; background: #eff6ff; color: #0038A8; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; box-shadow: 0 2px 5px rgba(0, 56, 168, 0.1); }

        /* Notification items (JS templates) */
        .notif-item { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; position: relative; }
        .notif-item-inner { display: flex; gap: 12px; }
        .notif-dot { width: 8px; height: 8px; background: #2563eb; border-radius: 50%; margin-top: 5px; flex-shrink: 0; }
        .notif-type { font-weight: 700; color: #1e293b; font-size: 13px; }
        .notif-msg { font-size: 12px; color: #64748b; line-height: 1.4; margin: 4px 0; }
        .notif-meta { font-size: 11px; color: #94a3b8; display: flex; align-items: center; gap: 10px; margin-top: 6px; }
        .notif-mark-read { color: #2563eb; text-decoration: none; font-weight: 700; }
        .mark-read { color: #2563eb; text-decoration: none; font-weight: 700; }
        .notif-empty-alt { padding: 40px 20px; text-align: center; color: #94a3b8; }
        .notif-empty-alt-icon { font-size: 24px; display: block; margin-bottom: 10px; opacity: 0.5; }
        .notif-empty-alt-text { font-size: 13px; margin: 0; }

        /* Notification */
        .notification-wrapper { position: relative; display: flex; align-items: center; }
        #notifBell { cursor: pointer; color: #475569; font-size: 20px; position: relative; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: #fff; border-radius: 50%; border: 1px solid #e2e8f0; transition: all 0.2s; }
        .notif-badge { display: none; position: absolute; top: -2px; right: -2px; background: #ef4444; color: white; font-size: 10px; min-width: 18px; height: 18px; align-items: center; justify-content: center; border-radius: 50%; font-weight: 800; border: 2px solid white; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3); }
        #notifDropdown { padding: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border: 1px solid #f1f5f9; }
        .notif-header { padding: 18px 20px; background: white; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .notif-header-left { display: flex; align-items: center; gap: 8px; }
        .notif-header-title { margin:0; font-size: 15px; font-weight: 800; color: #1e293b; }
        .notif-count-label { background: #eff6ff; color: #2563eb; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 700; }
        #markAllRead { font-size: 12px; color: #2563eb; text-decoration: none; font-weight: 600; transition: color 0.2s; }
        #notifContent { background: white; max-height: 350px; overflow-y: auto; }
        .notif-empty { padding: 40px 20px; text-align: center; color: #94a3b8; }
        .notif-empty-icon { font-size: 24px; display: block; margin-bottom: 10px; opacity: 0.5; }
        .notif-empty-text { font-size: 13px; margin: 0; }
        .notif-footer { padding: 15px 20px; border-top: 1px solid #f1f5f9; background: #f8fafc; text-align: center; }
        .notif-footer-link { color: #475569; text-decoration: none; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .notif-footer-arrow { font-size: 11px; }

        /* Alerts */
        .alert-success { padding: 15px; background: #ecfdf5; color: #047857; border-radius: 8px; margin-bottom: 20px; border: 1px solid #d1fae5; }
        .alert-error { padding: 15px; background: #fef2f2; color: #b91c1c; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fee2e2; }

        /* Global accessibility: minimum readable font size for badges/pills */
        .status-pill, .badge-freq, .badge-pm, .role-pill, .type-pill,
        .sp-active, .sp-inactive, .sp-pending, .sp-ongoing, .sp-completed,
        .badge-scheduled, .badge-ongoing, .badge-completed, .badge-auto,
        .queue-badge, .sa-type-pill { font-size: 11px !important; min-height: 20px; }

        /* Unified color standard */
        :root {
            --cmms-primary: #1e40af;
            --cmms-primary-dark: #002366;
            --cmms-white: #FFFFFF;
            --cmms-bg: #F3F4F6;
            --cmms-text: #1F2937;
            --cmms-border: #E5E7EB;
        }

        /* Unified status colors */
        .status-pending { background: #fff7ed !important; color: #c2410c !important; border: 1px solid #ffedd5 !important; }
        .status-ongoing { background: #eff6ff !important; color: #1d4ed8 !important; border: 1px solid #dbeafe !important; }
        .status-completed { background: #ecfdf5 !important; color: #047857 !important; border: 1px solid #d1fae5 !important; }
        .status-rejected { background: #fef2f2 !important; color: #b91c1c !important; border: 1px solid #fee2e2 !important; }

        /* ===== Sidebar accordion (Inventory group) ===== */
        .cmms-nav-group { display: block; }
        .cmms-nav-parent {
            width: 100%;
            display: flex; align-items: center; gap: clamp(8px, 0.8vw, 12px);
            min-height: clamp(42px, 3.6vw, 52px);
            padding: 0 clamp(16px, 1.8vw, 24px);
            color: rgba(255,255,255,0.85);
            background: transparent; border: none; border-left: 4px solid transparent;
            font-size: clamp(12px, 1vw, 15px); font-weight: 700;
            cursor: pointer; text-align: left;
            transition: background 0.2s, color 0.2s, border-left-color 0.2s;
            white-space: nowrap;
        }
        .cmms-nav-parent:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .cmms-nav-parent.active { background: rgba(255,255,255,0.18); color: #fff; border-left-color: #FDC113; }
        .cmms-nav-parent i:first-child { width: 16px; text-align: center; flex-shrink: 0; }
        .cmms-nav-caret { margin-left: auto; font-size: 12px; transition: transform 0.25s ease; }
        .cmms-nav-group.open .cmms-nav-caret { transform: rotate(180deg); }
        .cmms-nav-sub { display: none; padding: 2px 0 6px; }
        .cmms-nav-group.open .cmms-nav-sub { display: block; }
        .cmms-nav-sub-link {
            display: flex; align-items: center;
            padding: 9px clamp(32px, 4vw, 44px);
            margin: 0 clamp(4px, 0.4vw, 6px);
            border-radius: 6px;
            color: rgba(255,255,255,0.8); text-decoration: none;
            font-size: clamp(12px, 1vw, 13.5px); font-weight: 600;
            border-left: 3px solid transparent;
            transition: background 0.2s, color 0.2s;
            white-space: nowrap;
        }
        .cmms-nav-sub-link:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .cmms-nav-sub-link.active { background: rgba(255,255,255,0.18); color: #fff; border-left-color: #FDC113; }
    </style>
    <script nonce="{{ $cspNonce }}" src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @yield('styles')
    {{-- CRITICAL: Apply sidebar collapsed state BEFORE body renders to prevent flash --}}
    <script nonce="{{ $cspNonce }}">
        (function() {
            try {
                var saved = localStorage.getItem('cmms_sidebar_collapsed');
                if (saved === 'true' && window.innerWidth >= 1024) {
                    document.documentElement.setAttribute('data-sidebar', 'collapsed');
                }
            } catch(e) {}
        })();
    </script>
    <style nonce="{{ $cspNonce }}">
        /* If data-sidebar=collapsed, hide sidebar and expand main immediately (no transition) */
        html[data-sidebar="collapsed"] .sidebar { width: 0 !important; padding: 0 !important; overflow: hidden !important; }
        html[data-sidebar="collapsed"] .main { margin-left: 0 !important; max-width: 100vw !important; }
    </style>
</head>
<body style="font-family: Arial, sans-serif; -webkit-font-smoothing: antialiased;">

    @auth
        <!-- SIDEBAR (LEGACY STYLE) -->
        <aside class="sidebar" id="sidebar">
            <div class="logo-section">
                <img src="{{ asset('images/ncmb-logo.svg') }}" alt="NCMB Logo" class="sidebar-logo-img">
                <h1 class="sidebar-title">CMMS PORTAL</h1>
                <p class="sidebar-role">
                    @if(Auth::user()->role === 'super_admin') SUPER ADMIN
                    @elseif(Auth::user()->role === 'admin' || Auth::user()->role === 'supply_officer') 
                        {{ Auth::user()->canProcessSupply() ? 'ADMIN & SUPPLY' : 'DIVISION ADMIN' }}
                    @elseif(Auth::user()->role === 'it') IT PERSONNEL
                    @else USER
                    @endif
                </p>
            </div>

            <nav>
                <!-- DASHBOARD (ALL) -->
                <a href="{{ route(Auth::user()->dashboardRouteName()) }}" class="nav-link {{ request()->routeIs('dashboard.*') ? 'active' : '' }}" data-tooltip="Dashboard">
                    <i class="fa-solid fa-gauge"></i> <span>Dashboard</span>
                </a>

                <!-- PROFILE (ALL) -->
                <a href="{{ route('profile.index') }}" class="nav-link {{ request()->routeIs('profile.index') ? 'active' : '' }}" data-tooltip="Profile">
                    <i class="fa-solid fa-user-gear"></i> <span>Profile</span>
                </a>

                <div class="nav-section-divider"></div>

                @if(Auth::user()->role === 'super_admin')
                    <!-- SUPER ADMIN MODULES -->
                    <a href="{{ route('super_admin.users') }}" class="nav-link {{ request()->routeIs('super_admin.users') ? 'active' : '' }}" data-tooltip="User Management">
                        <i class="fa-solid fa-users-gear"></i> <span>User Management</span>
                    </a>
                    <a href="{{ route('super_admin.audit_logs') }}" class="nav-link {{ request()->routeIs('super_admin.audit_logs') ? 'active' : '' }}" data-tooltip="Audit Logs">
                        <i class="fa-solid fa-shield-halved"></i> <span>Audit Logs</span>
                    </a>
                    <a href="{{ route('ict.index') }}" class="nav-link {{ request()->routeIs('ict.index') ? 'active' : '' }}" data-tooltip="All Requests">
                        <i class="fa-solid fa-clipboard-list"></i> <span>All Requests</span>
                    </a>
                    <div class="cmms-nav-group">
                        <button type="button" class="cmms-nav-parent {{ request()->routeIs('super_admin.inventory*', 'super_admin.parts') ? 'active' : '' }}" aria-expanded="false">
                            <i class="fa-solid fa-boxes-stacked"></i>
                            <span>Inventory</span>
                            <i class="fa-solid fa-chevron-down cmms-nav-caret"></i>
                        </button>
                        <div class="cmms-nav-sub">
                            <a href="{{ route('super_admin.inventory') }}" class="cmms-nav-sub-link {{ request()->routeIs('super_admin.inventory*') ? 'active' : '' }}">Asset Registry</a>
                            <a href="{{ route('super_admin.parts') }}" class="cmms-nav-sub-link {{ request()->routeIs('super_admin.parts') ? 'active' : '' }}">Parts & Consumables</a>
                        </div>
                    </div>
                    <a href="{{ route('requisitions.index') }}" class="nav-link {{ request()->routeIs('requisitions.*') ? 'active' : '' }}" data-tooltip="Parts Requests">
                        <i class="fa-solid fa-box"></i> <span>My Parts Requests</span>
                    </a>
                    <a href="{{ route('pm-schedules.index') }}" class="nav-link {{ request()->routeIs('pm-schedules.*') ? 'active' : '' }}" data-tooltip="PM Schedules">
                        <i class="fa-solid fa-calendar-clock"></i> <span>PM Schedules</span>
                    </a>
                @elseif(Auth::user()->role === 'admin' || Auth::user()->role === 'supply_officer')
                    <!-- DIVISION ADMIN / SUPPLY OFFICER MODULES -->
                    <a href="{{ route('personnel.index') }}" class="nav-link {{ request()->routeIs('personnel.*') ? 'active' : '' }}" data-tooltip="Manage Personnel">
                        <i class="fa-solid fa-users"></i> <span>Manage Personnel</span>
                    </a>
                    @if(Auth::user()->canProcessSupply())
                    <div class="cmms-nav-group">
                        <button type="button" class="cmms-nav-parent {{ request()->routeIs('inventory.*', 'physical-count.*') ? 'active' : '' }}" aria-expanded="false">
                            <i class="fa-solid fa-boxes-stacked"></i>
                            <span>Inventory</span>
                            <i class="fa-solid fa-chevron-down cmms-nav-caret"></i>
                        </button>
                        <div class="cmms-nav-sub">
                            <a href="{{ route('inventory.index') }}" class="cmms-nav-sub-link {{ request()->routeIs('inventory.index') ? 'active' : '' }}">Inventory & Assets</a>
                            <a href="{{ route('inventory.parts') }}" class="cmms-nav-sub-link {{ request()->routeIs('inventory.parts*') ? 'active' : '' }}">Parts & Consumables</a>
                            <a href="{{ route('inventory.qr-batch') }}" class="cmms-nav-sub-link {{ request()->routeIs('inventory.qr-batch') ? 'active' : '' }}">Batch QR Sticker Print</a>
                            <a href="{{ route('physical-count.index') }}" class="cmms-nav-sub-link {{ request()->routeIs('physical-count.*') ? 'active' : '' }}">Physical Inventory Count</a>
                        </div>
                    </div>
                    @endif
                    <a href="{{ route('ict.index') }}" class="nav-link {{ request()->routeIs('ict.*') ? 'active' : '' }}" data-tooltip="Requests">
                        <i class="fa-solid fa-clipboard-list"></i> <span>{{ Auth::user()->department ? 'Department' : (Auth::user()->office ? 'Office' : 'Division') }} Requests</span>
                    </a>
                    @if(Auth::user()->canProcessSupply())
                    <a href="{{ route('requisitions.index') }}" class="nav-link {{ request()->routeIs('requisitions.*') ? 'active' : '' }}" data-tooltip="Supply Workspace">
                        <i class="fa-solid fa-truck"></i> <span>Supply Workspace</span>
                    </a>
                    @endif
                @elseif(Auth::user()->role === 'it')
                    <a href="{{ route('ict.index') }}" class="nav-link {{ request()->routeIs('ict.*') ? 'active' : '' }}" data-tooltip="My Assigned Jobs">
                        <i class="fa-solid fa-screwdriver-wrench"></i> <span>My Assigned Jobs</span>
                    </a>
                    <a href="{{ route('pm.tasks') }}" class="nav-link {{ request()->routeIs('pm.tasks') ? 'active' : '' }}" data-tooltip="PM Tasks">
                        <i class="fa-solid fa-calendar-check"></i> <span>PM Tasks</span>
                    </a>
                    <a href="{{ route('requisitions.index') }}" class="nav-link {{ request()->routeIs('requisitions.*') ? 'active' : '' }}" data-tooltip="Parts Requests">
                        <i class="fa-solid fa-box"></i> <span>My Parts Requests</span>
                    </a>
                @elseif(Auth::user()->role === 'user')
                    <!-- END USER MODULES -->
                    <a href="{{ route('profile.assets') }}" class="nav-link {{ request()->routeIs('profile.assets') ? 'active' : '' }}" data-tooltip="My Assets">
                        <i class="fa-solid fa-laptop-medical"></i> <span>My Assets</span>
                    </a>
                    <a href="{{ route('ict.create') }}" class="nav-link {{ request()->routeIs('ict.create') ? 'active' : '' }}" data-tooltip="ICT Request">
                        <i class="fa-solid fa-desktop"></i> <span>ICT Request</span>
                    </a>
                    {{-- PM is now scheduled by Super Admin — removed from user sidebar --}}
                    <a href="{{ route('ict.index') }}" class="nav-link {{ request()->routeIs('ict.*') || request()->routeIs('maintenance.*') ? 'active' : '' }}" data-tooltip="My Requests">
                        <i class="fa-solid fa-list-check"></i> <span>My Requests</span>
                    </a>
                @endif

                <div class="nav-section-divider"></div>
                
                <form action="{{ route('logout') }}" method="POST" id="logout-form">
                    @csrf
                </form>
                <a href="#" class="nav-link logout" id="logoutLink" data-tooltip="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <p>NCMB ICT Unit</p>
            </div>
        </aside>

        <!-- Mobile Sidebar Backdrop -->
        <div id="sidebarBackdrop"></div>

        <!-- MAIN CONTENT -->
        <main class="main" id="mainContent">
            <!-- TOP BAR (PREMIUM REFINED) -->
            <header class="topbar">
                <div class="topbar-left">
                    <button id="sidebarToggle" aria-label="Toggle sidebar">
                        <i class="fa-solid fa-bars-staggered"></i>
                    </button>
                    <div>
                        <h3 class="topbar-title">@yield('page-title', 'Dashboard')</h3>
                    </div>
                </div>
                
                <div class="topbar-right">
                    <!-- PREMIUM TIME & DATE -->
                    <div id="timeContainer">
                        <div class="time-text-wrap">
                            <div id="realTimeClock">00:00:00 AM</div>
                            <div id="realTimeDate">Monday, May 12, 2026</div>
                        </div>
                        <i class="fa-regular fa-clock time-clock-icon"></i>
                    </div>

                    <div class="notification-wrapper">
                        <div id="notifBell" aria-label="Notifications" role="button" tabindex="0">
                            <i class="fa-solid fa-bell"></i>
                            <span class="notif-badge" id="notifCount">0</span>
                        </div>
                        <!-- Notification Dropdown -->
                        <div class="notif-dropdown" id="notifDropdown">
                            <div class="notif-header">
                                <div class="notif-header-left">
                                    <h4 class="notif-header-title">Notifications</h4>
                                    <span class="notif-count-label" id="notifCountLabel">0 New</span>
                                </div>
                                <a href="#" id="markAllRead">Mark all as read</a>
                            </div>
                            <div class="notif-content" id="notifContent">
                                <div class="notif-empty">
                                    <i class="fa-solid fa-bell-slash notif-empty-icon"></i>
                                    <p class="notif-empty-text">No new notifications</p>
                                </div>
                            </div>
                            <div class="notif-footer">
                                <a href="{{ route('ict.index') }}" class="notif-footer-link">
                                    View All Activities <i class="fa-solid fa-arrow-right notif-footer-arrow"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="content-wrapper">
                @yield('content')
            </div>
        </main>
    @else
        @yield('content')
    @endauth

    <script nonce="{{ $cspNonce }}">
        // Smooth toggle — uses .collapsed class (no !important, so CSS transitions work)
        function toggleSidebar() {
            var sidebar = document.getElementById('sidebar');
            var main = document.getElementById('mainContent');
            var backdrop = document.getElementById('sidebarBackdrop');
            var isMobile = window.innerWidth < 1024;
            if (sidebar && main) {
                if (isMobile) {
                    sidebar.classList.toggle('sidebar-open');
                    if (backdrop) {
                        backdrop.classList.toggle('active');
                    }
                } else {
                    var isCollapsed = main.classList.contains('expanded');
                    if (isCollapsed) {
                        main.classList.remove('expanded');
                        sidebar.classList.remove('collapsed');
                        localStorage.setItem('cmms_sidebar_collapsed', 'false');
                    } else {
                        main.classList.add('expanded');
                        sidebar.classList.add('collapsed');
                        localStorage.setItem('cmms_sidebar_collapsed', 'true');
                    }
                }
            }
        }

        // On page load: if saved collapsed AND desktop, switch from data-sidebar to .collapsed
        (function() {
            var saved = localStorage.getItem('cmms_sidebar_collapsed');
            var sidebar = document.getElementById('sidebar');
            var main = document.getElementById('mainContent');
            if (saved === 'true' && window.innerWidth >= 1024 && sidebar && main) {
                // Disable transition temporarily to prevent flash
                sidebar.style.transition = 'none';
                main.style.transition = 'none';
                document.documentElement.removeAttribute('data-sidebar');
                sidebar.classList.add('collapsed');
                main.classList.add('expanded');
                // Force reflow then restore transition
                sidebar.offsetHeight;
                setTimeout(function() {
                    sidebar.style.transition = '';
                    main.style.transition = '';
                }, 20);
            } else {
                document.documentElement.removeAttribute('data-sidebar');
            }
        })();

        // Close sidebar on window resize from mobile to desktop
        window.addEventListener('resize', function() {
            var sidebar = document.getElementById('sidebar');
            var backdrop = document.getElementById('sidebarBackdrop');
            if (window.innerWidth >= 1024 && sidebar) {
                sidebar.classList.remove('sidebar-open');
                if (backdrop) backdrop.classList.remove('active');
            }
        });

        function updateClock() {
            const now = new Date();
            const clock = document.getElementById('realTimeClock');
            const dateDisplay = document.getElementById('realTimeDate');
            if (clock) {
                clock.textContent = now.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                });
            }
            if (dateDisplay) {
                dateDisplay.textContent = now.toLocaleDateString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            }
        }

        // Global Department Mapping
        window.cmmsDepartments = {
            'INTERNAL SERVICES DEPARTMENT': [
                { value: 'RESEARCH AND INFORMATION DIVISION', text: 'Research & Information Division' },
                { value: 'ADMINISTRATIVE DIVISION', text: 'Administrative Division' },
                { value: 'FINANCIAL AND MANAGEMENT DIVISION', text: 'Financial & Management Division' },
                { value: 'COMMISSION ON AUDIT', text: 'Commission on Audit' }
            ],
            'TECHNICAL SERVICES DEPARTMENT': [
                { value: 'CONCILIATION AND MEDIATION DIVISION', text: 'Conciliation & Mediation Division' },
                { value: 'VOLUNTARY ARBITRATION DIVISION', text: 'Voluntary Arbitration Division' },
                { value: 'WORKPLACE RELATIONS ENHANCEMENT DIVISION', text: 'Workplace Relations Enhancement Division' },
                { value: 'OFFICE OF THE EXECUTIVE DIRECTOR', text: 'Office of the Executive Director' }
            ]
        };

        function updateDepartmentDropdown(parentDeptId, childDivId) {
            const parentSelect = document.getElementById(parentDeptId);
            const childSelect = document.getElementById(childDivId);
            
            if (!parentSelect || !childSelect) return;
            
            const selectedDept = parentSelect.value.toUpperCase();
            const currentDivValue = childSelect.value;
            
            childSelect.innerHTML = '<option value="">All Divisions</option>';
            
            let options = [];
            if (selectedDept && window.cmmsDepartments[selectedDept]) {
                options = window.cmmsDepartments[selectedDept];
            } else if (!selectedDept) {
                options = [...window.cmmsDepartments['INTERNAL SERVICES DEPARTMENT'], ...window.cmmsDepartments['TECHNICAL SERVICES DEPARTMENT']];
            }
            
            options.forEach(opt => {
                const optionEl = document.createElement('option');
                optionEl.value = opt.value;
                optionEl.textContent = opt.text;
                childSelect.appendChild(optionEl);
            });
            
            if (currentDivValue) {
                childSelect.value = currentDivValue;
                if (childSelect.value !== currentDivValue) {
                    childSelect.value = "";
                }
            }
        }

        setInterval(updateClock, 1000);
        updateClock();

        // NOTIFICATION LOGIC
        const notifBell = document.getElementById('notifBell');
        const notifDropdown = document.getElementById('notifDropdown');
        const notifCount = document.getElementById('notifCount');
        const notifContent = document.getElementById('notifContent');
        const markAllRead = document.getElementById('markAllRead');

        function fetchNotifications() {
            fetch('{{ route("notifications.get") }}', {
                credentials: 'include'
            })
                .then(response => response.json())
                .then(data => {
                    const countLabel = document.getElementById('notifCountLabel');
                    if (data.count > 0) {
                        if (notifCount) { notifCount.textContent = data.count; notifCount.style.display = 'flex'; }
                        if (countLabel) countLabel.textContent = data.count + ' New';
                        
                        let html = '';
                        data.notifications.forEach(n => {
                            html += `
                                <div class="notif-item unread" data-id="${n.id}">
                                    <div class="notif-item-inner">
                                        <div class="notif-dot"></div>
                                        <div>
                                            <div class="notif-type">${n.type}</div>
                                            <div class="notif-msg">${n.message}</div>
                                            <div class="notif-meta">
                                                <span><i class="fa-regular fa-clock"></i> ${new Date(n.created_at).toLocaleDateString()}</span>
                                                <a href="#" class="mark-read" data-id="${n.id}">Mark as read</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        if (notifContent) notifContent.innerHTML = html;
                    } else {
                        if (notifCount) notifCount.style.display = 'none';
                        if (countLabel) countLabel.textContent = '0 New';
                        if (notifContent) notifContent.innerHTML = `
                            <div class="notif-empty-alt">
                                <i class="fa-solid fa-bell-slash notif-empty-alt-icon"></i>
                                <p class="notif-empty-alt-text">No new notifications</p>
                            </div>
                        `;
                    }
                })
                .catch(() => console.error('Failed to fetch notifications'));
        }

        if (notifBell) {
            notifBell.addEventListener('click', (e) => {
                e.stopPropagation();
                notifDropdown.style.display = notifDropdown.style.display === 'block' ? 'none' : 'block';
                fetchNotifications();
            });
        }

        document.addEventListener('click', () => {
            if (notifDropdown) notifDropdown.style.display = 'none';
        });

        if (notifDropdown) {
            notifDropdown.addEventListener('click', (e) => {
                if (e.target.classList.contains('mark-read')) {
                    e.preventDefault();
                    const id = e.target.getAttribute('data-id');
                    fetch("{{ route('notifications.read', ':id') }}".replace(':id', id), {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(() => fetchNotifications()).catch(() => console.error('Failed to mark notification as read'));
                }
            });
        }

        if (markAllRead) {
            markAllRead.addEventListener('click', (e) => {
                e.preventDefault();
                fetch('{{ route("notifications.read-all") }}', {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(() => fetchNotifications()).catch(() => console.error('Failed to mark all as read'));
            });
        }

        if (notifBell) {
            fetchNotifications();
            setInterval(fetchNotifications, 60000);
        }

        (function preserveSidebarScroll() {
            const sidebar = document.getElementById('sidebar');
            const sidebarNav = sidebar ? sidebar.querySelector('nav') : null;
            if (!sidebar || !sidebarNav) return;

            const storageKey = 'cmms_sidebar_nav_scroll';

            function saveSidebarScroll() {
                try {
                    sessionStorage.setItem(storageKey, String(sidebarNav.scrollTop || 0));
                } catch (e) {}
            }

            function restoreSidebarScroll() {
                let saved = 0;
                try {
                    saved = Number(sessionStorage.getItem(storageKey) || 0);
                } catch (e) {}

                if (!Number.isFinite(saved)) saved = 0;
                sidebarNav.scrollTop = saved;
            }

            sidebarNav.addEventListener('scroll', saveSidebarScroll, { passive: true });
            sidebarNav.querySelectorAll('a.nav-link').forEach(function(link) {
                link.addEventListener('pointerdown', saveSidebarScroll);
                link.addEventListener('click', saveSidebarScroll);
            });
            window.addEventListener('beforeunload', saveSidebarScroll);
            window.addEventListener('load', function() {
                requestAnimationFrame(restoreSidebarScroll);
            });
        })();

        document.getElementById('sidebarToggle').addEventListener('click', toggleSidebar);
        document.getElementById('sidebarBackdrop').addEventListener('click', toggleSidebar);
        var mcb = document.getElementById('mobileCloseBtn');
        if (mcb) mcb.addEventListener('click', toggleSidebar);
        document.getElementById('logoutLink').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('logout-form').submit();
        });

        // Global Loading State for all standard forms
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function() {
                if (this.classList.contains('no-loading') || this.target === '_blank' || this.hasAttribute('data-ajax')) return;
                
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    // Slight delay to allow native validation to kick in
                    setTimeout(() => {
                        if (!this.checkValidity || this.checkValidity()) {
                            submitBtn.disabled = true;
                            submitBtn.dataset.originalHtml = submitBtn.innerHTML;
                            submitBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Processing...';
                            submitBtn.style.opacity = '0.8';
                            submitBtn.style.cursor = 'not-allowed';
                        }
                    }, 50);
                }
            });
        });
    </script>
    
    <script nonce="{{ $cspNonce }}">
        (function () {
            // Auto-open the Inventory accordion when the current page is inside it.
            document.querySelectorAll('.cmms-nav-group').forEach(function (group) {
                if (group.querySelector('.cmms-nav-sub-link.active')) {
                    group.classList.add('open');
                    var btn = group.querySelector('.cmms-nav-parent');
                    if (btn) btn.setAttribute('aria-expanded', 'true');
                }
            });
            // Toggle the whole parent row — no need to hit a tiny arrow.
            document.querySelectorAll('.cmms-nav-parent').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var group = btn.closest('.cmms-nav-group');
                    if (!group) return;
                    var open = group.classList.toggle('open');
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
            });
        })();
    </script>
    @yield('scripts')
    {{-- MOBILE OVERRIDES — loaded last; !important wins over all stylesheets and inline styles --}}
    <style nonce="{{ $cspNonce }}">
        @media screen and (max-width: 1000px) {
            /* ── ALL GRID LAYOUTS — force 1 column on mobile/tablet ── */
            .admin-workspace-grid,
            .user-grid,
            .workbench-grid,
            .personnel-container,
            .inventory-container,
            .stats-grid,
            .analytics-grid,
            .stats-ribbon { grid-template-columns: 1fr !important; }
            /* ── ALL TABLES — horizontal scroll ── */
            table { min-width: 450px !important; }
            .premium-table-box,
            .table-box,
            .scroll-x,
            .table-wrap,
            .table-container { overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; }
            /* ── ALL FORMS — stacked full width (exclude SweetAlert2 internals) ── */
            input:not(.swal2-input):not(.swal2-checkbox input),
            select:not(.swal2-select),
            textarea:not(.swal2-textarea) { width: 100% !important; min-height: 44px !important; font-size: 15px !important; }
            label:not(.swal2-checkbox label):not(.swal2-radio label) { display: block !important; width: 100% !important; font-size: 12px !important; }
            .form-row, .form-group { flex-direction: column !important; width: 100% !important; }
            .modal-card { width: 95vw !important; max-width: 95vw !important; }
            .modal-footer { flex-direction: column !important; gap: 10px !important; }
            /* —— ALL BUTTONS — full width (exclude topbar icon buttons & SweetAlert buttons) —— */
            button:not(#sidebarToggle):not(#notifBell):not(.mobile-close-btn):not(.btn-dropdown-toggle):not(.swal2-confirm):not(.swal2-cancel):not(.swal2-deny),
            .btn, .btn-action-premium, .action-button-premium,
            .btn-view-modern, .btn-action-modern { width: 100% !important; }
        }

        /* ══════════════════════════════════════════════════════════
           STICKY ACTION BAR (For Long Forms)
        ══════════════════════════════════════════════════════════ */
        .sticky-action-bar {
            position: sticky;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            padding: 15px;
            border-top: 1px solid #e2e8f0;
            box-shadow: 0 -4px 10px rgba(0,0,0,0.05);
            z-index: 50;
            border-radius: 0 0 10px 10px;
        }

        @media screen and (max-width: 1000px) {
            /* Ensure topbar UI controls stay compact */
            #sidebarToggle { width: 38px !important; flex-shrink: 0 !important; }
            .notification-wrapper { flex-shrink: 0 !important; }

            /* Signature Pad Mobile Fix */
            canvas, .sig-canvas, .signature-pad { touch-action: none !important; }
        }
        @media screen and (max-width: 767px) {
            .card-header-accent { flex-direction: column !important; align-items: flex-start !important; gap: 10px !important; }
            .card-body-content { padding: 15px !important; }
            .filter-ribbon { flex-direction: column !important; gap: 10px !important; }
            .filter-ribbon .ribbon-input { width: 100% !important; }
            .gov-table-premium th, .gov-table-premium td { padding: 8px 10px !important; font-size: 12px !important; }
            .gov-table-premium { font-size: 13px !important; }
            .modal-card { width: calc(100vw - 20px) !important; max-width: 100% !important; margin: 10px !important; max-height: 85vh !important; }
            .modal-body { padding: 15px 12px !important; }
            .modal-overlay { padding: 10px !important; }
            .stats-ribbon { grid-template-columns: repeat(2, 1fr) !important; gap: 10px !important; }
            .stat-item-premium { padding: 12px !important; }
            .stat-item-premium .stat-info p { font-size: 10px !important; }
            .stat-item-premium .stat-info h4 { font-size: 18px !important; }
            .welcome-hero { padding: 20px !important; }
            .welcome-hero h2 { font-size: 18px !important; }
            .welcome-hero p { font-size: 13px !important; }
            .btn-action-premium { width: 100% !important; justify-content: center !important; }
            .dashboard-container > div[style*="display: grid"],
            .dashboard-container > div[style*="display:grid"] { grid-template-columns: 1fr !important; gap: 15px !important; }
            div[style*="grid-template-columns: 1fr 320px"] { grid-template-columns: 1fr !important; gap: 15px !important; }
            .form-grid-simple { grid-template-columns: 1fr !important; gap: 15px !important; }
            
            /* Hardware-accelerated sidebar sliding */
            .sidebar { left: 0 !important; transform: translateX(-100%) !important; width: min(280px, 85vw) !important; transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1) !important; will-change: transform !important; }
            .sidebar.sidebar-open { transform: translateX(0) !important; }
            #sidebarBackdrop { z-index: 99998 !important; }
            
            /* Add "Swipe ->" hint for tables */
            .table-wrap::after, .table-container::after {
                content: 'Swipe left to view more ➔';
                position: absolute;
                bottom: -20px;
                right: 0;
                font-size: 10px;
                color: #64748b;
                font-weight: 700;
                opacity: 0.8;
                pointer-events: none;
            }
            .table-wrap, .table-container { position: relative; margin-bottom: 25px !important; }
        }

        /* ══════════════════════════════════════════════════════════
           GLOBAL SWEETALERT2 MOBILE FIX
           The global input/button overrides above were making
           SweetAlert2 internal elements (checkbox, inputs, buttons)
           render incorrectly on mobile. These rules restore them.
           Applied globally — covers ALL roles and ALL pages.
        ══════════════════════════════════════════════════════════ */
        .swal2-popup input,
        .swal2-popup select,
        .swal2-popup textarea {
            width: auto !important;
            min-height: auto !important;
            font-size: inherit !important;
        }
        .swal2-popup label {
            display: inline !important;
            width: auto !important;
            font-size: inherit !important;
        }
        .swal2-checkbox {
            display: none !important;
        }
        .swal2-confirm,
        .swal2-cancel {
            width: auto !important;
            min-height: auto !important;
        }
        .swal2-deny { display: none !important; }
        .swal2-actions { flex-direction: row !important; gap: 4px !important; }
        .swal2-popup .swal2-actions button.swal2-confirm { width: auto !important; min-width: 80px !important; }
        .swal2-popup .swal2-actions button[style*="display: none"] { display: none !important; }
    </style>
</body>
</html>
