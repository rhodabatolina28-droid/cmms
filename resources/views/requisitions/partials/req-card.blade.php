@php
    $reqNo = 'REQ-' . str_pad($req->id, 5, '0', STR_PAD_LEFT);
    $statusKey = strtolower($req->status);
    $lines = [];
    foreach (array_slice($req->items ?? [], 0, 3) as $line) {
        $lines[] = ($line['quantity'] ?? 1) . ' × ' . \Illuminate\Support\Str::limit($line['description'] ?? '', 48);
    }
    $showTracker = $showTracker ?? false;
    $quickActions = $quickActions ?? false;
    $issueDestination = $req->ticket?->linkedAsset?->assignedUser
        ? $req->ticket->linkedAsset->assignedUser->full_name . ' for ' . $req->ticket->linkedAsset->item_name
        : null;
@endphp
<article class="cmms-req-card @if($quickActions && strtolower($req->status ?? '') === 'pending') cmms-req-card--needs-review @elseif($quickActions && strtolower($req->status ?? '') === 'approved') cmms-req-card--awaiting-issue @endif" data-status="{{ $statusKey }}">
    <div>
        <div class="cmms-req-card-top">
            <span class="cmms-req-id"><i class="fa-solid fa-file-invoice" style="opacity:0.6;margin-right:4px;"></i>{{ $reqNo }}</span>
            <span class="cmms-status-badge cmms-status-{{ $statusKey }}">{{ $req->status }}</span>
            @if($quickActions && strtolower($req->status ?? '') === 'pending')
                <span class="cmms-req-tag cmms-req-tag--review">Needs review</span>
                <span class="cmms-req-age">@php $reqAge = (int) $req->created_at->diffInDays(now()); @endphp <i class="fa-regular fa-clock" style="margin-right:3px;"></i>{{ $reqAge }}d ago</span>
            @elseif($quickActions && strtolower($req->status ?? '') === 'approved')
                <span class="cmms-req-tag cmms-req-tag--issue">Awaiting issue</span>
            @endif
            @if($req->ticket)
                <a href="{{ route('requisitions.show', $req->id) }}" class="cmms-req-jo" title="View linked Job Order"><i class="fa-solid fa-link" style="opacity:0.7;margin-right:3px;"></i>JO {{ $req->ticket->display_number ?? $req->ticket->request_number }}</a>
            @endif
        </div>
        <div class="cmms-req-meta">
            @if(!empty($showRequester))
            <span title="Requester"><i class="fa-regular fa-user" style="opacity:0.6;margin-right:4px;"></i><strong>Requester:</strong> {{ $req->requester?->full_name ?? '&mdash;' }}</span>
            @endif
            <span title="Date requested"><i class="fa-regular fa-calendar" style="opacity:0.6;margin-right:4px;"></i><strong>Date:</strong> {{ $req->created_at->format('d M Y, h:i A') }}</span>
        </div>
        @if(count($lines))
        <div class="cmms-req-items-preview">
            <i class="fa-solid fa-layer-group" style="opacity:0.6;margin-right:4px;"></i>{{ implode(' &middot; ', $lines) }}
            @if(count($req->items ?? []) > 3) <span style="font-weight:600; color:#0038A8;">+{{ count($req->items) - 3 }} more</span> @endif
        </div>
        @endif
    </div>
    @php $canQuick = $quickActions && in_array(strtolower($req->status ?? ''), ['pending', 'approved'], true); @endphp
    <div class="cmms-req-actions {{ $canQuick ? 'cmms-req-actions--quick' : '' }}">
        <a href="{{ route('requisitions.show', $req->id) }}" class="{{ $canQuick ? 'cmms-btn-secondary' : 'cmms-btn-primary' }}">{{ $actionLabel ?? 'View' }}</a>
        @if($canQuick)
            @if(strtolower($req->status) === 'pending')
                <button type="button" class="cmms-btn-primary supply-quick-btn" data-action="approve" data-id="{{ $req->id }}" data-pr="{{ $reqNo }}"><i class="fa-solid fa-check"></i> Approve</button>
                <button type="button" class="cmms-btn-danger-ghost supply-quick-btn" data-action="reject" data-id="{{ $req->id }}" data-pr="{{ $reqNo }}"><i class="fa-solid fa-xmark"></i> Disapprove</button>
            @else
                <button type="button" class="cmms-btn-success supply-quick-btn" data-action="issue" data-id="{{ $req->id }}" data-pr="{{ $reqNo }}" data-issue-destination="{{ $issueDestination }}"><i class="fa-solid fa-box-open"></i> Issue</button>
                <button type="button" class="cmms-btn-danger-ghost supply-quick-btn" data-action="reject" data-id="{{ $req->id }}" data-pr="{{ $reqNo }}"><i class="fa-solid fa-xmark"></i> Disapprove</button>
            @endif
        @endif
    </div>
    @if($quickActions)
    <div class="cmms-req-details-bar">
        <button type="button" class="cmms-req-details-btn" data-rid="{{ $req->id }}" aria-expanded="false"><i class="fa-solid fa-angles-down cmms-req-details-chevron"></i> View full details &amp; items</button>
    </div>
    <div class="cmms-req-details" id="req-details-{{ $req->id }}" hidden>
        <div class="cmms-req-details-grid">
            <div class="cmms-req-details-col">
                <div class="k"><i class="fa-solid fa-list-check" style="opacity:0.6;margin-right:6px;"></i>Line items &middot; {{ count($req->items ?? []) }}</div>
                @if(!empty($req->items))
                <ul class="cmms-req-details-list">
                    @foreach($req->items as $line)
                        <li><span class="q">{{ $line['quantity'] ?? 1 }}&times;</span> {{ $line['description'] ?? '' }}</li>
                    @endforeach
                </ul>
                @else
                    <p class="muted">No line items.</p>
                @endif
            </div>
            <div class="cmms-req-details-col">
                <div class="k"><i class="fa-solid fa-circle-info" style="opacity:0.6;margin-right:6px;"></i>Purpose / justification</div>
                <p>{{ $req->remarks ?: '—' }}</p>
            </div>
            <div class="cmms-req-details-col">
                <div class="k"><i class="fa-solid fa-laptop-medical" style="opacity:0.6;margin-right:6px;"></i>Job order context</div>
                <p style="font-weight:700; color:#0038A8; margin-bottom:6px;">JO {{ $req->ticket?->display_number ?? $req->ticket?->request_number ?? '&mdash;' }}</p>
                @if($req->ticket?->linkedAsset)
                    <p><span class="cmms-req-details-label">Asset:</span> {{ $req->ticket->linkedAsset->item_name }} <span class="muted">({{ $req->ticket->linkedAsset->serial_number ?? 'N/A' }})</span></p>
                    <p><span class="cmms-req-details-label">Custodian:</span> {{ $req->ticket->linkedAsset->assignedUser?->full_name ?? 'Not assigned' }}</p>
                @endif
                @if($req->requester)
                    <p style="margin-top:6px;"><span class="cmms-req-details-label">Requester:</span> {{ $req->requester->full_name }}</p>
                @endif
            </div>
        </div>
        @if($req->reviewer && $req->reviewed_at)
            <div class="cmms-req-details-meta">Last action by {{ $req->reviewer->full_name }} &middot; {{ $req->reviewed_at->format('d M Y, h:i A') }}</div>
        @endif
    </div>
    @endif
    @if($showTracker)
    <div class="cmms-req-card-track">
        @include('requisitions.partials.status-tracker', ['requisition' => $req, 'variant' => 'compact'])
    </div>
    @endif
</article>
