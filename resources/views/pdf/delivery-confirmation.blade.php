<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delivery Confirmation &middot; {{ $pr->pr_number }}</title>
    <style nonce="{{ $cspNonce }}">
        @page { size: A4 portrait; margin: 10mm 12mm 10mm 12mm; }
        body {
            font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #111827;
            margin: 0;
            padding: 0;
            line-height: 1.26;
        }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 0; }

        /* Title — same as the official PR document sheet */
        .doc-title {
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 2px;
            margin: 0 0 5px 0;
        }

        /* Bordered header field grid (mirrors the PR form) */
        .a60-hdr { margin-bottom: 6px; font-size: 11px; }
        .a60-hdr td { padding: 0; vertical-align: top; }
        .a60-field { border: 1px solid #374151; padding: 1.5px 7px; }
        .a60-field-lbl { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; margin-bottom: 1px; }
        .a60-field-val { font-weight: 700; color: #111827; font-size: 10.5px; }
        .a60-field-val.accent { color: #0038A8; }
        .a60-field-val.muted { color: #9ca3af; font-weight: 400; font-style: italic; }

        /* Bordered items table (mirrors the PR form) */
        .prd-table { margin-bottom: 6px; table-layout: fixed; }
        .prd-table th, .prd-table td { border: 1px solid #374151; padding: 1.5px 6px; text-align: left; vertical-align: top; word-break: break-word; }
        .prd-table th { background: #f3f4f6; font-size: 8.5px; text-transform: uppercase; letter-spacing: .4px; text-align: center; }
        .prd-table .num { text-align: center; }
        .prd-table .right { text-align: right; white-space: nowrap; }
        .prd-total-row td { font-weight: 800; background: #f9fafb; text-align: right; }
        .tr { page-break-inside: avoid; }
        .muted { color: #9ca3af; font-style: italic; }

        /* Section labels — plain bold small caps like the PR form */
        .sec-label { font-size: 10px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; margin: 6px 0 2px 0; }

        /* Purpose box */
        .prd-purpose { font-size: 10.5px; margin: 1px 0 6px 0; padding: 3px 10px; border: 1px solid #374151; min-height: 16px; }
        .prd-purpose .k { font-weight: 700; margin-right: 6px; }

        /* Certification + signatures (same grid as PR document) */
        .cert { font-size: 8.5px; color: #374151; text-align: justify; margin: 2px 0 0 0; }
        table.prd-signs { margin-top: 4px; font-size: 10.5px; page-break-inside: avoid; }
        .prd-signs td { padding: 3px 8px; vertical-align: bottom; }
        .prd-signs td.lbl { white-space: nowrap; width: 16%; color: #374151; padding-left: 0; }
        .prd-signs td.val { border-bottom: 1px solid #111827; text-align: center; font-weight: 700; height: 14px; width: 26%; }
        .prd-signs td.sig-space { height: 14px; }
        .prd-signs td.who { font-weight: 800; padding-bottom: 5px; padding-left: 0; font-size: 11px; }

        .footer { text-align: center; font-size: 8px; color: #9ca3af; margin-top: 2px; border-top: 1px solid #d1d5db; padding-top: 2px; }
    </style>
</head>
<body>

    <div class="doc-title">DELIVERY CONFIRMATION</div>

    <table class="a60-hdr">
        <colgroup>
            <col style="width:50%;">
            <col style="width:25%;">
            <col style="width:25%;">
        </colgroup>
        <tr>
            <td class="a60-field" style="border-right:none;">
                <div class="a60-field-lbl">Entity Name</div>
                <div class="a60-field-val">National Conciliation and Mediation Board</div>
            </td>
            <td class="a60-field" colspan="2" style="border-left:1px solid #374151;">
                <div class="a60-field-lbl">Fund Cluster</div>
                <div class="a60-field-val {{ $pr->fund_cluster ? '' : 'muted' }}">{{ $pr->fund_cluster ?: '— not specified —' }}</div>
            </td>
        </tr>
        <tr>
            <td class="a60-field" style="border-top:none;border-right:none;">
                <div class="a60-field-lbl">Office / Unit</div>
                <div class="a60-field-val {{ $pr->office_unit ? '' : 'muted' }}">{{ $pr->office_unit ?: '— not specified —' }}</div>
            </td>
            <td class="a60-field" style="border-top:none;border-left:1px solid #374151;border-right:none;">
                <div class="a60-field-lbl">PR No.</div>
                <div class="a60-field-val accent">{{ $pr->pr_number }}</div>
            </td>
            <td class="a60-field" style="border-top:none;border-left:1px solid #374151;">
                <div class="a60-field-lbl">PR Date</div>
                <div class="a60-field-val">{{ $pr->created_at?->format('F d, Y') }}</div>
            </td>
        </tr>
        <tr>
            <td class="a60-field" colspan="3" style="border-top:none;">
                <div class="a60-field-lbl">Responsibility Center Code</div>
                <div class="a60-field-val {{ $pr->responsibility_center ? '' : 'muted' }}">{{ $pr->responsibility_center ?: '— not specified —' }}</div>
            </td>
        </tr>
        <tr>
            <td class="a60-field" style="border-top:none;">
                <div class="a60-field-lbl">Received By</div>
                <div class="a60-field-val">{{ $pr->deliverer?->full_name ?? '—' }}</div>
            </td>
            <td class="a60-field" colspan="2" style="border-top:none;border-left:1px solid #374151;">
                <div class="a60-field-lbl">Date &amp; Time Received</div>
                <div class="a60-field-val">{{ optional($pr->delivered_at)->format('F d, Y h:i A') }}</div>
            </td>
        </tr>
    </table>

    {{-- ── Items — official bordered grid, delivery content (not a copy of
         the PR form; serial/property details live in the register below) ── --}}
    <table class="prd-table">
        <thead>
            <tr>
                <th style="width:10%;">Unit</th>
                <th>Description / specification</th>
                <th style="width:9%;">Qty</th>
                <th style="width:17%;">Unit Cost</th>
                <th style="width:18%;">Total Cost</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $line)
                <tr class="tr">
                    <td class="num">{{ $line['unit'] }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td class="num">{{ $line['qty'] }}</td>
                    <td class="right">{{ $line['unit_cost'] !== null ? number_format((float) $line['unit_cost'], 2) : '' }}</td>
                    <td class="right">{{ $line['total'] !== null ? number_format((float) $line['total'], 2) : '' }}</td>
                </tr>
            @endforeach
            <tr class="prd-total-row">
                <td colspan="4" style="text-align:right;">TOTAL</td>
                <td>@if($pr->total_amount !== null)PHP {{ number_format((float) $pr->total_amount, 2) }}@else&nbsp;@endif</td>
            </tr>
        </tbody>
    </table>

    <div class="prd-purpose">
        <span class="k">Purpose / justification:</span>{!! $pr->purpose ?: '&nbsp;' !!}
    </div>

    {{-- ── Serial / property register ── --}}
    <div class="sec-label">Serial / Property Register</div>
    @php
        $allUnits = collect($lines)
            ->flatMap(fn ($l) => collect($l['units'])->map(fn ($u) => $u + ['item' => $l['description'], 'destination' => $l['destination']]));
    @endphp
    <table class="prd-table">
        <thead>
            <tr>
                <th style="width:6%;">No.</th>
                <th style="width:26%;">Item</th>
                <th style="width:20%;">Serial No.</th>
                <th style="width:20%;">Property No.</th>
                <th style="width:28%;">Destination</th>
            </tr>
        </thead>
        <tbody>
            @forelse($allUnits as $i => $u)
                <tr class="tr">
                    <td class="num">{{ $i + 1 }}</td>
                    <td>{{ $u['item'] }}</td>
                    <td>{{ $u['serial'] !== '' ? $u['serial'] : '—' }}</td>
                    <td>{{ $u['property'] !== '' ? $u['property'] : '—' }}</td>
                    <td>{{ $u['destination'] ?: 'Add to inventory (stock)' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No individual units were recorded for this purchase request (consumable or received before unit tracking).</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Proof of purchase ── --}}
    <div class="sec-label">Proof of Purchase</div>
    <table class="prd-table">
        <thead>
            <tr>
                <th style="width:6%;">No.</th>
                <th style="width:22%;">Label</th>
                <th style="width:32%;">File Name</th>
                <th style="width:22%;">Uploaded By</th>
                <th style="width:18%;">Date Uploaded</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pr->attachments as $i => $att)
                <tr class="tr">
                    <td class="num">{{ $i + 1 }}</td>
                    <td>{{ $att->label ?: 'Receipt' }}</td>
                    <td>{{ $att->filename }}</td>
                    <td>{{ $att->uploader?->full_name ?? '—' }}</td>
                    <td>{{ optional($att->created_at)->format('F d, Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No proof of purchase on record.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Certification + signatures (same grid as the PR document) ── --}}
    <p class="cert">
        I hereby certify that the items listed above were received in good condition and recorded in the
        CMMS, with the proof(s) of purchase enumerated above supporting this purchase request
        ({{ $pr->pr_number }}).
    </p>
    <table class="prd-signs">
        <tr>
            <td class="who" colspan="2">Prepared by:</td>
            <td class="who" colspan="2">Received by:</td>
        </tr>
        <tr>
            <td class="lbl">&nbsp;</td>
            <td class="val sig-space">&nbsp;</td>
            <td class="lbl">&nbsp;</td>
            <td class="val sig-space">&nbsp;</td>
        </tr>
        <tr>
            <td class="lbl">Printed Name&nbsp;:</td>
            <td class="val">{{ $pr->requester?->full_name ?? '&nbsp;' }}</td>
            <td class="lbl">&nbsp;</td>
            <td class="val">{{ $pr->deliverer?->full_name ?? '&nbsp;' }}</td>
        </tr>
        <tr>
            <td class="lbl">Date&nbsp;:</td>
            <td class="val">{{ $pr->created_at?->format('F d, Y') }}</td>
            <td class="lbl">&nbsp;</td>
            <td class="val">{{ $pr->delivered_at?->format('F d, Y') }}</td>
        </tr>
    </table>

    <div class="footer">
        {{ $pr->pr_number }} &middot; Delivery Confirmation &middot; generated {{ now()->format('M d, Y h:i A') }} &middot; CMMS
    </div>

</body>
</html>