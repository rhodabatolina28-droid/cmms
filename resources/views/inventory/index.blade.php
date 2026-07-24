@extends('layouts.app')

@section('title', 'Inventory Masterlist | NCMB ICT System')
@section('page-title', 'Inventory Masterlist')

@section('styles')
    <style nonce="{{ $cspNonce }}">
        .inventory-container {
            width: 100%;
            margin-top: -10px;
            animation: fadeInSlide 0.4s ease-out;
        }

        .polish-card {
            background: white;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .card-header-accent {
            background: #f8fafc;
            padding: 18px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body-content {
            padding: 20px 24px;
        }

        /* Summary Stats Ribbon */
        .stats-ribbon {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 16px;
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
            font-size: 18px;
        }

        .stat-info p { margin: 0; font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; }
        .stat-info h4 { margin: 2px 0 0; font-size: 20px; font-weight: 800; color: #1e293b; }

        .filter-ribbon {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
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
            box-shadow: 0 0 0 2px rgba(0,56,168,0.15);
        }

        .btn-export-ribbon {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #0038A8;
            color: white;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-export-ribbon:hover { 
            background: #002d8c; 
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 56, 168, 0.2);
        }

        .gov-table-premium {
            width: 100%;
            border-collapse: collapse;
        }

        .gov-table-premium th {
            background: #f1f5f9;
            padding: 10px 10px;
            font-size: 11px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }

        .gov-table-premium td {
            padding: 10px 10px;
            font-size: 13px;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
        }

        .gov-table-premium th:first-child,
        .gov-table-premium td:first-child {
            padding-left: 14px;
        }
        .gov-table-premium th:last-child,
        .gov-table-premium td:last-child {
            padding-right: 14px;
        }

        .serial-font { font-family: 'Consolas', monospace; font-weight: 700; color: #0038A8; font-size: 12px; }

        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .sp-active { background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.15); }
        .sp-spare { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.15); }
        .sp-defective { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.15); }
        .sp-repair { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; box-shadow: 0 2px 4px rgba(194, 65, 12, 0.15); }
        .sp-maintenance { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; box-shadow: 0 2px 4px rgba(79, 70, 229, 0.15); }
        .sp-disposal { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.15); }
        .sp-scrapped { background: #fef2f2; color: #7f1d1d; border: 1px solid #fca5a5; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.15); }

        tr.row-disposal td { background: #fff8f8; }

        .gov-table-premium tr.tr-hover-row { transition: background 0.15s ease; position: relative; }
        .gov-table-premium tr.tr-hover-row:hover { background: #f8fafc !important; }
        .gov-table-premium tr.tr-hover-row:hover td:first-child { box-shadow: inset 4px 0 0 #0038A8; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }

        .btn-action-modern {
            padding: 6px 12px;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
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

        /* PAR Number Badge */
        .par-badge {
            display: inline-block;
            background: #eff6ff;
            color: #0038A8;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 700;
            font-family: 'Consolas', monospace;
            font-size: 11px;
            border: 1px solid #bfdbfe;
        }

        .par-badge.na {
            background: #f8fafc;
            color: #94a3b8;
            border-color: #e2e8f0;
            font-weight: 400;
        }

        /* Property Number */
        .prop-no {
            font-family: 'Consolas', monospace;
            font-size: 12px;
            color: #475569;
        }

        /* Actions Dropdown */
        .actions-dropdown {
            position: relative;
            display: inline-block;
        }

        .btn-dropdown-toggle {
            background: none;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 4px 8px;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
            color: #64748b;
            transition: all 0.2s;
        }

        .btn-dropdown-toggle:hover {
            background: #f1f5f9;
            border-color: #0038A8;
            color: #0038A8;
        }

        .btn-dropdown-toggle:focus {
            outline: none;
            border-color: #0038A8;
            box-shadow: 0 0 0 3px rgba(0, 56, 168, 0.1);
        }

        .dropdown-menu-custom {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            z-index: 1000;
            min-width: 190px;
            padding: 6px 0;
            margin-top: 4px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .dropdown-menu-custom.show {
            display: block;
        }

        .dropdown-item-custom {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 16px;
            font-size: 13px;
            color: #1e293b;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.15s;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-family: inherit;
        }

        .dropdown-item-custom:hover {
            background: #f1f5f9;
        }

        .dropdown-item-custom.text-danger {
            color: #dc2626;
        }

        .dropdown-item-custom.text-danger:hover {
            background: #fef2f2;
        }

        .dropdown-item-custom i {
            width: 16px;
            font-size: 13px;
            color: #64748b;
        }

        .dropdown-item-custom.text-danger i {
            color: #dc2626;
        }

        .dropdown-divider-custom {
            margin: 4px 0;
            border: none;
            border-top: 1px solid #e2e8f0;
        }

        /* Close dropdown when clicking outside */
        .dropdown-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: 999;
        }

        .dropdown-backdrop.show {
            display: block;
        }

        /* Modal Overlays */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .modal-card {
            background: white;
            width: 100%;
            max-width: 680px;
            max-height: 92vh;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 20px 25px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .modal-body {
            padding: 25px;
            overflow-y: auto;
            flex: 1;
            min-height: 0;
        }

        .modal-footer {
            flex-shrink: 0;
            padding: 16px 25px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-label-gov {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .form-input-gov {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            outline: none;
            transition: all 0.2s;
        }

        .form-input-gov:focus {
            border-color: #0038A8;
            box-shadow: 0 0 0 3px rgba(0, 56, 168, 0.1);
        }

        /* ── Utility classes (migrated from inline styles) ── */
        .si-total { background: #eff6ff; color: #1d4ed8; }
        .si-active { background: #ecfdf5; color: #059669; }
        .si-spare { background: #fffbeb; color: #d97706; }
        .si-defective { background: #fef2f2; color: #dc2626; }
        .card-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; }
        .card-subtitle { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .icon-box-blue { margin-right: 10px; color: #0038A8; }
        .btn-group { display: flex; gap: 8px; }
        .btn-qr { background: #f5f3ff; color: #6d28d9; border: 1px solid #c4b5fd; padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-pc { background: #f0fdf4; color: #166534; border: 1px solid #86efac; padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-import { background: #f0fdf4; color: #166534; border: 1px solid #86efac; padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .btn-import:hover { background: #dcfce7; }
        .btn-add { background: #0038A8; color: white; border: none; padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .btn-viewonly { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; padding: 9px 14px; border-radius: 8px; font-size: 12px; font-weight: 800; }
        .search-wrapper { position: relative; flex: 1; min-width: 250px; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; }
        .search-input { width: 100%; padding-left: 35px; }
        .filter-select { width: 150px; }
        .overflow-x-auto { overflow-x: auto; }
        .text-center { text-align: center !important; }
        .loading-row { text-align: center; padding: 40px; color: #64748b; }
        .modal-h4 { margin: 0; font-size: 16px; font-weight: 800; color: #1e293b; }
        .close-btn { background: none; border: none; cursor: pointer; color: #94a3b8; }
        .modal-form { display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden; }
        .edit-banner { display: none; background: #eff6ff; border: 1px solid #93c5fd; border-radius: 8px; padding: 12px 16px; margin-bottom: 18px; }
        .edit-banner-title { font-size: 10px; font-weight: 800; color: #1e40af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
        .edit-banner-info { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; font-size: 12px; }
        .edit-label { color: #64748b; }
        .edit-value { color: #1e293b; }
        .edit-sn { font-family: monospace; color: #475569; }
        .edit-custodian { color: #0038A8; }
        .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .spec-label { font-size: 10px; font-weight: 800; color: #64748b; }
        .section-title-sm { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 14px; }
        .icon-section { color: #0038A8; }
        .specs-box { display: none; padding: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 20px; }
        .specs-box-label { margin-bottom: 12px; color: #0038A8; }
        .acc-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        .acc-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .acc-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .col-span-2 { grid-column: span 2; }
        .net-grid { display: none; grid-template-columns: 1fr 1fr; gap: 15px; }
        .equip-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 10px; }
        .accessories-section { margin-top: 16px; padding-top: 14px; border-top: 1px solid #e2e8f0; }
        .section-uppercase { font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 10px; }
        .laptop-battery-section { display: none; margin-top: 16px; padding-top: 14px; border-top: 1px solid #e2e8f0; }
        .it-parts-box { display: none; background: #eff6ff; border: 1px solid #93c5fd; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px; }
        .it-parts-title { font-size: 11px; font-weight: 800; color: #1d4ed8; text-transform: uppercase; margin-bottom: 10px; }
        .it-parts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .it-part-label { font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px; }
        .textarea-md { min-height: 80px; resize: vertical; }
        .textarea-sm { min-height: 70px; resize: vertical; }
        .textarea-xs { min-height: 60px; resize: vertical; }
        .section-divider { border: none; border-top: 1px solid #e2e8f0; margin: 20px 0; }
        .lifecycle-title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 14px; }
        .lifecycle-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .mb-16 { margin-bottom: 16px; }
        .mb-20 { margin-bottom: 20px; }
        .mb-12 { margin-bottom: 12px; }
        .font-mono { font-family: monospace; }
        .info-text { margin: 5px 0 0; font-size: 11px; color: #64748b; }
        .info-icon-blue { margin-right: 4px; color: #3b82f6; }
        .info-text-sm { font-size: 11px; color: #64748b; margin: -5px 0 10px; }
        .transfer-card { max-width: 520px; }
        .transfer-info-box { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; }
        .transfer-info-title { font-size: 11px; font-weight: 800; color: #15803d; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 6px; }
        @media (max-width: 767px) {
            input[type="checkbox"] { display: none !important; }
            .swal2-checkbox { display: none !important; }
            .stats-ribbon { grid-template-columns: repeat(2, 1fr) !important; gap: 8px !important; }
            .stat-item-premium { padding: 10px 12px !important; }
            .stat-info p { font-size: 9px !important; }
            .stat-info h4 { font-size: 16px !important; }
            .stat-icon { width: 28px !important; height: 28px !important; font-size: 13px !important; }
            .filter-ribbon { flex-direction: column !important; gap: 10px !important; }
            .filter-ribbon .ribbon-input,
            .filter-ribbon .search-wrapper,
            .filter-ribbon .btn-export-ribbon { width: 100% !important; min-width: 0 !important; }
            .ribbon-input { min-height: 48px !important; font-size: 15px !important; padding: 12px !important; }
            .search-input { padding-left: 38px !important; }
            .search-icon { font-size: 14px !important; left: 14px !important; }
            .btn-export-ribbon { min-height: 48px !important; justify-content: center !important; }
            .card-header-accent { flex-direction: column !important; align-items: flex-start !important; gap: 10px !important; }
            .card-header-accent .btn-qr,
            .card-header-accent a,
            .card-header-accent .btn-add { width: 100% !important; text-align: center !important; justify-content: center !important; }
            .card-title { font-size: 16px !important; }
            .card-body-content { padding: 16px !important; }
            .gov-table-premium th,
            .gov-table-premium td { padding: 6px 8px !important; font-size: 11px !important; }
            .gov-table-premium tr:active td { background: #f1f5f9 !important; }
            .modal-card { width: 95vw !important; max-width: 95vw !important; }
            .modal-body { padding: 14px !important; }
            .modal-footer { flex-direction: column !important; gap: 10px !important; }
            .modal-footer button { width: 100% !important; min-height: 48px !important; }
            .lifecycle-grid { grid-template-columns: 1fr !important; }
            .form-grid-2 { grid-template-columns: 1fr !important; gap: 12px !important; }
            .form-grid-3 { grid-template-columns: 1fr !important; gap: 12px !important; }
            .acc-grid-4 { grid-template-columns: repeat(2, 1fr) !important; gap: 10px !important; }
            .acc-grid-3 { grid-template-columns: 1fr !important; gap: 10px !important; }
            .acc-grid-2 { grid-template-columns: 1fr !important; gap: 10px !important; }
            .net-grid { grid-template-columns: 1fr !important; }
            .equip-grid { grid-template-columns: 1fr !important; }
            .it-parts-grid { grid-template-columns: 1fr !important; }
            .modal-body > div[style*="grid-template-columns"] { grid-template-columns: 1fr !important; }
            .form-label-gov { font-size: 10px !important; }
            .section-title-sm { font-size: 10px !important; }
            .spec-label { font-size: 10px !important; }
            .edit-banner-info { flex-direction: column !important; gap: 4px !important; }
            .col-span-2 { grid-column: span 1 !important; }
            .modal-header { padding: 14px 16px !important; }
            .modal-body { padding: 14px !important; }
            .modal-footer { padding: 12px 14px !important; }
            .modal-header { justify-content: flex-start !important; }
            .form-input-gov { font-size: 15px !important; padding: 12px !important; min-height: 48px !important; }
            .btn-save { min-height: 48px !important; font-size: 14px !important; width: 100% !important; }
            .btn-cancel-modal { min-height: 48px !important; width: 100% !important; }
            .btn-success { min-height: 48px !important; width: 100% !important; }
            .btn-dropdown-toggle { min-width: 44px !important; min-height: 44px !important; padding: 8px 12px !important; font-size: 18px !important; }
            .dropdown-menu-custom { min-width: 220px !important; right: -10px !important; }
            .dropdown-item-custom { padding: 14px 16px !important; font-size: 14px !important; min-height: 44px !important; }
            .dropdown-item-custom i { font-size: 16px !important; width: 20px !important; }
            .transfer-info-box { padding: 12px !important; }
            .transfer-asset-name { font-size: 14px !important; }
        }
        .transfer-asset-name { font-weight: 800; font-size: 14px; color: #1e293b; }
        .transfer-custodian { margin-top: 6px; font-size: 12px; color: #64748b; }
        .warning-box { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px; padding: 10px 14px; font-size: 12px; color: #92400e; }
        .btn-cancel-modal { padding: 10px 20px; }
        .btn-save { background: #0038A8; color: white; border: none; padding: 10px 24px; border-radius: 6px; font-weight: 700; cursor: pointer; }
        .btn-success { background: #16a34a; color: white; border: none; padding: 10px 24px; border-radius: 6px; font-weight: 700; cursor: pointer; }
        .mr-8 { margin-right: 8px; }
        .flex-center-gap { display: flex; align-items: center; gap: 5px; }
        .text-red-required { color: #ef4444; }
        .color-green { color: #16a34a; }
        .border-default { border-color: #cbd5e1; }
        .inline-info { margin: -4px 0 8px; }
        .d-none { display: none; }
        .label-color-muted { color: #475569; }
    </style>
@endsection

@section('content')
<div class="inventory-container">

    <div class="polish-card">
        <!-- HEADER STRIP -->
        <div class="card-header-accent">
            <div>
                <h3 class="card-title">
                    <i class="fa-solid fa-boxes-stacked icon-box-blue"></i>
                    Supply Asset Registry
                </h3>
                <p class="card-subtitle">
                    @if($canWriteInventory)
                        Track, update, and assign ICT assets within your scope.
                    @else
                        Read-only view of ICT assets within your scope.
                    @endif
                </p>
            </div>
            @if($canWriteInventory)
                <div class="btn-group">
                    @if(empty($isSuperAdminView))
                    <a href="{{ route('inventory.qr-batch') }}" class="btn-qr" title="Print QR stickers for multiple assets at once">
                        <i class="fa-solid fa-qrcode"></i> Print QR Stickers
                    </a>
                    <a href="{{ route('physical-count.index') }}" class="btn-pc">
                        <i class="fa-solid fa-clipboard-check"></i> Physical Count
                    </a>
                    <button id="importCsvBtn" class="btn-import" type="button">
                        <i class="fa-solid fa-file-csv"></i> Import CSV
                    </button>
                    <input type="file" id="inventoryCsvInput" accept=".csv,text/csv" class="d-none">
                    <button id="addAssetBtn" class="btn-add">
                        <i class="fa-solid fa-plus"></i> Register Asset
                    </button>
                    @endif
                </div>

            @else
                <span class="btn-viewonly">
                    <i class="fa-solid fa-eye"></i> View-only Registry
                </span>
            @endif
        </div>

        <div class="card-body-content">
            <!-- SUMMARY RIBBON — inside card -->
            <div class="stats-ribbon">
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>Total Assets</p>
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
                        <p>Spare</p>
                        <h4 id="statSpare">--</h4>
                    </div>
                </div>
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>For Repair</p>
                        <h4 id="statRepair">--</h4>
                    </div>
                </div>
                <div class="stat-item-premium">
                    <div class="stat-info">
                        <p>For Disposal</p>
                        <h4 id="statDisposal">--</h4>
                    </div>
                </div>
            </div>

            <!-- FILTERS -->
            <div class="filter-ribbon">
                <div class="search-wrapper">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="searchInventoryInput" placeholder="Search item, serial number, or custodian..." class="ribbon-input search-input">
                </div>

                <select id="filterAssetCategory" class="ribbon-input filter-select">
                    <option value="">All Categories</option>
                    <option value="Desktop">Desktop</option>
                    <option value="Laptop">Laptop</option>
                    <option value="Monitor">Monitor</option>
                    <option value="Printer/Scanner">Printer/Scanner</option>
                    <option value="Peripherals">Peripherals</option>
                    <option value="Network/Server">Network/Server</option>
                    <option value="Others">Others</option>
                </select>

                <select id="filterAssetStatus" class="ribbon-input filter-select">
                    <option value="">All Status</option>
                    <option value="Active">Active / In Use</option>
                    <option value="Spare">Spare / Stock</option>
                    <option value="Defective">Defective</option>
                    <option value="For Repair">For Repair</option>
                    <option value="Under Maintenance">Under Maintenance</option>
                    <option value="For Disposal">For Disposal</option>
                    <option value="Scrapped">Scrapped</option>
                </select>

                <a href="#" id="exportInvLink" class="btn-export-ribbon" title="Export to CSV">
                    <i class="fa-solid fa-download"></i> Export
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="gov-table-premium">
                    <thead>
                        <tr>
                            <th>PAR No</th>
                            <th>Property No</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Serial No</th>
                            <th>Assigned To</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody">
                        <tr class="tr-hover-row"><td colspan="8" class="loading-row"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading asset records...</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="inventoryPagination"></div>
        </div>
    </div>
</div>

<!-- ADD/EDIT ASSET MODAL -->
<div class="modal-overlay" id="assetModal">
    <div class="modal-card">
        <div class="modal-header">
            <h4 id="assetModalTitle" class="modal-h4">Register Asset</h4>
        </div>
        <form id="assetForm" class="modal-form">
            <input type="hidden" id="assetId" value="">
            <div class="modal-body">
                {{-- Edit info banner — only shown when editing an existing asset --}}
                <div id="editAssetBanner" class="edit-banner">
                    <div class="edit-banner-title">
                        <i class="fa-solid fa-pen-to-square"></i> Editing Existing Asset
                    </div>
                    <div class="edit-banner-info">
                        <div><span class="edit-label">Item:</span> <strong id="editBannerName" class="edit-value"></strong></div>
                        <div><span class="edit-label">SN:</span> <span id="editBannerSN" class="font-mono edit-sn"></span></div>
                        <div><span class="edit-label">Custodian:</span> <strong id="editBannerCustodian" class="edit-custodian"></strong></div>
                    </div>
                </div>
                <div class="form-grid-2">
                    <div>
                        <label class="form-label-gov">Asset Category</label>
                        <select id="assetCategory" required class="form-input-gov">
                            <option value="Desktop">Desktop</option>
                            <option value="Laptop">Laptop</option>
                            <option value="Monitor">Monitor</option>
                            <option value="Printer/Scanner">Printer/Scanner</option>
                            <option value="Peripherals">Peripherals</option>
                            <option value="Network/Server">Network/Server</option>
                            <option value="IT Parts / Components">IT Parts / Components</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-gov">Item Model / Name</label>
                        <input type="text" id="assetItemName" required placeholder="e.g., HP ProBook 440 G8" class="form-input-gov">
                    </div>
                </div>

                <!-- DYNAMIC SPECS CONTAINER (Desktop / Laptop) -->
                <div id="dynamicSpecsContainer" class="specs-box">
                    <label class="form-label-gov specs-box-label">Hardware Specifications</label>
                    <div class="form-grid-3">

                        {{-- OS --}}
                        <div>
                            <label class="spec-label">Operating System</label>
                            <select id="specOs" class="form-input-gov">
                                <option value="">-- Select OS --</option>
                                <option value="Windows 10">Windows 10</option>
                                <option value="Windows 11">Windows 11</option>
                                <option value="macOS">macOS</option>
                                <option value="Linux">Linux</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        {{-- CPU --}}
                        <div class="col-span-2">
                            <label class="spec-label">Processor (CPU)</label>
                            <select id="specCpu" class="form-input-gov">
                                <option value="">-- Select CPU --</option>
                                <optgroup label="Intel Core i3">
                                    <option value="Intel Core i3-8100">Intel Core i3-8100 (8th Gen)</option>
                                    <option value="Intel Core i3-9100">Intel Core i3-9100 (9th Gen)</option>
                                    <option value="Intel Core i3-10100">Intel Core i3-10100 (10th Gen)</option>
                                    <option value="Intel Core i3-12100">Intel Core i3-12100 (12th Gen)</option>
                                    <option value="Intel Core i3-13100">Intel Core i3-13100 (13th Gen)</option>
                                </optgroup>
                                <optgroup label="Intel Core i5">
                                    <option value="Intel Core i5-8400">Intel Core i5-8400 (8th Gen)</option>
                                    <option value="Intel Core i5-8500">Intel Core i5-8500 (8th Gen)</option>
                                    <option value="Intel Core i5-9400">Intel Core i5-9400 (9th Gen)</option>
                                    <option value="Intel Core i5-10400">Intel Core i5-10400 (10th Gen)</option>
                                    <option value="Intel Core i5-10500">Intel Core i5-10500 (10th Gen)</option>
                                    <option value="Intel Core i5-11400">Intel Core i5-11400 (11th Gen)</option>
                                    <option value="Intel Core i5-12400">Intel Core i5-12400 (12th Gen)</option>
                                    <option value="Intel Core i5-12500">Intel Core i5-12500 (12th Gen)</option>
                                    <option value="Intel Core i5-13400">Intel Core i5-13400 (13th Gen)</option>
                                    <option value="Intel Core i5-13500">Intel Core i5-13500 (13th Gen)</option>
                                </optgroup>
                                <optgroup label="Intel Core i7">
                                    <option value="Intel Core i7-8700">Intel Core i7-8700 (8th Gen)</option>
                                    <option value="Intel Core i7-9700">Intel Core i7-9700 (9th Gen)</option>
                                    <option value="Intel Core i7-10700">Intel Core i7-10700 (10th Gen)</option>
                                    <option value="Intel Core i7-11700">Intel Core i7-11700 (11th Gen)</option>
                                    <option value="Intel Core i7-12700">Intel Core i7-12700 (12th Gen)</option>
                                    <option value="Intel Core i7-13700">Intel Core i7-13700 (13th Gen)</option>
                                    <option value="Intel Core i7-14700">Intel Core i7-14700 (14th Gen)</option>
                                </optgroup>
                                <optgroup label="Intel Core i9">
                                    <option value="Intel Core i9-9900K">Intel Core i9-9900K (9th Gen)</option>
                                    <option value="Intel Core i9-10900">Intel Core i9-10900 (10th Gen)</option>
                                    <option value="Intel Core i9-12900">Intel Core i9-12900 (12th Gen)</option>
                                    <option value="Intel Core i9-13900">Intel Core i9-13900 (13th Gen)</option>
                                </optgroup>
                                <optgroup label="AMD Ryzen 3">
                                    <option value="AMD Ryzen 3 3100">AMD Ryzen 3 3100 (3000 Series)</option>
                                    <option value="AMD Ryzen 3 3300X">AMD Ryzen 3 3300X (3000 Series)</option>
                                    <option value="AMD Ryzen 3 4100">AMD Ryzen 3 4100 (4000 Series)</option>
                                    <option value="AMD Ryzen 3 5300G">AMD Ryzen 3 5300G (5000 Series)</option>
                                </optgroup>
                                <optgroup label="AMD Ryzen 5">
                                    <option value="AMD Ryzen 5 3400G">AMD Ryzen 5 3400G (3000 Series)</option>
                                    <option value="AMD Ryzen 5 3600">AMD Ryzen 5 3600 (3000 Series)</option>
                                    <option value="AMD Ryzen 5 5500">AMD Ryzen 5 5500 (5000 Series)</option>
                                    <option value="AMD Ryzen 5 5600">AMD Ryzen 5 5600 (5000 Series)</option>
                                    <option value="AMD Ryzen 5 5600G">AMD Ryzen 5 5600G (5000 Series)</option>
                                    <option value="AMD Ryzen 5 5600X">AMD Ryzen 5 5600X (5000 Series)</option>
                                    <option value="AMD Ryzen 5 7600">AMD Ryzen 5 7600 (7000 Series)</option>
                                </optgroup>
                                <optgroup label="AMD Ryzen 7">
                                    <option value="AMD Ryzen 7 3700X">AMD Ryzen 7 3700X (3000 Series)</option>
                                    <option value="AMD Ryzen 7 5700G">AMD Ryzen 7 5700G (5000 Series)</option>
                                    <option value="AMD Ryzen 7 5700X">AMD Ryzen 7 5700X (5000 Series)</option>
                                    <option value="AMD Ryzen 7 5800X">AMD Ryzen 7 5800X (5000 Series)</option>
                                    <option value="AMD Ryzen 7 7700">AMD Ryzen 7 7700 (7000 Series)</option>
                                    <option value="AMD Ryzen 7 7700X">AMD Ryzen 7 7700X (7000 Series)</option>
                                </optgroup>
                                <optgroup label="AMD Ryzen 9">
                                    <option value="AMD Ryzen 9 5900X">AMD Ryzen 9 5900X (5000 Series)</option>
                                    <option value="AMD Ryzen 9 5950X">AMD Ryzen 9 5950X (5000 Series)</option>
                                    <option value="AMD Ryzen 9 7900X">AMD Ryzen 9 7900X (7000 Series)</option>
                                    <option value="AMD Ryzen 9 7950X">AMD Ryzen 9 7950X (7000 Series)</option>
                                </optgroup>
                                <option value="Other">Other (specify in notes)</option>
                            </select>
                        </div>

                        {{-- RAM --}}
                        <div>
                            <label class="spec-label">Memory (RAM)</label>
                            <select id="specRam" class="form-input-gov">
                                <option value="">-- Select RAM --</option>
                                <option value="4GB DDR3">4GB DDR3</option>
                                <option value="8GB DDR3">8GB DDR3</option>
                                <option value="4GB DDR4">4GB DDR4</option>
                                <option value="8GB DDR4">8GB DDR4</option>
                                <option value="16GB DDR4">16GB DDR4</option>
                                <option value="32GB DDR4">32GB DDR4</option>
                                <option value="64GB DDR4">64GB DDR4</option>
                                <option value="8GB DDR5">8GB DDR5</option>
                                <option value="16GB DDR5">16GB DDR5</option>
                                <option value="32GB DDR5">32GB DDR5</option>
                                <option value="64GB DDR5">64GB DDR5</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        {{-- Primary Storage --}}
                        <div>
                            <label class="spec-label">Primary Storage</label>
                            <select id="specHd1" class="form-input-gov">
                                <option value="">-- Select Storage --</option>
                                <optgroup label="M.2 NVMe SSD">
                                    <option value="128GB M.2 NVMe SSD">128GB M.2 NVMe SSD</option>
                                    <option value="256GB M.2 NVMe SSD">256GB M.2 NVMe SSD</option>
                                    <option value="512GB M.2 NVMe SSD">512GB M.2 NVMe SSD</option>
                                    <option value="1TB M.2 NVMe SSD">1TB M.2 NVMe SSD</option>
                                    <option value="2TB M.2 NVMe SSD">2TB M.2 NVMe SSD</option>
                                </optgroup>
                                <optgroup label="M.2 SATA SSD">
                                    <option value="128GB M.2 SATA SSD">128GB M.2 SATA SSD</option>
                                    <option value="256GB M.2 SATA SSD">256GB M.2 SATA SSD</option>
                                    <option value="512GB M.2 SATA SSD">512GB M.2 SATA SSD</option>
                                </optgroup>
                                <optgroup label="2.5&quot; SATA SSD">
                                    <option value="120GB 2.5&quot; SSD">120GB 2.5" SSD</option>
                                    <option value="240GB 2.5&quot; SSD">240GB 2.5" SSD</option>
                                    <option value="480GB 2.5&quot; SSD">480GB 2.5" SSD</option>
                                    <option value="1TB 2.5&quot; SSD">1TB 2.5" SSD</option>
                                </optgroup>
                                <optgroup label="3.5&quot; SATA HDD">
                                    <option value="500GB 3.5&quot; HDD">500GB 3.5" HDD</option>
                                    <option value="1TB 3.5&quot; HDD">1TB 3.5" HDD</option>
                                    <option value="2TB 3.5&quot; HDD">2TB 3.5" HDD</option>
                                    <option value="4TB 3.5&quot; HDD">4TB 3.5" HDD</option>
                                </optgroup>
                                <optgroup label="2.5&quot; HDD (Laptop)">
                                    <option value="500GB 2.5&quot; HDD">500GB 2.5" HDD</option>
                                    <option value="1TB 2.5&quot; HDD">1TB 2.5" HDD</option>
                                </optgroup>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        {{-- Secondary Storage --}}
                        <div>
                            <label class="spec-label">Secondary Storage (optional)</label>
                            <select id="specHd2" class="form-input-gov">
                                <option value="None">None</option>
                                <optgroup label="M.2 NVMe SSD">
                                    <option value="256GB M.2 NVMe SSD">256GB M.2 NVMe SSD</option>
                                    <option value="512GB M.2 NVMe SSD">512GB M.2 NVMe SSD</option>
                                    <option value="1TB M.2 NVMe SSD">1TB M.2 NVMe SSD</option>
                                </optgroup>
                                <optgroup label="2.5&quot; SSD">
                                    <option value="240GB 2.5&quot; SSD">240GB 2.5" SSD</option>
                                    <option value="480GB 2.5&quot; SSD">480GB 2.5" SSD</option>
                                    <option value="1TB 2.5&quot; SSD">1TB 2.5" SSD</option>
                                </optgroup>
                                <optgroup label="3.5&quot; HDD">
                                    <option value="500GB 3.5&quot; HDD">500GB 3.5" HDD</option>
                                    <option value="1TB 3.5&quot; HDD">1TB 3.5" HDD</option>
                                    <option value="2TB 3.5&quot; HDD">2TB 3.5" HDD</option>
                                    <option value="4TB 3.5&quot; HDD">4TB 3.5" HDD</option>
                                </optgroup>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        {{-- GPU --}}
                        <div class="col-span-2">
                            <label class="spec-label">Graphics Card (GPU)</label>
                            <select id="specGpu" class="form-input-gov">
                                <option value="">-- Select GPU --</option>
                                <optgroup label="Integrated Graphics">
                                    <option value="Intel UHD Graphics">Intel UHD Graphics (Integrated)</option>
                                    <option value="AMD Radeon Vega Graphics">AMD Radeon Vega (Integrated)</option>
                                </optgroup>
                                <optgroup label="NVIDIA GeForce GT Series">
                                    <option value="NVIDIA GeForce GT 710 1GB">GT 710 1GB</option>
                                    <option value="NVIDIA GeForce GT 730 2GB">GT 730 2GB</option>
                                    <option value="NVIDIA GeForce GT 1030 2GB">GT 1030 2GB GDDR5</option>
                                </optgroup>
                                <optgroup label="NVIDIA GeForce GTX Series">
                                    <option value="NVIDIA GeForce GTX 1050 2GB">GTX 1050 2GB GDDR5</option>
                                    <option value="NVIDIA GeForce GTX 1050 Ti 4GB">GTX 1050 Ti 4GB GDDR5</option>
                                    <option value="NVIDIA GeForce GTX 1060 3GB">GTX 1060 3GB GDDR5</option>
                                    <option value="NVIDIA GeForce GTX 1060 6GB">GTX 1060 6GB GDDR5</option>
                                    <option value="NVIDIA GeForce GTX 1650 4GB">GTX 1650 4GB GDDR6</option>
                                    <option value="NVIDIA GeForce GTX 1660 6GB">GTX 1660 6GB GDDR5</option>
                                    <option value="NVIDIA GeForce GTX 1660 Super 6GB">GTX 1660 Super 6GB GDDR6</option>
                                    <option value="NVIDIA GeForce GTX 1660 Ti 6GB">GTX 1660 Ti 6GB GDDR6</option>
                                </optgroup>
                                <optgroup label="NVIDIA GeForce RTX Series">
                                    <option value="NVIDIA GeForce RTX 2060 6GB">RTX 2060 6GB GDDR6</option>
                                    <option value="NVIDIA GeForce RTX 3050 8GB">RTX 3050 8GB GDDR6</option>
                                    <option value="NVIDIA GeForce RTX 3060 12GB">RTX 3060 12GB GDDR6</option>
                                    <option value="NVIDIA GeForce RTX 3060 Ti 8GB">RTX 3060 Ti 8GB GDDR6</option>
                                    <option value="NVIDIA GeForce RTX 3070 8GB">RTX 3070 8GB GDDR6</option>
                                    <option value="NVIDIA GeForce RTX 3080 10GB">RTX 3080 10GB GDDR6X</option>
                                    <option value="NVIDIA GeForce RTX 4060 8GB">RTX 4060 8GB GDDR6</option>
                                    <option value="NVIDIA GeForce RTX 4060 Ti 8GB">RTX 4060 Ti 8GB GDDR6</option>
                                    <option value="NVIDIA GeForce RTX 4070 12GB">RTX 4070 12GB GDDR6X</option>
                                </optgroup>
                                <optgroup label="AMD Radeon RX Series">
                                    <option value="AMD Radeon RX 550 4GB">RX 550 4GB</option>
                                    <option value="AMD Radeon RX 570 4GB">RX 570 4GB</option>
                                    <option value="AMD Radeon RX 580 8GB">RX 580 8GB</option>
                                    <option value="AMD Radeon RX 6500 XT 4GB">RX 6500 XT 4GB</option>
                                    <option value="AMD Radeon RX 6600 8GB">RX 6600 8GB</option>
                                    <option value="AMD Radeon RX 6650 XT 8GB">RX 6650 XT 8GB</option>
                                    <option value="AMD Radeon RX 6700 XT 12GB">RX 6700 XT 12GB</option>
                                    <option value="AMD Radeon RX 7600 8GB">RX 7600 8GB</option>
                                </optgroup>
                                <option value="Other">Other (specify in notes)</option>
                            </select>
                        </div>

                        {{-- MS Office --}}
                        <div>
                            <label class="spec-label">MS Office</label>
                            <select id="specOffice" class="form-input-gov">
                                <option value="">-- Select Office --</option>
                                <option value="None">None</option>
                                <option value="MS Office 2016">MS Office 2016</option>
                                <option value="MS Office 2019">MS Office 2019</option>
                                <option value="MS Office 2021">MS Office 2021</option>
                                <option value="Microsoft 365">Microsoft 365</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    {{-- Desktop-only accessories (hidden for Laptop) --}}
                    <div id="desktopAccessoriesSection" class="accessories-section">
                        <div class="section-uppercase">Desktop Accessories (optional)</div>
                        <div class="acc-grid-4">
                            <div>
                                <label class="spec-label">Form Factor</label>
                                <select id="specFormFactor" class="form-input-gov">
                                    <option value="">-- Select --</option>
                                    <option value="Tower">Tower</option>
                                    <option value="Mini-PC">Mini-PC</option>
                                    <option value="All-in-One">All-in-One</option>
                                </select>
                            </div>
                            <div>
                                <label class="spec-label">Monitor Included</label>
                                <select id="specMonitorIncluded" class="form-input-gov">
                                    <option value="">-- Select --</option>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                            <div>
                                <label class="spec-label">Keyboard</label>
                                <select id="specKeyboard" class="form-input-gov">
                                    <option value="">-- Select --</option>
                                    <option value="None">None</option>
                                    <option value="Wired">Wired</option>
                                    <option value="Wireless">Wireless</option>
                                </select>
                            </div>
                            <div>
                                <label class="spec-label">Mouse</label>
                                <select id="specMouse" class="form-input-gov">
                                    <option value="">-- Select --</option>
                                    <option value="None">None</option>
                                    <option value="Wired">Wired</option>
                                    <option value="Wireless">Wireless</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Laptop-only: battery condition --}}
                    <div id="laptopBatterySection" class="laptop-battery-section">
                        <div class="section-uppercase">Laptop Condition</div>
                        <div class="acc-grid-3">
                            <div>
                                <label class="spec-label">Battery Condition</label>
                                <select id="specBattery" class="form-input-gov">
                                    <option value="">-- Select --</option>
                                    <option value="Good">Good</option>
                                    <option value="Fair">Fair</option>
                                    <option value="Needs Replacement">Needs Replacement</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MONITOR SPECS CONTAINER -->
                <div id="monitorSpecsContainer" class="specs-box">
                    <label class="form-label-gov specs-box-label">Monitor Specifications</label>
                    <div class="acc-grid-2">
                        <div>
                            <label class="spec-label">Brand</label>
                            <select id="specMonitorBrand" class="form-input-gov">
                                <option value="">-- Select Brand --</option>
                                <option value="Dell">Dell</option>
                                <option value="HP">HP</option>
                                <option value="Lenovo">Lenovo</option>
                                <option value="Samsung">Samsung</option>
                                <option value="LG">LG</option>
                                <option value="Acer">Acer</option>
                                <option value="Asus">Asus</option>
                                <option value="AOC">AOC</option>
                                <option value="ViewSonic">ViewSonic</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="spec-label">Model</label>
                            <input type="text" id="specMonitorModel" class="form-input-gov" placeholder="P2422H">
                        </div>
                        <div>
                            <label class="spec-label">Screen Size</label>
                            <select id="specMonitorSize" class="form-input-gov">
                                <option value="">-- Select Size --</option>
                                <option value='19"'>19"</option>
                                <option value='21.5"'>21.5"</option>
                                <option value='22"'>22"</option>
                                <option value='24"'>24"</option>
                                <option value='27"'>27"</option>
                                <option value='32"'>32"</option>
                                <option value="Ultrawide">Ultrawide</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="spec-label">Resolution</label>
                            <select id="specMonitorResolution" class="form-input-gov">
                                <option value="">-- Select Resolution --</option>
                                <option value="1366x768 (HD)">1366x768 (HD)</option>
                                <option value="1920x1080 (FHD)">1920x1080 (FHD)</option>
                                <option value="2560x1440 (QHD)">2560x1440 (QHD)</option>
                                <option value="3840x2160 (4K UHD)">3840x2160 (4K UHD)</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="spec-label">Additional Notes</label>
                            <input type="text" id="specMonitorNotes" class="form-input-gov" placeholder="IPS, 75Hz, HDMI+VGA...">
                        </div>
                    </div>
                </div>

                <!-- NETWORK/SERVER SPECS CONTAINER -->
                <div id="networkSpecsContainer" class="specs-box">
                    <label class="form-label-gov specs-box-label">Network / Server Specifications</label>
                    <div class="mb-12">
                        <label class="spec-label">Device Type</label>
                        <select id="specNetworkDeviceType" class="form-input-gov">
                            <option value="">-- Select Type --</option>
                            <option value="Desktop">Desktop</option>
                            <option value="Laptop">Laptop</option>
                            <option value="Server">Server</option>
                            <option value="Network Equipment">Network Equipment</option>
                            <option value="Firewall">Firewall</option>
                            <option value="Switch">Switch</option>
                            <option value="Router">Router</option>
                        </select>
                    </div>
                    {{-- Desktop/Laptop-type network device specs --}}
                    <div id="networkDesktopLaptopSpecs" class="net-grid">
                        <div>
                            <label class="spec-label">CPU</label>
                            <input type="text" id="specNetworkCpu" class="form-input-gov" placeholder="Xeon E5">
                        </div>
                        <div>
                            <label class="spec-label">RAM</label>
                            <input type="text" id="specNetworkRam" class="form-input-gov" placeholder="32GB ECC">
                        </div>
                        <div>
                            <label class="spec-label">Storage</label>
                            <input type="text" id="specNetworkStorage" class="form-input-gov" placeholder="2TB RAID">
                        </div>
                        <div>
                            <label class="spec-label">OS</label>
                            <input type="text" id="specNetworkOs" class="form-input-gov" placeholder="Windows Server 2022">
                        </div>
                    </div>
                    {{-- Equipment-type specs --}}
                    <div id="networkEquipmentSpecs" class="d-none">
                        <div class="equip-grid">
                            <div>
                                <label class="spec-label">Brand</label>
                                <input type="text" id="specNetworkBrand" class="form-input-gov" placeholder="Cisco, Fortinet...">
                            </div>
                            <div>
                                <label class="spec-label">Model</label>
                                <input type="text" id="specNetworkModel" class="form-input-gov" placeholder="ASA 5505">
                            </div>
                        </div>
                        <div>
                            <label class="spec-label">Specifications / Notes</label>
                            <textarea id="specNetworkEquipmentSpecs" class="form-input-gov textarea-xs" placeholder="24-port, PoE, managed..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div>
                        <label class="form-label-gov">Serial Number</label>
                        <input type="text" id="assetSerialNumber" placeholder="SN-XXXX-XXXX" class="form-input-gov font-mono">
                    </div>
                    <div>
                        <label class="form-label-gov">Device Status</label>
                        <select id="assetStatus" required class="form-input-gov">
                            <option value="Active">Active / In Use</option>
                            <option value="Spare">Spare / Stock</option>
                            <option value="Defective">Defective</option>
                            <option value="For Repair">For Repair</option>
                            {{-- Scrapped/For Disposal are set ONLY by the repair/disposal workflow, not manually --}}
                        </select>
                    </div>
                </div>

                @if(false)
                {{-- ══════════════════════════════════════════════════════════════
                     SUPER ADMIN: Division/Office Selection
                     ══════════════════════════════════════════════════════════════ --}}
                <div class="mb-20">
                    <label class="form-label-gov flex-center-gap label-color-muted">
                        <i class="fa-solid fa-map-location-dot icon-section"></i>
                        Division/Office Location <span class="text-red-required">*</span>
                    </label>
                    <select id="assetOffice" required class="form-input-gov border-default">
                        <option value="">-- Select Division/Office --</option>
                        @foreach(['Central Office'] as $r)
                            <option value="{{ $r }}">{{ $r }}</option>
                        @endforeach
                    </select>
                    <p class="info-text">
                        <i class="fa-solid fa-circle-info info-icon-blue"></i>
                        Specify the division/office where this asset is physically deployed.
                    </p>
                </div>
                @else
                {{-- STANDARD ADMIN: Assign To Personnel --}}
                <div class="mb-20">
                    <label class="form-label-gov">Assign to Personnel</label>
                    <p class="info-text-sm">Personnel list is filtered to your office scope.</p>
                    <select id="assetAssignedUser" class="form-input-gov">
                        <option value="">-- Not Assigned (Available in Stock) --</option>
                    </select>
                </div>
                @endif


                <div id="generalSpecsGroup">
                    <!-- IT Parts / Components Quick-Fill -->
                    <div id="itPartsSection" class="it-parts-box">
                        <div class="it-parts-title">
                            <i class="fa-solid fa-screwdriver-wrench"></i> IT Part / Component Details
                        </div>
                        <div class="it-parts-grid">
                            <div>
                                <label class="it-part-label">Part Type</label>
                                <select id="itPartType" class="form-input-gov">
                                    <option value="">-- Select Part Type --</option>
                                    <option value="RAM">RAM (Memory)</option>
                                    <option value="SSD">SSD (Solid State Drive)</option>
                                    <option value="HDD">HDD (Hard Disk Drive)</option>
                                    <option value="GPU">GPU (Graphics Card)</option>
                                    <option value="CPU">CPU (Processor)</option>
                                    <option value="PSU">PSU (Power Supply Unit)</option>
                                    <option value="Motherboard">Motherboard</option>
                                    <option value="Battery">Battery (Laptop)</option>
                                    <option value="Cooling Fan">Cooling Fan</option>
                                    <option value="Network Card">Network Card / Wi-Fi Adapter</option>
                                    <option value="Keyboard">Keyboard (Replacement)</option>
                                    <option value="Other Part">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="it-part-label">Capacity / Speed / Size</label>
                                <input type="text" id="itPartSpec" class="form-input-gov" placeholder="e.g. 8GB DDR4, 512GB NVMe, RTX 3050" oninput="itPartTypeChange()">
                            </div>
                        </div>
                    </div>
                    <label class="form-label-gov">Other Details / Remarks</label>
                    <textarea id="generalSpecifications" class="form-input-gov textarea-md" placeholder="Additional technical details..."></textarea>
                </div>

                {{-- ── NEW: Financial & Lifecycle Fields ── --}}
                <hr class="section-divider">
                <div class="lifecycle-title">
                    <i class="fa-solid fa-calendar-days icon-section"></i> Lifecycle & Financial
                </div>
                <div class="lifecycle-grid">
                    <div>
                        <label class="form-label-gov">Date Acquired</label>
                        <input type="date" id="assetDateAcquired" class="form-input-gov">
                    </div>
                    <div>
                        <label class="form-label-gov">Acquisition Cost (₱)</label>
                        <input type="number" id="assetAcquisitionCost" step="0.01" min="0" placeholder="0.00" class="form-input-gov">
                    </div>
                    <div>
                        <label class="form-label-gov">Warranty Expiration</label>
                        <input type="date" id="assetWarrantyExpiration" class="form-input-gov">
                    </div>
                    <div>
                        <label class="form-label-gov">End of Useful Life</label>
                        <input type="date" id="assetEndOfUsefulLife" class="form-input-gov">
                    </div>
                </div>
                <div class="lifecycle-grid">
                    <div>
                        <label class="form-label-gov">Brand</label>
                        <input type="text" id="assetBrandInput" placeholder="e.g., HP, Dell, Lenovo" class="form-input-gov">
                    </div>
                    <div>
                        <label class="form-label-gov">Model</label>
                        <input type="text" id="assetModelInput" placeholder="e.g., ProBook 440 G8" class="form-input-gov">
                    </div>
                </div>
                <div class="mb-16">
                    <label class="form-label-gov">Property Number</label>
                    <input type="text" id="assetPropertyNumber" placeholder="e.g. NCMB-ICT-2024-001" class="form-input-gov">
                </div>
                <div class="mb-16">
                    <label class="form-label-gov">Asset Notes</label>
                    <textarea id="assetNotes" class="form-input-gov textarea-sm" placeholder="Additional notes about this asset..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action-modern close-asset-btn btn-cancel-modal">Cancel</button>
                <button type="submit" class="btn-save">Save Asset Record</button>
            </div>
        </form>
    </div>
</div>

<!-- TRANSFER / REASSIGN MODAL -->
<div class="modal-overlay" id="transferModal">
    <div class="modal-card transfer-card">
        <div class="modal-header">
            <h4 class="modal-h4">
                <i class="fa-solid fa-right-left color-green mr-8"></i> Transfer / Reassign Asset
            </h4>
            <button class="close-transfer-btn close-btn" aria-label="Close"><i class="fa-solid fa-times"></i></button>
        </div>
        <form id="transferForm" class="modal-form">
            <input type="hidden" id="transferAssetId">
            <div class="modal-body">

                <div class="transfer-info-box">
                    <div class="transfer-info-title">Asset Being Transferred</div>
                    <div id="transferAssetName" class="transfer-asset-name"></div>
                    <div class="transfer-custodian">
                        Current Custodian: <strong id="transferCurrentCustodian"></strong>
                    </div>
                </div>

                <div class="mb-16">
                    <label class="form-label-gov">New Custodian</label>
                    <p class="info-text-sm inline-info">Select the person who will receive and be accountable for this asset.</p>
                    <select id="transferAssignedUser" class="form-input-gov">
                        <option value="">-- Not Assigned (Return to Stock) --</option>
                    </select>
                </div>

                <div class="mb-16">
                    <label class="form-label-gov">Transfer Remarks (optional)</label>
                    <textarea id="transferRemarks" class="form-input-gov textarea-sm"
                              placeholder="Reason for transfer, condition of asset, etc..."></textarea>
                </div>

                <div class="warning-box">
                    <i class="fa-solid fa-circle-info"></i>
                    Custodian update will be recorded in the asset's Lifecycle History.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action-modern close-transfer-btn btn-cancel-modal">Cancel</button>
                <button type="submit" id="transferSaveBtn" class="btn-success">
                    <i class="fa-solid fa-right-left"></i> Confirm Transfer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ASSET HISTORY MODAL -->
<div class="modal-overlay" id="assetHistoryModal">
    <div class="modal-card">
        <div class="modal-header">
            <h4 class="modal-h4"><i class="fa-solid fa-history mr-8"></i> Lifecycle History</h4>
            <button class="close-history-btn close-btn"><i class="fa-solid fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="historyContent">
                <!-- Injected via JS -->
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-action-modern close-history-btn btn-cancel-modal">Close Tracking</button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}" src="{{ asset('js/inventory.js') }}?v={{ filemtime(public_path('js/inventory.js')) }}"></script>
<script nonce="{{ $cspNonce }}">
    window.CMMS_INVENTORY_CAN_WRITE = @json($canWriteInventory);
    window.CMMS_IS_SUPPLY_ADMIN = @json(empty($isSuperAdminView) && $canWriteInventory); // Skip division/dept filtering for supply admin
    window.CMMS_IS_SUPER_ADMIN_VIEW = @json(!empty($isSuperAdminView));
    @if(!empty($isSuperAdminView))
    window.CMMS_INVENTORY_DATA_URL = '{{ route('super_admin.inventory.data') }}';
    window.CMMS_INVENTORY_DETAIL_PREFIX = '{{ url('super-admin/inventory') }}';
    window.CMMS_RECEIPT_PREFIX = '{{ url('super-admin/inventory') }}';
    @else
    window.CMMS_INVENTORY_DATA_URL = '{{ route('inventory.data') }}';
    window.CMMS_INVENTORY_DETAIL_PREFIX = '{{ url('inventory') }}';
    window.CMMS_RECEIPT_PREFIX = '{{ url('inventory') }}';
    @endif

    // Wait for inventory.js to load before calling its functions
    document.addEventListener('DOMContentLoaded', function() {
        const transferForm = document.getElementById('transferForm');
        if (transferForm) transferForm.addEventListener('submit', saveTransfer);

        const itPartType = document.getElementById('itPartType');
        if (itPartType) itPartType.addEventListener('change', itPartTypeChange);

        const addAssetBtn = document.getElementById('addAssetBtn');
        if (addAssetBtn) addAssetBtn.addEventListener('click', openAddAssetModal);

        const exportInvLink = document.getElementById('exportInvLink');
        if (exportInvLink) {
            exportInvLink.addEventListener('click', function(e) {
                e.preventDefault();
                exportFilteredInventory();
            });
        }
        document.querySelectorAll('.close-asset-btn').forEach(function(el) {
            el.addEventListener('click', closeAssetModal);
        });
        document.querySelectorAll('.close-transfer-btn').forEach(function(el) {
            el.addEventListener('click', closeTransferModal);
        });
        document.querySelectorAll('.close-history-btn').forEach(function(el) {
            el.addEventListener('click', closeHistoryModal);
        });
    });
</script>
@endsection
