@php
    $ticket = $requisition->ticket;
    $prNo = 'PR-' . str_pad($requisition->id, 5, '0', STR_PAD_LEFT);
@endphp
<style nonce="{{ $cspNonce }}">.cmms-empty-cell { text-align: center; color: #5c6573; padding: 20px; }</style>
<div class="cmms-pr-sheet">
    <div class="cmms-pr-sheet-head">
        <div>
            <h2 class="doc-title">Purchase Request</h2>
            <p class="doc-org">NCMB · Property and Supply Division · ICT Unit</p>
        </div>
        <div class="cmms-pr-number-badge">{{ $prNo }}</div>
    </div>

    <div class="cmms-pr-meta-grid">
        <div class="cmms-pr-meta-block">
            <div class="cmms-pr-meta-row"><span class="k">Department</span><span class="v">ICT Unit</span></div>
            <div class="cmms-pr-meta-row"><span class="k">Requester</span><span class="v">{{ $requisition->requester?->full_name ?? '—' }}</span></div>
            <div class="cmms-pr-meta-row"><span class="k">Job order</span><span class="v">{{ $ticket?->display_number ?? $ticket?->request_number ?? '—' }}</span></div>
            <div class="cmms-pr-meta-row"><span class="k">Status</span><span class="v"><span class="cmms-status-badge cmms-status-{{ strtolower($requisition->status) }}">{{ $requisition->status }}</span></span></div>
            <div class="cmms-pr-meta-row"><span class="k">Reviewed by</span><span class="v">{{ $requisition->reviewer?->full_name ?? '—' }}</span></div>
        </div>
        <div class="cmms-pr-meta-block">
            <div class="cmms-pr-meta-row"><span class="k">Date of request</span><span class="v">{{ $requisition->created_at->format('d F Y') }}</span></div>
            <div class="cmms-pr-meta-row"><span class="k">Date reviewed</span><span class="v">{{ $requisition->reviewed_at?->format('d F Y') ?? '—' }}</span></div>
            <div class="cmms-pr-meta-row"><span class="k">Job order status</span><span class="v">{{ $ticket?->status ?? '—' }}</span></div>
            @if($ticket?->linkedAsset)
            <div class="cmms-pr-meta-row"><span class="k">Accountable property</span><span class="v">{{ $ticket->linkedAsset->item_name }} ({{ $ticket->linkedAsset->serial_number ?? 'N/A' }})</span></div>
            @endif
        </div>
    </div>

    <div class="cmms-pr-items-wrap">
        <table class="cmms-official-table">
            <thead>
                <tr>
                    <th class="col-num">No.</th>
                    <th class="col-desc">Description and specifications</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-price">Unit cost</th>
                    <th class="col-price">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requisition->items ?? [] as $i => $line)
                <tr>
                    <td class="col-num">{{ $i + 1 }}</td>
                    <td>{{ $line['description'] ?? '' }}</td>
                    <td class="col-qty">{{ $line['quantity'] ?? 1 }}</td>
                    <td class="col-price">—</td>
                    <td class="col-price">—</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="cmms-empty-cell">No line items on file.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="cmms-pr-justification">
        <div class="k">Purpose / justification</div>
        <div class="v">{{ $requisition->remarks ?: '—' }}</div>
    </div>
</div>
