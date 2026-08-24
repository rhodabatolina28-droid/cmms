@php
    $reqNo = 'REQ-' . str_pad($req->id, 5, '0', STR_PAD_LEFT);
    $statusKey = strtolower($req->status);
    $items = $req->items ?? [];
    $quickActions = $quickActions ?? false;
    $isPending = $statusKey === 'pending';
    $isApproved = $statusKey === 'approved';
    $canQuick = $quickActions && in_array($statusKey, ['pending', 'approved'], true);
    $rowAccent = '';
    if ($quickActions && $isPending) { $rowAccent = 'cmms-req-row--needs-review'; }
    elseif ($quickActions && $isApproved) { $rowAccent = 'cmms-req-row--awaiting-issue'; }
    $issueDestination = $req->ticket?->linkedAsset?->assignedUser
        ? $req->ticket->linkedAsset->assignedUser->full_name . ' for ' . $req->ticket->linkedAsset->item_name
        : null;
@endphp
<tr class="cmms-req-row {{ $rowAccent }}" data-status="{{ $statusKey }}">
    <td class="td-nowrap">
        <a href="{{ route('requisitions.show', $req->id) }}" class="cmms-req-id" style="text-decoration:none;" title="Open full record">
            <i class="fa-solid fa-file-invoice" style="opacity:.6;margin-right:4px;"></i>{{ $reqNo }}
        </a>
        @if($quickActions && $isPending)
            <div><span class="cmms-req-age"><i class="fa-regular fa-clock" style="margin-right:3px;"></i>{{ (int) $req->created_at->diffInDays(now()) }}d old</span></div>
        @endif
    </td>
    @if(!empty($showRequester))
    <td class="cell-trim" data-label="Requester" title="{{ $req->requester?->full_name ?? '' }}">{{ $req->requester?->full_name ?? '&mdash;' }}</td>
    @endif
    <td class="cell-trim" data-label="Job order">
        @if($req->ticket)
            <a href="{{ route('requisitions.show', $req->id) }}" class="cmms-req-jo"><i class="fa-solid fa-link" style="opacity:.7;margin-right:3px;"></i>JO {{ $req->ticket->display_number ?? $req->ticket->request_number }}</a>
        @else
            <span class="text-muted-none">&mdash;</span>
        @endif
    </td>
    <td class="cell-trim" data-label="Items" title="{{ count($items) }} item(s){{ isset($items[0]['description']) ? ': ' . $items[0]['description'] : '' }}">
        @if(count($items))
            {{ ($items[0]['quantity'] ?? 1) }}× {{ \Illuminate\Support\Str::limit($items[0]['description'] ?? '', 34) }}@if(count($items) > 1) <span style="font-weight:600;color:#0038A8;">+{{ count($items) - 1 }}</span>@endif
        @else
            <span class="text-muted-none">&mdash;</span>
        @endif
    </td>
    <td class="td-nowrap" data-label="Date">{{ $req->created_at->format('M d, Y | h:i A') }}</td>
    <td class="td-nowrap" data-label="Completed">
        @if($statusKey === 'issued' && $req->reviewed_at)
            {{ $req->reviewed_at->format('M d, Y | h:i A') }}
        @else
            <span class="text-muted-none">&mdash;</span>
        @endif
    </td>
    <td data-label="Status">
        <span class="cmms-status-badge cmms-status-{{ $statusKey }}">{{ $req->status }}</span>
        @if($quickActions && $isPending)
            <div style="margin-top:4px;"><span class="cmms-req-tag cmms-req-tag--review">Needs review</span></div>
        @elseif($quickActions && $isApproved)
            <div style="margin-top:4px;"><span class="cmms-req-tag cmms-req-tag--issue">Awaiting issue</span></div>
        @endif
    </td>
    <td data-label="Actions">
        <div class="row-actions">
            @if($canQuick && $isPending)
                <button type="button" class="act-btn in supply-quick-btn" data-action="approve" data-id="{{ $req->id }}" data-pr="{{ $reqNo }}" title="Approve requisition" aria-label="Approve requisition {{ $reqNo }}"><i class="fa-solid fa-check"></i></button>
                <button type="button" class="act-btn out supply-quick-btn" data-action="reject" data-id="{{ $req->id }}" data-pr="{{ $reqNo }}" title="Disapprove requisition" aria-label="Disapprove requisition {{ $reqNo }}"><i class="fa-solid fa-xmark"></i></button>
            @elseif($canQuick && $isApproved)
                <button type="button" class="act-btn in supply-quick-btn" data-action="issue" data-id="{{ $req->id }}" data-pr="{{ $reqNo }}" data-issue-destination="{{ $issueDestination }}" title="Issue parts to asset custodian" aria-label="Issue requisition {{ $reqNo }}"><i class="fa-solid fa-box-open"></i></button>
                <button type="button" class="act-btn out supply-quick-btn" data-action="reject" data-id="{{ $req->id }}" data-pr="{{ $reqNo }}" title="Disapprove requisition" aria-label="Disapprove requisition {{ $reqNo }}"><i class="fa-solid fa-xmark"></i></button>
            @endif
            <button type="button" class="act-btn cmms-req-details-btn" data-rid="{{ $req->id }}" aria-expanded="false" title="Full details and items" aria-label="Toggle details for {{ $reqNo }}"><i class="fa-solid fa-angles-down cmms-req-details-chevron"></i></button>
        </div>
    </td>
