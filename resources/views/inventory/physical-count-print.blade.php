<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Physical Count Report #{{ $session->id }}</title>
    <style nonce="{{ $cspNonce }}">
        /* ── PAGE SETUP: A4, back-to-back ── */
        @page {
            size: A4 landscape;
            margin: 0.35in 0.35in 0.5in 0.35in;
        }
        @page :left {
            margin-left: 0.45in;
            margin-right: 0.3in;
        }
        @page :right {
            margin-left: 0.3in;
            margin-right: 0.45in;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Calibri', 'Segoe UI', Arial, sans-serif;
            font-size: 8pt;
            color: #000;
            line-height: 1.2;
        }

        /* ── NO PRINT ── */
        .no-print {
            text-align: right;
            margin-bottom: 8px;
        }
        .no-print button {
            padding: 6px 12px; font-size: 11px; font-weight: 700;
            cursor: pointer; border: 1px solid #999; border-radius: 3px; background: #fff;
        }
        .no-print button:hover { background: #eee; }
        @media print { .no-print { display: none; } }

        /* ── HEADER (repeats on every page) ── */
        .page-header {
            text-align: center;
            border-bottom: 2px solid #0038A8;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .page-header .repub {
            font-size: 8pt; font-weight: 700;
            letter-spacing: 1.5px; text-transform: uppercase;
        }
        .page-header .agency {
            font-size: 9.5pt; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .page-header .title {
            font-size: 10pt; font-weight: 800;
            text-transform: uppercase; margin-top: 1px; letter-spacing: 1px;
        }
        .page-header .sub {
            font-size: 7pt; color: #555;
        }
        .page-header .page-num {
            font-size: 6.5pt; color: #888; float: right; margin-top: -14px;
        }

        /* ── SESSION INFO ── */
        .session-bar {
            font-size: 7.5pt;
            margin-bottom: 6px;
            padding: 2px 5px;
            background: #f2f2f2;
            border: 1px solid #bbb;
        }
        .session-bar span { margin-right: 14px; }
        .session-bar .lbl { font-weight: 700; }

        /* ── SUMMARY ── */
        .summary-row {
            display: flex; gap: 4px; margin-bottom: 6px;
        }
        .summary-item {
            flex: 1; border: 1px solid #888; text-align: center; padding: 2px 2px;
        }
        .summary-item .num { font-size: 13pt; font-weight: 800; }
        .summary-item .lbl { font-size: 6pt; font-weight: 700; text-transform: uppercase; }

        /* ── ASSET TABLE ── */
        table.asset-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
        }
        table.asset-table thead { display: table-header-group; }
        table.asset-table thead th {
            background: #0038A8;
            color: #fff;
            padding: 3px 2px;
            font-size: 6pt;
            font-weight: 700;
            text-transform: uppercase;
            text-align: left;
            border: 1px solid #0038A8;
            letter-spacing: 0.2px;
        }
        table.asset-table thead th.c { text-align: center; }
        table.asset-table tbody tr { page-break-inside: avoid; }
        table.asset-table tbody td {
            padding: 2px 2px;
            border: 0.5px solid #bbb;
            vertical-align: top;
        }
        table.asset-table tbody td.c { text-align: center; vertical-align: middle; }
        table.asset-table tbody tr:nth-child(even) { background: #f8f8f8; }
        .td-bold { font-weight: 600; }
        .serial { font-family: 'Courier New', monospace; font-size: 6.5pt; }

        /* Category divider row */
        .cat-div td {
            background: #e8e8e8;
            font-weight: 800;
            font-size: 7.5pt;
            padding: 3px 2px;
            border: 1px solid #888;
            text-transform: uppercase;
        }

        /* Custodian conforme section (group=custodian mode) */
        .conforme-section { page-break-inside: avoid; margin-top: 10px; }
        .conforme-title {
            font-size: 9pt; font-weight: 800; text-transform: uppercase;
            border-bottom: 2px solid #0038A8; padding-bottom: 3px; margin-bottom: 6px;
        }
        .conforme-note { font-size: 6.5pt; color: #555; margin-bottom: 8px; font-style: italic; }
        .conforme-grid { display: flex; flex-wrap: wrap; gap: 8px 16px; }
        .conforme-item { flex: 1 1 30%; min-width: 28%; text-align: center; page-break-inside: avoid; }
        .conforme-item .sig-line { border-top: 1px solid #000; margin-top: 34px; padding-top: 3px; }
        .conforme-item .conforme-name { font-weight: 700; font-size: 7.5pt; }
        .conforme-item .conforme-par { font-size: 6.5pt; font-family: 'Courier New', monospace; }
        .conforme-item .conforme-sub { font-size: 6.5pt; color: #555; }

        /* Checkbox column */
        .chk { width: 20px; text-align: center; }

        /* ── PAGE BREAK before maintenance section ── */
        .maintenance-section {
            page-break-before: always;
            page-break-after: always;
        }
        .maintenance-section .section-title {
            font-size: 10pt; font-weight: 800; text-transform: uppercase;
            border-bottom: 2px solid #0038A8;
            padding-bottom: 3px; margin-bottom: 8px;
        }
        .maintenance-section .instructions {
            font-size: 7pt; color: #555; margin-bottom: 8px;
        }

        table.maintenance-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5pt;
        }
        table.maintenance-table thead th {
            background: #0038A8;
            color: #fff;
            padding: 4px 3px;
            font-size: 6.5pt;
            font-weight: 700;
            text-transform: uppercase;
            border: 1px solid #0038A8;
            text-align: left;
        }
        table.maintenance-table thead th.c { text-align: center; }
        table.maintenance-table tbody td {
            padding: 3px;
            border: 0.5px solid #bbb;
            vertical-align: top;
        }
        table.maintenance-table tbody td.c { text-align: center; vertical-align: middle; }
        /* Row height for handwritten entries */
        table.maintenance-table tbody tr { height: 18px; }
        .maintenance-empty td { color: #bbb; font-style: italic; }

        /* ── LEGEND ── */
        .legend {
            display: flex; gap: 10px; font-size: 6.5pt; color: #555; margin-bottom: 4px;
        }

        /* ── SIGNATURES ── */
        .signatures {
            display: flex; gap: 20px; margin: 18px 0 6px 0; page-break-inside: avoid;
        }
        .sig { flex: 1; text-align: center; }
        .sig .line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 3px;
            font-weight: 700;
            font-size: 8pt;
        }
        .sig .title { font-size: 6.5pt; color: #555; margin-top: 1px; }

        /* ── FOOTER ── */
        .print-footer {
            text-align: center;
            font-size: 6pt;
            color: #999;
            border-top: 1px solid #ccc;
            padding-top: 3px;
            margin-top: 8px;
        }
    </style>
</head>
<body>

    <!-- ════════════════════════════════ -->
    <!--  FRONT: PHYSICAL COUNT REPORT   -->
    <!-- ════════════════════════════════ -->

    <div class="no-print">
        <button id="printBtn">🖨 Print / Save PDF</button>
        <button id="closePrintBtn">✕ Close</button>
    </div>

    <div class="page-header">
        <span class="page-num">Page 1</span>
        <div class="repub">Republic of the Philippines</div>
        <div class="agency">National Conciliation and Mediation Board</div>
        <div class="title">Physical Inventory Count Report</div>
        <div class="sub">Session #{{ $session->id }} &middot; {{ now()->format('F j, Y') }}@if($groupBy === 'custodian') &middot; Grouped by Custodian @endif</div>
    </div>

    <div class="session-bar">
        <span><span class="lbl">Region:</span> {{ $session->scope_region ?? 'N/A' }}</span>
        <span><span class="lbl">Branch:</span> {{ $session->scope_branch ?? 'All' }}</span>
        <span><span class="lbl">Started By:</span> {{ $session->startedBy->full_name ?? $session->startedBy->name ?? 'Unknown' }}</span>
        <span><span class="lbl">Date:</span> {{ $session->started_at->format('M d, Y') }}</span>
        <span><span class="lbl">Status:</span> {{ $session->status }}</span>
        @if($session->completed_at)
        <span><span class="lbl">Completed:</span> {{ $session->completed_at->format('M d, Y') }}</span>
        @endif
    </div>

    <div class="summary-row">
        <div class="summary-item">
            <div class="num">{{ $summary['total'] }}</div>
            <div class="lbl">Total</div>
        </div>
        <div class="summary-item">
            <div class="num">{{ $summary['counted'] }}</div>
            <div class="lbl">Counted</div>
        </div>
        <div class="summary-item">
            <div class="num" style="color:#166534;">{{ $summary['present'] }}</div>
            <div class="lbl">Present</div>
        </div>
        <div class="summary-item">
            <div class="num" style="color:#b91c1c;">{{ $summary['missing'] }}</div>
            <div class="lbl">Missing</div>
        </div>
        <div class="summary-item">
            <div class="num" style="color:#92400e;">{{ $summary['damaged'] }}</div>
            <div class="lbl">Damaged</div>
        </div>
    </div>

    <div class="legend">
        <span>☐ = Check if present</span>
        <span>OK = Present</span>
        <span>MISS = Missing</span>
        <span>DAM = Damaged</span>
        <span style="margin-left:auto;">Count = Initial of counter</span>
    </div>

    <table class="asset-table">
        <thead>
            <tr>
                <th class="c" style="width:16px;">#</th>
                <th class="c" style="width:18px;">☐</th>
                <th>Item Name</th>
                <th style="width:48px;">Brand / Model</th>
                <th style="width:65px;">Serial No</th>
                <th style="width:65px;">PAR No</th>
                <th style="width:65px;">Property No</th>
                <th style="width:28px;" class="c">St</th>
                <th style="width:34px;">Category</th>
                <th style="width:65px;">Custodian</th>
                <th style="width:26px;" class="c">Cnt</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @php $rowNum = 0; @endphp
            @forelse($grouped as $category => $assets)
                <tr class="cat-div">
                    <td colspan="12">{{ $category }} ({{ $assets->count() }})</td>
                </tr>
                @foreach($assets as $asset)
                    @php
                        $rowNum++;
                        $count = $session->counts->firstWhere('asset_id', $asset->asset_id);
                    @endphp
                    <tr>
                        <td class="c">{{ $rowNum }}</td>
                        <td class="chk">☐</td>
                        <td class="td-bold">{{ $asset->item_name }}</td>
                        <td>{{ $asset->brand ? $asset->brand . ($asset->model ? ' ' . $asset->model : '') : ($asset->model ?? '—') }}</td>
                        <td class="serial">{{ $asset->serial_number ?? '—' }}</td>
                        <td>{{ $asset->par_number ?? '—' }}</td>
                        <td>{{ $asset->property_number ?? '—' }}</td>
                        <td class="c">
                            @if($count)
                                {{ $count->status === 'Present' ? 'OK' : ($count->status === 'Missing' ? 'MISS' : 'DAM') }}
                            @else
                                <span style="color:#ccc;">—</span>
                            @endif
                        </td>
                        <td>{{ $asset->category ?? '—' }}</td>
                        <td>{{ $asset->assignedUser->full_name ?? $asset->assignedUser->name ?? '—' }}</td>
                        <td class="c">{{ $count && $count->countedBy ? (substr($count->countedBy->full_name, 0, 1) ?? '✓') : '—' }}</td>
                        <td>{{ $count->remarks ?? '' }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="12" style="text-align:center;padding:20px;color:#999;">No assets found.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($groupBy === 'custodian' && $custodianGroups)
    <div class="conforme-section">
        <div class="conforme-title">Custodian Conforme</div>
        <div class="conforme-note">I hereby certify that the properties listed under my accountability were physically counted and verified, and the reported conditions are correct.</div>
        <div class="conforme-grid">
            @foreach($custodianGroups->where('key', '!=', 0) as $group)
            <div class="conforme-item">
                <div class="sig-line"></div>
                <div class="conforme-name">{{ $group['name'] }}</div>
                @if($group['par'])<div class="conforme-par">PAR No: {{ $group['par'] }}</div>@endif
                <div class="conforme-sub">Custodian — Signature over Printed Name — Date</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="signatures">
        <div class="sig">
            <div class="line">COUNTED BY</div>
            <div class="title">Signature over Printed Name — Date</div>
        </div>
        <div class="sig">
            <div class="line">VERIFIED BY</div>
            <div class="title">Supply Officer / Designee — Date</div>
        </div>
        <div class="sig">
            <div class="line">NOTED BY</div>
            <div class="title">Chief Administrative Officer — Date</div>
        </div>
    </div>

    <!-- ═══════════════════════════════════ -->
    <!--  BACK: MAINTENANCE FINDINGS FORM   -->
    <!-- ═══════════════════════════════════ -->

    <div class="maintenance-section">
        <div class="page-header">
            <span class="page-num">Page 2</span>
            <div class="repub">Republic of the Philippines</div>
            <div class="agency">National Conciliation and Mediation Board</div>
            <div class="title">Preventive Maintenance / Repair Findings</div>
            <div class="sub">Physical Count Session #{{ $session->id }} — To be filled during inspection</div>
        </div>

        <div class="section-title">Instructions</div>
        <div class="instructions">
            For assets marked as <strong>Damaged</strong> or <strong>Missing</strong> during the physical count,
            or any asset requiring preventive maintenance, indicate the findings and recommended action below.
            This section serves as the referral for the Maintenance Team.
        </div>

        <table class="maintenance-table">
            <thead>
                <tr>
                    <th class="c" style="width:16px;">#</th>
                    <th style="width:80px;">Item Name</th>
                    <th style="width:50px;">Serial No</th>
                    <th style="width:40px;">Found Status</th>
                    <th style="width:80px;">Defect / Issue Observed</th>
                    <th style="width:70px;">Recommended Action</th>
                    <th style="width:50px;">Priority</th>
                    <th style="width:50px;">Assigned To</th>
                    <th style="width:40px;">Date<br>Attended</th>
                    <th style="width:40px;">Status</th>
                </tr>
            </thead>
            <tbody>
                @php $maintenanceRows = max(20, $summary['missing'] + $summary['damaged'] + 5); @endphp
                @for ($i = 1; $i <= $maintenanceRows; $i++)
                    <tr @if($i > $summary['missing'] + $summary['damaged']) class="maintenance-empty" @endif>
                        <td class="c">{{ $i }}</td>
                        <td>@if($i <= $summary['missing'] + $summary['damaged']) _________________ @endif</td>
                        <td>_________________</td>
                        <td class="c">
                            @if($i <= $summary['missing'] + $summary['damaged'])
                                ☐ OK &nbsp; ☐ MISS &nbsp; ☐ DAM
                            @endif
                        </td>
                        <td>_________________________</td>
                        <td>_________________________</td>
                        <td class="c">☐ Low ☐ Med ☐ High</td>
                        <td>_________________</td>
                        <td>__________</td>
                        <td class="c">☐ Open<br>☐ Done</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <div class="signatures">
            <div class="sig">
                <div class="line">ENDORSED BY</div>
                <div class="title">Supply Officer — Signature — Date</div>
            </div>
            <div class="sig">
                <div class="line">RECEIVED BY</div>
                <div class="title">Maintenance Team / ICT — Signature — Date</div>
            </div>
            <div class="sig">
                <div class="line">COMPLETED BY</div>
                <div class="title">Technician — Signature — Date</div>
            </div>
        </div>
    </div>

    <div class="print-footer">
        NCMB ICT System · Physical Count & Maintenance Report · {{ date('Y') }}
    </div>

    <script nonce="{{ $cspNonce }}">
        document.getElementById('printBtn').addEventListener('click', function() { window.print(); });
        document.getElementById('closePrintBtn').addEventListener('click', function() { window.close(); });
    </script>
</body>
</html>
