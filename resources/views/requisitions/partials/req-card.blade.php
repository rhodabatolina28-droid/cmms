@php
    $prNo = 'PR-' . str_pad($req->id, 5, '0', STR_PAD_LEFT);
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
<article class="cmms-req-card @if($quickActions && strtolower($req->status ?? '') === 'pending') cmms-req-card--needs-review @elseif($quickActions && strtolower($req->status ?? '') === 'approved') cmms-req-card--awaiting-issue @endif">
    <div>
        <div class="cmms-req-card-top">
            <span class="cmms-req-id">{{ $prNo }}</span>
            <span class="cmms-status-badge cmms-status-{{ $statusKey }}">{{ $req->status }}</span>
            @if($quickActions && strtolower($req->status ?? '') === 'pending')
                <span class="cmms-req-tag cmms-req-tag--review">Needs review</span>
                <span class="cmms-req-age">@php $reqAge = (int) $req->created_at->diffInDays(now()); @endphp {{ $reqAge }}d ago</span>
            @elseif($quickActions && strtolower($req->status ?? '') === 'approved')
                <span class="cmms-req-tag cmms-req-tag--issue">Awaiting issue</span>
            @endif
            @if($req->ticket)
                <a href="{{ route('requisitions.show', $req->id) }}" class="cmms-req-jo">JO {{ $req->ticket->display_number ?? $req->ticket->request_number }}</a>
            @endif
        </div>
        <div class="cmms-req-meta">
            @if(!empty($showRequester))
            <span><strong>Requester:</strong> {{ $req->requester?->full_name ?? '—' }}</span>
            @endif
            <span><strong>Date:</strong> {{ $req->created_at->format('d M Y, h:i A') }}</span>
        </div>
        @if(count($lines))
        <div class="cmms-req-items-preview">{{ implode(' · ', $lines) }}</div>
        @endif
    </div>
    @php $canQuick = $quickActions && in_array(strtolower($req->status ?? ''), ['pending', 'approved'], true); @endphp
    <div class="cmms-req-actions {{ $canQuick ? 'cmms-req-actions--quick' : '' }}">
        <a href="{{ route('requisitions.show', $req->id) }}" class="{{ $canQuick ? 'cmms-btn-secondary' : 'cmms-btn-primary' }}">{{ $actionLabel ?? 'View' }}</a>
        @if($canQuick)
            @if(strtolower($req->status) === 'pending')
                <button type="button" class="cmms-btn-primary supply-quick-btn" data-action="approve" data-id="{{ $req->id }}" data-pr="{{ $prNo }}"><i class="fa-solid fa-check"></i> Approve</button>
                <button type="button" class="cmms-btn-danger-ghost supply-quick-btn" data-action="reject" data-id="{{ $req->id }}" data-pr="{{ $prNo }}"><i class="fa-solid fa-xmark"></i> Disapprove</button>
            @else
                <button type="button" class="cmms-btn-success supply-quick-btn" data-action="issue" data-id="{{ $req->id }}" data-pr="{{ $prNo }}" data-issue-destination="{{ $issueDestination }}"><i class="fa-solid fa-box-open"></i> Issue</button>
                <button type="button" class="cmms-btn-danger-ghost supply-quick-btn" data-action="reject" data-id="{{ $req->id }}" data-pr="{{ $prNo }}"><i class="fa-solid fa-xmark"></i> Disapprove</button>
            @endif
        @endif
    </div>
    @if($quickActions)
    <div class="cmms-req-details-bar">
        <button type="button" class="cmms-req-details-btn" data-rid="{{ $req->id }}" aria-expanded="false"><i class="fa-solid fa-angles-down cmms-req-details-chevron"></i> View line items</button>
    </div>
    <div class="cmms-req-details" id="req-details-{{ $req->id }}" hidden>
        <div class="cmms-req-details-grid">
            <div class="cmms-req-details-col">
                <div class="k">Line items · {{ count($req->items ?? []) }}</div>
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
                <div class="k">Purpose / justification</div>
                <p>{{ $req->remarks ?: '&mdash;' }}</p>
            </div>
            <div class="cmms-req-details-col">
                <div class="k">Job order context</div>
                <p>JO {{ $req->ticket?->display_number ?? $req->ticket?->request_number ?? '&mdash;' }}</p>
                @if($req->ticket?->linkedAsset)
                    <p><span class="cmms-req-details-label">Asset:</span> {{ $req->ticket->linkedAsset->item_name }} <span class="muted">({{ $req->ticket->linkedAsset->serial_number ?? 'N/A' }})</span></p>
                    <p><span class="cmms-req-details-label">Custodian:</span> {{ $req->ticket->linkedAsset->assignedUser?->full_name ?? 'Not assigned' }}</p>
                @endif
                @if($req->requester)
                    <p><span class="cmms-req-details-label">Requester:</span> {{ $req->requester->full_name }}</p>
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