</tr>
<tr class="cmms-req-details-row" id="req-details-{{ $req->id }}" hidden>
    <td colspan="{{ !empty($showRequester) ? 8 : 7 }}">
        <div class="cmms-req-details-grid">
            <div class="cmms-req-details-col">
                <div class="k"><i class="fa-solid fa-list-check" style="opacity:.6;margin-right:6px;"></i>Line items &middot; {{ count($items) }}</div>
                @if(count($items))
                <ul class="cmms-req-details-list">
                    @foreach($items as $line)
                        <li><span class="q">{{ $line['quantity'] ?? 1 }}&times;</span> {{ $line['description'] ?? '' }}</li>
                    @endforeach
                </ul>
                @else
                    <p class="muted">No line items.</p>
                @endif
            </div>
            <div class="cmms-req-details-col">
                <div class="k"><i class="fa-solid fa-circle-info" style="opacity:.6;margin-right:6px;"></i>Purpose / justification</div>
                <p>{{ $req->remarks ?: '—' }}</p>
            </div>
            <div class="cmms-req-details-col">
                <div class="k"><i class="fa-solid fa-laptop-medical" style="opacity:.6;margin-right:6px;"></i>Job order context</div>
                <p style="font-weight:700;color:#0038A8;margin-bottom:6px;">JO {{ $req->ticket?->display_number ?? $req->ticket?->request_number ?? '—' }}</p>
                @if($req->ticket?->linkedAsset)
                    <p><span class="cmms-req-details-label">Asset:</span> {{ $req->ticket->linkedAsset->item_name }} <span class="muted">({{ $req->ticket->linkedAsset->serial_number ?? 'N/A' }})</span></p>
                    <p><span class="cmms-req-details-label">Custodian:</span> {{ $req->ticket->linkedAsset->assignedUser?->full_name ?? 'Not assigned' }}</p>
                @endif
                @if($req->requester)
                    <p style="margin-top:6px;"><span class="cmms-req-details-label">Requester:</span> {{ $req->requester->full_name }}</p>
                @endif
                <a href="{{ route('requisitions.show', $req->id) }}" class="cmms-btn-secondary" style="margin-top:8px;display:inline-block;">Open full record</a>
            </div>
        </div>
        @if($req->reviewer && $req->reviewed_at)
            <div class="cmms-req-details-meta">Last action by {{ $req->reviewer->full_name }} &middot; {{ $req->reviewed_at->format('d M Y, h:i A') }}</div>
        @endif
    </td>
</tr>