@foreach($requisitions as $req)
@php
    $rNo = 'REQ-' . str_pad($req->id, 5, '0', STR_PAD_LEFT);
    $rSt = strtolower($req->status);
    $rItems = $req->items ?? [];
    $isIssued = $rSt === 'issued';
@endphp
<tr>
    <td style="text-align:left;">
        <a href="{{ route('requisitions.show', $req->id) }}" style="font-weight:700;color:#0038A8;text-decoration:none;">
            {{ $rNo }}
        </a>
    </td>
    <td>
        <span class="cmms-req-tag {{ $req->ticket?->type === 'Preventive Maintenance' ? 'cmms-req-tag--issue' : 'cmms-req-tag--review' }}">{{ $req->ticket?->type === 'Preventive Maintenance' ? 'PM' : 'ICT' }}</span>
    </td>
    <td>
        <span class="cmms-status-badge cmms-status-{{ $rSt }}">{{ ucfirst($req->status) }}</span>
    </td>
    <td class="cell-trim" title="{{ $req->ticket?->display_number ?? $req->ticket?->request_number ?? '' }}">
        {{ \Illuminate\Support\Str::limit($req->ticket?->display_number ?? $req->ticket?->request_number ?? '—', 28) }}
    </td>
    <td class="cell-trim">
        @if(count($rItems))
            {{ ($rItems[0]['quantity'] ?? 1) }}× {{ \Illuminate\Support\Str::limit($rItems[0]['description'] ?? '', 30) }}@if(count($rItems) > 1) <span style="color:#0038A8;font-weight:600;">+{{ count($rItems) - 1 }}</span>@endif
        @else
            &mdash;
        @endif
    </td>
    <td data-label="Date">{{ $req->created_at->format('M d, Y | h:i A') }}</td>
    <td data-label="Completed">
        @if($isIssued && $req->reviewed_at)
            {{ $req->reviewed_at->format('M d, Y | h:i A') }}
        @else
            &mdash;
        @endif
    </td>
</tr>
@endforeach
