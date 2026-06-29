@extends('layouts.app')

@section('title', 'Job Order ' . ($request->display_number ?? $request->request_number))
@section('page-title', 'ICT Job Order Ticket')

@section('styles')
<link href="{{ asset('css/cmms-official.css') }}" rel="stylesheet">
<style nonce="{{ $cspNonce }}">
    .tkt-hero { padding: 28px 30px; }
    .tkt-meta-grid { grid-template-columns: repeat(4, minmax(180px, 1fr)); gap: 16px; margin-top: 24px; margin-bottom: 28px; }
    .tkt-panel-mb { margin-bottom: 22px; }
    .tkt-panel-body-grid { padding: 20px; display: grid; grid-template-columns: repeat(3, minmax(180px, 1fr)); gap: 16px; }
    .tkt-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 24px; align-items: center; }
    .tkt-btn-minw { min-width: 200px; }
    .tkt-btn-pdf { min-width: 160px; background: #059669; color: white; border-color: #059669; }
    .tkt-btn-back { min-width: 140px; }
    .tkt-form-inline { display: inline; }
    .tkt-btn-disposal { min-width: 200px; background-color: #dc2626; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
    .tkt-btn-disposal-tag { min-width: 180px; background-color: #4f46e5; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
    .tkt-body-pad { padding: 20px; }
    .tkt-summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .tkt-label { margin: 0 0 8px; font-weight: 700; color: #0f172a; }
    .tkt-value { margin: 0; color: #475569; }
    .tkt-supply-head { font-size: 12px; color: #64748b; }
    .tkt-body-nopad { padding: 0; }
    .tkt-empty-supply { padding: 32px; color: #64748b; text-align: center; }
    .tkt-supply-list { padding: 20px; background: #f8fafc; }
    .tkt-supply-card { background: white; border: 1px solid #cbd5e1; border-left: 4px solid #0f172a; border-radius: 6px; padding: 16px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .tkt-supply-card-left { display: flex; flex-direction: column; gap: 8px; }
    .tkt-supply-card-top { display: flex; align-items: center; gap: 12px; }
    .tkt-supply-id { font-weight: 800; font-size: 16px; color: #0f172a; }
    .tkt-supply-status { text-transform: uppercase; font-size: 11px; }
    .tkt-supply-time { font-size: 12px; color: #64748b; }
    .tkt-supply-desc { color: #475569; font-size: 14px; }
    .tkt-supply-requester { font-weight: 600; color: #334155; }
    .tkt-supply-more { color: #94a3b8; font-size: 12px; }
    .tkt-btn-supply { padding: 8px 16px; font-size: 13px; font-weight: 700; }
    .tkt-request-parts { margin-bottom: 22px; background: #f8fafc; border: 2px dashed #cbd5e1; text-align: center; padding: 30px; }
    .tkt-parts-icon { font-size: 32px; color: #94a3b8; margin-bottom: 12px; }
    .tkt-parts-title { margin: 0 0 8px; color: #0f172a; }
    .tkt-parts-text { margin: 0 0 20px; color: #475569; }
    .tkt-btn-create { padding: 12px 24px; font-size: 14px; display: inline-block; }
    .tkt-pending-banner { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 10px; padding: 18px; font-size: 14px; color: #92400e; margin-bottom: 22px; }
    .tkt-cmms-official { max-width: 1140px; }
</style>
@endsection

@section('content')
<div class="cmms-official tkt-cmms-official">

    <div class="cmms-official-hero tkt-hero">
        <div class="ref">National Conciliation and Mediation Board · CMMS</div>
        <h1>Job Order {{ $request->display_number ?? $request->request_number }}</h1>
        <p class="sub">{{ $request->type }} · {{ $request->status }} · {{ $request->created_at->format('M d, Y') }}</p>
    </div>

    <div class="cmms-meta-grid tkt-meta-grid">
        <div class="cmms-meta-card">
            <div class="label">Requestor</div>
            <div class="value">{{ $request->requestor_name ?? $request->user?->full_name ?? '—' }}</div>
        </div>
        <div class="cmms-meta-card">
            <div class="label">Office / Division</div>
            <div class="value">{{ $request->office ?? '—' }}</div>
        </div>
        <div class="cmms-meta-card">
            <div class="label">Assigned IT</div>
            <div class="value">{{ $request->assignedTo?->full_name ?? 'Unassigned' }}</div>
        </div>
        <div class="cmms-meta-card">
            <div class="label">Department</div>
            <div class="value">{{ $request->department ?? '—' }}</div>
        </div>
    </div>

    @if($request->linkedAsset)
    <div class="cmms-panel tkt-panel-mb">
        <div class="cmms-panel-head">
            <h2><i class="fa-solid fa-computer"></i> Linked Asset</h2>
        </div>
        <div class="cmms-panel-body tkt-panel-body-grid">
            <div>
                <div class="label">Asset Name</div>
                <div class="value">{{ $request->linkedAsset->item_name }}</div>
            </div>
            <div>
                <div class="label">Serial Number</div>
                <div class="value">{{ $request->linkedAsset->serial_number ?? 'N/A' }}</div>
            </div>
            <div>
                <div class="label">Current Asset Status</div>
                <div class="value">{{ $request->linkedAsset->status }}</div>
            </div>
        </div>
    </div>
    @endif

    <div class="tkt-actions">
        {{-- Primary action: Open ICT Repair Form --}}
        @if($canOpenIctForm)
        <a href="{{ route($canEditIctForm ? 'ict.edit' : 'ict.show', $request->id) }}" class="cmms-btn-primary tkt-btn-minw">
            <i class="fa-solid fa-file-lines"></i> Open ICT Repair Form
        </a>
        @endif

        {{-- Download PDF: only when request is fully Completed --}}
        @if($request->status === 'Completed')
        <a href="{{ route('ict.pdf', $request->id) }}" target="_blank" class="cmms-btn-secondary tkt-btn-pdf">
            <i class="fa-solid fa-file-pdf"></i> Download PDF
        </a>
        @endif

        {{-- Back to list --}}
        <a href="{{ Auth::user()->canProcessSupply() ? route('requisitions.index') : route('ict.index') }}" class="cmms-btn-secondary tkt-btn-back">
            <i class="fa-solid fa-arrow-left"></i> Back to List
        </a>

        {{-- Disposal actions: IT and Super Admin only --}}
        @if(in_array(Auth::user()->role, ['it', 'super_admin']) && $request->linkedAsset && $request->linkedAsset->status !== 'For Disposal' && !in_array($request->linkedAsset->status, ['Scrapped', 'Disposed', 'Pending']))
        <form id="disposalForm" action="{{ route('ict.recommend-disposal', $request->id) }}" method="POST" class="tkt-form-inline">
            @csrf
            <button type="submit" class="tkt-btn-disposal">
                <i class="fa-solid fa-trash-can"></i> Recommend For Disposal
            </button>
        </form>
        @elseif(in_array(Auth::user()->role, ['it', 'super_admin']) && $request->linkedAsset && $request->linkedAsset->status === 'For Disposal')
        <a href="{{ route('ict.disposal-tag', $request->id) }}" target="_blank" class="tkt-btn-disposal-tag">
            <i class="fa-solid fa-tag"></i> Print Disposal Tag
        </a>
        @endif
    </div>

    <div class="cmms-panel tkt-panel-mb">
        <div class="cmms-panel-head">
            <h2><i class="fa-solid fa-boxes-stacked"></i> Job Order Summary</h2>
        </div>
        <div class="cmms-panel-body tkt-body-pad">
            <div class="tkt-summary-grid">
                <div>
                    <p class="tkt-label">Description</p>
                    <p class="tkt-value">{{ $request->description ?: 'No description available.' }}</p>
                </div>
                <div>
                    <p class="tkt-label">Remarks</p>
                    <p class="tkt-value">{{ $request->remarks ?: '---' }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="cmms-panel tkt-panel-mb">
        <div class="cmms-panel-head">
            <h2><i class="fa-solid fa-box"></i> Supply Request History</h2>
            <span class="tkt-supply-head">All supply requisitions linked to this ticket.</span>
        </div>
        <div class="cmms-panel-body tkt-body-nopad">
            @if($requisitions->isEmpty())
                <div class="tkt-empty-supply">Walang parts request para sa ticket na ito.</div>
            @else
                <div class="tkt-supply-list">
                    @foreach($requisitions as $req)
                    <div class="tkt-supply-card">
                        <div class="tkt-supply-card-left">
                            <div class="tkt-supply-card-top">
                                <span class="tkt-supply-id">PR-{{ str_pad($req->id, 5, '0', STR_PAD_LEFT) }}</span>
                                <span class="cmms-status-badge cmms-status-{{ strtolower($req->status) }} tkt-supply-status">{{ $req->status }}</span>
                                <span class="tkt-supply-time"><i class="fa-regular fa-clock"></i> {{ $req->created_at->format('M d, Y H:i') }}</span>
                            </div>
                            <div class="tkt-supply-desc">
                                <span class="tkt-supply-requester">Requested by:</span> {{ $req->requester?->full_name }}
                            </div>
                            <div class="tkt-supply-desc">
                                @php $lines = []; @endphp
                                @foreach(array_slice($req->items ?? [], 0, 3) as $line)
                                    @php $lines[] = ($line['quantity'] ?? 1) . 'x ' . ($line['description'] ?? ''); @endphp
                                @endforeach
                                {{ implode(', ', $lines) }}
                                @if(count($req->items ?? []) > 3)
                                    <span class="tkt-supply-more">+ {{ count($req->items ?? []) - 3 }} more</span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <a href="{{ route('requisitions.show', $req->id) }}" class="cmms-btn-secondary tkt-btn-supply">
                                {{ Auth::user()->canProcessSupply() ? 'Process' : 'View Ticket' }}
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if(!empty($canRequestPartsOnTicket))
    <div class="cmms-panel tkt-request-parts">
        <i class="fa-solid fa-boxes-stacked tkt-parts-icon"></i>
        <h3 class="tkt-parts-title">Need parts for this repair?</h3>
        <p class="tkt-parts-text">Use the official Material Requisition Ticket to request parts from the Supply Office.</p>
        <a href="{{ route('requisitions.create', $request->id) }}" class="cmms-btn-primary tkt-btn-create">
            <i class="fa-solid fa-ticket"></i> Create Requisition Ticket
        </a>
    </div>
    @elseif(Auth::user()->role === 'it')
    <div class="tkt-pending-banner">
        <i class="fa-solid fa-clock"></i> May pending request na sa Supply para sa ticket na ito. Buksan ang <strong>View Ticket</strong> sa history sa itaas.
    </div>
    @endif
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
document.addEventListener('DOMContentLoaded', function() {
    var f = document.getElementById('disposalForm');
    if (f) {
        f.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to recommend this asset for disposal? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    }
});
</script>
@endsection
