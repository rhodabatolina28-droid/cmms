@extends('layouts.app')

@section('title', 'Asset Detail | ' . $asset->item_name)
@section('page-title', 'Asset Profile')

@section('styles')
<style nonce="{{ $cspNonce }}">
/* ===== LAYOUT ===== */
.detail-wrapper { max-width: 100%; margin: 0; padding: 0; }
.back-link { color: #0038A8; font-size: 14px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: all 0.2s; padding: 8px 12px; border-radius: 6px; margin-top: 8px; }
.back-link:hover { background: #f1f5f9; transform: translateX(-2px); }

/* ===== HERO BANNER ===== */
.hero-banner { background: linear-gradient(135deg, #0038A8 0%, #003f87 100%); color: white; border-radius: 12px; padding: 24px; margin-bottom: 16px; margin-top: 12px; box-shadow: 0 4px 6px -1px rgba(0, 56, 168, 0.2); }
.hero-label { font-size: 10px; color: rgba(255,255,255,0.7); font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
.hero-title { margin: 0; font-size: 24px; font-weight: 800; color: white; line-height: 1.3; }
.hero-subtitle { font-size: 15px; color: rgba(255,255,255,0.85); margin-top: 4px; }
.hero-badges { margin-top: 10px; display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.hero-badge { font-size: 10px; color: rgba(255,255,255,0.9); background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 6px; font-weight: 600; backdrop-filter: blur(4px); }
.hero-badge-mono { font-size: 10px; color: rgba(255,255,255,0.9); background: rgba(255,255,255,0.15); padding: 4px 8px; border-radius: 6px; font-family: monospace; font-weight: 600; backdrop-filter: blur(4px); }
.hero-actions { margin-top: 14px; display: flex; gap: 6px; flex-wrap: wrap; }
.hero-btn { background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); padding: 10px 16px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; backdrop-filter: blur(4px); min-height: 44px; }
.hero-btn:hover { background: rgba(255,255,255,0.3); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.hero-btn:active { background: rgba(255,255,255,0.4); transform: translateY(0); }
.hero-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-danger { background: #ef4444; color: white; border: none; padding: 10px 16px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; min-height: 44px; }
.btn-danger:hover { background: #dc2626; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }
.btn-danger:active { background: #b91c1c; transform: translateY(0); }
.btn-danger:disabled { opacity: 0.5; cursor: not-allowed; }
.locked-badge { background: rgba(0,0,0,0.3); color: rgba(255,255,255,0.8); padding: 10px 16px; border-radius: 8px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 8px; backdrop-filter: blur(4px); min-height: 44px; }

/* ===== STATS GRID ===== */
.stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
.stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; text-align: center; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0, 56, 168, 0.1); border-color: #0038A8; }
.stat-label { font-size: 13px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; }
.stat-value { font-size: 22px; font-weight: 800; color: #1e293b; }
.stat-value-primary { font-size: 22px; font-weight: 800; color: #0038A8; }
.stat-value-muted { font-size: 22px; font-weight: 800; color: #94a3b8; }
.stat-meta { font-size: 13px; color: #94a3b8; margin-top: 6px; }

/* ===== MAIN CONTENT GRID ===== */
.main-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.main-grid-full { grid-column: 1 / -1; }

/* ===== DETAIL CARDS ===== */
.detail-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06); transition: all 0.2s; }
.detail-card:hover { box-shadow: 0 4px 6px rgba(0,0,0,0.08); border-color: #cbd5e1; }
.detail-card-header { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 16px 24px; border-bottom: 1px solid #e2e8f0; font-weight: 800; font-size: 14px; color: #1e293b; text-transform: uppercase; letter-spacing: 0.06em; display: flex; align-items: center; gap: 10px; }
.detail-card-body { padding: 24px; }
.field-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid #f1f5f9; font-size: 15px; }
.field-row:last-child { border-bottom: none; }
.field-label { color: #64748b; font-weight: 600; font-size: 14px; }
.field-value { color: #1e293b; font-weight: 700; text-align: right; max-width: 60%; font-size: 15px; }

/* ===== STATUS PILLS ===== */
.status-pill { padding: 4px 12px; border-radius: 99px; font-size: 11px; font-weight: 800; display: inline-block; }
.sp-active { background: #dcfce7; color: #166534; }
.sp-spare { background: #dbeafe; color: #1e40af; }
.sp-defective { background: #fee2e2; color: #991b1b; }
.sp-repair { background: #ffedd5; color: #9a3412; }
.sp-maintenance { background: #e0e7ff; color: #3730a3; }

/* ===== WARRANTY BADGES ===== */
.warranty-badge { font-size: 12px; font-weight: 800; padding: 4px 10px; border-radius: 6px; display: inline-block; }
.warranty-expired { color: #ef4444; background: #fee2e2; }
.warranty-expiring-soon { color: #f59e0b; background: #fef3c7; }
.warranty-valid { color: #10b981; background: #dcfce7; }

/* ===== TIMELINE ===== */
.timeline-item { display: flex; gap: 12px; margin-bottom: 14px; }
.timeline-dot { width: 8px; height: 8px; border-radius: 50%; background: #0038A8; margin-top: 5px; flex-shrink: 0; box-shadow: 0 0 0 2px rgba(0, 56, 168, 0.15); }
.timeline-dot.green { background: #10b981; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.15); }
.timeline-dot.red { background: #ef4444; box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.15); }
.timeline-dot.yellow { background: #f59e0b; box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.15); }
.timeline-content { flex: 1; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }
.timeline-content:last-child { border-bottom: none; }
.tl-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 4px; }
.tl-req-num { font-weight: 800; font-size: 13px; color: #1e293b; }
.tl-type { font-size: 10px; background: #f1f5f9; color: #475569; padding: 3px 8px; border-radius: 99px; font-weight: 700; }
.tl-date { font-size: 11px; color: #94a3b8; }
.tl-meta { font-size: 12px; color: #64748b; margin-top: 4px; }
.tl-footer { margin-top: 8px; }
.tl-link { font-size: 11px; color: #0038A8; font-weight: 700; text-decoration: none; }
.tl-link:hover { text-decoration: underline; }

/* ===== TRANSFER LOG ===== */
.tr-dot { background: #8b5cf6; box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.15); }
.tr-header { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 4px; }
.tr-action { font-weight: 800; font-size: 13px; color: #1e293b; }
.tr-meta { font-size: 12px; color: #64748b; margin-top: 4px; }
.tr-user-old { color: #64748b; }
.tr-arrow { color: #8b5cf6; margin: 0 4px; }
.tr-user-new { font-weight: 700; color: #1e293b; }
.tr-status { font-size: 12px; margin-top: 4px; color: #64748b; }
.tr-remarks { font-size: 11px; color: #94a3b8; margin-top: 4px; font-style: italic; }
.tr-date { font-size: 11px; color: #94a3b8; }

/* ===== ATTACHMENTS ===== */
.attach-item { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
.attach-item:last-child { border-bottom: none; }
.attach-icon { width: 36px; height: 36px; background: #eff6ff; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #0038A8; font-size: 16px; flex-shrink: 0; }
.attach-info { flex: 1; }
.attach-name { font-weight: 700; font-size: 13px; color: #1e293b; }
.attach-meta { font-size: 11px; color: #94a3b8; margin-top: 3px; }
.attach-actions { display: flex; gap: 6px; }
.btn-view { padding: 8px 14px; background: #eff6ff; color: #0038A8; border-radius: 6px; font-size: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; min-height: 40px; }
.btn-view:hover { background: #dbeafe; }
.btn-view:active { background: #bfdbfe; }
.btn-del-attach { padding: 8px 12px; background: #fee2e2; color: #991b1b; border: none; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; min-height: 40px; }
.btn-del-attach:hover { background: #fecaca; }
.btn-del-attach:active { background: #fca5a5; }
.btn-del-attach:disabled { opacity: 0.5; cursor: not-allowed; }

/* ===== MODALS ===== */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.modal-box { background: #fff; border-radius: 12px; padding: 24px; width: 100%; max-width: 460px; margin: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
.modal-title { font-weight: 800; font-size: 16px; color: #1e293b; margin-bottom: 16px; }
.modal-row { margin-bottom: 14px; }
.modal-row-last { margin-bottom: 18px; }
.modal-label { font-size: 12px; font-weight: 700; color: #64748b; display: block; margin-bottom: 6px; }
.modal-input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; box-sizing: border-box; transition: all 0.2s; }
.modal-input:focus { border-color: #0038A8; box-shadow: 0 0 0 3px rgba(0, 56, 168, 0.1); outline: none; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
.btn-modal-cancel { padding: 10px 18px; background: #f1f5f9; color: #475569; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; min-height: 44px; }
.btn-modal-cancel:hover { background: #e2e8f0; }
.btn-modal-cancel:active { background: #cbd5e1; }
.btn-modal-primary { padding: 10px 18px; background: #0038A8; color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; min-height: 44px; }
.btn-modal-primary:hover { background: #002d8c; }
.btn-modal-primary:active { background: #001f5c; }
.btn-modal-primary:disabled { opacity: 0.5; cursor: not-allowed; }

/* ===== UPLOAD ZONE ===== */
.upload-zone { border: 2px dashed #cbd5e1; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s; background: #f9fafb; }
.upload-zone:hover { border-color: #0038A8; background: #eff6ff; }
.upload-icon { font-size: 20px; color: #94a3b8; display: block; margin-bottom: 4px; }
.upload-text { font-size: 12px; color: #64748b; }
.upload-hint { font-size: 10px; color: #94a3b8; margin-top: 2px; }
.upload-filename { margin-top: 6px; font-size: 12px; font-weight: 700; color: #0038A8; }

/* ===== SCRAP MODAL ===== */
.scrap-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(2px); }
.scrap-box { background: #fff; border-radius: 12px; width: 100%; max-width: 480px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.25); }
.scrap-header { background: #7f1d1d; color: white; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; }
.scrap-header-title { font-weight: 800; font-size: 15px; }
.scrap-close-btn { background: none; border: none; color: white; cursor: pointer; font-size: 16px; }
.scrap-body { padding: 20px; }
.scrap-warning { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; font-size: 12px; color: #991b1b; }
.scrap-info { margin-bottom: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; }
.scrap-info-label { font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 4px; }
.scrap-asset-name { font-weight: 700; color: #1e293b; font-size: 13px; }
.scrap-asset-details { font-size: 11px; color: #64748b; font-family: monospace; }
.scrap-field { margin-bottom: 16px; }
.scrap-textarea { width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 12px; resize: vertical; box-sizing: border-box; }
.scrap-actions { display: flex; gap: 8px; justify-content: flex-end; }
.btn-scrap-cancel { background: #f1f5f9; color: #475569; border: none; padding: 10px 16px; border-radius: 8px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; min-height: 44px; }
.btn-scrap-cancel:hover { background: #e2e8f0; }
.btn-scrap-cancel:active { background: #cbd5e1; }
.btn-scrap-confirm { background: #7f1d1d; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; min-height: 44px; }
.btn-scrap-confirm:hover { background: #991b1b; }
.btn-scrap-confirm:active { background: #450a0a; }
.btn-scrap-confirm:disabled { opacity: 0.5; cursor: not-allowed; }

/* ===== UTILITY ===== */
.d-none { display: none; }
.font-mono { font-family: monospace; }
.text-specs { font-size: 12px; color: #475569; }
.text-notes { font-size: 12px; color: #475569; line-height: 1.5; margin: 0; }
.text-empty { color: #94a3b8; font-size: 12px; text-align: center; padding: 16px 0; }
.card-mt20 { margin-top: 16px; }
.qr-body { text-align: center; padding: 20px; }
.qr-frame { display: inline-block; background: white; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; }
.qr-id { margin-top: 6px; font-size: 11px; font-weight: 700; color: #64748b; font-family: monospace; }
.btn-print { background: #0038A8; color: white; border: none; padding: 8px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; min-height: 40px; }
.btn-print:hover { background: #002d8c; }
.btn-print:active { background: #001f5c; }
.icon-warning { color: #f59e0b; }
.icon-purple { color: #8b5cf6; }
.icon-sky { color: #0ea5e9; }
.ml-6 { margin-left: 6px; }
.ml-4 { margin-left: 4px; }
.mb-18 { margin-bottom: 16px; }

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr) !important; }
    .main-grid { grid-template-columns: 1fr !important; }
}
@media (max-width: 768px) {
    .detail-wrapper { padding: 16px !important; }
    .hero-banner { padding: 24px !important; }
    .hero-title { font-size: 24px !important; }
    .hero-actions { width: 100%; flex-direction: column; }
    .hero-btn, .btn-danger, .locked-badge { width: 100%; justify-content: center; padding: 12px 16px !important; font-size: 13px !important; }
    .stats-grid { grid-template-columns: 1fr 1fr !important; gap: 12px !important; }
    .stat-card { padding: 14px !important; }
    .stat-label { font-size: 11px !important; }
    .stat-value { font-size: 16px !important; }
    .field-row { flex-direction: column !important; align-items: flex-start !important; gap: 4px !important; padding: 10px 0 !important; }
    .field-value { text-align: left !important; max-width: 100% !important; }
    .detail-card { margin-bottom: 14px !important; }
    .detail-card-header { padding: 12px 16px !important; font-size: 11px !important; }
    .detail-card-body { padding: 16px !important; }
    .timeline-item { gap: 10px !important; }
    .tl-header { flex-direction: column !important; gap: 4px !important; }
    .tl-meta { font-size: 11px !important; }
    .attach-item { flex-direction: column !important; align-items: flex-start !important; gap: 10px !important; }
    .attach-actions { width: 100%; }
    .btn-view, .btn-del-attach { flex: 1; text-align: center; padding: 10px !important; }
    .back-link { font-size: 13px !important; padding: 10px 14px !important; background: #f8fafc !important; border: 1px solid #e2e8f0 !important; border-radius: 6px !important; min-height: 44px !important; }
}
@media (max-width: 480px) {
    .detail-wrapper { padding: 8px !important; }
    .hero-banner { padding: 14px !important; border-radius: 8px !important; }
    .hero-title { font-size: 16px !important; }
    .hero-badges { gap: 4px !important; }
    .hero-badge, .hero-badge-mono { font-size: 9px !important; padding: 3px 6px !important; }
    .stats-grid { grid-template-columns: 1fr !important; gap: 6px !important; }
    .stat-card { padding: 8px !important; }
    .stat-label { font-size: 8px !important; }
    .stat-value { font-size: 12px !important; }
    .detail-card { border-radius: 8px !important; }
    .detail-card-header { padding: 8px 10px !important; font-size: 9px !important; }
    .detail-card-body { padding: 10px !important; }
    .field-row { padding: 6px 0 !important; }
    .field-label { font-size: 9px !important; }
    .field-value { font-size: 10px !important; }
}
</style>
@endsection

@section('content')
<div class="detail-wrapper">

    {{-- Back button --}}
    <div class="mb-18">
        @if(!empty($isSuperAdminView))
            <a href="{{ route('super_admin.inventory') }}" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Asset Registry
            </a>
        @else
            <a href="{{ route('inventory.index') }}" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to Inventory
            </a>
        @endif
    </div>

    {{-- Asset header --}}
    <div class="hero-banner">
        <div>
            <div class="hero-label">{{ $asset->category }}</div>
            <h2 class="hero-title">{{ $asset->item_name }}</h2>
            @if($asset->brand || $asset->model)
                <div class="hero-subtitle">{{ trim(($asset->brand ?? '') . ' ' . ($asset->model ?? '')) }}</div>
            @endif
            <div class="hero-badges">
                @php
                    $sc = match($asset->status) {
                        'Active' => 'sp-active', 'Spare' => 'sp-spare',
                        'Defective', 'Scrapped', 'Disposed', 'For Disposal' => 'sp-defective', 
                        'Under Maintenance' => 'sp-maintenance',
                        default => 'sp-repair' 
                    };
                @endphp
                <span class="status-pill {{ $sc }}">{{ $asset->status }}</span>
                @if($asset->serial_number)
                    <span class="hero-badge-mono">SN: {{ $asset->serial_number }}</span>
                @endif
                @if($asset->property_number)
                    <span class="hero-badge">Prop#: {{ $asset->property_number }}</span>
                @endif
                @if($asset->par_number)
                    <span class="hero-badge-mono">PAR: {{ $asset->par_number }}</span>
                @endif
            </div>
            <div class="hero-actions">
                @if(Auth::user()->canProcessSupply())
                    <button id="showUploadModalBtn" class="hero-btn">
                        <i class="fa-solid fa-paperclip"></i> <span>Add Document</span>
                    </button>
                    
                    @if($asset->status === 'For Disposal')
                        <button id="showScrappedModalBtn" class="btn-danger">
                            <i class="fa-solid fa-circle-check"></i> <span>Confirm Disposal</span>
                        </button>
                    @elseif($asset->status === 'Scrapped')
                        <span class="locked-badge">
                            <i class="fa-solid fa-lock"></i> <span>Record Locked</span>
                        </span>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Quick Stats Bar --}}
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Custodian</div>
            <div class="stat-value-primary">{{ $asset->assignedUser?->full_name ?? 'Unassigned' }}</div>
            @if($asset->assignedUser)
                <div class="stat-meta">{{ $asset->assignedUser->office ?? 'N/A' }}</div>
            @endif
        </div>

        <div class="stat-card">
            <div class="stat-label">Warranty Status</div>
            @php
                $warrantyStatus = $asset->warranty_status;
                $warrantyClass = match($warrantyStatus) { 'Expired' => 'warranty-expired', 'Expiring Soon' => 'warranty-expiring-soon', default => 'warranty-valid' };
            @endphp
            <div class="warranty-badge {{ $warrantyClass }}">
                {{ $warrantyStatus }}
            </div>
            @if($asset->warranty_expiration)
                <div class="stat-meta">{{ $asset->warranty_expiration->format('M d, Y') }}</div>
            @endif
        </div>

        <div class="stat-card">
            <div class="stat-label">Asset Age</div>
            @if($asset->date_acquired)
                <div class="stat-value">{{ $asset->date_acquired->diffForHumans(null, true) }}</div>
                <div class="stat-meta">Acquired: {{ $asset->date_acquired->format('M d, Y') }}</div>
            @else
                <div class="stat-value-muted">N/A</div>
            @endif
        </div>

        <div class="stat-card">
            <div class="stat-label">Acquisition Cost</div>
            <div class="stat-value">
                {{ $asset->acquisition_cost ? 'â‚± ' . number_format($asset->acquisition_cost, 2) : 'N/A' }}
            </div>
            @if($asset->total_maintenance_cost && $asset->total_maintenance_cost > 0)
                <div class="stat-meta">Maintenance: â‚±{{ number_format($asset->total_maintenance_cost, 2) }}</div>
            @endif
        </div>
    </div>

    <div class="main-grid">
        {{-- Asset Info Card --}}
        <div class="detail-card">
            <div class="detail-card-header"><i class="fa-solid fa-microchip"></i> Asset Information</div>
            <div class="detail-card-body">
                <div class="field-row"><span class="field-label">Category</span><span class="field-value">{{ $asset->category }}</span></div>
                <div class="field-row"><span class="field-label">Item Name</span><span class="field-value">{{ $asset->item_name }}</span></div>
                <div class="field-row"><span class="field-label">Brand / Model</span><span class="field-value">{{ trim(($asset->brand ?? '') . ' / ' . ($asset->model ?? '')) ?: 'N/A' }}</span></div>
                <div class="field-row"><span class="field-label">Serial Number</span><span class="field-value" class="font-mono">{{ $asset->serial_number ?: 'N/A' }}</span></div>
                <div class="field-row"><span class="field-label">PAR Number</span><span class="field-value" class="font-mono">{{ $asset->par_number ?: 'N/A' }}</span></div>
                <div class="field-row"><span class="field-label">Property Number</span><span class="field-value">{{ $asset->property_number ?: 'N/A' }}</span></div>
                <div class="field-row"><span class="field-label">Current Status</span><span class="field-value"><span class="status-pill {{ $sc }}">{{ $asset->status }}</span></span></div>
                <div class="field-row"><span class="field-label">Assigned To</span><span class="field-value">{{ $asset->assignedUser?->full_name ?? 'Unassigned (Stock)' }}</span></div>
                <div class="field-row"><span class="field-label">Division / Office</span><span class="field-value">{{ $asset->office ?: 'N/A' }}</span></div>
                <div class="field-row"><span class="field-label">Branch</span><span class="field-value">{{ $asset->branch ?: 'N/A' }}</span></div>
            </div>
        </div>

        {{-- Lifecycle / Financial Card --}}
        <div class="detail-card">
            <div class="detail-card-header"><i class="fa-solid fa-calendar-days"></i> Lifecycle & Financial</div>
            <div class="detail-card-body">
                <div class="field-row">
                    <span class="field-label">Date Acquired</span>
                    <span class="field-value">{{ $asset->date_acquired ? $asset->date_acquired->format('M d, Y') : 'N/A' }}</span>
                </div>
                <div class="field-row">
                    <span class="field-label">Acquisition Cost</span>
                    <span class="field-value">{{ $asset->acquisition_cost ? 'â‚± ' . number_format($asset->acquisition_cost, 2) : 'N/A' }}</span>
                </div>
                <div class="field-row">
                    <span class="field-label">Warranty Expiration</span>
                    <span class="field-value">
                        @if($asset->warranty_expiration)
                            {{ $asset->warranty_expiration->format('M d, Y') }}
                            @php $ws = $asset->warranty_status; @endphp
                            @if($ws === 'Expired')
                                <span class="tag-danger"> EXPIRED</span>
                            @elseif($ws === 'Expiring Soon')
                                <span class="tag-warning"> EXPIRING SOON</span>
                            @else
                                <span class="tag-success"> VALID</span>
                            @endif
                        @else
                            N/A
                        @endif
                    </span>
                </div>
                <div class="field-row">
                    <span class="field-label">End of Useful Life</span>
                    <span class="field-value">
                        @if($asset->end_of_useful_life)
                            {{ $asset->end_of_useful_life->format('M d, Y') }}
                            @if($asset->end_of_useful_life->isPast())
                                <span class="tag-danger"> DUE FOR DISPOSAL</span>
                            @endif
                        @else
                            {{ $asset->is_depreciated ? 'Estimated expired (5yr rule)' : 'N/A' }}
                        @endif
                    </span>
                </div>
                <div class="field-row">
                    <span class="field-label">Age</span>
                    <span class="field-value">
                        @if($asset->date_acquired)
                            {{ $asset->date_acquired->diffForHumans(null, true) }}
                        @else N/A @endif
                    </span>
                </div>
                <div class="field-row"><span class="field-label">Date Added to System</span><span class="field-value">{{ $asset->created_at ? $asset->created_at->format('M d, Y') : 'N/A' }}</span></div>
            </div>
        </div>

        {{-- Specifications Card --}}
        @if($asset->specifications)
        <div class="detail-card">
            <div class="detail-card-header"><i class="fa-solid fa-list-check"></i> Technical Specifications</div>
            <div class="detail-card-body">
                @php
                    $specs = is_array($asset->specifications) ? $asset->specifications : json_decode($asset->specifications, true);
                    $labelMap = ['cpu'=>'Processor (CPU)','ram'=>'Memory (RAM)','hd1'=>'Storage 1','hd2'=>'Storage 2','os'=>'Operating System','office'=>'Office Suite','gpu'=>'Graphics Card','monitor_brand'=>'Brand','monitor_model'=>'Model','monitor_size'=>'Screen Size','monitor_resolution'=>'Resolution'];
                @endphp
                @if(is_array($specs))
                    @foreach($specs as $key => $val)
                        @if($val && $val !== 'None' && $val !== 'Integrated Graphics')
                        <div class="field-row">
                            <span class="field-label">{{ $labelMap[$key] ?? ucwords(str_replace('_',' ',$key)) }}</span>
                            <span class="field-value">{{ is_array($val) ? json_encode($val) : $val }}</span>
                        </div>
                        @endif
                    @endforeach
                @else
                    <p class="text-specs">{{ $asset->specifications }}</p>
                @endif
            </div>
        </div>
        @endif

        {{-- Set Components Card \u2014 only when this asset belongs to a Complete Set --}}
        @if($asset->components->isNotEmpty() || $asset->parentAsset)
        @php
            $detailRouteName = isset($isSuperAdminView) && $isSuperAdminView
                ? 'super_admin.inventory.detail'
                : 'inventory.detail';
        @endphp
        <div class="detail-card">
            <div class="detail-card-header"><i class="fa-solid fa-layer-group"></i> Set Components</div>
            <div class="detail-card-body">
                @if($asset->components->isNotEmpty())
                    {{-- Parent view: list the components that share this asset's PAR. --}}
                    <p style="margin: 0 0 10px 0; font-size: 12px; color: #64748b;">Parent of a Complete Set \u2014 the following components share this asset's PAR number:</p>
                    @foreach($asset->components as $component)
                    <div class="field-row">
                        <span class="field-label">{{ $component->category }}</span>
                        <span class="field-value">
                            <a href="{{ route($detailRouteName, $component->asset_id) }}" style="color: #0038A8; text-decoration: none; font-weight: 600;">{{ $component->item_name }}</a>
                            @if($component->serial_number)
                                <span class="font-mono" style="margin-left: 8px; font-size: 11px; color: #64748b;">SN: {{ $component->serial_number }}</span>
                            @else
                                <span style="margin-left: 8px; font-size: 11px; color: #d97706;">SN: pending verification</span>
                            @endif
                            <span style="margin-left: 8px; font-size: 11px; color: #64748b;">{{ $component->property_number }}</span>
                        </span>
                    </div>
                    @endforeach
                @endif

                @if($asset->parentAsset)
                    {{-- Component view: show parent and any sibling components. --}}
                    <p style="margin: 0 0 10px 0; font-size: 12px; color: #64748b;">This asset is a component of a Complete Set:</p>
                    <div class="field-row">
                        <span class="field-label">Parent ({{ $asset->parentAsset->category }})</span>
                        <span class="field-value">
                            <a href="{{ route($detailRouteName, $asset->parentAsset->asset_id) }}" style="color: #0038A8; text-decoration: none; font-weight: 600;">{{ $asset->parentAsset->item_name }}</a>
                            @if($asset->parentAsset->serial_number)
                                <span class="font-mono" style="margin-left: 8px; font-size: 11px; color: #64748b;">SN: {{ $asset->parentAsset->serial_number }}</span>
                            @endif
                        </span>
                    </div>
                    @foreach($asset->parentAsset->components as $sibling)
                        @if($sibling->asset_id !== $asset->asset_id)
                        <div class="field-row">
                            <span class="field-label">{{ $sibling->category }}</span>
                            <span class="field-value">
                                <a href="{{ route($detailRouteName, $sibling->asset_id) }}" style="color: #0038A8; text-decoration: none; font-weight: 600;">{{ $sibling->item_name }}</a>
                                @if($sibling->serial_number)
                                    <span class="font-mono" style="margin-left: 8px; font-size: 11px; color: #64748b;">SN: {{ $sibling->serial_number }}</span>
                                @else
                                    <span style="margin-left: 8px; font-size: 11px; color: #d97706;">SN: pending verification</span>
                                @endif
                            </span>
                        </div>
                        @endif
                    @endforeach
                @endif
            </div>
        </div>
        @endif

        {{-- Notes Card --}}
        @if($asset->asset_notes)
        <div class="detail-card">
            <div class="detail-card-header"><i class="fa-solid fa-note-sticky"></i> Notes</div>
            <div class="detail-card-body">
                <p class="text-notes">{{ $asset->asset_notes }}</p>
            </div>
        </div>
        @endif

        {{-- QR Code Card --}}
        @if($asset->qr_code)
        <div class="detail-card">
            <div class="detail-card-header"><i class="fa-solid fa-qrcode"></i> QR Code</div>
            <div class="detail-card-body qr-body">
                <div class="qr-frame">
                    {!! $asset->qr_code !!}
                </div>
                <div class="qr-id">
                    ID:{{ $asset->asset_id }}
                </div>
                @if(Auth::user()->canProcessSupply() && empty($isSuperAdminView))
                <div style="margin-top: 10px;">
                    <button id="printStickerBtn" data-id="{{ $asset->asset_id }}" class="btn-print">
                        <i class="fa-solid fa-print"></i> Print Sticker
                    </button>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- Repair / Maintenance History --}}
    <div class="detail-card card-mt20">
        <div class="detail-card-header"><i class="fa-solid fa-screwdriver-wrench icon-warning"></i> Repair & Maintenance History ({{ $repairHistory->count() }} records)</div>
        <div class="detail-card-body">
            @if($repairHistory->isEmpty())
                <p class="text-empty">No repair or maintenance records linked to this asset.</p>
            @else
                <div class="downtime-summary mb-4">
                    <strong>Total Downtime: {{ $asset->formatted_downtime }}</strong>
                </div>
                @foreach($repairHistory as $req)
                @php
                    $detail = $req->type === 'Preventive Maintenance' ? $req->maintenanceRequest : $req->repairRequest;
                    $statusClass = match($req->status) { 'Completed' => 'status-completed', 'Rejected','Cancelled' => 'status-rejected', default => 'status-pending' };
                    $dotClass = match($req->status) { 'Completed' => 'green', 'Rejected','Cancelled' => 'red', default => 'yellow' };
                    
                    if ($req->type === 'Preventive Maintenance') {
                        $problem = $detail?->problem_description ?? null;
                        $diagnosis = $detail?->diagnosis ?? null;
                        $action = null; 
                    } else {
                        $problem = $detail?->repair_description ?? $detail?->problem_description ?? null;
                        $action = $detail?->it_remarks ?? $detail?->action_taken ?? null;
                        $diagnosis = $detail?->initial_diagnosis ?? null;
                    }
                @endphp
                <div class="timeline-item">
                    <div class="timeline-dot {{ $dotClass }}"></div>
                    <div class="timeline-content">
                        <div class="tl-header">
                            <div>
                                <span class="tl-req-num">{{ $req->display_number ?? $req->request_number }}</span>
                                <span class="tl-type ml-6">{{ $req->type }}</span>
                                <span class="status-badge {{ $statusClass }}">{{ $req->status }}</span>
                            </div>
                            <span class="tl-date">{{ $req->created_at->format('M d, Y') }}</span>
                        </div>
                        <div class="tl-meta">
                            Requested by: <strong>{{ $req->user?->full_name ?? 'Unknown' }}</strong>
                            @if($req->assignedTo) Â· Handled by: <strong>{{ $req->assignedTo->full_name }}</strong>@endif
                        </div>
                        @if($req->formatted_downtime_duration !== 'N/A')
                        <div class="downtime-badge">
                            Downtime: {{ $req->formatted_downtime_duration }}
                            @if($req->downtime_start)
                                ({{ \Carbon\Carbon::parse($req->downtime_start)->format('M d, Y g:i A') }}
                                @if($req->downtime_end) - {{ \Carbon\Carbon::parse($req->downtime_end)->format('M d, Y g:i A')}}@else - Ongoing @endif)
                            @endif
                        </div>
                        @endif
                        @if($problem)
                            <div class="repair-problem"><i class="fa-solid fa-triangle-exclamation"></i> <strong>Problem:</strong> {{ $problem }}</div>
                        @endif
                        @if($diagnosis)
                            <div class="repair-action repair-diagnosis"><i class="fa-solid fa-magnifying-glass"></i> <strong>Diagnosis:</strong> {{ $diagnosis }}</div>
                        @endif
                        @if($action)
                            <div class="repair-action"><i class="fa-solid fa-check"></i> <strong>Action Taken:</strong> {{ $action }}</div>
                        @endif
                        <div class="tl-footer">
                                @php
                                    if ($req->type === 'ICT') {
                                        $reportRoute = route('ict.edit', $req->id);
                                    } elseif (Auth::user()->canProcessSupply()) {
                                        $reportRoute = route('maintenance.show', $req->id) . '?from_asset=' . $asset->asset_id;
                                    } else {
                                        $reportRoute = route('maintenance.edit', $req->id) . '?from_asset=' . $asset->asset_id;
                                    }
                                @endphp
                                <a href="{{ $reportRoute }}" class="tl-link">
                                    View Full Report <i class="fa-solid fa-arrow-right"></i>
                                </a>
                        </div>
                    </div>
                </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- Transfer / Custody History --}}
    <div class="detail-card card-mt20">
        <div class="detail-card-header"><i class="fa-solid fa-arrows-rotate icon-purple"></i> Custody & Transfer Log</div>
        <div class="detail-card-body">
            @if($transferHistory->isEmpty())
                <p class="text-empty">No transfer records found.</p>
            @else
                @foreach($transferHistory as $h)
                <div class="timeline-item">
                    <div class="timeline-dot tr-dot"></div>
                    <div class="timeline-content">
                        <div class="tr-header">
                            <div>
                                <span class="tl-req-num">{{ $h->action }}</span>
                            </div>
                            <span class="tl-date">{{ $h->created_at->format('M d, Y h:i A') }}</span>
                        </div>
                        <div class="tr-meta">
                            By: <strong>{{ $h->performedByUser?->full_name ?? 'System' }}</strong>
                        </div>
                        @if($h->previous_user_id !== $h->new_user_id)
                        <div class="tr-meta">
                            <span class="tr-user-old">{{ $h->previousUser?->full_name ?? 'Unassigned' }}</span>
                            <i class="fa-solid fa-arrow-right tr-arrow"></i>
                            <span class="tr-user-new">{{ $h->newUser?->full_name ?? 'Unassigned' }}</span>
                        </div>
                        @endif
                        @if($h->previous_status !== $h->new_status)
                        <div class="tr-status">
                            Status: {{ $h->previous_status }} → <strong>{{ $h->new_status }}</strong>
                        </div>
                        @endif
                        @if($h->remarks)
                            <div class="tr-remarks">{{ $h->remarks }}</div>
                        @endif
                    </div>
                </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- Attachments --}}
    <div class="detail-card card-mt20">
        <div class="detail-card-header"><i class="fa-solid fa-paperclip icon-sky"></i> Documents & Attachments ({{ $asset->attachments->count() }})</div>
        <div class="detail-card-body">
            @if($asset->attachments->isEmpty())
                <p class="text-empty">No documents attached. Click "Add Document" to upload files.</p>
            @else
                @foreach($asset->attachments as $att)
                @php
                    $icon = match(true) {
                        str_contains($att->filetype, 'pdf') => 'fa-file-pdf',
                        str_contains($att->filetype, 'word') || str_contains($att->filename, '.doc') => 'fa-file-word',
                        str_contains($att->filetype, 'excel') || str_contains($att->filename, '.xls') => 'fa-file-excel',
                        str_contains($att->filetype, 'image') => 'fa-file-image',
                        str_contains($att->filetype, 'zip') => 'fa-file-zipper',
                        default => 'fa-file'
                    };
                @endphp
                <div class="attach-item">
                    <div class="attach-icon"><i class="fa-solid {{ $icon }}"></i></div>
                    <div class="attach-info">
                        <div class="attach-name">{{ $att->filename }}</div>
                        <div class="attach-meta">
                            @if($att->label)<span class="attach-label">{{ $att->label }}</span> Â· @endif
                            Uploaded by {{ $att->uploader?->full_name ?? 'Unknown' }} Â· {{ $att->created_at->format('M d, Y') }}
                            Â· {{ $att->file_size }}
                        </div>
                    </div>
                    <div class="attach-actions">
                        <a href="{{ !empty($isSuperAdminView) ? route('super_admin.inventory.attachment.download', $att->id) : route('inventory.attachments.download', $att->id) }}" target="_blank"
                           class="btn-view">
                            <i class="fa-solid fa-eye"></i> View
                        </a>
                        @if(Auth::user()->canProcessSupply())
                        <button data-action="delete-attachment" data-attach-id="{{ $att->id }}"
                                class="btn-del-attach">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                        @endif
                    </div>
                </div>
                @endforeach
            @endif
        </div>
    </div>

@include('partials.inventory._detail_modals')
@include('partials.inventory._detail_scripts')
@endsection