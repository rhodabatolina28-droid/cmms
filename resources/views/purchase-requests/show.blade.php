@extends('layouts.app')

@section('title', $purchaseRequest->pr_number)
@section('page-title', $purchaseRequest->pr_number)

@php
    $canWrite = auth()->user()->canProcessSupply();
    $status = $purchaseRequest->status;
@endphp

@section('styles')
<style nonce="{{ $cspNonce }}">
    .prx-container { width: 100%; margin-top: -10px; animation: fadeInSlide 0.4s ease-out; }
    .prx-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
    .prx-head { padding: 18px 22px; border-bottom: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; }
    .prx-title { font-size: 20px; font-weight: 900; color: #0f172a; }
    .p-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 800; }
    .p-pending { background: #fef3c7; color: #92400e; }
    .p-approved { background: #dbeafe; color: #1e40af; }
    .p-received { background: #dcfce7; color: #166534; }
    .p-cancelled { background: #fee2e2; color: #991b1b; }
    .prx-body { padding: 22px; display: grid; grid-template-columns: 1fr 320px; gap: 24px; }
    .prx-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 20px; }
    .prx-meta { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; }
    .prx-meta .k { font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: #64748b; }
    .prx-meta .v { font-size: 14px; font-weight: 700; color: #1e293b; margin-top: 3px; }
    .prx-table { width: 100%; border-collapse: collapse; }
    .prx-table th { text-align: left; padding: 11px 14px; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
    .prx-table td { padding: 12px 14px; font-size: 13.5px; border-bottom: 1px solid #f1f5f9; }
    .stripe { padding: 14px 18px; border-radius: 10px; font-size: 13px; font-weight: 700; margin-bottom: 12px; border: 1px solid; }
    .stripe-info { background: #eff6ff; color: #0038A8; border-color: #bfdbfe; }
    .stripe-ok { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
    .btn-navy { background: #0038A8; color: #fff; border: none; padding: 11px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; }
    .btn-navy:hover { background: #002d8a; }
    .btn-green { background: #15803d; color: #fff; border: none; padding: 11px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; }
    .btn-red { background: #b91c1c; color: #fff; border: none; padding: 11px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; }
    .btn-ghost { background: #fff; color: #334155; border: 1px solid #cbd5e1; padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; text-align: center; }
    .btn-ghost:hover { border-color: #0038A8; color: #0038A8; }
    @media (max-width: 900px) { .prx-body { grid-template-columns: 1fr; } }
</style>
@endsection

@section('content')
<div class="prx-container">
    <div class="prx-card">
        <div class="prx-head">
            <div class="prx-title">{{ $purchaseRequest->pr_number }}</div>
            <span class="p-badge p-{{ $status }}">{{ ucfirst($status) }}</span>
        </div>

        <div class="prx-body">
            <div>
                @if($status === 'pending' && $canWrite)
                    <div class="stripe stripe-info">⏳ Waiting for approval by Supply Office.</div>
                @elseif($status === 'approved' && $canWrite)
                    <div class="stripe stripe-info">✅ Approved — pindutin ang <b>Receive</b> kapag dumating na ang parts para ma-stock-in.</div>
                @elseif($status === 'received')
                    <div class="stripe stripe-ok">📦 Received — ang on-hand ng parts ay na-update na (Stock In).</div>
                @elseif($status === 'cancelled')
                    <div class="stripe stripe-ok">🚫 Cancelled.</div>
                @endif

                <div class="prx-grid">
                    <div class="prx-meta"><div class="k">Source requisition</div><div class="v">{{ $purchaseRequest->requisition?->ticket?->display_number ?? '—' }}</div></div>
                    <div class="prx-meta"><div class="k">Requested by</div><div class="v">{{ $purchaseRequest->requester?->full_name ?? '—' }}</div></div>
                    <div class="prx-meta"><div class="k">Date created</div><div class="v">{{ $purchaseRequest->created_at->format('M d, Y') }}</div></div>
                    <div class="prx-meta"><div class="k">Approved by</div><div class="v">{{ $purchaseRequest->approver?->full_name ?? '—' }}@if($purchaseRequest->approved_at) · {{ $purchaseRequest->approved_at->format('M d, Y') }}@endif</div></div>
                    <div class="prx-meta"><div class="k">Received by</div><div class="v">{{ $purchaseRequest->receiver?->full_name ?? '—' }}@if($purchaseRequest->received_at) · {{ $purchaseRequest->received_at->format('M d, Y g:i A') }}@endif</div></div>
                    @if($status === 'cancelled')
                    <div class="prx-meta"><div class="k">Cancelled by</div><div class="v">{{ $purchaseRequest->cancelled_by ? (\App\Models\User::find($purchaseRequest->cancelled_by)?->full_name ?? '—') : '—' }}@if($purchaseRequest->cancelled_at) · {{ $purchaseRequest->cancelled_at->format('M d, Y') }}@endif</div></div>
                    @endif
                </div>

                <table class="prx-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th>Qty (to order)</th>
                            <th>On-hand ngayon</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchaseRequest->items ?? [] as $i => $line)
                        @php $sh = $stockHalves[$i] ?? null; $part = $sh['part'] ?? null; @endphp
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $line['description'] ?? '' }}</td>
                            <td>{{ $line['quantity'] ?? 1 }}</td>
                            <td>{{ $part ? $part->on_hand_qty . ' ' . $part->unit : '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align:center;color:#64748b;">Walang items.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if($purchaseRequest->remarks)
                <div class="prx-meta" style="margin-top:16px;"><div class="k">Remarks</div><div class="v">{{ $purchaseRequest->remarks }}</div></div>
                @endif
            </div>

            <aside>
                @if($canWrite)
                <div style="display:flex;flex-direction:column;gap:10px;">
                    @if($status === 'pending')
                        <button class="btn-navy prx-act" data-action="approve">✓ Approve Purchase Request</button>
                        <button class="btn-red prx-act" data-action="cancel">Cancel</button>
                    @elseif($status === 'approved')
                        <button class="btn-green prx-act" data-action="receive">📦 Receive — Stock In</button>
                        <button class="btn-red prx-act" data-action="cancel">Cancel</button>
                    @endif
                </div>
                @endif

                <div style="margin-top:14px;">
                    <a href="{{ url('purchase-requests') }}" class="btn-ghost" style="width:100%;box-sizing:border-box;">← Return to list</a>
                </div>
            </aside>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@if($canWrite && in_array($status, ['pending', 'approved'], true))
<script nonce="{{ $cspNonce }}">
    (function () {
        document.querySelectorAll('.prx-act').forEach(btn => {
            btn.addEventListener('click', async () => {
                const action = btn.dataset.action;
                const confirm = await Swal.fire({
                    icon: 'question',
                    title: 'Purchase Request — ' + action,
                    text: 'Ito ay ire-record sa ilalim ng iyong supply account.',
                    showCancelButton: true,
                    confirmButtonColor: '#0038A8',
                    confirmButtonText: 'Confirm',
                });
                if (!confirm.isConfirmed) return;

                let r;
                try {
                    r = await fetch('{{ url('purchase-requests') }}/' + @json($purchaseRequest->id) + '/' + action, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                            'Accept': 'application/json',
                        },
                    });
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'Network error', confirmButtonColor: '#0038A8' });
                    return;
                }

                const data = await r.json().catch(() => ({}));
                if (data.success) {
                    await Swal.fire({ icon: 'success', title: 'Done', text: data.message, confirmButtonColor: '#0038A8' });
                    window.location.href = data.redirect || '{{ route('purchase_requests.show', $purchaseRequest->id) }}';
                    return;
                }
                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Could not process.', confirmButtonColor: '#0038A8' });
            });
        });
    })();
</script>
@endif
@endsection