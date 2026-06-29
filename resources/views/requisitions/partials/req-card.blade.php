@php
    $prNo = 'PR-' . str_pad($req->id, 5, '0', STR_PAD_LEFT);
    $statusKey = strtolower($req->status);
    $lines = [];
    foreach (array_slice($req->items ?? [], 0, 3) as $line) {
        $lines[] = ($line['quantity'] ?? 1) . ' × ' . \Illuminate\Support\Str::limit($line['description'] ?? '', 48);
    }
    $showTracker = $showTracker ?? false;
@endphp
<article class="cmms-req-card">
    <div>
        <div class="cmms-req-card-top">
            <span class="cmms-req-id">{{ $prNo }}</span>
            <span class="cmms-status-badge cmms-status-{{ $statusKey }}">{{ $req->status }}</span>
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
    <div class="cmms-req-actions">
        <a href="{{ route('requisitions.show', $req->id) }}" class="cmms-btn-primary">{{ $actionLabel ?? 'View' }}</a>
    </div>
    @if($showTracker)
    <div class="cmms-req-card-track">
        @include('requisitions.partials.status-tracker', ['requisition' => $req, 'variant' => 'compact'])
    </div>
    @endif
</article>
