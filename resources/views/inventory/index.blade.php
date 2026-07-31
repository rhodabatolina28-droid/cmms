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

        /* ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ Utility classes (migrated from inline styles) ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚ÂÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ */
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
            <!-- SUMMARY RIBBON ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â inside card -->
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

@include('inventory.partials._modal_asset')

@include('inventory.partials._modal_transfer')

@include('inventory.partials._modal_history')

@endsection

@section('scripts')
    @vite(['resources/js/inventory.js'])
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
    window.addEventListener('load', function() {
        const transferForm = document.getElementById('transferForm');
        if (transferForm) transferForm.addEventListener('submit', window.saveTransfer);

        const itPartType = document.getElementById('itPartType');
        if (itPartType) itPartType.addEventListener('change', window.itPartTypeChange);

        const addAssetBtn = document.getElementById('addAssetBtn');
        if (addAssetBtn) addAssetBtn.addEventListener('click', window.openAddAssetModal);

        const exportInvLink = document.getElementById('exportInvLink');
        if (exportInvLink) {
            exportInvLink.addEventListener('click', function(e) {
                e.preventDefault();
                window.exportFilteredInventory();
            });
        }
        document.querySelectorAll('.close-asset-btn').forEach(function(el) {
            el.addEventListener('click', window.closeAssetModal);
        });
        document.querySelectorAll('.close-transfer-btn').forEach(function(el) {
            el.addEventListener('click', window.closeTransferModal);
        });
        document.querySelectorAll('.close-history-btn').forEach(function(el) {
            el.addEventListener('click', window.closeHistoryModal);
        });
    });
</script>
@endsection
