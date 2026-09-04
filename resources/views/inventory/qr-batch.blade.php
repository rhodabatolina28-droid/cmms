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
                margin: 10mm;
            }

            .sticker-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 5mm;
                padding-bottom: 10mm;
            }

            .sticker-item {
                width: 95mm;
                height: 45mm;
                border: 1px dashed #94a3b8;
                border-radius: 2mm;
                display: flex;
                flex-direction: row;
                align-items: center;
                justify-content: flex-start;
                padding: 4mm;
                page-break-inside: avoid;
                overflow: hidden;
                box-sizing: border-box;
            }

            .sticker-item svg {
                width: 32mm;
                height: 32mm;
                flex-shrink: 0;
                margin-right: 4mm;
            }

            .sticker-info {
                display: flex;
                flex-direction: column;
                justify-content: center;
                overflow: hidden;
                width: 100%;
            }

            .sticker-item .s-name {
                font-family: Arial, sans-serif;
                font-size: 11pt;
                font-weight: 900;
                color: #000;
                line-height: 1.1;
                margin-bottom: 2mm;
                text-transform: uppercase;
                word-wrap: break-word;
            }

            .sticker-item .s-id {
                font-family: 'Courier New', monospace;
                font-size: 8pt;
                font-weight: 700;
                color: #333;
                line-height: 1.3;
            }
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
        .icon-blue { color: #0038A8; margin-right: 8px; }
        .icon-gray { color: #94a3b8; }
        .search-wide { width: 280px; }
        .mobile-table-hint { display: none; }
        td.loading-row { text-align: center; padding: 40px; color: #64748b; }
        td.error-row { text-align: center; padding: 40px; color: #dc2626; }
        .row-pointer { cursor: pointer; }
        .id-monospace { font-family: monospace; font-weight: 700; color: #0038A8; }
        td.name-bold { font-weight: 600; }
        td.cell-mono { font-family: monospace; font-size: 12px; }
        @media (max-width: 767px) {
            .page-header { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; padding: 12px 16px !important; }
            .page-header h1 { font-size: 16px !important; }
            .header-actions { width: 100% !important; display: flex !important; flex-direction: column !important; gap: 8px !important; }
            .header-actions a,
            .header-actions button { width: 100% !important; justify-content: center !important; font-size: 13px !important; padding: 10px 14px !important; border-radius: 6px !important; min-height: 44px !important; }
            .header-actions .btn-print { padding: 12px 14px !important; font-size: 14px !important; letter-spacing: 0.5px !important; }
            .header-actions .btn-select-all { background: #f1f5f9 !important; border-color: #94a3b8 !important; }
            .selected-count { width: 100% !important; text-align: center !important; padding: 6px !important; font-size: 12px !important; order: -1 !important; margin-bottom: 4px !important; }
            .btn-back { display: flex !important; border-color: #e2e8f0 !important; background: #fff !important; order: 10 !important; }
            .filter-bar { flex-direction: column !important; padding: 12px 16px !important; }
            .filter-bar .filter-input { width: 100% !important; }
            .table-container { padding: 10px 12px !important; overflow-x: auto !important; }
            .asset-table { min-width: 600px !important; }
            .asset-table th,
            .asset-table td { padding: 8px 10px !important; font-size: 12px !important; }
            .print-preview-note { margin: 0 12px 12px !important; padding: 10px 14px !important; font-size: 12px !important; }
            .cb-col { width: 36px !important; }
            .search-wide { width: 100% !important; }
            .mobile-table-hint { display: flex !important; align-items: center !important; gap: 8px !important; background: #eff6ff !important; border: 1px solid #bfdbfe !important; border-radius: 8px !important; padding: 9px 12px !important; margin: 0 12px 10px !important; color: #1e40af !important; font-size: 12px !important; font-weight: 700 !important; }
            .asset-table input[type="checkbox"] { width: 20px !important; height: 20px !important; accent-color: #0038A8 !important; }
        }
    </style>
</head>
<body>

<!-- SCREEN HEADER -->
<div class="page-header">
    <div>
        <h1><i class="fa-solid fa-qrcode icon-blue"></i>Batch QR Sticker Print</h1>
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
    <input type="text" class="filter-input search-wide" id="searchInput" placeholder="Search item name or serial number..." oninput="filterTable()">
    <select class="filter-input" id="statusFilter">
        <option value="">All Status</option>
        <option value="Active">Active</option>
        <option value="Spare">Spare</option>
        <option value="Defective">Defective</option>
        <option value="For Repair">For Repair</option>
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
    <span>I-select ang gustong i-print na assets. Kapag nag-print, lalabas ang <strong>2 stickers per row</strong> sa A4 — may QR code at malaking text na item name + asset ID. I-cut bago idikit!</span>
</div>

<!-- ASSET TABLE -->
        <div class="mobile-table-hint"><i class="fa-solid fa-arrow-right-arrow-left"></i> Swipe table horizontally to view all columns</div>
        <div class="table-container">
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
            renderTable(allAssets);
        } catch (e) {
            tbody.innerHTML =
                '<tr><td colspan="7" class="error-row">Failed to load assets. Please refresh.</td></tr>';
        }
    }
    loadAllAssets();

    function renderTable(assets) {
        const tbody = document.getElementById('tableBody');
        if (!assets.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="empty-state"><i class="fa-solid fa-box-open"></i><p>No assets found.</p></td></tr>';
            return;
        }

        tbody.innerHTML = assets.map(a => {
            const checked = selectedIds.has(a.asset_id) ? 'checked' : '';
            const rowClass = selectedIds.has(a.asset_id) ? 'selected' : '';
            const statusClass = a.status === 'Active' ? 'sp-active' : (a.status === 'Spare' ? 'sp-spare' : 'sp-other');
            const sn = a.serial_number || '—';
            const par = a.par_number || '—';
            return `
                <tr class="${rowClass} asset-row row-pointer tr-hover-row" id="row-${a.asset_id}" data-id="${a.asset_id}">
                    <td class="cb-col">
                        <input type="checkbox" class="asset-checkbox" data-id="${a.asset_id}" ${checked}>
                    </td>
                    <td><span class="id-monospace">#${a.asset_id}</span></td>
                    <td class="name-bold">${escHtml(a.item_name)}</td>
                    <td class="cell-mono">${escHtml(sn)}</td>
                    <td class="cell-mono">${escHtml(par)}</td>
                    <td>${escHtml(a.category || '—')}</td>
                    <td><span class="status-pill ${statusClass}">${escHtml(a.status)}</span></td>
                </tr>`;
        }).join('');
    }

    function toggleRow(id) {
        const cb = document.querySelector(`input[data-id="${id}"]`);
        if (!cb) return;
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
        updateUI();
    }

    function masterToggle(masterCb) {
        const visibleCbs = document.querySelectorAll('#tableBody input[type=checkbox]');
        visibleCbs.forEach(cb => {
            const id = parseInt(cb.dataset.id);
            cb.checked = masterCb.checked;
            if (masterCb.checked) selectedIds.add(id);
            else selectedIds.delete(id);
            const row = document.getElementById(`row-${id}`);
            if (row) row.classList.toggle('selected', masterCb.checked);
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
        document.getElementById('selectedCount').textContent = `${count} selected`;
        document.getElementById('printBtn').disabled = count === 0;
    }

    function filterTable() {
        const search = document.getElementById('searchInput').value.toLowerCase();
        const status = document.getElementById('statusFilter').value;
        const category = document.getElementById('categoryFilter').value;

        const filtered = allAssets.filter(a => {
            const matchSearch = !search ||
                (a.item_name || '').toLowerCase().includes(search) ||
                (a.serial_number || '').toLowerCase().includes(search) ||
                (a.par_number || '').toLowerCase().includes(search);
            const matchStatus = !status || a.status === status;
            const matchCat = !category || a.category === category;
            return matchSearch && matchStatus && matchCat;
        });
        renderTable(filtered);
    }

    function triggerPrint() {
        const selected = allAssets.filter(a => selectedIds.has(a.asset_id));
        if (!selected.length) return;

        // Build sticker grid from server-generated QR codes
        const promises = selected.map(a =>
            fetch(`{{ route('inventory.qr-sticker', '_ID_') }}`.replace('_ID_', a.asset_id) + '?raw=1')
                .then(r => r.text())
                .then(html => {
                    // Extract SVG from the response
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const svg = doc.querySelector('svg');
                    return { asset: a, svgHtml: svg ? svg.outerHTML : '' };
                })
                .catch(() => ({ asset: a, svgHtml: '' }))
        );

        Promise.all(promises).then(results => {
            const grid = document.getElementById('stickerGrid');
            grid.innerHTML = results.map(({ asset, svgHtml }) => `
                <div class="sticker-item">
                    ${svgHtml}
                    <div class="sticker-info">
                        <div class="s-name">${escHtml(asset.item_name)}</div>
                        <div class="s-id">ID: #${asset.asset_id}<br>SN: ${asset.serial_number ? escHtml(asset.serial_number) : 'N/A'}</div>
                    </div>
                </div>
            `).join('');

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
        document.getElementById('masterCheck').addEventListener('change', function() {
            masterToggle(this);
        });
        document.getElementById('selectAllBtn').addEventListener('click', toggleSelectAll);
        document.getElementById('printBtn').addEventListener('click', triggerPrint);
    });

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('asset-checkbox') && e.target.dataset.id) {
            toggleById(parseInt(e.target.dataset.id), e.target.checked);
        }
    });

    document.addEventListener('click', function(e) {
        var row = e.target.closest('.asset-row');
        if (row) {
            toggleRow(parseInt(row.dataset.id));
        }
    });
</script>

</body>
</html>
