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
    .col-price-muted { color:#cbd5e1; }
    .req-list-flush { padding:0; background:transparent; }
    .paginator-compact { padding:8px 0; }
    .pr-page-wrap { width:100%; }
    /* Requisition card hover effect */
    .req-card-hover { transition: all 0.2s ease; }
    .req-card-hover:hover { background: #f8fafc; }
    /* Status badge glow — already in cmms-official.css */
    /* MOBILE RESPONSIVE */
    @media (max-width: 767px) {
        .cmms-official-hero h1 { font-size: 1.1rem !important; }
        .cmms-official-hero { padding: 16px !important; }
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

        <div class="cmms-official-hero">
            <div class="ref">National Conciliation and Mediation Board · ICT Unit</div>
            <h1>My Parts Requisitions</h1>
            <p class="sub">Request and track parts tied to your assigned ICT job orders.</p>
        </div>

        <div class="cmms-tabs" role="tablist">
            <button type="button" class="cmms-tab active" data-target="tab-new" role="tab">Request Parts</button>
            <button type="button" class="cmms-tab" data-target="tab-history" role="tab">History</button>
        </div>

        <div id="tab-new" class="cmms-tab-content active" role="tabpanel">
            @if($activeTickets->isEmpty())
                <div class="cmms-panel">
                    <div class="cmms-panel-body cmms-empty">
                        <h3>No open ICT job order</h3>
                        <p>Parts can only be requested from an assigned ICT job order that is still active.</p>
                        <a href="{{ route('dashboard.it') }}" class="cmms-btn-secondary btn-back-top">Back to IT dashboard</a>
                    </div>
                </div>
            @else
                <form id="ticketReqForm">
                    <div class="cmms-pr-sheet">
                        <div class="cmms-pr-sheet-head">
                            <div>
                                <h2 class="doc-title">Parts requisition</h2>
                                <p class="doc-org">Select the ICT job order that needs parts.</p>
                            </div>
                            <div class="cmms-pr-number-badge muted">Draft</div>
                        </div>

                        <div class="cmms-pr-meta-grid">
                            <div class="cmms-pr-meta-block">
                                <div class="cmms-pr-meta-row"><span class="k">Unit</span><span class="v">ICT Unit</span></div>
                                <div class="cmms-pr-meta-row"><span class="k">Requester</span><span class="v">{{ Auth::user()->full_name }}</span></div>
                                <div class="cmms-pr-meta-row">
                                    <span class="k">Job order no.</span>
                                    <span class="v">
                                        <select id="request_id" class="cmms-pr-select pr-select" required>
                                            <option value="" disabled {{ !$selectedTicketId ? 'selected' : '' }}>Select job order</option>
                                            @foreach($activeTickets as $ticket)
                                                <option value="{{ $ticket->id }}" {{ (string) $ticket->id === (string) ($selectedTicketId ?? '') ? 'selected' : '' }}>
                                                    {{ $ticket->display_number ?? $ticket->request_number }} &middot; {{ $ticket->status }}
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
                                        <th class="col-qty">Qty</th>
                                        <th>Description and specifications</th>
                                        <th class="col-price">Unit price</th>
                                        <th class="col-price">Total</th>
                                        <th class="col-th-action"></th>
                                    </tr>
                                </thead>
                                <tbody id="ticketReqItemsList">
                                    <tr class="ticket-req-row">
                                        <td class="col-qty"><input type="number" class="cmms-pr-input ticket-req-qty input-qty-center" min="1" value="1" required></td>
                                        <td class="col-desc"><input type="text" class="cmms-pr-input ticket-req-desc" placeholder="e.g. NVMe SSD 1TB, DDR4 8GB RAM" required></td>
                                        <td class="col-price col-price-muted">-</td>
                                        <td class="col-price col-price-muted">-</td>
                                        <td class="col-action"><button type="button" class="cmms-row-remove remove-item-btn" tabindex="-1" title="Remove row">&times;</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" id="addTicketReqLine" class="cmms-pr-add-row">Add line item</button>

                        <div class="cmms-pr-justification">
                            <div class="k">Purpose / justification</div>
                            <textarea id="ticketReqRemarks" class="cmms-pr-textarea" rows="3" placeholder="State the purpose of the request and relevant technical particulars."></textarea>
                        </div>

                        <div class="cmms-pr-footer">
                            <label class="cmms-pr-option">
                                <input type="checkbox" id="ticketReqAwaitingParts" checked>
                                Mark job order as <strong>Awaiting Parts</strong>
                            </label>
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
            <td class="col-qty"><input type="number" class="cmms-pr-input ticket-req-qty input-qty-center" min="1" value="1" required></td>
            <td class="col-desc"><input type="text" class="cmms-pr-input ticket-req-desc" placeholder="Item description" required></td>
            <td class="col-price col-price-muted">-</td>
            <td class="col-price col-price-muted">-</td>
            <td class="col-action"><button type="button" class="cmms-row-remove remove-item-btn" tabindex="-1">&times;</button></td>
        </tr>`;

    document.getElementById('addTicketReqLine')?.addEventListener('click', () => {
        ticketReqItemsList.insertAdjacentHTML('beforeend', rowHtml());
    });

    ticketReqItemsList.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-item-btn')) {
            if (ticketReqItemsList.querySelectorAll('.ticket-req-row').length > 1) {
                e.target.closest('tr').remove();
            } else {
                Swal.fire({ icon: 'warning', title: 'Cannot remove', text: 'At least one item is required.', confirmButtonColor: '#0038A8' });
            }
        }
    });

    document.getElementById('ticketPartsSubmitBtn')?.addEventListener('click', async () => {
        if (submitting) return;

        const reqId = document.getElementById('request_id').value;
        if (!reqId) {
            Swal.fire({ icon: 'warning', title: 'Select job order', text: 'Choose the ICT job order this request is for.', confirmButtonColor: '#0038A8' });
            return;
        }

        const items = [];
        let hasEmptyDesc = false;
        document.querySelectorAll('.ticket-req-row').forEach(row => {
            const desc = row.querySelector('.ticket-req-desc')?.value?.trim();
            const qty = parseInt(row.querySelector('.ticket-req-qty')?.value || '1', 10);
            if (desc) items.push({ description: desc, quantity: qty });
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