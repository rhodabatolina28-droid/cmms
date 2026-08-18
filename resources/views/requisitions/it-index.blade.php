@extends('layouts.app')

@section('title', 'My Parts Requisitions')
@section('page-title', 'My Parts Requisitions')

@section('styles')
@include('requisitions.partials.official-assets')
<style nonce="{{ $cspNonce }}">
    .btn-back-top { margin-top:14px; }
    .pr-select { border:1px solid #e2e8f0; background:#fff; }
    .col-th-action { width:44px; }
    .input-qty-center { text-align:center; }
    .ticket-req-source { margin-top:6px; width:100%; border:1px solid #e2e8f0; background:#fff; color:#334155; padding:7px 10px; font-size:12px; border-radius:6px; }
    .ticket-req-source:focus { border-color:#0038A8; outline:none; }
    .col-price-muted { color:#cbd5e1; }
    .req-list-flush { padding:0; background:transparent; }
    .paginator-compact { padding:8px 0; }
    .pr-page-wrap { width:100%; }
    .col-num { width:44px; text-align:center; color:#64748b; font-weight:700; font-size:12px; }
    .cmms-pr-add-row-bar { display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin-top:12px; }
    .cmms-item-count { font-size:12px; color:#94a3b8; font-weight:700; }
    .cmms-pr-footer { align-items:flex-start; }
    .cmms-pr-option-wrap { display:flex; flex-direction:column; gap:4px; }
    .cmms-pr-option-hint { font-size:11px; color:#94a3b8; font-weight:500; }
    /* Requisition card hover effect */
    .req-card-hover { transition: all 0.2s ease; }
    .req-card-hover:hover { background: #f8fafc; }
    /* Status badge glow — already in cmms-official.css */
    /* MOBILE RESPONSIVE */
    @media (max-width: 767px) {
        .cmms-official-hero h1,
        .cmms-official-hero,
        .cmms-hero-badge,
        .cmms-official-hero .ref,
        .cmms-official-hero .sub {
            display: none !important;
        }
        .cmms-tab { padding: 12px 16px !important; font-size: 13px !important; min-height: 44px !important; }
        .cmms-pr-sheet { padding: 0 !important; }
        .cmms-pr-sheet-head { flex-direction: column !important; gap: 8px !important; padding: 16px !important; }
        .cmms-pr-sheet-head .doc-title { font-size: 16px !important; }
        .cmms-pr-select { min-height: 48px !important; font-size: 14px !important; }
        .cmms-pr-input { min-height: 44px !important; font-size: 14px !important; }
        .cmms-pr-textarea { min-height: 80px !important; font-size: 14px !important; }
        .cmms-pr-footer { flex-direction: column !important; gap: 12px !important; padding: 16px !important; }
        .cmms-pr-footer .cmms-btn-primary { width: 100% !important; justify-content: center !important; }
        .cmms-pr-add-row { width: 100% !important; justify-content: center !important; }
        .cmms-official-table { min-width: 480px !important; }
        .cmms-official-table th { font-size: 10px !important; padding: 8px !important; }
        .cmms-official-table td { padding: 8px !important; }
        .cmms-pr-items-wrap { overflow-x: auto !important; }
        .cmms-pr-justification { padding: 16px !important; }
        .ticket-req-qty { max-width: 60px !important; }
    }
</style>
@endsection

@section('content')
<div class="pr-page-wrap">
    <div class="cmms-official cmms-official-page">
        <div class="cmms-page-card">
            <div class="cmms-page-card-head">
                <div>
                    <h2>My Parts Requisitions</h2>
                    <div class="sub">Request and track parts tied to your assigned ICT or PM job orders.</div>
                </div>
                @if($activeTickets->isEmpty())
                <a href="{{ route('dashboard.it') }}" class="cmms-btn-secondary">Back to IT dashboard</a>
                @endif
            </div>
            <div class="cmms-page-card-body">
                <div class="cmms-tabs" role="tablist">
            <button type="button" class="cmms-tab active" data-target="tab-new" role="tab">Request Parts</button>
            <button type="button" class="cmms-tab" data-target="tab-history" role="tab">History</button>
        </div>

        <div id="tab-new" class="cmms-tab-content active" role="tabpanel">
            @if($activeTickets->isEmpty())
                <div class="cmms-panel">
                    <div class="cmms-panel-body cmms-empty">
                        <i class="fa-solid fa-clipboard-list cmms-empty-icon"></i>
                        <h3>No open ICT or PM job order</h3>
                        <p>Parts can only be requested from an assigned ICT or manual PM job order that is still active.</p>
                        <a href="{{ route('dashboard.it') }}" class="cmms-btn-secondary btn-back-top">Back to IT dashboard</a>
                    </div>
                </div>
            @else
                <form id="ticketReqForm">
                    <div class="cmms-pr-sheet">
                        <div class="cmms-pr-sheet-head">
                            <div>
                                <h2 class="doc-title">Parts requisition</h2>
                                <p class="doc-org">Select the ICT or PM job order that needs parts.</p>
                            </div>
                            <div class="cmms-pr-number-badge muted">Draft</div>
                        </div>

                        <div class="cmms-pr-meta-grid">
                            <div class="cmms-pr-meta-block">
                                <div class="cmms-pr-meta-row"><span class="k">Unit</span><span class="v">ICT / PM</span></div>
                                <div class="cmms-pr-meta-row"><span class="k">Requester</span><span class="v">{{ Auth::user()->full_name }}</span></div>
                                <div class="cmms-pr-meta-row">
                                    <span class="k">Job order no.</span>
                                    <span class="v">
                                        <select id="request_id" class="cmms-pr-select pr-select" required>
                                            <option value="" disabled {{ !$selectedTicketId ? 'selected' : '' }}>Select job order</option>
                                            @foreach($activeTickets as $ticket)
                                                <option value="{{ $ticket->id }}" {{ (string) $ticket->id === (string) ($selectedTicketId ?? '') ? 'selected' : '' }}>
                                                    {{ $ticket->type === 'Preventive Maintenance' ? '[PM] ' : '[ICT] ' }}{{ $ticket->display_number ?? $ticket->request_number }} &middot; {{ $ticket->status }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </span>
                                </div>
                            </div>
                            <div class="cmms-pr-meta-block">
                                <div class="cmms-pr-meta-row"><span class="k">Date prepared</span><span class="v">{{ date('F d, Y') }}</span></div>
                                <div class="cmms-pr-meta-row"><span class="k">Supply action</span><span class="v">Pending review</span></div>
                                <div class="cmms-pr-meta-row"><span class="k">Status after submit</span><span class="v">Awaiting Parts</span></div>
                            </div>
                        </div>

                        <div class="cmms-pr-items-wrap">
                            <table class="cmms-official-table">
                                <thead>
                                    <tr>
                                        <th class="col-num">#</th>
                                        <th class="col-qty">Qty</th>
                                        <th>Description and specifications</th>
                                        <th class="col-th-action"></th>
                                    </tr>
                                </thead>
                                <tbody id="ticketReqItemsList">
                                    <tr class="ticket-req-row">
                                        <td class="col-num input-qty-center"></td>
                                        <td class="col-qty"><input type="number" class="cmms-pr-input ticket-req-qty input-qty-center" min="1" value="1" required></td>
                                        <td class="col-desc">
                                            <input type="text" class="cmms-pr-input ticket-req-desc" placeholder="e.g. NVMe SSD 1TB, DDR4 8GB RAM" required>
                                            <select class="ticket-req-source">
                                                <option value="">Source: type manually / spare asset</option>
                                                @foreach($partsStock as $ps)
                                                    <option value="{{ $ps->id }}" data-name="{{ $ps->item_name }}" data-unit="{{ $ps->unit }}" data-onhand="{{ $ps->on_hand_qty }}">From Parts Stock: {{ $ps->item_name }} ({{ $ps->on_hand_qty }} {{ $ps->unit }})</option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" class="ticket-req-part-id" value="">
                                            <input type="hidden" class="ticket-req-source-val" value="other">
                                        </td>
                                        <td class="col-action"><button type="button" class="cmms-row-remove remove-item-btn" tabindex="-1" title="Remove row">&times;</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="cmms-pr-add-row-bar">
                            <button type="button" id="addTicketReqLine" class="cmms-pr-add-row">Add line item</button>
                            <span id="ticketReqItemCount" class="cmms-item-count" aria-live="polite">1 line item</span>
                        </div>

                        <div class="cmms-pr-justification">
                            <div class="k">Purpose / justification</div>
                            <textarea id="ticketReqRemarks" class="cmms-pr-textarea" rows="3" placeholder="State the purpose of the request and relevant technical particulars."></textarea>
                        </div>

                        <div class="cmms-pr-footer">
                            <div class="cmms-pr-option-wrap">
                                <label class="cmms-pr-option">
                                    <input type="checkbox" id="ticketReqAwaitingParts" checked>
                                    Mark job order as <strong>Awaiting Parts</strong>
                                </label>
                                <span class="cmms-pr-option-hint">Sets the job order status to "Awaiting Parts" once submitted.</span>
                            </div>
                            <button type="button" id="ticketPartsSubmitBtn" class="cmms-btn-primary">Submit to Supply</button>
                        </div>
                    </div>
                </form>
            @endif
        </div>

        <div id="tab-history" class="cmms-tab-content" role="tabpanel">
            @if($requisitions->isEmpty())
                <div class="cmms-panel">
                    <div class="cmms-panel-body cmms-empty">
                        <i class="fa-solid fa-box-archive cmms-empty-icon"></i>
                        <h3>No requisition history</h3>
                        <p>Parts requests submitted to Supply will appear here.</p>
                    </div>
                </div>
            @else
                <div class="cmms-req-list req-list-flush">
                    @foreach($requisitions as $req)
                        <div class="req-card-hover">
                            @include('requisitions.partials.req-card', [
                                'req' => $req,
                                'showTracker' => true,
                                'actionLabel' => 'View',
                            ])
                        </div>
                    @endforeach
                </div>
                <div class="paginator-compact">{{ $requisitions->links() }}</div>
            @endif
        </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
(function () {
    document.querySelectorAll('.cmms-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.cmms-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.cmms-tab-content').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            document.getElementById(this.dataset.target).classList.add('active');
        });
    });

    const ticketReqItemsList = document.getElementById('ticketReqItemsList');
    if (!ticketReqItemsList) return;

    let submitting = false;

    const rowHtml = () => `
        <tr class="ticket-req-row">
            <td class="col-num input-qty-center"></td>
            <td class="col-qty"><input type="number" class="cmms-pr-input ticket-req-qty input-qty-center" min="1" value="1" required></td>
            <td class="col-desc">
                <input type="text" class="cmms-pr-input ticket-req-desc" placeholder="Item description" required>
                <select class="ticket-req-source">
                    <option value="">Source: type manually / spare asset</option>
                    @foreach($partsStock as $ps)
                        <option value="{{ $ps->id }}" data-name="{{ $ps->item_name }}" data-unit="{{ $ps->unit }}" data-onhand="{{ $ps->on_hand_qty }}">From Parts Stock: {{ $ps->item_name }} ({{ $ps->on_hand_qty }} {{ $ps->unit }})</option>
                    @endforeach
                </select>
                <input type="hidden" class="ticket-req-part-id" value="">
                <input type="hidden" class="ticket-req-source-val" value="other">
            </td>
            <td class="col-action"><button type="button" class="cmms-row-remove remove-item-btn" tabindex="-1">&times;</button></td>
        </tr>`;

    const renumberTicketRows = () => {
        const rows = ticketReqItemsList.querySelectorAll('.ticket-req-row');
        rows.forEach((row, idx) => {
            const num = row.querySelector('.col-num');
            if (num) num.textContent = (idx + 1);
        });
        const countEl = document.getElementById('ticketReqItemCount');
        if (countEl) countEl.textContent = rows.length + (rows.length === 1 ? ' line item' : ' line items');
    };

    document.getElementById('addTicketReqLine')?.addEventListener('click', () => {
        ticketReqItemsList.insertAdjacentHTML('beforeend', rowHtml());
        renumberTicketRows();
        const rows = ticketReqItemsList.querySelectorAll('.ticket-req-row');
        const lastDesc = rows[rows.length - 1]?.querySelector('.ticket-req-desc');
        if (lastDesc) lastDesc.focus();
    });

    ticketReqItemsList.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-item-btn')) {
            if (ticketReqItemsList.querySelectorAll('.ticket-req-row').length > 1) {
                e.target.closest('tr').remove();
                renumberTicketRows();
            } else {
                Swal.fire({ icon: 'warning', title: 'Cannot remove', text: 'At least one item is required.', confirmButtonColor: '#0038A8' });
            }
        }
    });

    // When IT picks a part from Parts Stock, prefill the description + part_id/source.
    ticketReqItemsList.addEventListener('change', (e) => {
        if (!e.target.classList.contains('ticket-req-source')) return;
        const row = e.target.closest('tr');
        const opt = e.target.selectedOptions[0];
        const partIdInput = row.querySelector('.ticket-req-part-id');
        const sourceValInput = row.querySelector('.ticket-req-source-val');
        const descInput = row.querySelector('.ticket-req-desc');
        const qtyInput = row.querySelector('.ticket-req-qty');

        if (opt && opt.value) {
            partIdInput.value = opt.value;
            sourceValInput.value = 'parts-stock';
            descInput.value = opt.dataset.name || '';
            if (!qtyInput.value || parseInt(qtyInput.value, 10) < 1) qtyInput.value = 1;
        } else {
            partIdInput.value = '';
            sourceValInput.value = 'other';
        }
    });

    renumberTicketRows();

    document.getElementById('ticketPartsSubmitBtn')?.addEventListener('click', async () => {
        if (submitting) return;

        const reqId = document.getElementById('request_id').value;
        if (!reqId) {
            Swal.fire({ icon: 'warning', title: 'Select job order', text: 'Choose the ICT or PM job order this request is for.', confirmButtonColor: '#0038A8' });
            return;
        }

        const items = [];
        let hasEmptyDesc = false;
        document.querySelectorAll('.ticket-req-row').forEach(row => {
            const desc = row.querySelector('.ticket-req-desc')?.value?.trim();
            const qty = parseInt(row.querySelector('.ticket-req-qty')?.value || '1', 10);
            if (desc) items.push({
                description: desc,
                quantity: qty,
                source: row.querySelector('.ticket-req-source-val')?.value || 'other',
                part_id: row.querySelector('.ticket-req-part-id')?.value || null,
            });
            else hasEmptyDesc = true;
        });

        if (items.length === 0 || hasEmptyDesc) {
            Swal.fire({ icon: 'warning', title: 'Incomplete items', text: 'Fill in every item description.', confirmButtonColor: '#0038A8' });
            return;
        }

        submitting = true;
        const btn = document.getElementById('ticketPartsSubmitBtn');
        const originalLabel = btn.textContent;
        btn.textContent = 'Submitting...';
        btn.disabled = true;

        let url = @json(route('requisitions.store', 'REPLACE_ID'));
        url = url.replace('REPLACE_ID', reqId);

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    items,
                    remarks: document.getElementById('ticketReqRemarks')?.value || '',
                    set_awaiting_parts: document.getElementById('ticketReqAwaitingParts')?.checked,
                }),
            });
            const data = await res.json();

            if (res.ok && data.success) {
                await Swal.fire({ icon: 'success', title: 'Submitted', text: data.message, confirmButtonColor: '#0038A8' });
                window.location.href = '{{ url('requisitions') }}/' + data.requisition_id;
                return;
            }

            Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Could not submit.', confirmButtonColor: '#0038A8' });
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Network error', text: 'Check your connection and try again.', confirmButtonColor: '#0038A8' });
        }

        submitting = false;
        btn.textContent = originalLabel;
        btn.disabled = false;
    });
})();
</script>
@endsection