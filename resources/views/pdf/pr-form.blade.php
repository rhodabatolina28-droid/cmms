<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Purchase Request {{ $pr->pr_number }}</title>
    <style>
        /* ── Mirrors the EXISTING .prd-sheet PRINT design (show.blade.php @media print) ── */
        @page { size: A4 portrait; margin: 12mm 10mm 14mm 10mm; }

        /* Print version: no outer card border/radius/padding — plain A4 sheet */
        .prd-sheet { background:#fff; border:none; border-radius:0; padding:0; max-width:none; margin:0 auto; font-family:Arial, Helvetica, sans-serif; color:#111827; }
        .prd-title { text-align:center; font-size:14px; font-weight:700; letter-spacing:2px; margin:2px 0 10px; }

        /* ── Header field grid ── */
        .a60-hdr { width:100%; border-collapse:collapse; margin-bottom:12px; font-size:12px; }
        .a60-hdr td { padding:0; vertical-align:top; }
        .a60-field { border:1px solid #374151; padding:4px 8px 3px; }
        .a60-field-lbl { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#6b7280; margin-bottom:2px; }
        .a60-field-val { font-weight:700; color:#111827; font-size:12.5px; }
        .a60-field-val.accent { color:#0038A8; }
        .a60-field-val.muted { color:#9ca3af; font-weight:400; font-style:italic; }
        .hdr-br-none { border-right-style:none; }
        .hdr-bl { border-left:1px solid #374151; }
        .hdr-bt-none { border-top-style:none; }

        /* ── Items table ── */
        .prd-table { width:100%; border-collapse:collapse; font-size:11px; margin-bottom:10px; table-layout:fixed; }
        .prd-table th, .prd-table td { border:1px solid #374151; padding:4px 6px; text-align:left; vertical-align:top; word-break:break-word; }
        .prd-table th { background:#f3f4f6; font-family:Arial, sans-serif; font-size:9.5px; text-transform:uppercase; letter-spacing:.4px; text-align:center; }
        .prd-table .num { text-align:center; }
        .prd-table .right { text-align:right; white-space:nowrap; }
        .prd-total-row td { font-weight:800; background:#f9fafb; text-align:right; }

        .prd-purpose { font-size:11.5px; margin-bottom:14px; padding:7px 10px; border:1px solid #374151; min-height:38px; }
        .prd-purpose .k { font-weight:700; margin-right:6px; }

        .prd-req-ref { font-size:11px; color:#4b5563; margin-bottom:20px; }

        /* ── Signatures ── */
        table.prd-signs { width:100%; border-collapse:collapse; margin-top:64px; font-size:11.5px; page-break-inside:avoid; }
        .prd-signs td { padding:13px 8px; vertical-align:bottom; }
        .prd-signs td.lbl { white-space:nowrap; width:16%; color:#374151; padding-left:0; }
        .prd-signs td.val { border-bottom:1px solid #111827; text-align:center; font-weight:700; height:24px; width:26%; }
        .prd-signs td.sig-space { height:54px; }
        .prd-signs td.who { font-weight:800; padding-bottom:16px; padding-left:0; font-size:12px; }
    </style>
</head>
<body>

    <div class="prd-sheet">
        <div class="prd-title">PURCHASE REQUEST</div>

        <table class="a60-hdr">
            <colgroup>
                <col style="width:50%;">
                <col style="width:25%;">
                <col style="width:25%;">
            </colgroup>
            {{-- Row 1: Entity Name | Fund Cluster --}}
            <tr>
                <td class="a60-field hdr-br-none">
                    <div class="a60-field-lbl">Entity Name</div>
                    <div class="a60-field-val">National Conciliation and Mediation Board</div>
                </td>
                <td class="a60-field hdr-bl" colspan="2">
                    <div class="a60-field-lbl">Fund Cluster</div>
                    <div class="a60-field-val {{ $pr->fund_cluster ? '' : 'muted' }}">
                        {{ $pr->fund_cluster ?: '— not specified —' }}
                    </div>
                </td>
            </tr>
            {{-- Row 2: Office/Unit | PR No. | Date --}}
            <tr>
                <td class="a60-field hdr-bt-none hdr-br-none">
                    <div class="a60-field-lbl">Office / Unit</div>
                    <div class="a60-field-val {{ $pr->office_unit ? '' : 'muted' }}">
                        {{ $pr->office_unit ?: '— not specified —' }}
                    </div>
                </td>
                <td class="a60-field hdr-bt-none hdr-bl hdr-br-none">
                    <div class="a60-field-lbl">PR No.</div>
                    <div class="a60-field-val accent">{{ $pr->pr_number }}</div>
                </td>
                <td class="a60-field hdr-bt-none hdr-bl">
                    <div class="a60-field-lbl">Date</div>
                    <div class="a60-field-val">{{ optional($pr->created_at)->format('F d, Y') }}</div>
                </td>
            </tr>
            {{-- Row 3: Responsibility Center Code (full width) --}}
            <tr>
                <td class="a60-field hdr-bt-none" colspan="3">
                    <div class="a60-field-lbl">Responsibility Center Code</div>
                    <div class="a60-field-val {{ $pr->responsibility_center ? '' : 'muted' }}">
                        {{ $pr->responsibility_center ?: '— not specified —' }}
                    </div>
                </td>
            </tr>
        </table>

        <table class="prd-table">
            <thead>
                <tr>
                    <th style="width:14%;">Stock/Property No.</th>
                    <th style="width:9%;">Unit</th>
                    <th>Description / specification</th>
                    <th style="width:8%;">Qty</th>
                    <th style="width:15%;">Unit Cost</th>
                    <th style="width:16%;">Total Cost</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pr->items ?? [] as $line)
                    @php
                        $qty = (int) ($line['quantity'] ?? 0);
                        $cost = isset($line['unit_cost']) && $line['unit_cost'] !== null ? (float) $line['unit_cost'] : null;
                        $amount = $cost !== null ? $qty * $cost : null;
                    @endphp
                    <tr>
                        <td class="num">&nbsp;</td>
                        <td class="num">@if(!empty($line['unit'])){{ $line['unit'] }}@else&nbsp;@endif</td>
                        <td>@if(!empty($line['description'])){{ $line['description'] }}@else&nbsp;@endif</td>
                        <td class="num">@if($qty > 0){{ $qty }}@else&nbsp;@endif</td>
                        <td class="right">@if($cost !== null){{ number_format($cost, 2) }}@else&nbsp;@endif</td>
                        <td class="right">@if($amount !== null){{ number_format($amount, 2) }}@else&nbsp;@endif</td>
                    </tr>
                @endforeach
                {{-- Pad with blank rows so the printed sheet shows a full official grid --}}
                @php $blankRows = max(0, 8 - count($pr->items ?? [])); @endphp
                @for($b = 0; $b < $blankRows; $b++)
                    <tr>
                        <td class="num">&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td class="num">&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                @endfor
                <tr class="prd-total-row">
                    <td colspan="5" style="text-align:right;">TOTAL</td>
                    <td class="right">@if($pr->total_amount !== null)&#8369; {{ number_format((float) $pr->total_amount, 2) }}@else&nbsp;@endif</td>
                </tr>
            </tbody>
        </table>

        <div class="prd-purpose">
            <span class="k">Purpose / justification:</span>
            {{ trim((string) ($pr->purpose ?: $pr->remarks)) ?: '' }}
        </div>

        @if($pr->requisition)
            <div class="prd-req-ref">
                Requisition reference: {{ $pr->requisition->display_number }}
                @if($pr->finalized_at)
                    &middot; Finalized {{ $pr->finalized_at->format('F d, Y') }}
                    @if($pr->finalizer) by {{ $pr->finalizer->full_name }}@endif
                @endif
            </div>
        @endif

        <table class="prd-signs">
            <tr>
                <td class="who" colspan="2">Requested by:</td>
                <td class="who" colspan="2">Approved by:</td>
            </tr>
            <tr>
                <td class="lbl">Signature&nbsp;:</td>
                <td class="val sig-space">&nbsp;</td>
                <td class="lbl">&nbsp;</td>
                <td class="val sig-space">&nbsp;</td>
            </tr>
            <tr>
                <td class="lbl">Printed Name&nbsp;:</td>
                <td class="val">@if(!empty($pr->requester?->full_name)){{ $pr->requester->full_name }}@else&nbsp;@endif</td>
                <td class="lbl">&nbsp;</td>
                <td class="val">@if(!empty($pr->finalizer?->full_name)){{ $pr->finalizer->full_name }}@else&nbsp;@endif</td>
            </tr>
            <tr>
                <td class="lbl">Designation&nbsp;:</td>
                <td class="val">@if(!empty($pr->requester?->position)){{ $pr->requester->position }}@else&nbsp;@endif</td>
                <td class="lbl">&nbsp;</td>
                <td class="val">@if(!empty($pr->finalizer?->position)){{ $pr->finalizer->position }}@else&nbsp;@endif</td>
            </tr>
        </table>
    </div>

</body>
</html>