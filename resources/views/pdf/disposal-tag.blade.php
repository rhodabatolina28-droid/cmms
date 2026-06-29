<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Disposal Tag - {{ $asset->property_number ?? $asset->serial_number }}</title>
    <style nonce="{{ $cspNonce }}">
        @page { size: A4 portrait; margin: 20mm; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        .tag-container {
            border: 4px solid #dc2626; /* Red for disposal */
            padding: 25px;
            width: 100%;
            max-width: 450px;
            margin: 20px auto;
            border-radius: 12px;
            position: relative;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #dc2626;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #dc2626;
            margin: 0;
            font-size: 32px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .header h2 {
            margin: 5px 0 0;
            font-size: 16px;
            color: #333;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 15px;
        }
        th {
            text-align: left;
            width: 40%;
            color: #4b5563;
            font-weight: bold;
        }
        td {
            font-weight: bold;
            color: #111827;
        }
        .reason-box {
            margin-top: 25px;
            border: 2px dashed #dc2626;
            background: #fff5f5;
            padding: 15px;
            border-radius: 8px;
        }
        .reason-title {
            font-weight: bold;
            color: #991b1b;
            margin-bottom: 8px;
            font-size: 13px;
            text-transform: uppercase;
        }
        .reason-text {
            font-size: 15px;
            color: #111827;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
        }
        .sig-line {
            border-bottom: 1px solid #000;
            width: 80%;
            margin: 0 auto 5px;
            height: 40px;
        }
        .sig-name {
            font-weight: bold;
            font-size: 16px;
            text-transform: uppercase;
        }
        .sig-title {
            font-size: 13px;
            color: #4b5563;
        }
        .disposal-footnote { margin-top: 20px; font-size: 12px; color: #6b7280; font-style: italic; border-top: 1px solid #e5e7eb; padding-top: 15px; }
    </style>
</head>
<body>
    <div class="tag-container">
        <div class="header">
            <h1>⚠️ FOR DISPOSAL</h1>
            <h2>NCMB CMMS Asset Tag</h2>
        </div>

        <table>
            <tr>
                <th>Item / Asset:</th>
                <td>{{ $asset->item_name }}</td>
            </tr>
            <tr>
                <th>Property No.:</th>
                <td>{{ $asset->property_number ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Serial No.:</th>
                <td>{{ $asset->serial_number ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Linked Ticket:</th>
                <td>{{ $request->request_number }}</td>
            </tr>
            <tr>
                <th>Recommended On:</th>
                <td>{{ \Carbon\Carbon::now()->format('M d, Y') }}</td>
            </tr>
        </table>

        <div class="reason-box">
            <div class="reason-title">IT Findings / Reason:</div>
            <div class="reason-text">
                {{ $repairRequest->findings_remarks ?? $repairRequest->initial_diagnosis ?? 'Beyond Economic Repair (BER)' }}
            </div>
        </div>

        <div class="footer">
            <div class="sig-line"></div>
            <div class="sig-name">{{ $itUser->full_name }}</div>
            <div class="sig-title">IT Personnel</div>
            <div class="disposal-footnote">
                Please print this tag and attach it to the physical device. Turn over the unit to the Administrative / Supply Office for processing and disposal.
            </div>
        </div>
    </div>
</body>
</html>
