<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>QR Sticker — {{ $asset->item_name }}</title>
    <style nonce="{{ $cspNonce }}">
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: Arial, sans-serif; padding: 10px; background: #f8fafc; }
        /* ===== 1" x 1" (25.4mm) sticker — QR Print + Scan Hub Plan §9.5.
           Markup = inventory/_sticker.blade.php (shared with the batch grid);
           keep this CSS in sync with qr-batch.blade.php (same class names). ===== */
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

        @media print {
            body { min-height: auto; padding: 0; background: white; display: block; }
            @page { size: auto; margin: 6mm; }
            .sticker { margin: 0 auto; }
        }
        @media screen and (max-width: 480px) {
            body { padding: 6px; }
        }
    </style>
</head>
<body>
    @include('inventory._sticker')
    <script nonce="{{ $cspNonce }}">
        window.onload = function() { window.print(); }
    </script>
</body>
</html>
