<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Physical Count Report #{{ $session->id }}</title>
    <style>
        @page { size: A4 landscape; margin: 0.4in 0.4in 0.5in 0.4in; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #000; }

        .hdr { text-align: center; border-bottom: 2px solid #0038A8; padding-bottom: 6px; margin-bottom: 10px; }
        .hdr h1 { font-size: 15pt; letter-spacing: 1px; }
        .hdr .sub { font-size: 9pt; color: #444; margin-top: 2px; }

        .meta { width: 100%; margin-bottom: 8px; font-size: 9pt; }
        .meta td { padding: 1.5px 4px; vertical-align: top; }
        .meta .lbl { color: #555; width: 130px; }

        .sum { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .sum th, .sum td { border: 1px solid #999; padding: 3px 6px; text-align: center; font-size: 9pt; }
        .sum th { background: #eef3fb; }

        .grp-head { background: #0038A8; color: #fff; font-size: 10pt; font-weight: bold;
                    padding: 4px 8px; margin-top: 10px; }
        .grp-sub { font-size: 8.5pt; color: #333; padding: 2px 8px 6px; }

        table.assets { width: 100%; border-collapse: collapse; }
        table.assets th { background: #eef3fb; border: 1px solid #999; padding: 3px 5px; font-size: 8.5pt; }
        table.assets td { border: 1px solid #bbb; padding: 2.5px 5px; font-size: 8.5pt; }

        .present { color: #0a7a2f; font-weight: bold; }
        .missing { color: #c1121f; font-weight: bold; }
        .damaged { color: #b26a00; font-weight: bold; }
        .notcounted { color: #777; font-style: italic; }

        .foot { margin-top: 14px; font-size: 8pt; color: #666; border-top: 1px solid #ccc; padding-top: 4px; }
    </style>
</head>
<body>

    <div class="hdr">
        <h1>PHYSICAL COUNT REPORT</h1>
        <div class="sub">Annual Physical Inventory of Property, Plant and Equipment — Session #{{ $session->id }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="lbl">Started:</td>
            <td>{{ optional($session->started_at)->format('M j, Y g:i A') }}</td>
            <td class="lbl">Started by:</td>
            <td>{{ $session->startedBy?->full_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Completed:</td>
            <td>{{ optional($session->completed_at)->format('M j, Y g:i A') ?? '—' }}</td>
            <td class="lbl">Scope:</td>
            <td>{{ $session->scope_region ?? 'All regions' }}{{ $session->scope_branch ? ' — ' . $session->scope_branch : '' }}</td>
        </tr>
    </table>

    <table class="sum">
        <tr>
            <th>Total Assets</th>
            <th>Counted</th>
            <th>Present</th>
            <th>Missing</th>
            <th>Damaged</th>
        </tr>
        <tr>
            <td>{{ $summary['total'] }}</td>
            <td>{{ $summary['counted'] }}</td>
            <td>{{ $summary['present'] }}</td>
            <td>{{ $summary['missing'] }}</td>
            <td>{{ $summary['damaged'] }}</td>
        </tr>
    </table>

    @foreach ($custodianGroups as $group)
        <div class="grp-head">
            {{ $group['name'] }}
        </div>
        <div class="grp-sub">
            {{ $group['par'] ? 'PAR: ' . $group['par'] . ' — ' : '' }}{{ $group['counted'] }} of {{ $group['total'] }} counted
        </div>
        <table class="assets">
            <tr>
                <th style="width:14%">Category</th>
                <th style="width:22%">Item</th>
                <th style="width:16%">Serial No.</th>
                <th style="width:16%">Property No.</th>
                <th style="width:10%">Status</th>
                <th style="width:12%">Actual Location</th>
                <th style="width:10%">Remarks</th>
            </tr>
            @foreach ($group['assets'] as $asset)
                @php
                    $count = $session->counts->firstWhere('asset_id', $asset->asset_id);
                    $status = $count->status ?? null;
                @endphp
                <tr>
                    <td>{{ $asset->category }}</td>
                    <td>{{ $asset->item_name }}</td>
                    <td>{{ $asset->serial_number }}</td>
                    <td>{{ $asset->property_number }}</td>
                    <td>
                        @if ($status === 'Present') <span class="present">Present</span>
                        @elseif ($status === 'Missing') <span class="missing">Missing</span>
                        @elseif ($status === 'Damaged') <span class="damaged">Damaged</span>
                        @else <span class="notcounted">Not counted</span>
                        @endif
                    </td>
                    <td>{{ $count->actual_location ?? '—' }}</td>
                    <td>{{ $count->remarks ?? '' }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach

    <div class="foot">
        Official archival copy — generated by CMMS upon session completion. Session #{{ $session->id }} ·
        {{ optional($session->completed_at)->format('M j, Y g:i A') }}
    </div>

</body>
</html>
