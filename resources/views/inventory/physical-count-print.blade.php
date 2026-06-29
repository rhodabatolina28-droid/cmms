<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Physical Count Report #{{ $session->id }}</title>
    <style nonce="{{ $cspNonce }}">
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', Courier, monospace; font-size: 11px; color: #000; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 12px; }
        .header h1 { font-size: 16px; font-weight: 800; text-transform: uppercase; margin-bottom: 4px; }
        .header p { font-size: 11px; color: #333; }
        .section { margin-bottom: 16px; }
        .section-title { font-size: 12px; font-weight: 800; text-transform: uppercase; border-bottom: 1px solid #666; padding-bottom: 4px; margin-bottom: 8px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; font-size: 11px; }
        .info-grid .label { font-weight: 700; }
        .summary-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-bottom: 16px; }
        .summary-box { border: 1px solid #000; padding: 8px 10px; text-align: center; }
        .summary-box .num { font-size: 20px; font-weight: 800; }
        .summary-box .lbl { font-size: 9px; text-transform: uppercase; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #eee; padding: 6px 8px; font-size: 9px; font-weight: 800; text-transform: uppercase; text-align: left; border: 1px solid #000; }
        td { padding: 5px 8px; font-size: 10px; border: 1px solid #ccc; }
        td.centered { text-align: center; }
        .pill { display: inline-block; padding: 1px 6px; font-size: 9px; font-weight: 700; border: 1px solid #000; }
        .pill-present { background: #fff; }
        .pill-missing { background: #fff; }
        .pill-damaged { background: #fff; }
        .footer { text-align: center; font-size: 9px; color: #666; border-top: 1px solid #ccc; padding-top: 8px; margin-top: 20px; }
        .counted-row { }
        .not-counted { color: #999; }
        .print-hide { display: none; }
    .no-print { text-align:right;margin-bottom:12px; }
    .print-btn { padding:8px 16px;font-size:13px;font-weight:700;cursor:pointer; }
    .th-width { width:30px; }
    .td-bold { font-weight:700; }
    .empty-print { text-align:center;padding:20px; }
        @media print {
            body { padding: 0.5in; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button id="printBtn" class="print-btn">Print / Save PDF</button>
        <button id="closePrintBtn" class="print-btn">Close</button>
    </div>

    <div class="header">
        <h1>Physical Inventory Count Report</h1>
        <p>Session #{{ $session->id }}</p>
    </div>

    <div class="section">
        <div class="section-title">Session Information</div>
        <div class="info-grid">
            <div><span class="label">Region:</span> {{ $session->scope_region }}</div>
            <div><span class="label">Branch:</span> {{ $session->scope_branch ?? 'All' }}</div>
            <div><span class="label">Started By:</span> {{ $session->startedBy->full_name ?? $session->startedBy->name ?? 'Unknown' }}</div>
            <div><span class="label">Date Started:</span> {{ $session->started_at->format('F j, Y g:i A') }}</div>
            <div><span class="label">Status:</span> {{ $session->status }}</div>
            @if($session->completed_at)
            <div><span class="label">Date Completed:</span> {{ $session->completed_at->format('F j, Y g:i A') }}</div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-title">Summary</div>
        <div class="summary-grid">
            <div class="summary-box"><div class="num">{{ $summary['total'] }}</div><div class="lbl">Total Assets</div></div>
            <div class="summary-box"><div class="num">{{ $summary['counted'] }}</div><div class="lbl">Counted</div></div>
            <div class="summary-box"><div class="num">{{ $summary['present'] }}</div><div class="lbl">Present</div></div>
            <div class="summary-box"><div class="num">{{ $summary['missing'] }}</div><div class="lbl">Missing</div></div>
            <div class="summary-box"><div class="num">{{ $summary['damaged'] }}</div><div class="lbl">Damaged</div></div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Asset Details</div>
        <table>
            <thead>
                <tr>
                    <th class="th-width">#</th>
                    <th>Item Name</th>
                    <th>Serial No</th>
                    <th>PAR No</th>
                    <th>Property No</th>
                    <th>Status</th>
                    <th>Counted By</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse($allAssets as $idx => $asset)
                    @php
                        $count = $session->counts->firstWhere('asset_id', $asset->asset_id);
                    @endphp
                    <tr>
                        <td class="centered">{{ $idx + 1 }}</td>
                        <td class="td-bold">{{ $asset->item_name }}</td>
                        <td>{{ $asset->serial_number ?? '—' }}</td>
                        <td>{{ $asset->par_number ?? '—' }}</td>
                        <td>{{ $asset->property_number ?? '—' }}</td>
                        <td class="centered">
                            @if($count)
                                <span class="pill pill-{{ $count->status }}">{{ $count->status }}</span>
                            @else
                                <span class="not-counted">Not Counted</span>
                            @endif
                        </td>
                        <td>{{ $count ? ($count->countedBy->full_name ?? $count->countedBy->name ?? '—') : '—' }}</td>
                        <td>{{ $count->remarks ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-print">No assets found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">
        Generated on {{ now()->format('F j, Y g:i A') }} &middot; NCMB ICT System &middot; Physical Count Report
    </div>
<script nonce="{{ $cspNonce }}">
document.getElementById('printBtn').addEventListener('click', function() { window.print(); });
document.getElementById('closePrintBtn').addEventListener('click', function() { window.close(); });
</script>
</body>
</html>