<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch QR Print — CMMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style nonce="{{ $cspNonce }}">
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        /* ===== SCREEN LAYOUT ===== */
        .page-header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .page-header h1 {
            font-size: 18px;
            font-weight: 800;
            color: #1e293b;
        }

        .page-header p {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-back {
            background: white;
            border: 1px solid #e2e8f0;
            color: #475569;
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-back:hover { border-color: #94a3b8; background: #f8fafc; }

        .btn-select-all {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #374151;
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-select-all:hover { border-color: #0038A8; color: #0038A8; }

        .btn-print {
            background: #0038A8;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }
        .btn-print:hover { background: #002d8c; }
        .btn-print:disabled { background: #94a3b8; cursor: not-allowed; }

        .selected-count {
            background: #eff6ff;
            color: #0038A8;
            border: 1px solid #bfdbfe;
            padding: 9px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 800;
        }

        /* ===== FILTER BAR ===== */
        .filter-bar {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 28px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-input {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }
        .filter-input:focus { border-color: #0038A8; }

        /* ===== ASSET TABLE ===== */
        .table-container {
            padding: 20px 28px;
        }

        .asset-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .asset-table th {
            background: #f1f5f9;
            padding: 11px 14px;
            font-size: 11px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }

        .asset-table td {
            padding: 11px 14px;
            font-size: 13px;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
        }

        .asset-table tbody tr:hover td { background: #f8faff; }
        .asset-table tbody tr.tr-hover-row { transition: all 0.2s; position: relative; }
        .asset-table tbody tr.tr-hover-row:hover { background: #f8fafc !important; }
        .asset-table tbody tr.tr-hover-row:hover td:first-child { box-shadow: inset 4px 0 0 #0038A8; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }

        .asset-table tbody tr.selected td {
            background: #eff6ff;
        }

        .cb-col { width: 44px; text-align: center; }

        .asset-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #0038A8;
        }

        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .sp-active  { background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.15); }
        .sp-spare   { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.15); }
        .sp-other   { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.15); }

        /* ===== CUSTODIAN GROUPS + SET ROWS (QR plan Phase A) ===== */
        .group-row td {
            background: #f1f5f9;
            font-size: 12px;
            font-weight: 800;
            color: #0038A8;
            padding: 9px 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        .group-meta { color: #64748b; font-weight: 600; margin-left: 10px; font-size: 11px; }
        .group-select {
            float: right;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 700;
            color: #374151;
            cursor: pointer;
        }
        .group-select:hover { border-color: #0038A8; color: #0038A8; }
        .component-row td { background: #fafafa; color: #94a3b8; }
        .component-row .name-bold { font-weight: 600; }
        .component-note { font-size: 10px; font-style: italic; color: #94a3b8; font-weight: 500; }
        .set-badge {
            display: inline-block;
            background: #eff6ff;
            color: #0038A8;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 10px;
            font-weight: 800;
            margin-left: 6px;
            vertical-align: middle;
        }
        tr.covered td { background: #f0fdf4; }

        /* ===== 1" x 1" (25.4mm) sticker — QR plan §9.5.
           Same class names as inventory/qr-sticker.blade.php; markup comes from
           the shared fragment (inventory/_sticker.blade.php) via ?fragment=1. ===== */
        .sticker {
            width: 25.4mm;
            height: 25.4mm;
            background: #fff;
            border: 0.5px dashed #94a3b8;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 0.9mm;
            overflow: hidden;
            text-align: center;
            box-sizing: border-box;
            line-height: 1;
        }
        .sticker .qr { width: 17mm; height: 17mm; }
        .sticker .qr svg { width: 100% !important; height: 100% !important; display: block; }
        .sticker .s-id { font-family: 'Courier New', monospace; font-size: 6pt; font-weight: 800; color: #0f172a; margin-top: 0.5mm; }
        .sticker .s-name { font-size: 5pt; font-weight: 700; color: #334155; margin-top: 0.2mm; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sticker .s-flag { font-size: 5pt; font-weight: 800; color: #0038A8; margin-top: 0.2mm; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sticker .s-serial { font-family: 'Courier New', monospace; font-size: 4.5pt; color: #64748b; margin-top: 0.2mm; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* ===== PRINT LAYOUT ===== */
        @media print {
            body * { visibility: hidden; }

            #printSection, #printSection * { visibility: visible; }

            #printSection {
                position: absolute;
                top: 0; left: 0;
                width: 100%;
            }

            @page {
                size: A4 portrait;
                margin: 5mm;
            }

            /* QR plan §9.5 — 1" x 1" cells: 7 across x 10 rows = 70/A4 */
            .sticker-grid {
                display: grid;
                grid-template-columns: repeat(7, 25.4mm);
                gap: 3mm;
                justify-content: center;
                padding-bottom: 6mm;
            }

            .sticker-grid .sticker { page-break-inside: avoid; }
        }

        /* Screen preview of sticker grid */
        .print-preview-note {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 10px 16px;
            margin: 0 28px 16px;
            font-size: 13px;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #printSection {
            display: none;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        .empty-state i { font-size: 40px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; }

        /* ===== INLINE STYLE REPLACEMENTS ===== */
        .icon-gray { color: #94a3b8; }
        .search-wide { width: 280px; }
        .mobile-table-hint { display: none; }
        td.loading-row { text-align: center; padding: 40px; color: #64748b; }
        td.error-row { text-align: center; padding: 40px; color: #dc2626; }
        .row-pointer { cursor: pointer; }
        .id-monospace { font-family: monospace; font-weight: 700; color: #0038A8; }
        td.name-bold { font-weight: 600; }
        td.cell-mono { font-family: monospace; font-size: 12px; }
        /* ===== MOBILE STICKY PRINT BAR (lilitaw lang sa ≤767px) ===== */
        .mobile-print-bar { display: none; }

        @media screen and (max-width: 767px) {
            body { padding-bottom: 84px; }

            /* Header: compact chips — Back + Select All sa isang hilera.
               Ang count at Print ay nasa sticky bottom bar na. */
            .page-header { flex-direction: column !important; align-items: stretch !important; gap: 8px !important; padding: 10px 14px !important; }
            .page-header h1 { font-size: 16px !important; }
            .page-header p { display: none !important; }
            .header-actions { width: 100% !important; display: flex !important; flex-direction: row !important; flex-wrap: wrap !important; gap: 8px !important; }
            .header-actions .btn-back,
            .header-actions .btn-select-all {
                flex: 1 1 0 !important;
                width: auto !important;
                min-height: 44px !important;
                justify-content: center !important;
                font-size: 13px !important;
                padding: 10px 12px !important;
                border-radius: 8px !important;
            }
            .header-actions .btn-print { display: none !important; }
            .header-actions .selected-count { display: none !important; }

            /* Filters: full-width ang search, magkatabi ang dalawang select */
            .filter-bar {
                display: grid !important;
                grid-template-columns: 1fr 1fr;
                gap: 8px !important;
                padding: 10px 14px !important;
            }
            .filter-bar .icon-gray { display: none !important; }
            .filter-bar .search-wide { grid-column: 1 / -1; width: 100% !important; }
            .filter-bar .filter-input { width: 100% !important; }

            .print-preview-note { margin: 0 12px 12px !important; padding: 10px 14px !important; font-size: 12px !important; align-items: flex-start !important; }
            /* Cards na ang listahan — walang horizontal scroll, tanggal ang hint */
            .mobile-table-hint { display: none !important; }

            /* ===== TABLE → STACKED CARDS (CSS-only; iisang render path) ===== */
            .table-container { overflow: visible !important; padding: 0 12px !important; }
            .asset-table {
                display: block !important;
                min-width: 0 !important;
                width: 100% !important;
                background: transparent !important;
                border: none !important;
                border-radius: 0 !important;
            }
            .asset-table thead { display: none !important; }
            .asset-table tbody { display: block !important; }
            .asset-table tbody tr { display: block !important; }
            .asset-table tbody tr > td { display: block !important; }
            /* Lahat ng cell tints → transparent; ang kulay ay nasa <tr> na */
            .asset-table tbody tr.asset-row > td {
                background: transparent !important;
                border: none !important;
                padding: 0 !important;
                font-size: 13px !important;
            }

            /* Custodian group header → card header bar (select button = laging kita) */
            .asset-table tbody tr.group-row {
                margin: 16px 0 8px;
                background: #f1f5f9;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
            }
            .asset-table tbody tr.group-row > td {
                display: flex !important;
                flex-wrap: wrap;
                align-items: center;
                gap: 4px 8px;
                background: transparent !important;
                border: none !important;
                border-radius: 10px;
                padding: 10px 12px !important;
            }
            .group-row .group-label { flex: 1 1 auto; min-width: 0; }
            .group-row .group-meta { flex: 1 0 100%; order: 3; margin-left: 0 !important; }
            .group-row .group-select {
                float: none !important;
                flex: 0 0 auto;
                order: 2;
                margin-left: auto !important;
                min-height: 36px;
                padding: 6px 14px !important;
                border-radius: 8px !important;
                font-size: 12px !important;
            }

            /* Asset card: line1 = ☑ | name + SET badge ... status
                           line2 = #ID · SN: … · PAR: …  (forced break) */
            .asset-table tbody tr.asset-row {
                display: flex !important;
                flex-wrap: wrap;
                position: relative;
                align-items: flex-start;
                column-gap: 10px;
                row-gap: 3px;
                padding: 10px 12px;
                margin-bottom: 8px;
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
            }
            /* Pseudo flex item na 100% ang basis — pinipilit ang bagong linya
               bago ang #ID / SN / PAR (hindi na sila dumidikit sa status pill) */
            .asset-table tbody tr.asset-row::before {
                content: '';
                flex: 1 0 100%;
                order: 4;
                height: 0;
            }
            .asset-table tbody tr.asset-row > td.cb-col {
                order: 1;
                width: auto !important;
                flex: 0 0 auto !important;
                align-self: flex-start;
                padding: 2px 10px 0 0 !important;
            }
            .asset-table tbody tr.asset-row > td:nth-child(3) { order: 2; flex: 1 1 auto; min-width: 0; }
            .asset-table tbody tr.asset-row > td:nth-child(7) { order: 3; flex: 0 0 auto; }
            .asset-table tbody tr.asset-row > td:nth-child(2) { order: 5; flex: 0 1 auto; }
            .asset-table tbody tr.asset-row > td:nth-child(4),
            .asset-table tbody tr.asset-row > td:nth-child(5) { order: 6; flex: 0 1 auto; }
            /* Mga label na nawala sa pagka-hide ng table headers */
            .asset-table tbody tr.asset-row > td:nth-child(4)::before { content: 'SN '; font-weight: 800; color: #94a3b8; font-size: 10px; }
            .asset-table tbody tr.asset-row > td:nth-child(5)::before { content: 'PAR '; font-weight: 800; color: #94a3b8; font-size: 10px; }
            .asset-table tbody tr.asset-row > td:nth-child(6) { display: none !important; } /* may Category filter naman */
            .asset-table tbody tr.asset-row > td:nth-child(2),
            .asset-table tbody tr.asset-row > td:nth-child(4),
            .asset-table tbody tr.asset-row > td:nth-child(5) { font-size: 11px !important; color: #64748b; }

            /* Row states — nasa <tr> na ang kulay (selected > covered kung pareho) */
            .asset-table tbody tr.asset-row.selected { background: #eff6ff !important; border-color: #bfdbfe; }
            .asset-table tbody tr.component-row { background: #fafafa; }
            .asset-table tbody tr.asset-row.covered { background: #f0fdf4 !important; border-color: #bbf7d0; }
            .asset-table tbody tr.component-row > td:nth-child(3) { padding-left: 16px !important; }

            /* Sticky bottom bar: laging kitang-kita ang count at Print */
            .mobile-print-bar {
                display: flex !important;
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 200;
                align-items: center;
                gap: 10px;
                padding: 10px 14px calc(10px + env(safe-area-inset-bottom, 0px));
                background: #fff;
                border-top: 1px solid #e2e8f0;
                box-shadow: 0 -4px 16px rgba(15, 23, 42, 0.10);
            }
            .mobile-print-bar .mcb-count { flex: 1 1 auto; min-width: 0; font-size: 12px; font-weight: 800; color: #475569; }
            .mobile-print-bar .mcb-print {
                flex: 0 0 auto;
                display: flex;
                align-items: center;
                gap: 8px;
                background: #0038A8;
                color: #fff;
                border: none;
                border-radius: 8px;
                min-height: 46px;
                padding: 10px 18px;
                font-size: 14px;
                font-weight: 800;
                cursor: pointer;
            }
            .mobile-print-bar .mcb-print:disabled { background: #94a3b8; cursor: not-allowed; }

            .asset-table input[type="checkbox"] { width: 22px !important; height: 22px !important; accent-color: #0038A8 !important; }
        }
    </style>
</head>
<body>

<!-- SCREEN HEADER -->
<div class="page-header">
    <div>
        <h1>Batch QR Sticker Print</h1>
        <p>Piliin ang mga assets, tapos i-click ang Print. Icut ang bawat sticker bago idikit sa asset.</p>
    </div>
    <div class="header-actions">
        <a href="{{ route('inventory.index') }}" class="btn-back">
            <i class="fa-solid fa-arrow-left"></i> Back to Inventory
        </a>
        <button class="btn-select-all" id="selectAllBtn">
            <i class="fa-solid fa-check-double"></i> Select All
        </button>
        <span class="selected-count" id="selectedCount">0 selected</span>
        <button class="btn-print" id="printBtn" disabled>
            <i class="fa-solid fa-print"></i> Print Selected
        </button>
    </div>
</div>

<!-- FILTER BAR -->
<div class="filter-bar">
    <i class="fa-solid fa-magnifying-glass icon-gray"></i>
    <input type="text" class="filter-input search-wide" id="searchInput" placeholder="Search custodian/asset, serial, PAR..."><!-- input listener = nonce'd script below (inline oninput is blocked by the strict production CSP) -->
    <select class="filter-input" id="statusFilter">
        <option value="">All Status</option>
        <option value="Active">Active</option>
        <option value="Spare">Spare</option>
        <option value="For Repair">For Repair</option>
        <option value="Under Maintenance">Under Maintenance</option>
    </select>
    <select class="filter-input" id="categoryFilter">
        <option value="">All Categories</option>
        <option value="Desktop">Desktop</option>
        <option value="Laptop">Laptop</option>
        <option value="Monitor">Monitor</option>
        <option value="Printer/Scanner">Printer/Scanner</option>
        <option value="Peripherals">Peripherals</option>
        <option value="Network/Server">Network/Server</option>
        <option value="Others">Others</option>
    </select>
</div>

<!-- NOTICE -->
<div class="print-preview-note">
    <i class="fa-solid fa-circle-info"></i>
    <span>I-select ang gustong i-print na assets. <strong>SET = isang sticker lang sa parent</strong> — auto-covered ang components (isang scan = buong set). Bawat sticker ay <strong>1" × 1"</strong> (25.4mm), grid ng <strong>70 stickers/A4</strong> — QR, asset ID, at item name. Print sa <strong>100% scale</strong> (huwag "Fit to page" — liliit ang QR at hindi ma-i-scan). I-cut bago idikit!</span>
</div>

<!-- ASSET TABLE -->
        <div class="mobile-table-hint"><i class="fa-solid fa-arrow-right-arrow-left"></i> Swipe table horizontally to view all columns</div>
        <div class="table-container">
    <table class="asset-table" id="assetTable">
        <thead>
            <tr>
                <th class="cb-col"><input type="checkbox" id="masterCheck" class="asset-checkbox"></th>
                <th>Asset ID</th>
                <th>Item Name</th>
                <th>Serial Number</th>
                <th>PAR No.</th>
                <th>Category</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody id="tableBody">
            <tr><td colspan="7" class="loading-row"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading assets...</td></tr>
        </tbody>
    </table>
</div>

<!-- HIDDEN PRINT SECTION — Generated dynamically before print -->
<div id="printSection">
    <div class="sticker-grid" id="stickerGrid"></div>
</div>

<!-- MOBILE STICKY PRINT BAR — count + Print laging nasa reach; CSS-hidden on desktop -->
<div class="mobile-print-bar" id="mobilePrintBar">
    <span class="mcb-count" id="mobileSelectedCount">0 stickers · covers 0 pcs</span>
    <button type="button" class="mcb-print" id="mobilePrintBtn" disabled>
        <i class="fa-solid fa-print"></i> Print Selected
    </button>
</div>

<script nonce="{{ $cspNonce }}">
    let allAssets = [];
    let selectedIds = new Set();

    // Fetch ALL assets — endpoint is paginated (50/100 per page), so loop
    // through every page to make the selection list complete.
    async function loadAllAssets() {
        const tbody = document.getElementById('tableBody');
        let collected = [];
        let page = 1;
        try {
            while (true) {
                const res = await fetch('{{ route('inventory.data') }}?per_page=100&page=' + page);
                const data = await res.json();
                if (!data.success) throw new Error('Asset load failed');
                collected = collected.concat(data.assets);
                const lastPage = Math.max(data.last_page, 1);
                tbody.innerHTML = '<tr><td colspan="7" class="loading-row"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading assets... page ' + page + ' of ' + lastPage + '</td></tr>';
                if (!data.assets.length || page >= lastPage) break;
                page++;
            }
            allAssets = collected;
            // Re-apply the ACTIVE search/filters — typing while the paged fetch
            // is still running must survive each page arrival (the old full-list
            // render clobbered the filtered view back to everything).
            filterTable();
        } catch (e) {
            tbody.innerHTML =
                '<tr><td colspan="7" class="error-row">Failed to load assets. Please refresh.</td></tr>';
        }
    }
    loadAllAssets();

    function groupByCustodian(assets) {
        const groups = new Map();
        assets.forEach(a => {
            const key = a.assigned_to_name ? 'u:' + a.assigned_to_name : ' unassigned';
            if (!groups.has(key)) {
                groups.set(key, {
                    key: key,
                    label: a.assigned_to_name || 'Unassigned / Spare',
                    office: a.assigned_to_name ? (a.assigned_to_office || a.assigned_to_department || '') : '',
                    assets: []
                });
            }
            groups.get(key).assets.push(a);
        });
        const ordered = [];
        const unassigned = groups.get(' unassigned');
        groups.forEach((g, key) => { if (key !== ' unassigned') ordered.push(g); });
        if (unassigned) ordered.push(unassigned);
        return ordered;
    }

    function orderGroupAssets(assets) {
        const isComponent = a => a.parent_asset_id != null;
        const parents = assets.filter(a => !isComponent(a));
        const components = assets.filter(isComponent);
        const out = [];
        const placed = new Set();
        parents.forEach(p => {
            out.push(p);
            components.filter(c => c.parent_asset_id === p.asset_id).forEach(c => {
                out.push(c);
                placed.add(c.asset_id);
            });
        });
        components.filter(c => !placed.has(c.asset_id)).forEach(c => out.push(c));
        return out;
    }

    function renderTable(assets) {
        const tbody = document.getElementById('tableBody');
        if (!assets.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="empty-state"><i class="fa-solid fa-box-open"></i><p>No assets found.</p></td></tr>';
            return;
        }

        tbody.innerHTML = groupByCustodian(assets).map(group => {
            const setCount = group.assets.filter(a => !a.parent_asset_id && (a.components_count || 0) > 0).length;
            const header = `
                <tr class="group-row">
                    <td colspan="7">
                        <span class="group-label">▾ ${escHtml(group.label)}${group.office ? ' — ' + escHtml(group.office) : ''}</span>
                        <span class="group-meta">${group.assets.length} assets · ${setCount} sets</span>
                        <button type="button" class="group-select" data-group="${group.key}">select all</button>
                    </td>
                </tr>`;
            return header + orderGroupAssets(group.assets).map(a => renderRow(a, group)).join('');
        }).join('');
        updateUI();
    }

    function renderRow(a, group) {
        const checked = selectedIds.has(a.asset_id) ? 'checked' : '';
        const selectedClass = selectedIds.has(a.asset_id) ? 'selected' : '';
        const statusClass = a.status === 'Active' ? 'sp-active' : (a.status === 'Spare' ? 'sp-spare' : 'sp-other');
        const sn = a.serial_number || '—';
        const par = a.par_number || '—';
        const isComponent = a.parent_asset_id != null;
        const coveredClass = isComponent && selectedIds.has(a.parent_asset_id) ? 'covered' : '';

        if (isComponent) {
            return `
                <tr class="${selectedClass} ${coveredClass} asset-row component-row row-pointer tr-hover-row" id="row-${a.asset_id}" data-id="${a.asset_id}" data-parent="${a.parent_asset_id}" data-group="${group.key}">
                    <td class="cb-col">
                        <input type="checkbox" class="asset-checkbox" data-id="${a.asset_id}" disabled ${checked}>
                    </td>
                    <td><span class="id-monospace">↳ #${a.asset_id}</span></td>
                    <td class="name-bold">${escHtml(a.item_name)} <span class="component-note">component of #${a.parent_asset_id} — no sticker</span></td>
                    <td class="cell-mono">${escHtml(sn)}</td>
                    <td class="cell-mono">${escHtml(par)}</td>
                    <td>${escHtml(a.category || '—')}</td>
                    <td><span class="status-pill ${statusClass}">${escHtml(a.status)}</span></td>
                </tr>`;
        }

        const setBadge = (a.components_count || 0) > 0 ? ` <span class="set-badge">▣ SET(${a.components_count})</span>` : '';
        return `
            <tr class="${selectedClass} asset-row row-pointer tr-hover-row" id="row-${a.asset_id}" data-id="${a.asset_id}" data-group="${group.key}">
                <td class="cb-col">
                    <input type="checkbox" class="asset-checkbox" data-id="${a.asset_id}" ${checked}>
                </td>
                <td><span class="id-monospace">#${a.asset_id}</span></td>
                <td class="name-bold">${escHtml(a.item_name)}${setBadge}</td>
                <td class="cell-mono">${escHtml(sn)}</td>
                <td class="cell-mono">${escHtml(par)}</td>
                <td>${escHtml(a.category || '—')}</td>
                <td><span class="status-pill ${statusClass}">${escHtml(a.status)}</span></td>
            </tr>`;
    }

    function toggleRow(id) {
        const cb = document.querySelector(`input[data-id="${id}"]`);
        if (!cb || cb.disabled) return;
        toggleById(id, !cb.checked);
        cb.checked = !cb.checked;
    }

    function toggleById(id, checked) {
        if (checked) {
            selectedIds.add(id);
        } else {
            selectedIds.delete(id);
        }
        const row = document.getElementById(`row-${id}`);
        if (row) row.classList.toggle('selected', checked);
        refreshCovered(id, checked);
        updateUI();
    }

    // Visual feedback only: a selected parent marks its component rows as
    // "covered" (green tint) — components never enter selectedIds themselves.
    function refreshCovered(parentId, covered) {
        document.querySelectorAll(`tr[data-parent="${parentId}"]`).forEach(r => {
            r.classList.toggle('covered', covered);
        });
    }

    function masterToggle(masterCb) {
        const visibleCbs = document.querySelectorAll('#tableBody input.asset-checkbox');
        visibleCbs.forEach(cb => {
            if (cb.disabled) return; // components: covered by their parent's sticker
            const id = parseInt(cb.dataset.id);
            cb.checked = masterCb.checked;
            if (masterCb.checked) selectedIds.add(id);
            else selectedIds.delete(id);
            const row = document.getElementById(`row-${id}`);
            if (row) row.classList.toggle('selected', masterCb.checked);
            refreshCovered(id, masterCb.checked);
        });
        updateUI();
    }

    // Per-custodian group header button: toggles only that group's selectable rows.
    function groupToggle(key, forceState) {
        const rows = document.querySelectorAll(`#tableBody tr[data-group="${key}"]`);
        const cbs = [];
        rows.forEach(r => {
            const cb = r.querySelector('input.asset-checkbox');
            if (cb && !cb.disabled) cbs.push(cb);
        });
        if (!cbs.length) return;
        const target = (forceState != null) ? forceState : !cbs.every(cb => cb.checked);
        cbs.forEach(cb => {
            const id = parseInt(cb.dataset.id);
            cb.checked = target;
            if (target) selectedIds.add(id);
            else selectedIds.delete(id);
            const row = document.getElementById(`row-${id}`);
            if (row) row.classList.toggle('selected', target);
            refreshCovered(id, target);
        });
        updateUI();
    }

    function toggleSelectAll() {
        const masterCb = document.getElementById('masterCheck');
        masterCb.checked = !masterCb.checked;
        masterToggle(masterCb);
    }

    function updateUI() {
        const count = selectedIds.size;
        // "covers" = selected stickers + every component linked under them
        let covered = count;
        allAssets.forEach(a => {
            if (selectedIds.has(a.asset_id)) covered += (a.components_count || 0);
        });
        document.getElementById('selectedCount').textContent = `${count} stickers · covers ${covered} pcs`;
        document.getElementById('printBtn').disabled = count === 0;
        // Sticky mobile bar mirrors the header count + print enable state
        document.getElementById('mobileSelectedCount').textContent = `${count} stickers · covers ${covered} pcs`;
        document.getElementById('mobilePrintBtn').disabled = count === 0;
        const enabled = document.querySelectorAll('#tableBody input.asset-checkbox:not([disabled])');
        let checkedCount = 0;
        enabled.forEach(cb => { if (cb.checked) checkedCount++; });
        const master = document.getElementById('masterCheck');
        if (master) master.checked = enabled.length > 0 && checkedCount === enabled.length;
    }

    function filterTable() {
        const search = document.getElementById('searchInput').value.trim().toLowerCase();
        const status = document.getElementById('statusFilter').value;
        const category = document.getElementById('categoryFilter').value;

        const filtered = allAssets.filter(a => {
            // Server-side parity (GetInventoryAssetsAction: item_name, serial, par,
            // property) + custodian name — the page groups BY custodian, so typing
            // a person's name must surface their group.
            const matchSearch = !search ||
                (a.item_name || '').toLowerCase().includes(search) ||
                (a.serial_number || '').toLowerCase().includes(search) ||
                (a.par_number || '').toLowerCase().includes(search) ||
                (a.property_number || '').toLowerCase().includes(search) ||
                (a.assigned_to_name || '').toLowerCase().includes(search);
            const matchStatus = !status || a.status === status;
            const matchCat = !category || a.category === category;
            return matchSearch && matchStatus && matchCat;
        });
        renderTable(filtered);
    }

    function triggerPrint() {
        const selected = allAssets.filter(a => selectedIds.has(a.asset_id));
        if (!selected.length) return;

        // ONE shared sticker template (inventory/_sticker.blade.php): fetch the
        // fragment (?fragment=1) per selected asset — same markup as the single
        // sticker page (1" x 1" cells; parents print, components reference them).
        const promises = selected.map(a =>
            fetch(`{{ route('inventory.qr-sticker', '_ID_') }}`.replace('_ID_', a.asset_id) + '?fragment=1')
                .then(r => r.text())
                .catch(() => '')
        );

        Promise.all(promises).then(cells => {
            const grid = document.getElementById('stickerGrid');
            grid.innerHTML = cells.join('');

            document.getElementById('printSection').style.display = 'block';
            setTimeout(() => {
                window.print();
                document.getElementById('printSection').style.display = 'none';
            }, 300);
        });
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('statusFilter').addEventListener('change', filterTable);
        document.getElementById('categoryFilter').addEventListener('change', filterTable);
        // Search: registered here (nonce'd script) — the strict production CSP
        // blocks inline oninput attributes, so this is the LIVE wiring.
        document.getElementById('searchInput').addEventListener('input', filterTable);
        document.getElementById('masterCheck').addEventListener('change', function() {
            masterToggle(this);
        });
        document.getElementById('selectAllBtn').addEventListener('click', toggleSelectAll);
        document.getElementById('printBtn').addEventListener('click', triggerPrint);
        document.getElementById('mobilePrintBtn').addEventListener('click', triggerPrint);
    });

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('asset-checkbox') && e.target.dataset.id) {
            toggleById(parseInt(e.target.dataset.id), e.target.checked);
        }
    });

    document.addEventListener('click', function(e) {
        // Checkbox clicks own themselves via the native toggle + change handler
        // above — without this guard the row handler double-toggles and the
        // selection instantly empties (print button stays disabled forever).
        if (e.target.closest('input[type="checkbox"]')) return;
        var groupBtn = e.target.closest('.group-select');
        if (groupBtn) {
            groupToggle(groupBtn.dataset.group);
            return;
        }
        var row = e.target.closest('.asset-row');
        if (row) {
            // Component rows are not selectable (covered by the parent's sticker)
            // — clicking one toggles its PARENT instead.
            var targetId = row.dataset.parent ? parseInt(row.dataset.parent) : parseInt(row.dataset.id);
            toggleRow(targetId);
        }
    });
</script>

</body>
</html>
