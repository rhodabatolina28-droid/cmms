@extends('layouts.app')

@section('title', 'Record Delivery · '.$purchaseRequest->pr_number)
@section('page-title', 'Record Delivery')

@section('styles')
<style nonce="{{ $cspNonce }}">
    .rx-page { max-width: 860px; margin: 0 auto; }
    .rx-topbar { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
    .rx-back { display:inline-flex; align-items:center; gap:7px; font-size:12px; font-weight:700; color:#475569; text-decoration:none; background:#fff; border:1px solid #e2e8f0; border-radius:9px; padding:8px 14px; transition:all .15s; }
    .rx-back:hover { color:#0038A8; border-color:#bfdbfe; background:#f8fafc; }
    .rx-crumb { display:flex; align-items:center; gap:6px; font-size:12px; color:#94a3b8; }
    .rx-crumb b { color:#334155; }
    .rx-hero { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:20px 22px; margin-bottom:16px; }
    .rx-hero h1 { margin:0 0 6px; font-size:19px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:11px; }
    .rx-hero .rx-sub { font-size:12.5px; color:#475569; line-height:1.5; max-width:540px; }
    .rx-hero-meta { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
    .rx-stat { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:8px 15px; min-width:105px; }
    .rx-stat .k { font-size:9.5px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:#94a3b8; }
    .rx-stat .v { font-size:13.5px; font-weight:800; color:#0f172a; margin-top:1px; }
    .rx-panel { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:20px 22px; margin-bottom:16px; box-shadow:0 1px 2px rgba(15,23,42,.04); }
    .rx-step-head { display:flex; align-items:center; gap:11px; padding-bottom:12px; border-bottom:1px solid #f1f5f9; margin-bottom:14px; }
    .rx-step-badge { width:30px; height:30px; border-radius:50%; background:#0038A8; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:13px; font-weight:800; flex:none; }
    .rx-step-head h2 { margin:0; font-size:15.5px; font-weight:800; color:#111827; }
    .rx-step-head .st-sub { font-size:11.5px; color:#94a3b8; }
    .rx-hint { font-size:12px; color:#6b7280; margin:0 0 14px; line-height:1.55; }
    .rx-line { border:1.5px solid #e5e7eb; border-radius:12px; padding:15px 16px; margin-bottom:12px; background:#fff; transition:border-color .15s, box-shadow .15s; }
    .rx-line:focus-within { border-color:#0038A8; box-shadow:0 0 0 3px rgba(0,56,168,.07); }
    .rx-line-no { width:26px; height:26px; border-radius:8px; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; font-size:12px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; flex:none; }
    .rx-line-no { width:26px; height:26px; border-radius:8px; background:#eff6ff; color:#0038A8; border:1px solid #bfdbfe; font-size:12px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; flex:none; }
    .rx-line-desc { flex:1; font-size:13.5px; font-weight:700; color:#111827; }
    .rx-line-desc em { color:#475569; font-style:normal; font-weight:700; background:#f1f5f9; border-radius:999px; padding:2px 10px; font-size:11.5px; margin-left:7px; }
    .rx-field-label { display:block; font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#64748b; margin-bottom:5px; }
    .rx-cols { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    @media (max-width:680px){ .rx-cols { grid-template-columns:1fr; } }
    .rx-select-wrap select, .rx-input { width:100%; padding:9px 12px; border:1.5px solid #d1d5db; border-radius:9px; font-size:12.5px; background:#fff; transition:border-color .15s; }
    .rx-select-wrap select:focus, .rx-input:focus { outline:none; border-color:#0038A8; }
    .rx-dest { display:grid; grid-template-columns:1fr 1fr; gap:9px; }
    @media (max-width:680px){ .rx-dest { grid-template-columns:1fr; } }
    .rx-dest label { display:flex; gap:10px; align-items:flex-start; border:1.5px solid #d1d5db; border-radius:11px; padding:11px 13px; cursor:pointer; background:#fff; transition:all .15s; }
    .rx-dest label:hover { border-color:#86efac; }
    .rx-dest label input[type=radio] { width:16px; height:16px; accent-color:#0038A8; margin-top:1px; flex:none; cursor:pointer; }
    .rx-dest label.is-checked { border-color:#0038A8; background:#eff6ff; box-shadow:0 0 0 3px rgba(0,56,168,.08); }
    .rx-dest label.is-disabled { opacity:.45; cursor:not-allowed; background:#f8fafc; }
    .rx-dest .t { display:block; font-size:12.5px; font-weight:800; color:#111827; }
    .rx-dest .d { display:block; font-size:11px; color:#64748b; margin-top:3px; line-height:1.45; }
    .rx-unit-grid { display:none; margin-top:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; }
    .rx-unit-grid.show { display:block; }
    .rx-unit-title { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#374151; margin-bottom:8px; }
    .rx-unit-row { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px; }
    @media (max-width:560px){ .rx-unit-row { grid-template-columns:1fr; } }
    .rx-warn { display:flex; gap:10px; align-items:flex-start; background:#fffbeb; border:1.5px solid #fde68a; border-radius:12px; padding:13px 15px; margin-bottom:16px; font-size:12.5px; color:#92400e; line-height:1.5; }
    .rx-warn i { margin-top:2px; }

    /* Inline "create new part" mini-form */
    .rx-newpart { margin-top:8px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px 12px; }
    .rx-newpart[hidden] { display:none; }

    /* Unified page buttons */
    .rxb { display:inline-flex; align-items:center; justify-content:center; gap:7px; border:none; border-radius:10px; font-family:inherit; font-size:13px; font-weight:700; padding:10px 18px; cursor:pointer; text-decoration:none; transition:all .15s; line-height:1; }
    .rxb-green { background:#15803d; color:#fff; box-shadow:0 1px 3px rgba(21,128,61,.35); }
    .rxb-green:hover { background:#166534; transform:translateY(-1px); box-shadow:0 4px 10px rgba(21,128,61,.3); color:#fff; }
    .rxb-blue { background:#0038A8; color:#fff; box-shadow:0 1px 3px rgba(0,56,168,.3); }
    .rxb-blue:hover { background:#002d85; transform:translateY(-1px); color:#fff; }
    .rxb-white { background:#fff; color:#334155; border:1.5px solid #e2e8f0; }
    .rxb-white:hover { border-color:#cbd5e1; color:#0038A8; }
    .rxb-sm { padding:6px 12px; font-size:11.5px; border-radius:8px; }

    /* Upload dropzone */
    .rx-dropzone { border:2px dashed #cbd5e1; border-radius:12px; padding:20px; text-align:center; background:#f8fafc; transition:all .15s; cursor:pointer; }
    .rx-dropzone:hover, .rx-dropzone.dragover { border-color:#0038A8; background:#eff6ff; }
    .rx-dropzone i.dz-ic { font-size:22px; color:#94a3b8; margin-bottom:8px; display:block; }
    .rx-dropzone .dz-t { font-size:12.5px; font-weight:700; color:#334155; }
    .rx-dropzone .dz-d { font-size:11px; color:#94a3b8; margin-top:2px; }

    /* Sticky confirm */
    .rx-confirm-bar { position:sticky; bottom:14px; z-index:20; background:#fff; border:1px solid #bbf7d0; border-radius:14px; padding:14px 18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; box-shadow:0 8px 24px rgba(15,23,42,.12); }
    .rx-confirm-bar .txt { font-size:12px; color:#475569; }
    .rx-confirm-bar { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:14px 18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; position:sticky; bottom:12px; box-shadow:0 4px 14px rgba(15,23,42,.08); z-index:5; }
    .rx-confirm-bar .txt { font-size:12px; color:#64748b; }
</style>
@endsection

@section('content')
<div class="rx-page">

    <div class="rx-topbar">
        <a href="{{ route('purchase_requests.show', $purchaseRequest->id) }}" class="rx-back" aria-label="Back to purchase request document">
            <i class="fa-solid fa-arrow-left"></i>Back to document
        </a>
        <div class="rx-crumb">Purchase Request <i class="fa-solid fa-angle-right"></i> <b>{{ $purchaseRequest->pr_number }}</b> <i class="fa-solid fa-angle-right"></i> Record delivery</div>
    </div>

    <div class="rx-hero">
        <h1>{{ !empty($viewOnly) ? 'Delivery record' : 'Record delivery' }}</h1>
        <p class="rx-sub">
            @if(!empty($viewOnly))
                <b class="rx-hero-delivered">{{ $purchaseRequest->pr_number }}</b> was received {{ optional($purchaseRequest->delivered_at)->format('M d, Y h:i A') }} by {{ $purchaseRequest->deliverer?->full_name ?? 'Supply Officer' }}. View the proof of purchase and recorded items below.
            @else
                The goods for <b>{{ $purchaseRequest->pr_number }}</b> have physically arrived.
                Log what was purchased and where each piece went — this closes the purchase request.
            @endif
        </p>
        <div class="rx-hero-meta">
            <div class="rx-stat"><div class="k">Items</div><div class="v">{{ count($purchaseRequest->items ?? []) }}</div></div>
            <div class="rx-stat"><div class="k">Total cost</div><div class="v">{{ $purchaseRequest->total_amount !== null ? '₱' . number_format((float) $purchaseRequest->total_amount, 2) : '—' }}</div></div>
            @if($purchaseRequest->isDelivered())
                <div class="rx-stat"><div class="k">Status</div><div class="v" style="color:#15803d;">Delivered</div></div>
            @endif
            @if($linkedAsset)
                <div class="rx-stat"><div class="k">Target asset</div><div class="v">{{ $linkedAsset->asset_code ?? ('#' . $linkedAsset->asset_id) }}</div></div>
            @endif
        </div>
    </div>

    @if($errors->any())
        <div class="rx-warn" role="alert" style="background:#fef2f2; border-color:#fecaca; color:#b91c1c;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div>@foreach($errors->all() as $err)<p style="margin:1px 0;">{{ $err }}</p>@endforeach</div>
        </div>
    @endif

    @include('purchase-requests.partials.receive-panel')
    @include('purchase-requests.partials.attachments-card')
</div>

<script>
// The serial/property grid must appear for ANY destination when the selected
// part is serialized (requires_unit_tracking) — stock-in included. Backend
// validation enforces one serial + property per quantity for tracked parts.
function rxPartTracked(sel) {
    if (!sel || !sel.value) return false;
    if (sel.value === 'new') return true; // new parts are always serialized
    var opt = sel.options[sel.selectedIndex];
    return !!(opt && opt.dataset.tracked === '1');
}
function rxSyncUnits(line) {
    if (!line) return;
    var grid = line.querySelector('[data-units]');
    var sel = line.querySelector('select[name$="[part_id]"]');
    if (grid) { grid.classList.toggle('show', rxPartTracked(sel)); }
}
function rxPartChoice(sel) {
    var line = sel.closest('.rx-line');
    var box = line && line.querySelector('[data-newpart]');
    if (box) { box.hidden = sel.value !== 'new'; }
    rxSyncUnits(line);
}
document.addEventListener('change', function (e) {
    if (e.target.matches('input[name$="[destination]"]')) {
        e.target.closest('.rx-dest').querySelectorAll('label').forEach(function (l) {
            l.classList.toggle('is-checked', l.contains(e.target));
        });
        rxSyncUnits(e.target.closest('.rx-line'));
    }
});
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.rx-line').forEach(function (line) {
        rxSyncUnits(line);
        var checked = line.querySelector('.rx-dest input:checked');
        if (checked) checked.closest('label').classList.add('is-checked');
    });
    document.querySelectorAll('[data-newpart]').forEach(function (b) {
        var sel = b.closest('.rx-line')?.querySelector('select[name$="[part_id]"]');
        if (sel) b.hidden = sel.value !== 'new';
    });
});
</script>
@endsection
