@foreach($myPrs as $pr)
<tr>
    <td style="text-align:left;"><strong>{{ $pr->pr_number }}</strong></td>
    <td>{{ $pr->total_amount !== null ? '₱' . number_format((float) $pr->total_amount, 2) : '—' }}</td>
    <td>{{ $pr->created_at?->format('M d, Y | h:i A') }}</td>
    <td>
        @if($pr->isLegacyStatus())
            <span class="req-pill">{{ ucfirst($pr->status) }} (legacy)</span>
        @elseif($pr->status === 'delivered')
            <span class="req-pill cmms-status-received">Delivered</span>
        @elseif($pr->status === 'finalized')
            <span class="req-pill cmms-status-issued">Finalized</span>
        @elseif($pr->status === 'draft')
            <span class="req-pill">Draft</span>
        @else
            <span class="req-pill cmms-status-pending">Submitted to Supply</span>
        @endif
    </td>
    <td class="nowrap" style="text-align:left;">
        <div style="display:flex;align-items:center;gap:6px;">
            <a href="{{ route('purchase_requests.show', $pr->id) }}" class="cmms-btn-secondary" style="padding:5px 12px;font-size:11.5px;">View</a>
            @if($pr->status === 'finalized' && $pr->isSmallPurchase())
                <a href="{{ route('purchase_requests.receiveForm', $pr->id) }}" class="cmms-btn-primary" style="padding:5px 12px;font-size:11.5px;" aria-label="Record delivery for {{ $pr->pr_number }}" title="You bought this yourself (under ₱10k) — log the delivery here">
                    Record delivery
                </a>
            @elseif($pr->status === 'finalized')
                <span style="font-size:11px; color:#94a3b8;" title="≥₱10k purchases are received by the Supply Officer after procurement">With Procurement</span>
            @endif
        </div>
    </td>
</tr>
@endforeach
