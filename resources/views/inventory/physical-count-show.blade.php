@extends('layouts.app')

@section('title', 'Physical Count #' . $session->id)
@section('page-title', 'Physical Inventory Count')

@section('styles')
<style nonce="{{ $cspNonce }}">
    .pc-container { width: 100%; margin-top: -10px; animation: fadeInSlide 0.4s ease-out; }
    .polish-card { background: white; border-radius: 10px; border: 1px solid #e2e8f0; overflow: hidden; }
    .card-header-accent { background: #f8fafc; padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
    .card-body-content { padding: 20px 24px; }
    .btn-primary { background: #0038A8; color: white; border: none; padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
    .btn-primary:hover { background: #002d8c; color: white; }
    .btn-secondary { background: white; color: #475569; border: 1px solid #cbd5e1; padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
    .btn-secondary:hover { background: #f8fafc; border-color: #0038A8; color: #0038A8; }
    .btn-success { background: #16a34a; color: white; border: none; padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 800; cursor: pointer; }
    .btn-success:hover { background: #15803d; }
    .btn-danger { background: #dc2626; color: white; border: none; padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 800; cursor: pointer; }

    .stats-bar { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 18px; }
    .stat-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; text-align: center; }
    .stat-box p { margin: 0; font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; }
    .stat-box h3 { margin: 4px 0 0; font-size: 22px; font-weight: 800; color: #1e293b; }

    .search-area { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 18px; }
    .search-input-wrap { display: flex; gap: 10px; align-items: center; }
    .search-input-wrap input { flex: 1; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; outline: none; }
    .search-input-wrap input:focus { border-color: #0038A8; box-shadow: 0 0 0 3px rgba(0,56,168,0.1); }
    .search-field { position: relative; flex: 1; display: flex; align-items: center; }
    .search-field input { width: 100%; padding-left: 38px; padding-right: 40px; }
    .search-icon-inside { position: absolute; left: 12px; color: #94a3b8; pointer-events: none; font-size: 14px; }
    .clear-x-btn {
        position: absolute; right: 8px;
        width: 26px; height: 26px; border: none; border-radius: 50%;
        background: #e2e8f0; color: #475569; cursor: pointer;
        display: none; align-items: center; justify-content: center;
        font-size: 12px; padding: 0; line-height: 1;
    }
    .clear-x-btn:hover { background: #cbd5e1; }
    .clear-x-btn.visible { display: flex; }
    .search-results { margin-top: 12px; display: none; }
    .search-result-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: white; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 6px; }
    .custodian-block { border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 14px; overflow: hidden; }
    .custodian-header { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; }
    .custodian-name { font-size: 13px; font-weight: 800; color: #1e293b; }
    .custodian-par { font-size: 11px; font-weight: 700; color: #0038A8; font-family: monospace; margin-left: 8px; }
    .custodian-progress { font-size: 11px; font-weight: 800; color: #64748b; margin-left: 8px; }
        .search-group-header { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 14px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; margin-bottom: 6px; font-size: 13px; flex-wrap: wrap; }
        .search-group-header .group-count { font-size: 11px; font-weight: 800; color: #0038A8; background: white; border: 1px solid #bfdbfe; padding: 2px 8px; border-radius: 99px; }
        .search-custodian { font-size: 11px; color: #64748b; margin-top: 2px; }
    .search-result-item:last-child { margin-bottom: 0; }

    .mark-btn { padding: 6px 14px; border-radius: 4px; font-size: 11px; font-weight: 800; border: none; cursor: pointer; }
    .mark-operational { background: #dcfce7; color: #166534; }
    .mark-operational:hover { background: #bbf7d0; }
    .mark-non-ops { background: #fef2f2; color: #991b1b; }
    .mark-non-ops:hover { background: #fecaca; }

    .counted-operational { background: #f0fdf4; }
    .counted-non-ops { background: #fef2f2; }

    .status-pill-small { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
    .pill-operational { background: #dcfce7; color: #166534; }
    .pill-non-ops { background: #fef2f2; color: #991b1b; }

    .btn-scan { background: #0038A8; color: white; border: none; padding: 14px 20px; border-radius: 10px; font-size: 15px; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; margin-top: 12px; }
    .btn-scan:hover { background: #002d8c; }

    .asset-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .asset-table th { background: #f1f5f9; padding: 10px 12px; font-size: 10px; font-weight: 800; color: #475569; text-transform: uppercase; text-align: left; border-bottom: 2px solid #e2e8f0; }
    .asset-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; }
    .asset-table tr:hover td { background: #f8fafc; }

    /* ── BUG FIX (mobile): ang scanner modal ay may `overflow: hidden` at walang
       max-height → sa maikling screen (o landscape) na-clip ang Cancel/Scan
       buttons. Ngayon: max-height + scroll ang box at ang overlay. ── */
    .scanner-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 12px; overflow-y: auto; box-sizing: border-box; }
    .scanner-modal { background: white; border-radius: 12px; width: 100%; max-width: 420px; max-height: calc(100dvh - 24px); overflow-y: auto; box-sizing: border-box; animation: fadeInSlide 0.3s ease-out; }
    .scanner-modal-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .scanner-modal-header h4 { margin: 0; font-size: 16px; font-weight: 800; color: #1e293b; }
    .scanner-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; padding: 0 4px; }
    .scanner-modal-body { padding: 16px; }
    #scannerContainer { width: 100%; aspect-ratio: 1; max-height: 320px; background: #000; border-radius: 8px; overflow: hidden; }
    #scannerContainer video { width: 100% !important; height: auto !important; }

    .profile-card { background: white; border: 2px solid #0038A8; border-radius: 12px; overflow: hidden; margin-top: 12px; animation: fadeInSlide 0.3s ease-out; }
    .profile-card-inner { padding: 16px; }
    .profile-header { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; padding-bottom: 14px; border-bottom: 1px solid #e2e8f0; }
    .profile-serial { font-size: 11px; color: #64748b; }
    .profile-details { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; margin-top: 12px; margin-bottom: 12px; }
    .detail-row { font-size: 12px; min-width: 0; }
    .detail-row span { color: #64748b; }
    .detail-row strong { color: #1e293b; display: block; overflow-wrap: anywhere; }
    /* ── Scan result: COMPACT na listahan — pangalan ng custodian sa TOP,
       sunod-sunod na asset, at isang linya ng mahahalagang detalye bawat isa
       (SN · PAR · Property · Category · Status). Walang icon, walang
       vertical line, walang box border kada row. ── */
    #scanResultCard .scan-asset-block .search-result-item {
        border: none; border-radius: 0; padding: 0; margin: 0; background: transparent;
        gap: 10px; align-items: flex-start;
    }
    #scanResultCard .search-result-item > div:first-child { min-width: 0; flex: 1 1 auto; }
    #scanResultCard .search-result-item > div:last-child { flex: 0 0 auto; display: flex; gap: 6px; align-items: center; }
    .scan-asset-block { padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
    .scan-asset-block:last-child { padding-bottom: 0; border-bottom: none; }
    .scan-meta { font-size: 11px; color: #64748b; line-height: 1.6; margin-top: 3px; overflow-wrap: anywhere; }
    .scan-meta b { color: #475569; font-weight: 800; }
    .scan-meta-sep { color: #cbd5e1; margin: 0 5px; }
    /* Ngayon, ito na ang ROOT block ng card: custodian header + listahan */
    .scan-other-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; padding-bottom: 10px; margin-bottom: 4px; border-bottom: 1px solid #e2e8f0; }
    /* Header = pangalan ng custodian (hindi ang na-scan) */
    .scan-owner { font-size: 14px; font-weight: 800; color: #1e293b; min-width: 0; overflow-wrap: anywhere; }
    .scan-owner-label { display: block; font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px; }
    .scan-scanned-badge { font-size: 10px; font-weight: 800; color: #0038A8; background: #eff6ff; border: 1px solid #bfdbfe; padding: 2px 8px; border-radius: 99px; margin-left: 6px; }
    .scan-mark-all[hidden] { display: none; }

    @media screen and (max-width: 640px) {
        .stats-bar { grid-template-columns: repeat(2, 1fr); }
        .stat-box h3 { font-size: 18px; }
        /* Stats cards (inline-styled sa markup): 2x2 sa phone para hindi siksik */
        .show-stats-grid { grid-template-columns: repeat(2, 1fr) !important; }
        .show-stats-grid > div { padding: 8px !important; }
        .show-stats-grid h3 { font-size: 19px !important; }
        /* Full-width ang Scan QR para madaling tamaan ng daliri */
        .btn-scan { padding: 15px 16px !important; font-size: 15px !important; }
        .btn-complete-session { width: 100% !important; min-height: 48px !important; }
        .search-input-wrap { flex-wrap: wrap; }
        .search-input-wrap .search-field { flex: 1 1 100%; }
        .profile-details { grid-template-columns: 1fr; }
        .profile-actions { flex-direction: column; }
        .profile-actions .mark-btn { width: 100%; padding: 14px !important; font-size: 15px !important; }
        /* ── Scan result card: parehong itsura ng normal na row; huwag payagan
           maipit ang teksto sa tabi ng buttons (wrap kapag sapat na ang liit) ── */
        #scanResultCard .profile-card-inner { padding: 12px !important; }
        #scanResultCard .search-result-item { flex-wrap: wrap; row-gap: 8px; }
        #scanResultCard .search-result-item > div:first-child { flex: 1 1 auto; }
        #scanResultCard .mark-btn { min-height: 40px; }
        #scanResultCard .profile-details { gap: 6px 12px; }
        .scan-other-head { margin-bottom: 6px; }
        .scan-mark-all { width: 100% !important; min-height: 44px !important; font-size: 12.5px !important; }
        .card-body-content { padding: 12px 14px; }
        .asset-table { font-size: 11px; }
        .asset-table th, .asset-table td { padding: 6px 8px; }
        /* Scanner modal: naka-top + maliit na video para laging kita ang buttons */
        .scanner-modal-overlay { align-items: flex-start !important; }
        #scannerContainer { max-height: 44vh; }
        .scanner-modal-body button, .scanner-modal-body .btn { min-height: 48px !important; }
    }
    /* Hide QR scanner on desktop — phone only */
    @media (min-width: 768px) {
        .btn-scan { display: none !important; }
        #scannerModalOverlay { display: none !important; }
    }

    /* Utility classes */
    .back-link { color: #0038A8; font-size: 13px; font-weight: 700; text-decoration: none; }
    .session-info-box { background: #f0fdf4; border: 1px solid #86efac; border-radius: 10px; padding: 18px 22px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
    .session-info-label { font-size: 11px; font-weight: 800; color: #15803d; text-transform: uppercase; }
    .session-info-text { font-size: 13px; color: #1e293b; margin-top: 2px; }
    .session-actions-wrap { display: flex; gap: 8px; align-items: center; }
    .inline-form { display:inline; }
    .stat-box-blue { background:#eff6ff; }
    .stat-box-green { background:#f0fdf4; }
    .stat-box-red { background:#fef2f2; }
    .stat-value-blue { color:#1d4ed8; }
    .stat-value-green { color:#16a34a; }
    .stat-value-red { color:#dc2626; }
    .card-h4 { margin:0; font-size:15px; font-weight:800; color:#1e293b; }
    .search-icon-gray { color:#94a3b8; }
    .btn-secondary-sm { font-size: 12px; }
    .scanner-modal-text { text-align:center;font-size:12px;color:#64748b;margin:10px 0 6px; }
    .btn-cancel-scanner { width:100%;justify-content:center;margin-top:4px; }
    .scroll-x { overflow-x: auto; }
    .th-center { text-align: center !important; }
    .td-bold { font-weight:700; }
    .td-mono { font-family:monospace;font-size:12px; }
    .td-actions { text-align:center; vertical-align: middle; white-space: nowrap; }
    .action-btn-group { display:flex;gap:4px;justify-content:center; }
    .disabled-btn { opacity:0.3; }
    .not-counted-text { color:#94a3b8;font-size:11px; }
    .empty-table { text-align:center;padding:40px;color:#94a3b8; }
    .mb-16 { margin-bottom: 16px; }
    .mb-12 { margin-bottom: 12px; }
    .hidden-display { display: none; }
    .search-no-result { padding:12px;color:#94a3b8;text-align:center; }
    .search-sn { color:#64748b;font-size:12px;margin-left:8px; }
    .counted-text { color:#16a34a;font-size:12px;font-weight:700; }
    .profile-serial { font-size:11px;color:#64748b; }
    .counted-box { text-align:center;padding:12px;background:#f0fdf4;border-radius:6px;color:#166534;font-weight:700;font-size:13px; }
    .counted-note {
        background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8;
        border-radius:8px; padding:10px 14px; margin-bottom:12px;
        font-size:12.5px; font-weight:700;
    }
    .counted-note[hidden] { display: none; }
    .mark-btn-flex { flex:1;padding:12px;font-size:14px; }

    /* ── Pagination: Prev / Page X of Y / Next bar — MOBILE ONLY; desktop uses custom links ── */
    .custodian-pagination { margin-top: 14px; }
    .pag-mobile-bar { display: none; }
    .pag-desktop { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
    .pag-desktop .pag-info { flex: 0 0 auto; }
    .pag-btns { display: flex; gap: 5px; flex-wrap: wrap; }
    .pag-active { background: #0038A8 !important; color: white !important; border-color: #0038A8 !important; }
    .pag-mobile { display: flex; align-items: stretch; gap: 8px; }
    .pag-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        min-width: 84px; min-height: 46px; padding: 10px 14px;
        border: 1px solid #cbd5e1; background: white; border-radius: 10px;
        font-size: 13px; font-weight: 800; color: #1e293b; text-decoration: none;
    }
    a.pag-btn:hover { background: #f1f5f9; }
    a.pag-btn:active { background: #e2e8f0; }
    .pag-btn.pag-disabled { opacity: 0.35; }
    .pag-info {
        flex: 1; display: flex; align-items: center; justify-content: center;
        background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
        font-size: 12px; font-weight: 700; color: #475569; padding: 10px 8px;
    }
    @media screen and (max-width: 767px) {
        .stats-bar { grid-template-columns: repeat(2, 1fr) !important; gap: 8px !important; }
        input[type="checkbox"] { display: none !important; }
        .swal2-checkbox { display: none !important; }
        .session-info-box { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; }
        .session-actions-wrap { width: 100% !important; flex-direction: column !important; gap: 8px !important; }
        .session-actions-wrap a,
        .session-actions-wrap button,
        .session-actions-wrap form { width: 100% !important; }
        .session-actions-wrap .btn-primary,
        .session-actions-wrap .btn-secondary,
        .session-actions-wrap .btn-success { width: 100% !important; justify-content: center !important; }
        .search-input-wrap { flex-direction: column !important; gap: 8px !important; }
        .search-input-wrap input { width: 100% !important; }
        .search-input-wrap .btn-secondary { width: 100% !important; justify-content: center !important; }
        .action-btn-group { flex-direction: column !important; gap: 4px !important; }
        .action-btn-group .mark-btn { width: 100% !important; padding: 10px !important; font-size: 13px !important; min-height: 44px !important; }
        .search-result-item { flex-direction: column !important; align-items: flex-start !important; gap: 8px !important; }
        .search-result-item > div:last-child { width: 100% !important; display: flex !important; flex-direction: column !important; gap: 4px !important; }
        .search-result-item .mark-btn { width: 100% !important; padding: 10px !important; font-size: 13px !important; min-height: 44px !important; }
        .back-link { display: block !important; width: 100% !important; text-align: center !important; padding: 10px !important; border: 1px solid #cbd5e1 !important; border-radius: 6px !important; background: white !important; }
        input[type="checkbox"] { display: none !important; }
        .asset-table { min-width: 600px !important; }

        /* ── Custodian asset cards — walk-around mobile UX (desktop unaffected) ── */
        .custodian-block table { min-width: 0 !important; }
        .custodian-block table thead { display: none !important; }
        .custodian-block table,
        .custodian-block tbody,
        .custodian-block tr,
        .custodian-block td { display: block !important; width: 100% !important; }
        .custodian-block tr { border: 1px solid #e2e8f0 !important; border-radius: 8px !important; padding: 10px 12px !important; margin-bottom: 10px !important; background: white !important; }
        .custodian-block tr.counted-operational { background: #f0fdf4 !important; border-color: #bbf7d0 !important; }
        .custodian-block tr.counted-non-ops { background: #fef2f2 !important; border-color: #fecaca !important; }
        .custodian-block td { padding: 2px 0 !important; border: none !important; font-size: 12px; }
        .custodian-block td[data-label]::before { content: attr(data-label) ": "; font-weight: 800; color: #94a3b8; font-size: 10px; text-transform: uppercase; letter-spacing: 0.3px; }
        .custodian-block td.td-bold { font-size: 14px !important; }
        .custodian-block td.td-mono { font-size: 11px !important; }
        .custodian-block td[data-label="Status"]::before { content: none !important; }
        .custodian-header { flex-direction: column !important; align-items: stretch !important; }
        .custodian-header .mark-btn { width: 100% !important; min-height: 46px !important; border-radius: 10px !important; font-size: 14px !important; }

        /* Sticky search/scan bar — always reachable while walking (below sticky topbar) */
        .search-area { position: sticky !important; top: 58px !important; z-index: 80; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }

        /* Complete Session — same length/height as Scan QR button */
        /* Scan QR — bigger touch target, consistent rounding */
        .btn-scan { border-radius: 10px !important; min-height: 50px !important; font-size: 15px !important; }

        /* ── Compact stats chips — single row, less scroll to reach the list ── */
        .show-stats-grid {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 0 !important;
            background: white !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 10px !important;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .show-stats-grid > div {
            padding: 10px 4px !important;
            border-radius: 0 !important;
            border: none !important;
            border-right: 1px solid #f1f5f9 !important;
            min-width: 0 !important;
        }
        .show-stats-grid > div:last-child { border-right: none !important; }
        .show-stats-grid p { font-size: 8px !important; letter-spacing: 0.3px !important; }
        .show-stats-grid h3 { font-size: 17px !important; margin-top: 2px !important; }

        /* ── Header buttons — stacked full-width touch targets ── */
        .card-header-accent > div:first-child { flex-direction: column !important; align-items: stretch !important; }
        .card-header-accent > div:first-child > div {
            width: 100% !important;
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 6px !important;
        }
        .card-header-accent .btn-secondary-sm {
            width: 100% !important;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            min-height: 44px !important;
            border-radius: 8px !important;
            font-size: 12px !important;
        }

        /* ── Mark buttons side-by-side inside asset cards (less vertical bulk) ── */
        .action-btn-group { display: grid !important; grid-template-columns: 1fr 1fr !important; gap: 6px !important; }
        .action-btn-group .mark-btn {
            width: 100% !important;
            min-height: 46px !important;
            border-radius: 10px !important;
            font-size: 13px !important;
            padding: 10px 4px !important;
        }

        /* ── Pagination — visible, centered, 44px touch targets ── */
        /* Pagination switch: mobile bar visible, standard links hidden */
        .pag-desktop-links { display: none !important; }
        .pag-mobile-bar { display: block !important; }

        /* Complete Session — exact match of Scan QR button (length + height) */
        .search-input-wrap form { width: 100% !important; display: block !important; }
        .search-input-wrap .btn-complete-session {
            width: 100% !important;
            min-height: 50px !important;
            border-radius: 10px !important;
            font-size: 15px !important;
            font-weight: 800 !important;
            padding: 14px 20px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-sizing: border-box !important;
        }
        /* Scan QR — bigger touch target, consistent rounding */
        .btn-scan {
            border-radius: 10px !important;
            min-height: 50px !important;
            font-size: 15px !important;
            margin-top: 8px !important;
        }
    }
</style>
@endsection

@section('content')
<div class="pc-container">
    <div class="mb-16">
        <a href="{{ route('physical-count.index') }}" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Count Sessions
        </a>
    </div>

    <div class="polish-card">
        <div class="card-header-accent" style="flex-direction:column;align-items:stretch;gap:14px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <h4 class="card-h4">
                    Asset List
                </h4>
                @if($session->status !== 'Ongoing')
                <div style="display:flex;gap:8px;align-items:center;">
                    <a href="{{ route('physical-count.export', $session->id) }}" class="btn-secondary btn-secondary-sm">
                        <i class="fa-solid fa-download"></i> Export CSV
                    </a>
                    <a href="{{ route('physical-count.print', $session->id) }}" class="btn-secondary btn-secondary-sm" target="_blank">
                        <i class="fa-solid fa-print"></i> Print Report
                    </a>
                    <a href="{{ route('physical-count.print', ['sessionId' => $session->id, 'group' => 'custodian']) }}" class="btn-secondary btn-secondary-sm" target="_blank">
                        <i class="fa-solid fa-user-tag"></i> Print by Custodian
                    </a>
                </div>
                @endif
            </div>
            <div class="show-stats-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px;text-align:center;">
                    <p style="margin:0;font-size:10px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;">Total Assets</p>
                    <h3 id="statTotal" style="margin:4px 0 0;font-size:24px;font-weight:800;color:#1e293b;">{{ $summary['total'] }}</h3>
                </div>
                <div style="background:#eff6ff;border:1px solid #dbeafe;border-radius:8px;padding:10px;text-align:center;">
                    <p style="margin:0;font-size:10px;font-weight:800;color:#1d4ed8;text-transform:uppercase;letter-spacing:0.3px;">Counted</p>
                    <h3 id="statCounted" style="margin:4px 0 0;font-size:24px;font-weight:800;color:#1d4ed8;">{{ $summary['counted'] }}</h3>
                </div>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px;text-align:center;">
                    <p style="margin:0;font-size:10px;font-weight:800;color:#16a34a;text-transform:uppercase;letter-spacing:0.3px;">Operational</p>
                    <h3 id="statOperational" style="margin:4px 0 0;font-size:24px;font-weight:800;color:#16a34a;">{{ $summary['present'] }}</h3>
                </div>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px;text-align:center;">
                    <p style="margin:0;font-size:10px;font-weight:800;color:#dc2626;text-transform:uppercase;letter-spacing:0.3px;">Non-Ops</p>
                    <h3 id="statNonOps" style="margin:4px 0 0;font-size:24px;font-weight:800;color:#dc2626;">{{ $summary['missing'] + $summary['damaged'] }}</h3>
                </div>
            </div>
        </div>
        <div class="card-body-content">
            @if($session->status === 'Ongoing')
            <div class="search-area">
                <div class="search-input-wrap">
                    <div class="search-field">
                        <i class="fa-solid fa-magnifying-glass search-icon-inside"></i>
                        <input type="text" id="scanSearchInput" placeholder="Search or scan QR..." autocomplete="off">
                        <button type="button" id="clearSearchBtn" class="clear-x-btn" aria-label="Clear search"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <form id="completeSessionForm" method="POST" action="{{ route('physical-count.complete', $session->id) }}" class="inline-form" style="margin:0;">
                        @csrf
                        <button type="submit" class="btn-success btn-complete-session">Complete Session</button>
                    </form>
                </div>
                <button id="openScannerBtn" class="btn-scan">
                    <i class="fa-solid fa-camera"></i> Scan QR
                </button>
                <div id="scanResultCard" class="profile-card hidden-display"></div>
                <div id="searchResults" class="search-results"></div>
            </div>

            <div id="scannerModalOverlay" class="scanner-modal-overlay hidden-display">
                <div class="scanner-modal">
                    <div class="scanner-modal-header">
                        <h4><i class="fa-solid fa-camera"></i> Scan QR</h4>
                        <button class="close-scanner-btn scanner-modal-close">&times;</button>
                    </div>
                    <div class="scanner-modal-body">
                        <div id="scannerContainer"></div>
                        <p class="scanner-modal-text">Point your camera at the asset's QR sticker</p>
                        <button class="close-scanner-btn btn-secondary btn-cancel-scanner">Cancel</button>
                    </div>
                </div>
            </div>
            @endif

            @if($session->status === 'Ongoing')
            {{-- Pending-only view: ipakita kung ilan ang nakatago (na-count na) --}}
            <div class="counted-note" id="countedNote" data-counted="{{ $summary['counted'] }}" @if($summary['counted'] < 1) hidden @endif>
                Showing uncounted assets only — {{ $summary['counted'] }} already counted {{ $summary['counted'] === 1 ? 'asset is' : 'assets are' }} hidden.
            </div>
            @endif

            <div class="scroll-x" id="groupsWrap">
                @forelse($custodianGroups as $group)
                    @php
                        $pending = $group['assets']->filter(fn ($a) => !in_array($a->asset_id, $countedIds));
                    @endphp
                    <div class="custodian-block" data-group-key="{{ $group['key'] }}" data-group-total="{{ $group['total'] }}" data-group-counted="{{ $group['counted'] }}">
                        <div class="custodian-header">
                            <div>
                                <strong class="custodian-name">{{ $group['name'] }}</strong>
                                @if($group['par'])<span class="custodian-par">PAR: {{ $group['par'] }}</span>@endif
                                <span class="custodian-progress" data-role="group-progress">{{ $group['counted'] }}/{{ $group['total'] }} counted</span>
                            </div>
                            @if($session->status === 'Ongoing' && $pending->isNotEmpty())
                            <button type="button" class="mark-btn mark-operational" data-action="mark-all-group" data-role="mark-all" data-ids="{{ $pending->pluck('asset_id')->implode(',') }}">
                                Mark all Present ({{ $pending->count() }})
                            </button>
                            @endif
                        </div>
                        <table class="asset-table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Item Name</th>
                                    <th>Serial No</th>
                                    <th>PAR No</th>
                                    <th>Property No</th>
                                    <th>Category</th>
                                    @if($session->status === 'Ongoing')
                                    <th class="th-center">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($group['assets'] as $asset)
                                    @php
                                        $count = $session->counts->firstWhere('asset_id', $asset->asset_id);
                                        $displayStatus = $count ? ($count->status === 'Present' ? 'Operational' : 'Non-Operational') : 'Not Counted';
                                        $rowClass = $count ? ($count->status === 'Present' ? 'counted-operational' : 'counted-non-ops') : '';
                                        $pillClass = $count ? ($count->status === 'Present' ? 'pill-operational' : 'pill-non-ops') : '';
                                    @endphp
                                    <tr class="{{ $rowClass }}" data-asset-id="{{ $asset->asset_id }}">
                                        <td data-label="Status">
                                            @if($count)
                                                <span class="status-pill-small {{ $pillClass }}">{{ $displayStatus }}</span>
                                            @else
                                                <span class="not-counted-text">Not Counted</span>
                                            @endif
                                        </td>
                                        <td class="td-bold" data-label="Item">{{ $asset->item_name }}</td>
                                        <td class="td-mono" data-label="Serial">{{ $asset->serial_number ?? '—' }}</td>
                                        <td data-label="PAR">{{ $asset->par_number ?? '—' }}</td>
                                        <td class="td-mono" data-label="Property">{{ $asset->property_number ?? '—' }}</td>
                                        <td data-label="Category">{{ $asset->category ?? '—' }}</td>
                                        @if($session->status === 'Ongoing')
                                        <td class="td-actions" data-label="Action">
                                            <div class="action-btn-group">
                                                <button data-action="mark-asset" data-asset-id="{{ $asset->asset_id }}" data-status="Present" class="mark-btn mark-operational {{ $count ? 'disabled-btn' : '' }}" {{ $count ? 'disabled' : '' }}>Operational</button>
                                                <button data-action="mark-asset" data-asset-id="{{ $asset->asset_id }}" data-status="Missing" class="mark-btn mark-non-ops {{ $count ? 'disabled-btn' : '' }}" {{ $count ? 'disabled' : '' }}>Non-Operational</button>
                                            </div>
                                        </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <div class="empty-table" id="groupsEmptyState">
                        @if($session->status === 'Ongoing' && $summary['counted'] > 0)
                            All assets in your scope are already counted ({{ $summary['counted'] }} counted).
                        @else
                            No assets found in your scope.
                        @endif
                    </div>
                @endforelse
            </div>
            <div class="custodian-pagination">
                <div class="pag-desktop-links">
                    @if($custodianGroups->lastPage() > 1)
                    <div class="pag-desktop">
                        <span class="pag-info">Showing {{ $custodianGroups->firstItem() }} to {{ $custodianGroups->lastItem() }} of {{ $custodianGroups->total() }} custodians</span>
                        <div class="pag-btns">
                            @if($custodianGroups->currentPage() > 1)
                            <a href="{{ $custodianGroups->previousPageUrl() }}" class="pag-btn">&lsaquo; Prev</a>
                            @endif
                            @foreach($custodianGroups->getUrlRange(1, $custodianGroups->lastPage()) as $p => $url)
                                @if($p == $custodianGroups->currentPage())
                                <span class="pag-btn pag-active">{{ $p }}</span>
                                @else
                                <a href="{{ $url }}" class="pag-btn">{{ $p }}</a>
                                @endif
                            @endforeach
                            @if($custodianGroups->hasMorePages())
                            <a href="{{ $custodianGroups->nextPageUrl() }}" class="pag-btn">Next &rsaquo;</a>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
                <div class="pag-mobile-bar">
                    @if($custodianGroups->lastPage() > 1)
                    <div class="pag-mobile">
                        @if($custodianGroups->currentPage() > 1)
                        <a href="{{ $custodianGroups->previousPageUrl() }}" class="pag-btn"><i class="fa-solid fa-chevron-left"></i> Prev</a>
                        @else
                        <span class="pag-btn pag-disabled"><i class="fa-solid fa-chevron-left"></i> Prev</span>
                        @endif
                        <span class="pag-info">Page {{ $custodianGroups->currentPage() }} of {{ $custodianGroups->lastPage() }}</span>
                        @if($custodianGroups->hasMorePages())
                        <a href="{{ $custodianGroups->nextPageUrl() }}" class="pag-btn">Next <i class="fa-solid fa-chevron-right"></i></a>
                        @else
                        <span class="pag-btn pag-disabled">Next <i class="fa-solid fa-chevron-right"></i></span>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}" src="{{ asset('js/html5-qrcode.min.js') }}"></script>
<script nonce="{{ $cspNonce }}" src="{{ asset('js/qr-scanner.js') }}?v={{ time() }}"></script>
<script nonce="{{ $cspNonce }}">
const SESSION_ID = {{ $session->id }};
const SEARCH_URL = '{{ route("physical-count.search", $session->id) }}';
const MARK_URL = '{{ route('physical-count.mark', $session->id) }}';
const API_PROFILE_URL = '{{ route('api.asset.profile', '_ID_') }}';
const COUNTED_IDS = @json($countedIds);
let searchTimeout;

/* 429 (rate limit) — dapat LAGING nakikita ng user. Dati ito ay silent:
   walang laman ang search, "already counted" ang maling summary ng markMany,
   at nakadikit ang "..." sa mark button. */
function pcRetryAfter(res) {
    var s = parseInt(res.headers.get('Retry-After') || '', 10);
    return (!isNaN(s) && s > 0) ? s : 30;
}
function pcTooManyMsg(res) {
    return 'Masyadong mabilis — maghintay ng ~' + pcRetryAfter(res) + ' segundo bago ulit subukan.';
}

document.getElementById('scanSearchInput')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim();
    const clearBtn = document.getElementById('clearSearchBtn');
    if (clearBtn) clearBtn.classList.toggle('visible', q.length > 0);
    if (q.length < 1) {
        document.getElementById('searchResults').style.display = 'none';
        return;
    }
    searchTimeout = setTimeout(() => searchAsset(q), 300);
});

async function searchAsset(q) {
    const container = document.getElementById('searchResults');
    try {
        const formData = new FormData();
        formData.set('q', q);

        const res = await fetch(SEARCH_URL, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData,
        });
        if (res.status === 429) {
            container.innerHTML = '<div class="search-no-result">' + pcTooManyMsg(res) + '</div>';
            container.style.display = 'block';
            return;
        }
        const data = await res.json();
        if (!data.success) return;

        // Custodian group: text matched exactly one user → their whole assigned set
        if (data.custodian_group) {
            renderCustodianGroup(data.custodian_group, data.counted_ids);
            container.style.display = 'block';
            return;
        }

        if (data.assets.length === 0) {
            container.innerHTML = '<div class="search-no-result">No matching assets found.</div>';
            container.style.display = 'block';
            return;
        }

        container.innerHTML = data.assets.map(a => {
            const counted = data.counted_ids.includes(a.asset_id);
            return `<div class="search-result-item">
                <div>
                    <strong>${a.item_name}</strong>
                    <span class="search-sn">SN: ${a.serial_number || 'N/A'}</span>
                    ${a.assigned_user ? `<div class="search-custodian">${a.assigned_user.full_name}</div>` : ''}
                </div>
                <div data-asset-actions="${a.asset_id}">
                    ${!counted ? `
                    <button data-action="mark-asset" data-asset-id="${a.asset_id}" data-status="Present" class="mark-btn mark-operational">Operational</button>
                    <button data-action="mark-asset" data-asset-id="${a.asset_id}" data-status="Missing" class="mark-btn mark-non-ops">Non-Operational</button>
                    ` : '<span class="counted-text">Counted</span>'}
                </div>
            </div>`;
        }).join('');
        container.style.display = 'block';
    } catch (e) {
        console.error('Search error:', e);
    }
}

function clearSearch() {
    document.getElementById('scanSearchInput').value = '';
    document.getElementById('searchResults').style.display = 'none';
    const clearBtn = document.getElementById('clearSearchBtn');
    if (clearBtn) clearBtn.classList.remove('visible');
}

function renderCustodianGroup(group, countedIds) {
    const container = document.getElementById('searchResults');
    const pending = group.assets.filter(a => !countedIds.includes(a.asset_id));

    let html = '<div class="search-group-header">'
        + '<div>Assets of <strong>' + group.full_name + '</strong>'
        + ' <span class="group-count">' + group.total + (group.total === 1 ? ' item' : ' items') + '</span></div>'
        + (pending.length > 0
            ? '<button type="button" class="mark-btn mark-operational" data-action="mark-all-group" data-ids="' + pending.map(a => a.asset_id).join(',') + '">Mark all Present (' + pending.length + ')</button>'
            : '<span class="counted-text">All Counted</span>')
        + '</div>';

    html += group.assets.map(a => {
        const counted = countedIds.includes(a.asset_id);
        return '<div class="search-result-item">'
            + '<div><strong>' + a.item_name + '</strong>'
            + '<span class="search-sn">SN: ' + (a.serial_number || 'N/A') + '</span>'
            + '</div>'
            + '<div data-asset-actions="' + a.asset_id + '">'
            + (counted
                ? '<span class="counted-text">Counted</span>'
                : '<button data-action="mark-asset" data-asset-id="' + a.asset_id + '" data-status="Present" class="mark-btn mark-operational">Operational</button>'
                + '<button data-action="mark-asset" data-asset-id="' + a.asset_id + '" data-status="Missing" class="mark-btn mark-non-ops">Non-Operational</button>')
            + '</div></div>';
    }).join('');

    container.innerHTML = html;
}

/* ── IN-PLACE UPDATE HELPERS ──────────────────────────────────────────
   Ang dating daloy ay `location.reload()` pagkatapos mag-mark. Dalawang
   problema: (1) bumabalik sa taas ng pahina kahit nasa ilalim na ng
   listahan ang nag-co-count, (2) hindi agad nawawala ang na-count.
   Ngayon: i-update ang DOM sa lugar lang (stats bar, group counters,
   at tanggalin ang na-count na row) — hindi na nagre-reload. */
function pcInt(el) { return el ? (parseInt(el.textContent, 10) || 0) : 0; }

function pcUpdateCountedNote(counted) {
    const note = document.getElementById('countedNote');
    if (!note) return;
    note.dataset.counted = String(counted);
    if (counted < 1) { note.hidden = true; return; }
    note.hidden = false;
    note.textContent = 'Showing uncounted assets only — ' + counted
        + ' already counted ' + (counted === 1 ? 'asset is' : 'assets are') + ' hidden.';
}

function pcBumpStats(status, n) {
    if (!n) return;
    const countedEl = document.getElementById('statCounted');
    if (countedEl) countedEl.textContent = String(pcInt(countedEl) + n);
    const el = document.getElementById(status === 'Present' ? 'statOperational' : 'statNonOps');
    if (el) el.textContent = String(pcInt(el) + n);
    pcUpdateCountedNote(pcInt(countedEl));
}

/* Inaayos ang isang custodian block: progress counter, "Mark all Present"
   na bilang, at inaalis ang block kapag wala nang pending. */
function pcSyncGroup(block) {
    const rows = block.querySelectorAll('tbody tr');
    const total = parseInt(block.dataset.groupTotal || '0', 10) || 0;
    const counted = Math.max(total - rows.length, 0);
    block.dataset.groupCounted = String(counted);

    const progress = block.querySelector('[data-role="group-progress"]');
    if (progress) progress.textContent = counted + '/' + total + ' counted';

    const allBtn = block.querySelector('[data-role="mark-all"]');
    if (rows.length === 0) {
        if (allBtn) allBtn.remove();
        block.remove();
        return;
    }
    if (allBtn) {
        allBtn.dataset.ids = Array.from(rows).map(r => r.dataset.assetId).join(',');
        allBtn.textContent = 'Mark all Present (' + rows.length + ')';
    }
}

function pcSyncEmptyState() {
    const wrap = document.getElementById('groupsWrap');
    if (!wrap) return;
    let empty = document.getElementById('groupsEmptyState');
    const hasBlocks = wrap.querySelectorAll('.custodian-block').length > 0;

    if (hasBlocks) {
        if (empty && empty.parentElement === wrap) empty.remove();
        return;
    }
    if (!empty) {
        empty = document.createElement('div');
        empty.id = 'groupsEmptyState';
        empty.className = 'empty-table';
        wrap.appendChild(empty);
    }
    const counted = pcInt(document.getElementById('statCounted'));
    empty.textContent = counted > 0
        ? 'All assets in your scope are already counted (' + counted + ' counted).'
        : 'No assets found in your scope.';
}

/* Kapag may search/custodian group na nakabukas, i-update ang header nito. */
function pcSyncSearchGroupHeader() {
    const header = document.querySelector('#searchResults .search-group-header');
    if (!header) return;
    const btn = header.querySelector('[data-action="mark-all-group"]');
    if (!btn) return;
    const remaining = Array.from(document.querySelectorAll('#searchResults [data-asset-actions]'))
        .filter(el => el.querySelector('[data-action="mark-asset"]'))
        .map(el => el.dataset.assetActions);
    if (remaining.length === 0) {
        btn.outerHTML = '<span class="counted-text">All Counted</span>';
    } else {
        btn.dataset.ids = remaining.join(',');
        btn.textContent = 'Mark all Present (' + remaining.length + ')';
    }
}

/* Kapag bukas ang scanned profile card, i-update ang "Mark all Present". */
function pcSyncScanCard() {
    const card = document.getElementById('scanResultCard');
    if (!card) return;
    const btn = card.querySelector('[data-role="scan-mark-all"]');
    if (!btn) return;
    const remaining = Array.from(card.querySelectorAll('[data-asset-actions]'))
        .filter(el => el.querySelector('[data-action="mark-asset"]'))
        .map(el => el.dataset.assetActions);
    if (remaining.length < 2) { btn.remove(); return; }
    btn.dataset.ids = remaining.join(',');
    btn.textContent = 'Mark all Present (' + remaining.length + ')';
}

/* Itago/isara ang scanned card — walang laman at handa na sa susunod na scan. */
function pcHideScanCard() {
    const card = document.getElementById('scanResultCard');
    if (!card) return;
    card.style.display = 'none';
    card.classList.add('hidden-display');
    card.innerHTML = '';
}

/* Ituring na COUNTED ang asset na "already counted" na sa server (422).
   Kailangan ito dahil hindi na nagre-reload ang pahina — ang `COUNTED_IDS`
   na nakuha noong pagkarga ay maaaring LUMA na (halimbawa: na-mark na sa
   ibang device/tab). Kapag hindi in-adopt, maiiwang nakabukas ang scanned
   card na puro 422 ("already counted") lang ang sagot sa mga buttons. */
function pcAdoptCounted(ids, status) {
    const fresh = ids.filter(function (id) { return !COUNTED_IDS.includes(id); });
    fresh.forEach(function (id) { COUNTED_IDS.push(id); });
    if (fresh.length) pcBumpStats(status || 'Present', fresh.length);
    return fresh;
}

/* Isara ang scanned card kapag ubos na ang pending sa loob nito (lahat
   counted na) — hindi na kailangang mag-tap ng hiwalay na close. Agad itong
   handa para sa SUSUNOD na scan: bubuksan muli ang camera kung available,
   kung hindi, ibabalik ang view sa "Scan QR" button. Walang reload. */
function pcMaybeCloseScanCard() {
    const card = document.getElementById('scanResultCard');
    if (!card || card.style.display === 'none') return false;
    // Walang laman / may hindi pa na-count → manatiling bukas para matapos muna.
    if (!card.querySelector('.scan-asset-block')) return false;
    if (card.querySelector('[data-action="mark-asset"]')) return false;

    pcHideScanCard();

    // Ipagpatuloy agad ang pag-scan kung may camera (kapag wala, ang
    // "Scan QR" button na lang ang hihilingin sa user).
    try {
        if (scanner.isCameraAvailable()) { openScanner(); return true; }
    } catch (e) { /* walang camera module → huwag pilitin */ }
    const btn = document.getElementById('openScannerBtn');
    if (btn) btn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    return true;
}

/* Ipalit ang mark buttons ng asset (sa search list / scanned card) ng "Counted". */
function pcApplyCountedState(assetId) {
    document.querySelectorAll('[data-asset-actions="' + assetId + '"]').forEach(function (el) {
        el.innerHTML = '<span class="counted-text">Counted</span>';
    });
}

function pcRemoveRows(ids) {
    const blocks = new Set();
    const rows = [];
    ids.forEach(function (id) {
        document.querySelectorAll('tr[data-asset-id="' + id + '"]').forEach(function (tr) {
            const block = tr.closest('.custodian-block');
            if (block) blocks.add(block);
            rows.push(tr);
        });
    });

    // I-compensate ang scroll kapag may natanggal sa itaas ng viewport para
    // hindi tumalon pataas ang nilalaman sa ilalim ng daliri ng user.
    let removedAbove = 0;
    rows.forEach(function (tr) {
        const r = tr.getBoundingClientRect();
        if (r.bottom <= 0) removedAbove += r.height;
    });

    rows.forEach(tr => tr.remove());
    ids.forEach(id => pcApplyCountedState(id));

    blocks.forEach(function (block) {
        if (block.querySelectorAll('tbody tr').length === 0) {
            const r = block.getBoundingClientRect();
            if (r.bottom <= 0) removedAbove += r.height;
        }
    });

    blocks.forEach(pcSyncGroup);
    pcSyncSearchGroupHeader();
    pcSyncScanCard();
    pcSyncEmptyState();

    // Kapag tapos na ang bilang sa scanned card (wala nang mark buttons),
    // isara ito at ihanda agad ang susunod na scan.
    pcMaybeCloseScanCard();

    if (removedAbove > 0) window.scrollBy(0, -removedAbove);
}

async function markMany(ids) {
    if (isMarking || !ids.length) return;
    isMarking = true;
    const total = ids.length;
    let marked = 0, skipped = 0, rateLimited = false, retrySecs = 30;
    const markedIds = [];
    const alreadyIds = [];
    const alreadyStatuses = [];

    Swal.fire({
        title: 'Marking assets...',
        html: '0 of ' + total,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => { Swal.showLoading(); }
    });

    for (let i = 0; i < ids.length; i++) {
        try {
            const formData = new FormData();
            formData.set('asset_id', ids[i]);
            formData.set('status', 'Present');

            const res = await fetch(MARK_URL, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: formData,
            });
            if (res.status === 429) {
                // Ihinto ang loop — lahat ng natitira ay 429 din lang, at
                // HINDI ito "already counted" (dati rito nahuhulog ang maling
                // summary at patuloy pa ring pinapadalhan ang lahat).
                rateLimited = true;
                retrySecs = pcRetryAfter(res);
                break;
            }
            const data = await res.json();
            if (data.success) {
                marked++;
                markedIds.push(parseInt(ids[i], 10));
            } else if (res.status === 422 && /already counted/i.test(data.message || '')) {
                // Na-count na sa server (luma ang COUNTED_IDS ng pahina) →
                // ituring na counted para hindi na ipakita pa ang buttons.
                const sm = /already counted as (Present|Missing)/i.exec(data.message || '');
                alreadyIds.push(parseInt(ids[i], 10));
                alreadyStatuses.push(sm ? (sm[1].charAt(0).toUpperCase() + sm[1].slice(1).toLowerCase()) : 'Present');
                skipped++;
            } else { skipped++; }
        } catch (e) {
            skipped++;
        }
        const box = Swal.getHtmlContainer();
        if (box) box.textContent = (marked + skipped) + ' of ' + total;
    }

    await Swal.fire({
        icon: rateLimited ? 'warning' : 'success',
        title: rateLimited ? 'Stopped — rate limit' : 'Done',
        text: rateLimited
            ? marked + ' marked as Present, ' + skipped + ' processed, ' + (total - marked - skipped) + ' not yet sent. '
                + 'Masyadong mabilis — maghintay ng ~' + retrySecs + ' segundo bago pindutin ulit ang "Mark all Present" para ipagpatuloy.'
            : marked + ' marked as Present, ' + skipped + ' skipped (already counted).',
        confirmButtonColor: '#0038A8',
    });

    // In-place update — walang reload kaya hindi nawawala ang kinaroroonan
    // ng user at agad natatanggal ang na-count na asset sa listahan.
    pcAdoptCounted(markedIds, 'Present');
    // Ang mga 422 ("already counted") ay counted NA sa server — ia-adopt din
    // para walang buttons na maiiwan at agad magsasara ang scanned card.
    alreadyIds.forEach(function (id, i) { pcAdoptCounted([id], alreadyStatuses[i]); });
    pcRemoveRows(markedIds.concat(alreadyIds));
    isMarking = false;
}

let isMarking = false;

async function markAsset(assetId, status, btn) {
    if (isMarking) return;
    isMarking = true;
    if (btn) { btn.disabled = true; btn.textContent = '...'; }
    try {
        const formData = new FormData();
        formData.set('asset_id', assetId);
        formData.set('status', status);

        const res = await fetch(MARK_URL, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData,
        });
        if (res.status === 429) {
            // Ibalik ang button (dati ay naiiwan itong "...") at ipakita kung
            // gaano katagal maghihintay.
            if (btn) { btn.disabled = false; btn.textContent = status === 'Present' ? 'Operational' : 'Non-Operational'; }
            Swal.fire({ icon: 'warning', title: 'Rate limit', text: pcTooManyMsg(res), confirmButtonColor: '#0038A8' });
            isMarking = false;
            return;
        }
        const data = await res.json();
        if (data.success) {
            // In-place update — hindi na nagre-reload (dating bumabalik sa taas)
            const id = parseInt(assetId, 10);
            pcAdoptCounted([id], status);
            pcRemoveRows([id]);
            isMarking = false;
        } else if (res.status === 422 && /already counted/i.test(data.message || '')) {
            // Na-count na sa server (luma ang COUNTED_IDS ng pahina) → ituring
            // na counted sa halip na magpakita ng maling "Failed" error; sasara
            // rin ang scanned card kapag wala nang pending.
            const id = parseInt(assetId, 10);
            const sm = /already counted as (Present|Missing)/i.exec(data.message || '');
            pcAdoptCounted([id], sm ? (sm[1].charAt(0).toUpperCase() + sm[1].slice(1).toLowerCase()) : status);
            pcRemoveRows([id]);
            isMarking = false;
        } else {
            // Ibalik ang button sa lahat ng iba pang failure (dati naiiwan
            // itong nakadisable at "...", kaya hindi na makapag-scan ulit).
            if (btn) { btn.disabled = false; btn.textContent = status === 'Present' ? 'Operational' : 'Non-Operational'; }
            Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Failed to mark asset.', confirmButtonColor: '#0038A8' });
            isMarking = false;
        }
    } catch (e) {
        if (btn) { btn.disabled = false; btn.textContent = status === 'Present' ? 'Operational' : 'Non-Operational'; }
        Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to server. Please try again.', confirmButtonColor: '#0038A8' });
        isMarking = false;
    }
}

// QR Scanner
const scanner = new AssetScanner({
    onScan: async (assetId, rawContent) => {
        closeScanner();
        // Fetch asset profile
        const res = await fetch(API_PROFILE_URL.replace('_ID_', assetId), {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            // Also fetch user assets via search endpoint
            let userAssets = [];
            try {
                const formData = new FormData();
                formData.set('asset_id', assetId);
                const searchRes = await fetch(SEARCH_URL, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                if (searchRes.status === 429) {
                    // Ipaalam (dati ay silent na walang laman ang listahan) —
                    // itutuloy pa rin ang pagpapakita ng na-scan na asset.
                    Swal.fire({
                        icon: 'warning',
                        title: 'Rate limit',
                        text: pcTooManyMsg(searchRes),
                        confirmButtonColor: '#0038A8',
                    });
                } else {
                    const searchData = await searchRes.json();
                    if (searchData.success && searchData.user_assets) {
                        userAssets = searchData.user_assets;
                    }
                }
            } catch (e) {
                console.error('Failed to fetch user assets:', e);
            }
            showScanResult(data.data, userAssets);
        } else {
            Swal.fire({ icon: 'warning', title: 'Asset Not Found', text: 'This QR code does not match any asset in scope.', confirmButtonColor: '#0038A8' });
        }
    },
    onError: (err) => {
        console.error('Scanner error:', err);
        closeScanner();
        Swal.fire({ icon: 'error', title: 'Camera Error', text: 'Please check if your browser has camera permission allowed for this site.', confirmButtonColor: '#0038A8' });
    }
});

function openScanner() {
    if (!scanner.isCameraAvailable()) {
        Swal.fire({ icon: 'info', title: 'Camera Not Available', text: 'Camera is not available on this device. Please search manually using the search box.', confirmButtonColor: '#0038A8' });
        return;
    }
    // Bagong scan = malinis na simula: isasara ang dating scanned card para
    // hindi ito maiwang nakabukas habang nag-scan ng susunod.
    pcHideScanCard();
    document.getElementById('scannerModalOverlay').style.display = 'flex';
    scanner.startCamera('scannerContainer');
}

function closeScanner() {
    scanner.stopCamera();
    document.getElementById('scannerModalOverlay').style.display = 'none';
}

/* Isang asset block: normal na row (gaya ng search result) + mahahalagang
   detalye. Pareho itong ginagamit ng NA-SCAN at ng IBA PANG asset ng may-ari
   para makita rin sa kanila ang importante (PAR No, Property No, Brand/Model,
   Category, Status) — hindi lang sa na-scan. */
function scanAssetBlock(a, countedFlag, isScanned) {
    // Mahahalagang detalye sa ISANG compact na linya (hindi na 5-row grid) para
    // hindi masyadong mahaba — nakikita pa rin sa LAHAT ng asset, hindi lang
    // sa na-scan.
    const meta = [
        ['SN', a.serial_number],
        ['PAR', a.par_number],
        ['Property', a.property_number],
        ['Category', a.category],
        ['Status', a.status]
    ].filter(function (m) { return m[1]; })
        .map(function (m) { return '<span><b>' + m[0] + ':</b> ' + m[1] + '</span>'; })
        .join('<span class="scan-meta-sep">&middot;</span>');

    return '<div class="scan-asset-block">'
        + '<div class="search-result-item">'
        + '<div>'
        + '<strong>' + a.item_name + '</strong>'
        + (isScanned ? '<span class="scan-scanned-badge">Scanned</span>' : '')
        + '<div class="scan-meta">' + (meta || '<span>—</span>') + '</div>'
        + '</div>'
        + '<div data-asset-actions="' + a.asset_id + '">'
        + (countedFlag
            ? '<span class="counted-text">Counted</span>'
            : '<button data-action="mark-asset" data-asset-id="' + a.asset_id + '" data-status="Present" class="mark-btn mark-operational">Operational</button>'
              + '<button data-action="mark-asset" data-asset-id="' + a.asset_id + '" data-status="Missing" class="mark-btn mark-non-ops">Non-Operational</button>')
        + '</div>'
        + '</div>'
        + '</div>';
}

function showScanResult(data, userAssets) {
    const card = document.getElementById('scanResultCard');
    const asset = data.asset;
    const isCounted = COUNTED_IDS.includes(asset.asset_id);

    // Ang listahan = ang na-scan + lahat ng asset ng may-ari nito (dedupe para
    // hindi madoble kung kasama na ang na-scan sa ibinigay na listahan).
    const list = [];
    const seenIds = {};
    [asset].concat(userAssets || []).forEach(function (a) {
        if (a && !seenIds[a.asset_id]) {
            seenIds[a.asset_id] = true;
            list.push(a);
        }
    });

    // Isang click lang: lahat ng pending (na-scan + iba pang asset).
    const pendingIds = list
        .filter(function (a) { return !COUNTED_IDS.includes(a.asset_id); })
        .map(function (a) { return a.asset_id; });

    // Ang TOP ay ang PANGALAN NG CUSTODIAN (hindi ang na-scan), tapos
    // sunod-sunod na listahan ng mga asset niya (+ ang na-scan kung hindi
    // ito kasama), bawat isa may compact na mahahalagang detalye.
    const ownerName = asset.assigned_user ? asset.assigned_user.full_name : 'Unassigned / Spare';

    let html = `<div class="profile-card-inner scan-other-block">
        <div class="scan-other-head">
            <div class="scan-owner">
                <span class="scan-owner-label">Custodian</span>${ownerName}
            </div>
            ${pendingIds.length >= 2
                ? `<button type="button" class="mark-btn mark-operational scan-mark-all" data-action="mark-all-group" data-role="scan-mark-all" data-ids="${pendingIds.join(',')}">Mark all Present (${pendingIds.length})</button>`
                : ''}
        </div>`;

    // Sunod-sunod na listahan (nauna ang na-scan, may "Scanned" badge).
    list.forEach(function (a) {
        html += scanAssetBlock(a, COUNTED_IDS.includes(a.asset_id), a.asset_id === asset.asset_id);
    });
    html += `</div>`;

    card.innerHTML = html;
    card.style.display = 'block';
    card.scrollIntoView({ behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', function() {
    var alertBox = document.querySelector('.alert-success');
    if (alertBox) {
        var msg = alertBox.textContent.trim();
        alertBox.style.display = 'none';
        Swal.fire({
            icon: 'success',
            title: 'Completed!',
            text: msg,
            confirmButtonColor: '#0038A8',
            confirmButtonText: 'OK'
        });
    }

    document.getElementById('completeSessionForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'End this session?',
            text: 'This will complete the physical count. Assets not yet counted will be marked as Not Counted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, complete it!',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                e.target.submit();
            }
        });
    });
    document.getElementById('clearSearchBtn')?.addEventListener('click', clearSearch);
    document.getElementById('openScannerBtn')?.addEventListener('click', openScanner);
    document.getElementById('scannerModalOverlay')?.addEventListener('click', function(e) {
        if (e.target === this) closeScanner();
    });
    document.querySelectorAll('.close-scanner-btn').forEach(function(el) {
        el.addEventListener('click', closeScanner);
    });
});
document.addEventListener('click', function(e) {
    var markBtn = e.target.closest('[data-action="mark-asset"]');
    if (markBtn) {
        markAsset(parseInt(markBtn.dataset.assetId), markBtn.dataset.status, markBtn);
        return;
    }
    var allBtn = e.target.closest('[data-action="mark-all-group"]');
    if (allBtn) {
        var ids = allBtn.dataset.ids.split(',').map(x => parseInt(x, 10)).filter(Boolean);
        markMany(ids);
    }
});
</script>
@endsection