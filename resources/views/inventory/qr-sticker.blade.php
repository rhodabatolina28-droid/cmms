<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>QR Sticker — {{ $asset->item_name }}</title>
    <style nonce="{{ $cspNonce }}">
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: Arial, sans-serif; padding: 10px; background: #f8fafc; }
        .sticker { text-align: center; padding: 20px 15px; border: 2px dashed #94a3b8; border-radius: 12px; max-width: min(280px, 85vw); width: 100%; background: white; }
        .sticker svg { width: 100% !important; max-width: 180px !important; height: auto !important; display: block; margin: 0 auto; }
        .sticker svg rect { width: 100% !important; }
        .label { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin: 8px 0 4px; }
        .value { font-size: 14px; font-weight: 700; color: #1e293b; word-break: break-word; }
        .sn { font-family: monospace; font-size: 12px; color: #475569; margin-top: 4px; word-break: break-all; }
        @media print {
            body { min-height: auto; padding: 0; background: white; }
            .sticker { border: none; padding: 10px; max-width: 200px; }
            .sticker svg { max-width: 140px !important; }
        }
        .label-top { margin-top: 10px; }
        .sn-small { margin-top: 6px; font-size: 10px; }
        @media (max-width: 480px) {
            .sticker { padding: 12px 10px; max-width: 90vw; }
            .sticker svg { max-width: 140px !important; }
            .value { font-size: 12px; }
            .label { font-size: 10px; }
        }
    </style>
</head>
<body>
    <div class="sticker">
        {!! $asset->qr_code !!}
        <div class="label label-top">ID:{{ $asset->asset_id }}</div>
        <div class="value">{{ $asset->item_name }}</div>
        <div class="sn">{{ $asset->serial_number ?? '' }}</div>
        <div class="sn sn-small">{{ $asset->par_number ?? '' }}</div>
    </div>
    <script nonce="{{ $cspNonce }}">
        window.onload = function() { window.print(); }
    </script>
</body>
</html>
