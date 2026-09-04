<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Scanned | CMMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style nonce="{{ $cspNonce }}">
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f1f5f9; padding: 16px; min-height: 100vh; display: flex; flex-direction: column; align-items: center; }
        .card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 480px; width: 100%; overflow: hidden; }
        .header { background: linear-gradient(135deg, #0038A8, #1e40af); color: white; padding: 20px 24px; }
        .header h1 { font-size: 19px; margin-bottom: 2px; }
        .header .sub { font-size: 13px; opacity: 0.8; }
        .body { padding: 20px 24px; }
        .asset-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 10px; text-decoration: none; background: #fff; transition: border-color 0.15s, background 0.15s; }
        .asset-row:hover, .asset-row:active { border-color: #0038A8; background: #f8faff; }
        .asset-row.scanned { border-color: #0038A8; background: #f8faff; }
        .asset-row .info { flex: 1; min-width: 0; }
        .asset-row .name { font-size: 14px; font-weight: 700; color: #1e293b; }
        .asset-row .meta { font-size: 12px; color: #64748b; margin-top: 3px; }
        .asset-row .meta .id { font-family: monospace; font-weight: 700; color: #0038A8; }
        .asset-row .arrow { color: #94a3b8; flex-shrink: 0; margin-left: 10px; }
        .asset-row.scanned .arrow { color: #0038A8; }
        .cta { display: flex; align-items: center; justify-content: center; gap: 8px; background: #dc2626; color: white; text-decoration: none; padding: 14px 18px; border-radius: 10px; font-size: 15px; font-weight: 800; min-height: 48px; margin-bottom: 22px; transition: background 0.2s; }
        .cta:hover { background: #b91c1c; }
        .section-title { font-size: 13px; font-weight: 700; color: #0038A8; text-transform: uppercase; letter-spacing: 0.03em; margin: 22px 0 10px; padding-top: 16px; border-top: 1px solid #e2e8f0; }
        .status-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .status-Serviceable { background: #d1fae5; color: #047857; }
        .status-Unserviceable { background: #fef3c7; color: #b45309; }
        .status-Disposal, .status-Scrapped { background: #fee2e2; color: #b91c1c; }
        .status-Repair { background: #dbeafe; color: #1d4ed8; }
        .empty-note { font-size: 13px; color: #94a3b8; padding: 8px 0; }
        .footer-note { font-size: 11px; color: #94a3b8; text-align: center; margin-top: 18px; }    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1><i class="fa-solid fa-qrcode"></i> Asset Scanned</h1>
            <div class="sub">Batch QR Sticker Scan &middot; CMMS</div>
        </div>
        <div class="body">
            @php
                $statusMap = ['Serviceable'=>'Serviceable','Unserviceable'=>'Unserviceable','For Disposal'=>'Disposal','Scrapped'=>'Scrapped','For Repair'=>'Repair'];
            @endphp
            <div class="asset-row scanned">
                <div class="info">
                    <div class="name">{{ $asset->item_name }}</div>
                    <div class="meta">
                        <span class="id">#{{ $asset->asset_id }}</span>
                        <span>&middot; SN: {{ $asset->serial_number }}</span>
                        <span class="status-badge status-{{ $statusMap[$asset->status] ?? 'Serviceable' }}">{{ $asset->status }}</span>
                    </div>
                </div>
                <i class="fa-solid fa-check-double arrow"></i>
            </div>

            <a href="{{ route('ict.create', ['asset_id' => $asset->asset_id]) }}" class="cta">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Report Repair &mdash; this asset
            </a>

            <div class="section-title">Your Other Assets ({{ $otherAssets ? $otherAssets->count() : 0 }})</div>

            @if($otherAssets && $otherAssets->count() > 0)
                @foreach($otherAssets as $oa)
                    <a href="{{ route('ict.create', ['asset_id' => $oa->asset_id]) }}" class="asset-row">
                        <div class="info">
                            <div class="name">{{ $oa->item_name }}</div>
                            <div class="meta">
                                <span class="id">#{{ $oa->asset_id }}</span>
                                <span>&middot; SN: {{ $oa->serial_number }}</span>
                                <span class="status-badge status-{{ $statusMap[$oa->status] ?? 'Serviceable' }}">{{ $oa->status }}</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right arrow"></i>
                    </a>
                @endforeach
                <div class="footer-note">Tap an asset to report a repair for it.</div>
            @else
                <div class="empty-note">You have no other assigned assets. Use the button above to report a repair for the scanned asset.</div>
            @endif

            <div class="footer-note">CMMS &middot; National Conciliation and Mediation Board</div>
        </div>
    </div>
</body>
</html>