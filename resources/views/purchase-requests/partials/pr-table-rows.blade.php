@foreach($requests as $pr)
    @php
        $firstItem = $pr->items[0]['description'] ?? '—';
        $more = max(0, count($pr->items ?? []) - 1);
        $isSubmitted = $pr->status === 'submitted';
        $waitDays = ($isSubmitted && $pr->created_at)
            ? (int) floor($pr->created_at->startOfDay()->diffInDays(now()->startOfDay()))
            : 0;
    @endphp
    <tr>
        <td class="cell-trim nowrap"><strong>{{ $pr->pr_number }}</strong></td>
        <td class="cell-trim">{{ $pr->requester?->full_name ?? '—' }}</td>
        <td class="cell-trim" style="min-width:180px;">
            {{ $firstItem }}@if($more > 0) <em>+{{ $more }} more</em>@endif
        </td>
        <td class="nowrap" style="font-weight:700;color:#0038A8;">{{ $pr->total_amount !== null ? '₱' . number_format((float) $pr->total_amount, 2) : '—' }}</td>
        <td class="nowrap">
            {{ $pr->created_at?->format('M d, Y') }}
            @if($isSubmitted && $waitDays > 0)
                <div>
                    <span class="pr-wait-badge {{ $waitDays >= 7 ? 'urgent' : 'warn' }}">
                        ⏳ waiting {{ $waitDays }}d
                    </span>
                </div>
            @endif
        </td>
        <td class="nowrap">
            @if($pr->status === 'finalized')
                <span class="req-pill cmms-status-issued">Finalized</span>
            @elseif($pr->status === 'delivered')
                <span class="req-pill cmms-status-received">Delivered</span>
            @else
                <span class="req-pill cmms-status-pending">{{ ucfirst($pr->status) }}</span>
            @endif
        </td>
        <td class="nowrap">
            <div style="display:flex;align-items:center;gap:6px;">
                <a href="{{ route('purchase_requests.show', $pr->id) }}" class="act-btn" aria-label="View {{ $pr->pr_number }} document" title="Open full document">
                    <i class="fa-solid fa-eye"></i>
                </a>
                @if($isSubmitted)
                    <form method="POST" action="{{ route('purchase_requests.finalize', $pr->id) }}">
                        @csrf
                        <button type="submit" class="act-btn in pr-finalize-btn" data-pr="{{ $pr->pr_number }}" aria-label="Finalize and print {{ $pr->pr_number }}" title="Finalize & enable printing">
                            <i class="fa-solid fa-stamp"></i>
                        </button>
                    </form>
                @elseif($pr->status === 'finalized')
                    <a href="{{ route('purchase_requests.receiveForm', $pr->id) }}" class="act-btn in" aria-label="Record delivery for {{ $pr->pr_number }}" title="Goods arrived? Log items, serials, and destination">
                        <i class="fa-solid fa-truck-fast"></i>
                    </a>
                @elseif($pr->status === 'delivered')
                    <a href="{{ route('purchase_requests.receiveForm', $pr->id) }}" class="act-btn" aria-label="View delivery record for {{ $pr->pr_number }}" title="View what arrived, where it went, and the proof of purchase">
                        <i class="fa-solid fa-receipt"></i>
                    </a>
                @endif
            </div>
        </td>
    </tr>
@endforeach
