@extends('layouts.app')

@section('title', 'Physical Count #' . $session->id)
@section('page-title', 'Physical Inventory Count')

@section('styles')
<style nonce="{{ $cspNonce }}">
    .pc-container { width: 100%; margin-top: -10px; animation: fadeInSlide 0.4s ease-out; }
    .polish-card { background: white; border-radius: 10px; border: 1px solid #e2e8f0; overflow: hidden; }
    .card-header-accent { background: #f8fafc; padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
    .card-body-content { padding: 20px 24px; }
    .btn-primary { background: #0038A8; color: white; border: none; padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
    .btn-primary:hover { background: #002d8c; color: white; }
    .btn-secondary { background: white; color: #475569; border: 1px solid #cbd5e1; padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
    .btn-secondary:hover { background: #f8fafc; border-color: #0038A8; color: #0038A8; }
    .btn-success { background: #16a34a; color: white; border: none; padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 800; cursor: pointer; }
    .btn-success:hover { background: #15803d; }
    .btn-danger { background: #dc2626; color: white; border: none; padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 800; cursor: pointer; }

    .stats-bar { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 18px; }
    .stat-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; text-align: center; }
    .stat-box p { margin: 0; font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; }
    .stat-box h3 { margin: 4px 0 0; font-size: 22px; font-weight: 800; color: #1e293b; }

    .search-area { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 18px; }
    .search-input-wrap { display: flex; gap: 10px; align-items: center; }
    .search-input-wrap input { flex: 1; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; outline: none; }
    .search-input-wrap input:focus { border-color: #0038A8; box-shadow: 0 0 0 3px rgba(0,56,168,0.1); }
    .search-results { margin-top: 12px; display: none; }
    .search-result-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: white; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 6px; }
    .search-result-item:last-child { margin-bottom: 0; }

    .mark-btn { padding: 6px 14px; border-radius: 4px; font-size: 11px; font-weight: 800; border: none; cursor: pointer; }
    .mark-operational { background: #dcfce7; color: #166534; }
    .mark-operational:hover { background: #bbf7d0; }
    .mark-non-ops { background: #fef2f2; color: #991b1b; }
    .mark-non-ops:hover { background: #fecaca; }

    .counted-operational { background: #f0fdf4; }
    .counted-non-ops { background: #fef2f2; }

    .status-pill-small { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
    .pill-operational { background: #dcfce7; color: #166534; }
    .pill-non-ops { background: #fef2f2; color: #991b1b; }

    .btn-scan { background: #0038A8; color: white; border: none; padding: 14px 20px; border-radius: 10px; font-size: 15px; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; margin-top: 12px; }
    .btn-scan:hover { background: #002d8c; }

    .asset-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .asset-table th { background: #f1f5f9; padding: 10px 12px; font-size: 10px; font-weight: 800; color: #475569; text-transform: uppercase; text-align: left; border-bottom: 2px solid #e2e8f0; }
    .asset-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; }
    .asset-table tr:hover td { background: #f8fafc; }

    .scanner-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 16px; }
    .scanner-modal { background: white; border-radius: 12px; width: 100%; max-width: 420px; overflow: hidden; animation: fadeInSlide 0.3s ease-out; }
    .scanner-modal-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .scanner-modal-header h4 { margin: 0; font-size: 16px; font-weight: 800; color: #1e293b; }
    .scanner-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; padding: 0 4px; }
    .scanner-modal-body { padding: 16px; }
    #scannerContainer { width: 100%; aspect-ratio: 1; max-height: 320px; background: #000; border-radius: 8px; overflow: hidden; }
    #scannerContainer video { width: 100% !important; height: auto !important; }

    .profile-card { background: white; border: 2px solid #0038A8; border-radius: 12px; overflow: hidden; margin-top: 12px; animation: fadeInSlide 0.3s ease-out; }
    .profile-card-inner { padding: 16px; }
    .profile-header { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; padding-bottom: 14px; border-bottom: 1px solid #e2e8f0; }
    .profile-icon { width: 48px; height: 48px; background: #eff6ff; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #0038A8; flex-shrink: 0; }
    .profile-title h4 { margin: 0; font-size: 16px; font-weight: 800; color: #1e293b; }
    .profile-details { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; margin-bottom: 14px; }
    .detail-row { font-size: 12px; }
    .detail-row span { color: #64748b; }
    .detail-row strong { color: #1e293b; display: block; }
    .profile-actions { display: flex; gap: 8px; }

    @media (max-width: 640px) {
        .stats-bar { grid-template-columns: repeat(2, 1fr); }
        .stat-box h3 { font-size: 18px; }
        .profile-details { grid-template-columns: 1fr; }
        .profile-actions { flex-direction: column; }
        .profile-actions .mark-btn { width: 100%; padding: 14px !important; font-size: 15px !important; }
        .card-body-content { padding: 12px 14px; }
        .asset-table { font-size: 11px; }
        .asset-table th, .asset-table td { padding: 6px 8px; }
    }
    /* Hide QR scanner on desktop — phone only */
    @media (min-width: 768px) {
        .btn-scan { display: none !important; }
        #scannerModalOverlay { display: none !important; }
    }

    /* Utility classes */
    .back-link { color: #0038A8; font-size: 13px; font-weight: 700; text-decoration: none; }
    .session-info-box { background: #f0fdf4; border: 1px solid #86efac; border-radius: 10px; padding: 18px 22px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
    .session-info-label { font-size: 11px; font-weight: 800; color: #15803d; text-transform: uppercase; }
    .session-info-text { font-size: 13px; color: #1e293b; margin-top: 2px; }
    .session-actions-wrap { display: flex; gap: 8px; align-items: center; }
    .inline-form { display:inline; }
    .stat-box-blue { background:#eff6ff; }
    .stat-box-green { background:#f0fdf4; }
    .stat-box-red { background:#fef2f2; }
    .stat-value-blue { color:#1d4ed8; }
    .stat-value-green { color:#16a34a; }
    .stat-value-red { color:#dc2626; }
    .card-h4 { margin:0; font-size:15px; font-weight:800; color:#1e293b; }
    .search-icon-gray { color:#94a3b8; }
    .btn-secondary-sm { font-size: 12px; }
    .scanner-modal-text { text-align:center;font-size:12px;color:#64748b;margin:10px 0 6px; }
    .btn-cancel-scanner { width:100%;justify-content:center;margin-top:4px; }
    .scroll-x { overflow-x: auto; }
    .th-center { text-align: center !important; }
    .td-bold { font-weight:700; }
    .td-mono { font-family:monospace;font-size:12px; }
    .td-actions { text-align:center; vertical-align: middle; white-space: nowrap; }
    .action-btn-group { display:flex;gap:4px;justify-content:center; }
    .disabled-btn { opacity:0.3; }
    .not-counted-text { color:#94a3b8;font-size:11px; }
    .empty-table { text-align:center;padding:40px;color:#94a3b8; }
    .mb-16 { margin-bottom: 16px; }
    .mb-12 { margin-bottom: 12px; }
    .hidden-display { display: none; }
    .search-no-result { padding:12px;color:#94a3b8;text-align:center; }
    .search-sn { color:#64748b;font-size:12px;margin-left:8px; }
    .already-counted-sm { color:#16a34a;font-size:11px;margin-left:6px; }
    .counted-text { color:#16a34a;font-size:12px;font-weight:700; }
    .profile-serial { font-size:11px;color:#64748b; }
    .counted-box { text-align:center;padding:12px;background:#f0fdf4;border-radius:6px;color:#166534;font-weight:700;font-size:13px; }
    .mark-btn-flex { flex:1;padding:12px;font-size:14px; }
    @media (max-width: 767px) {
        .stats-bar { grid-template-columns: repeat(2, 1fr) !important; gap: 8px !important; }
        input[type="checkbox"] { display: none !important; }
        .swal2-checkbox { display: none !important; }
        .session-info-box { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; }
        .session-actions-wrap { width: 100% !important; flex-direction: column !important; gap: 8px !important; }
        .session-actions-wrap a,
        .session-actions-wrap button,
        .session-actions-wrap form { width: 100% !important; }
        .session-actions-wrap .btn-primary,
        .session-actions-wrap .btn-secondary,
        .session-actions-wrap .btn-success { width: 100% !important; justify-content: center !important; }
        .search-input-wrap { flex-direction: column !important; gap: 8px !important; }
        .search-input-wrap input { width: 100% !important; }
        .search-input-wrap .btn-secondary { width: 100% !important; justify-content: center !important; }
        .action-btn-group { flex-direction: column !important; gap: 4px !important; }
        .action-btn-group .mark-btn { width: 100% !important; padding: 10px !important; font-size: 13px !important; min-height: 44px !important; }
        .search-result-item { flex-direction: column !important; align-items: flex-start !important; gap: 8px !important; }
        .search-result-item > div:last-child { width: 100% !important; display: flex !important; flex-direction: column !important; gap: 4px !important; }
        .search-result-item .mark-btn { width: 100% !important; padding: 10px !important; font-size: 13px !important; min-height: 44px !important; }
        .back-link { display: block !important; width: 100% !important; text-align: center !important; padding: 10px !important; border: 1px solid #cbd5e1 !important; border-radius: 6px !important; background: white !important; }
        input[type="checkbox"] { display: none !important; }
        .asset-table { min-width: 600px !important; }
    }
</style>
@endsection

@section('content')
<div class="pc-container">
    <div class="mb-16">
        <a href="{{ route('physical-count.index') }}" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Count Sessions
        </a>
    </div>

    <div class="polish-card">
        <div class="card-header-accent" style="flex-direction:column;align-items:stretch;gap:14px;">
            <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:14px 18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <div>
                    <div style="font-size:11px;font-weight:800;color:#15803d;text-transform:uppercase;">
                        <i class="fa-solid fa-clipboard-check"></i>
                        @if($session->status === 'Ongoing') Ongoing Physical Count @else Completed Count @endif
                    </div>
                    <div style="font-size:13px;color:#1e293b;margin-top:2px;">
                        Started {{ $session->started_at->format('M d, Y h:i A') }} by {{ $session->startedBy->full_name ?? 'Unknown' }}
                        @if($session->completed_at)
                            &middot; Completed {{ $session->completed_at->format('M d, Y h:i A') }}
                        @endif
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    @if($session->status !== 'Ongoing')
                        <a href="{{ route('physical-count.export', $session->id) }}" class="btn-secondary btn-secondary-sm">
                            <i class="fa-solid fa-download"></i> Export CSV
                        </a>
                        <a href="{{ route('physical-count.print', $session->id) }}" class="btn-secondary btn-secondary-sm" target="_blank">
                            <i class="fa-solid fa-print"></i> Print Report
                        </a>
                    @endif
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <h4 class="card-h4">
                    <i class="fa-solid fa-list"></i> Asset List
                </h4>
                @if($session->status === 'Ongoing')
                <form id="completeSessionForm" method="POST" action="{{ route('physical-count.complete', $session->id) }}" class="inline-form">
                    @csrf
                    <button type="submit" class="btn-success" style="padding:8px 18px;font-size:12px;"><i class="fa-solid fa-check"></i> Complete Session</button>
                </form>
                @endif
            </div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px;text-align:center;">
                    <p style="margin:0;font-size:10px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;">Total Assets</p>
                    <h3 style="margin:4px 0 0;font-size:24px;font-weight:800;color:#1e293b;">{{ $summary['total'] }}</h3>
                </div>
                <div style="background:#eff6ff;border:1px solid #dbeafe;border-radius:8px;padding:10px;text-align:center;">
                    <p style="margin:0;font-size:10px;font-weight:800;color:#1d4ed8;text-transform:uppercase;letter-spacing:0.3px;">Counted</p>
                    <h3 style="margin:4px 0 0;font-size:24px;font-weight:800;color:#1d4ed8;">{{ $summary['counted'] }}</h3>
                </div>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px;text-align:center;">
                    <p style="margin:0;font-size:10px;font-weight:800;color:#16a34a;text-transform:uppercase;letter-spacing:0.3px;">Operational</p>
                    <h3 style="margin:4px 0 0;font-size:24px;font-weight:800;color:#16a34a;">{{ $summary['present'] }}</h3>
                </div>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:10px;text-align:center;">
                    <p style="margin:0;font-size:10px;font-weight:800;color:#dc2626;text-transform:uppercase;letter-spacing:0.3px;">Non-Ops</p>
                    <h3 style="margin:4px 0 0;font-size:24px;font-weight:800;color:#dc2626;">{{ $summary['missing'] + $summary['damaged'] }}</h3>
                </div>
            </div>
        </div>
        <div class="card-body-content">
            @if($session->status === 'Ongoing')
            <div class="search-area">
                <div class="search-input-wrap">
                    <i class="fa-solid fa-magnifying-glass search-icon-gray"></i>
                    <input type="text" id="scanSearchInput" placeholder="Search or scan QR sticker..." autocomplete="off">
                    <button id="clearSearchBtn" class="btn-secondary"><i class="fa-solid fa-times"></i> Clear</button>
                </div>
                <button id="openScannerBtn" class="btn-scan">
                    <i class="fa-solid fa-camera"></i> Scan QR Sticker
                </button>
                <div id="scanResultCard" class="profile-card hidden-display"></div>
                <div id="searchResults" class="search-results"></div>
            </div>

            <div id="scannerModalOverlay" class="scanner-modal-overlay hidden-display">
                <div class="scanner-modal">
                    <div class="scanner-modal-header">
                        <h4><i class="fa-solid fa-camera"></i> Scan QR Sticker</h4>
                        <button class="close-scanner-btn scanner-modal-close">&times;</button>
                    </div>
                    <div class="scanner-modal-body">
                        <div id="scannerContainer"></div>
                        <p class="scanner-modal-text">Point your camera at the asset's QR sticker</p>
                        <button class="close-scanner-btn btn-secondary btn-cancel-scanner">Cancel</button>
                    </div>
                </div>
            </div>
            @endif

            <div class="scroll-x">
                <table class="asset-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Item Name</th>
                            <th>Serial No</th>
                            <th>PAR No</th>
                            <th>Property No</th>
                            <th>Assigned To</th>
                            @if($session->status === 'Ongoing')
                            <th class="th-center">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($allAssets as $asset)
                            @php
                                $count = $session->counts->firstWhere('asset_id', $asset->asset_id);
                                $displayStatus = $count ? ($count->status === 'Present' ? 'Operational' : 'Non-Operational') : 'Not Counted';
                                $rowClass = $count ? ($count->status === 'Present' ? 'counted-operational' : 'counted-non-ops') : '';
                                $pillClass = $count ? ($count->status === 'Present' ? 'pill-operational' : 'pill-non-ops') : '';
                            @endphp
                            <tr class="{{ $rowClass }}">
                                <td>
                                    @if($count)
                                        <span class="status-pill-small {{ $pillClass }}">{{ $displayStatus }}</span>
                                    @else
                                        <span class="not-counted-text">Not Counted</span>
                                    @endif
                                </td>
                                <td class="td-bold">{{ $asset->item_name }}</td>
                                <td class="td-mono">{{ $asset->serial_number ?? '—' }}</td>
                                <td>{{ $asset->par_number ?? '—' }}</td>
                                <td class="td-mono">{{ $asset->property_number ?? '—' }}</td>
                                <td>{{ $asset->assignedUser->full_name ?? 'Unassigned' }}</td>
                                @if($session->status === 'Ongoing')
                                <td class="td-actions">
                                    <div class="action-btn-group">
                                        <button data-action="mark-asset" data-asset-id="{{ $asset->asset_id }}" data-status="Present" class="mark-btn mark-operational {{ $count ? 'disabled-btn' : '' }}" {{ $count ? 'disabled' : '' }}>Operational</button>
                                        <button data-action="mark-asset" data-asset-id="{{ $asset->asset_id }}" data-status="Missing" class="mark-btn mark-non-ops {{ $count ? 'disabled-btn' : '' }}" {{ $count ? 'disabled' : '' }}>Non-Operational</button>
                                    </div>
                                </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="7" class="empty-table">No assets found in your scope.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}" src="{{ asset('js/html5-qrcode.min.js') }}"></script>
<script nonce="{{ $cspNonce }}" src="{{ asset('js/qr-scanner.js') }}?v={{ time() }}"></script>
<script nonce="{{ $cspNonce }}">
const SESSION_ID = {{ $session->id }};
const SEARCH_URL = '{{ route("physical-count.search", $session->id) }}';
const MARK_URL = '{{ route('physical-count.mark', $session->id) }}';
const API_PROFILE_URL = '{{ route('api.asset.profile', '_ID_') }}';
const COUNTED_IDS = @json($countedIds);
let searchTimeout;

document.getElementById('scanSearchInput')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim();
    if (q.length < 1) {
        document.getElementById('searchResults').style.display = 'none';
        return;
    }
    searchTimeout = setTimeout(() => searchAsset(q), 300);
});

async function searchAsset(q) {
    const container = document.getElementById('searchResults');
    try {
        const formData = new FormData();
        formData.set('q', q);

        const res = await fetch(SEARCH_URL, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData,
        });
        const data = await res.json();
        if (!data.success) return;

        if (data.assets.length === 0) {
            container.innerHTML = '<div class="search-no-result">No matching assets found.</div>';
            container.style.display = 'block';
            return;
        }

        container.innerHTML = data.assets.map(a => {
            const counted = data.counted_ids.includes(a.asset_id);
            return `<div class="search-result-item">
                <div>
                    <strong>${a.item_name}</strong>
                    <span class="search-sn">SN: ${a.serial_number || 'N/A'}</span>
                    ${counted ? '<span class="already-counted-sm"><i class="fa-solid fa-check"></i> Already Counted</span>' : ''}
                </div>
                <div>
                    ${!counted ? `
                    <button data-action="mark-asset" data-asset-id="${a.asset_id}" data-status="Present" class="mark-btn mark-operational">Operational</button>
                    <button data-action="mark-asset" data-asset-id="${a.asset_id}" data-status="Missing" class="mark-btn mark-non-ops">Non-Operational</button>
                    ` : '<span class="counted-text"><i class="fa-solid fa-check"></i> Counted</span>'}
                </div>
            </div>`;
        }).join('');
        container.style.display = 'block';
    } catch (e) {
        console.error('Search error:', e);
    }
}

function clearSearch() {
    document.getElementById('scanSearchInput').value = '';
    document.getElementById('searchResults').style.display = 'none';
}

let isMarking = false;

async function markAsset(assetId, status, btn) {
    if (isMarking) return;
    isMarking = true;
    if (btn) { btn.disabled = true; btn.textContent = '...'; }
    try {
        const formData = new FormData();
        formData.set('asset_id', assetId);
        formData.set('status', status);

        const res = await fetch(MARK_URL, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData,
        });
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            Swal.fire({ icon: 'error', title: 'Failed', text: data.message || 'Failed to mark asset.', confirmButtonColor: '#0038A8' });
            isMarking = false;
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Could not connect to server. Please try again.', confirmButtonColor: '#0038A8' });
        isMarking = false;
    }
}

// QR Scanner
const scanner = new AssetScanner({
    onScan: async (assetId, rawContent) => {
        closeScanner();
        const res = await fetch(API_PROFILE_URL.replace('_ID_', assetId), {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            showScanResult(data.data);
        } else {
            Swal.fire({ icon: 'warning', title: 'Asset Not Found', text: 'This QR code does not match any asset in scope.', confirmButtonColor: '#0038A8' });
        }
    },
    onError: (err) => {
        console.error('Scanner error:', err);
        closeScanner();
        Swal.fire({ icon: 'error', title: 'Camera Error', text: 'Please check if your browser has camera permission allowed for this site.', confirmButtonColor: '#0038A8' });
    }
});

function openScanner() {
    if (!scanner.isCameraAvailable()) {
        Swal.fire({ icon: 'info', title: 'Camera Not Available', text: 'Camera is not available on this device. Please search manually using the search box.', confirmButtonColor: '#0038A8' });
        return;
    }
    document.getElementById('scannerModalOverlay').style.display = 'flex';
    scanner.startCamera('scannerContainer');
}

function closeScanner() {
    scanner.stopCamera();
    document.getElementById('scannerModalOverlay').style.display = 'none';
}

function showScanResult(data) {
    const card = document.getElementById('scanResultCard');
    const asset = data.asset;
    const isCounted = COUNTED_IDS.includes(asset.asset_id);

    card.innerHTML = `
        <div class="profile-card-inner">
            <div class="profile-header">
                <div class="profile-icon">
                    <i class="fa-solid fa-microchip"></i>
                </div>
                <div class="profile-title">
                    <h4>${asset.item_name}</h4>
                    <span class="profile-serial">${asset.serial_number || ''}</span>
                </div>
            </div>
            <div class="profile-details">
                <div class="detail-row"><span>PAR No</span><strong>${asset.par_number || '—'}</strong></div>
                <div class="detail-row"><span>Property No</span><strong>${asset.property_number || '—'}</strong></div>
                <div class="detail-row"><span>Brand / Model</span><strong>${[asset.brand, asset.model].filter(Boolean).join(' ') || '—'}</strong></div>
                <div class="detail-row"><span>Category</span><strong>${asset.category || '—'}</strong></div>
                <div class="detail-row"><span>Status</span><strong>${asset.status || '—'}</strong></div>
                <div class="detail-row"><span>Assigned To</span><strong>${asset.assigned_user ? asset.assigned_user.full_name : 'Unassigned'}</strong></div>
            </div>
            ${isCounted ?
                `<div class="counted-box">
                    <i class="fa-solid fa-check-circle"></i> Already Counted
                </div>` :
                `<div class="profile-actions">
                    <button data-action="mark-asset" data-asset-id="${asset.asset_id}" data-status="Present" class="mark-btn mark-operational mark-btn-flex">✅ Operational</button>
                    <button data-action="mark-asset" data-asset-id="${asset.asset_id}" data-status="Missing" class="mark-btn mark-non-ops mark-btn-flex">❌ Non-Operational</button>
                </div>`
            }
        </div>
    `;
    card.style.display = 'block';
    card.scrollIntoView({ behavior: 'smooth' });
}

document.addEventListener('DOMContentLoaded', function() {
    var alertBox = document.querySelector('.alert-success');
    if (alertBox) {
        var msg = alertBox.textContent.trim();
        alertBox.style.display = 'none';
        Swal.fire({
            icon: 'success',
            title: 'Completed!',
            text: msg,
            confirmButtonColor: '#0038A8',
            confirmButtonText: 'OK'
        });
    }

    document.getElementById('completeSessionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'End this session?',
            text: 'This will complete the physical count. Assets not yet counted will be marked as Not Counted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, complete it!',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                e.target.submit();
            }
        });
    });
    document.getElementById('clearSearchBtn').addEventListener('click', clearSearch);
    document.getElementById('openScannerBtn').addEventListener('click', openScanner);
    document.getElementById('scannerModalOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeScanner();
    });
    document.querySelectorAll('.close-scanner-btn').forEach(function(el) {
        el.addEventListener('click', closeScanner);
    });
});
document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-action="mark-asset"]');
    if (btn) {
        markAsset(parseInt(btn.dataset.assetId), btn.dataset.status, btn);
    }
});
</script>
@endsection