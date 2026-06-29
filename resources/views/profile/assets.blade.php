@extends('layouts.app')

@section('title', 'My Accountable Assets | NCMB ICT System')
@section('page-title', 'My Assets')

@section('styles')
    <style nonce="{{ $cspNonce }}">
        .assets-container {
            width: 100%;
            margin-top: -10px;
            animation: fadeInSlide 0.4s ease-out;
        }

        .polish-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .card-header-accent {
            background: #f8fafc;
            padding: 20px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body-content {
            padding: 25px 30px;
        }

        .gov-table-premium {
            width: 100%;
            border-collapse: collapse;
        }

        .gov-table-premium th {
            background: #f1f5f9;
            padding: 12px 15px;
            font-size: 11px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }

        .gov-table-premium td {
            padding: 14px 15px;
            font-size: 14px;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .gov-table-premium tr.tr-hover-row { transition: all 0.2s; position: relative; }
        .gov-table-premium tr.tr-hover-row:hover { background: #f8fafc !important; transform: scale(1.002); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .gov-table-premium tr.tr-hover-row:hover td:first-child { box-shadow: inset 4px 0 0 #0038A8; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }

        .asset-status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .status-active { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.15); }
        .status-defective { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.15); }
        .status-spare { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(100, 116, 139, 0.15); }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .modal-overlay.show {
            display: flex;
        }
        .modal-box {
            background: white;
            border-radius: 15px;
            width: 480px;
            max-width: 94%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            animation: modalIn 0.25s ease-out;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.92) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-header {
            padding: 18px 22px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-close {
            width: 30px; height: 30px;
            border-radius: 8px;
            border: none;
            background: #f1f5f9;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #64748b;
            transition: all 0.15s;
        }
        .modal-close:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        .modal-body {
            padding: 20px 22px 22px;
        }

        .detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #f8fafc;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            width: 120px;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            flex-shrink: 0;
        }
        .detail-value {
            font-size: 14px;
            color: #1e293b;
            font-weight: 600;
        }
        .detail-value.mono {
            font-family: monospace;
        }

        .btn-view {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            color: #1d4ed8;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-transform: uppercase;
        }
        .btn-view:hover {
            background: #1d4ed8;
            color: white;
            border-color: #1d4ed8;
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(29, 78, 216, 0.2);
        }

        .asset-header-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; }
        .asset-header-desc { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .asset-header-icon { font-size: 24px; color: #0038A8; opacity: 0.5; }
        .table-wrap { overflow-x: auto; }
        .th-center { text-align: center; }
        .td-name { font-weight: 700; color: #0038A8; }
        .td-category { font-weight: 600; }
        .td-serial { font-family: monospace; color: #64748b; }
        .td-center { text-align: center; }
        .td-center-empty { text-align: center; padding: 50px; color: #94a3b8; }
        .icon-empty { font-size: 40px; display: block; margin-bottom: 15px; opacity: 0.3; }
        .modal-title { margin: 0; font-size: 16px; font-weight: 800; color: #0038A8; }
        .modal-category { font-size: 12px; color: #64748b; }
        .detail-preline { white-space: pre-line; }
        .warranty-chip { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 800; border: 1px solid; }
        .warranty-active { background: #ecfdf5; color: #065f46; border-color: #065f46; }
        .warranty-expired { background: #fef2f2; color: #991b1b; border-color: #991b1b; }
        .warranty-expiring { background: #fffbeb; color: #b45309; border-color: #b45309; }
        .warranty-default { background: #f3f4f6; color: #6b7280; border-color: #6b7280; }
        .warranty-expiry { font-size: 12px; color: #64748b; margin-left: 8px; }
        @media (max-width: 767px) {
            .card-header-accent { flex-direction: column !important; gap: 10px !important; }
            .table-wrap, .scroll-x { overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; }
            input, select, textarea { min-height: 48px !important; font-size: 15px !important; }
            .btn, button:not(#sidebarToggle):not(#notifBell):not(.swal2-confirm):not(.swal2-cancel) { min-height: 48px !important; width: 100% !important; font-size: 14px !important; }
            .warranty-chip { font-size: 10px !important; }
            .modal-category { font-size: 13px !important; }
        }
    </style>
@endsection

@section('content')
<div class="assets-container">

    <div class="polish-card">
        <div class="card-header-accent">
            <div>
                <h3 class="asset-header-title">Accountable Assets</h3>
                <p class="asset-header-desc">
                    {{ $assets->count() }} item(s) assigned to your account.
                </p>
            </div>
            <i class="fa-solid fa-laptop-medical asset-header-icon"></i>
        </div>

        <div class="card-body-content">
            <div class="table-wrap">
                <table class="gov-table-premium">
                    <thead>
                        <tr>
                            <th>Item Description</th>
                            <th>Category</th>
                            <th>Serial / Property #</th>
                            <th style="width: 140px; text-align: center;">Condition</th>
                            <th style="width: 140px; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assets as $asset)
                            <tr class="tr-hover-row">
                                <td class="td-name">{{ $asset->item_name }}</td>
                                <td class="td-category">{{ $asset->category }}</td>
                                <td class="td-serial">
                                    {{ $asset->serial_number ?: 'N/A' }}
                                </td>
                                <td class="td-center">
                                    <span class="asset-status-pill status-{{ str_replace(' ', '-', strtolower($asset->status)) }}">
                                        {{ $asset->status }}
                                    </span>
                                </td>
                                <td class="td-center">
                                    <button class="btn-view" data-action="open-modal" data-id="{{ $asset->asset_id }}">
                                        <i class="fa-solid fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="td-center-empty">
                                    <i class="fa-solid fa-box-open icon-empty"></i>
                                    No assets found under your account.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

@foreach($assets as $asset)
    @php
        $ws = $asset->warranty_status;
        $wsClass = match($ws) {
            'Active' => 'warranty-active',
            'Expired' => 'warranty-expired',
            'Expiring Soon' => 'warranty-expiring',
            default => 'warranty-default'
        };
    @endphp
    <div id="modal-{{ $asset->asset_id }}" class="modal-overlay" data-id="{{ $asset->asset_id }}">
        <div class="modal-box">
            <div class="modal-header">
                <div>
                    <h4 class="modal-title">{{ $asset->item_name }}</h4>
                    <span class="modal-category">{{ $asset->category }}</span>
                </div>
                <button class="modal-close" data-action="close-modal" data-id="{{ $asset->asset_id }}">&times;</button>
            </div>
            <div class="modal-body">
                <div class="detail-row">
                    <div class="detail-label">Condition</div>
                    <div class="detail-value">
                        <span class="asset-status-pill status-{{ str_replace(' ', '-', strtolower($asset->status)) }}">
                            {{ $asset->status }}
                        </span>
                    </div>
                </div>
                @if($asset->brand || $asset->model)
                <div class="detail-row">
                    <div class="detail-label">Brand / Model</div>
                    <div class="detail-value">{{ $asset->brand }} {{ $asset->model }}</div>
                </div>
                @endif
                @if($asset->specifications)
                <div class="detail-row">
                    <div class="detail-label">Specifications</div>
                    <div class="detail-preline detail-value">
                        @if(is_array($asset->specifications))
                            {{ implode(', ', $asset->specifications) }}
                        @else
                            {{ $asset->specifications }}
                        @endif
                    </div>
                </div>
                @endif
                <div class="detail-row">
                    <div class="detail-label">Serial Number</div>
                    <div class="detail-value mono">{{ $asset->serial_number ?: 'N/A' }}</div>
                </div>
                @if($asset->property_number)
                <div class="detail-row">
                    <div class="detail-label">Property #</div>
                    <div class="detail-value mono">{{ $asset->property_number }}</div>
                </div>
                @endif
                @if($asset->par_number)
                <div class="detail-row">
                    <div class="detail-label">PAR #</div>
                    <div class="detail-value">{{ $asset->par_number }}</div>
                </div>
                @endif
                <div class="detail-row">
                    <div class="detail-label">Date Acquired</div>
                    <div class="detail-value">{{ $asset->date_acquired ? $asset->date_acquired->format('M d, Y') : 'N/A' }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Warranty</div>
                    <div class="detail-value">
                        <span class="warranty-chip {{ $wsClass }}">
                            {{ $ws }}
                        </span>
                        @if($asset->warranty_expiration)
                            <span class="warranty-expiry">Expires {{ $asset->warranty_expiration->format('M d, Y') }}</span>
                        @endif
                    </div>
                </div>
                @if($asset->acquisition_cost)
                <div class="detail-row">
                    <div class="detail-label">Acquisition Cost</div>
                    <div class="detail-value">₱{{ number_format($asset->acquisition_cost, 2) }}</div>
                </div>
                @endif
                @if($asset->asset_notes)
                <div class="detail-row">
                    <div class="detail-label">Notes</div>
                    <div class="detail-value">{{ $asset->asset_notes }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>
@endforeach
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
    function openModal(id) {
        document.getElementById('modal-' + id).classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        document.getElementById('modal-' + id).classList.remove('show');
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.show').forEach(function(el) {
                el.classList.remove('show');
            });
            document.body.style.overflow = '';
        }
    });
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-action="open-modal"]');
        if (btn) { openModal(parseInt(btn.dataset.id)); }
        var closeBtn = e.target.closest('[data-action="close-modal"]');
        if (closeBtn) { closeModal(parseInt(closeBtn.dataset.id)); }
        var overlay = e.target.closest('.modal-overlay');
        if (overlay && overlay.dataset.id && e.target === overlay) {
            closeModal(parseInt(overlay.dataset.id));
        }
    });
</script>
@endsection