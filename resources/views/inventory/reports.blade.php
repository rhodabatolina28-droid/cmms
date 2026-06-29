@extends('layouts.app')

@section('title', 'Inventory Reports | NCMB CMMS')
@section('page-title', 'Inventory Reports')

@section('styles')
<style nonce="{{ $cspNonce }}">
    .report-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 24px;
        margin-bottom: 30px;
    }
    .report-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    }
    .report-card h3 {
        font-size: 13px;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0 0 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid #f1f5f9;
    }
    .stat-big {
        font-size: 36px;
        font-weight: 900;
        color: #0038A8;
        line-height: 1;
        margin-bottom: 4px;
    }
    .stat-label {
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
    }
    .dist-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #f8fafc;
        font-size: 14px;
    }
    .dist-row:last-child { border-bottom: none; }
    .dist-label { color: #1e293b; font-weight: 600; }
    .dist-count {
        background: #eff6ff;
        color: #0038A8;
        font-weight: 800;
        padding: 2px 10px;
        border-radius: 10px;
        font-size: 13px;
    }
    .warn-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }
    .warn-item:last-child { border-bottom: none; }
    .warn-item a { color: #1e293b; text-decoration: none; font-weight: 600; }
    .warn-item a:hover { color: #0038A8; }
    .fade-in { animation: fadeInSlide 0.4s ease-out; }
    .header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .export-btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; background: #0038A8; color: white; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; }
    .warranty-warning { color: #ca8a04; }
    .warranty-danger { color: #dc2626; }
    .warranty-safe { color: #10b981; }
    .text-muted-sm { color: #64748b; font-size: 14px; }
    .empty-text { color: #94a3b8; font-size: 13px; }
    .sn-text { display: block; font-size: 11px; color: #64748b; font-weight: 400; }
    .warn-expiring { font-size: 11px; color: #ca8a04; font-weight: 700; }
    .warn-expired { font-size: 11px; color: #dc2626; font-weight: 700; }
    .strong-text { color: #1e293b; }
    .sub-text { display: block; font-size: 11px; color: #64748b; }
    .item-status { font-size: 11px; color: #64748b; font-weight: 700; }
</style>
@endsection

@section('content')
<div class="fade-in">
        <div class="header-row">
        <p class="text-muted-sm">Overview of inventory assets in your scope.</p>
        <a href="{{ route('inventory.export', request()->query()) }}" class="export-btn">
            <i class="fa-solid fa-download"></i> Export CSV
        </a>
    </div>

    <div class="report-grid">
        <div class="report-card">
            <h3>Total Assets</h3>
            <div class="stat-big">{{ number_format($totalAssets) }}</div>
            <div class="stat-label">in your scope</div>
        </div>
        <div class="report-card">
            <h3>Total Acquisition Value</h3>
            <div class="stat-big">₱{{ number_format($totalValue, 2) }}</div>
            <div class="stat-label">sum of all acquisition costs</div>
        </div>
        <div class="report-card">
            <h3>Total Maintenance Cost</h3>
            <div class="stat-big">₱{{ number_format($totalMaintenanceCost, 2) }}</div>
            <div class="stat-label">accumulated repair costs</div>
        </div>
        <div class="report-card">
            <h3>Warranty Alerts</h3>
            <div class="stat-big {{ $warrantyExpiring->count() > 0 ? 'warranty-warning' : ($warrantyExpired->count() > 0 ? 'warranty-danger' : 'warranty-safe') }}">
                {{ $warrantyExpiring->count() + $warrantyExpired->count() }}
            </div>
            <div class="stat-label">{{ $warrantyExpiring->count() }} expiring soon, {{ $warrantyExpired->count() }} expired</div>
        </div>
    </div>

    <div class="report-grid">
        <div class="report-card">
            <h3>Status Distribution</h3>
            @forelse($statusDistribution as $row)
                <div class="dist-row">
                    <span class="dist-label">{{ $row->status }}</span>
                    <span class="dist-count">{{ $row->count }}</span>
                </div>
            @empty
                <p class="empty-text">No assets found.</p>
            @endforelse
        </div>

        <div class="report-card">
            <h3>Category Distribution</h3>
            @forelse($categoryDistribution as $row)
                <div class="dist-row">
                    <span class="dist-label">{{ $row->category }}</span>
                    <span class="dist-count">{{ $row->count }}</span>
                </div>
            @empty
                <p class="empty-text">No assets found.</p>
            @endforelse
        </div>

        <div class="report-card">
            <h3>Warranty Expiring ({{ $warrantyExpiring->count() }})</h3>
            @forelse($warrantyExpiring->take(10) as $asset)
                <div class="warn-item">
                    <a href="{{ route('inventory.detail', $asset->asset_id) }}">
                        {{ $asset->item_name }}
                        <span class="sn-text">
                            SN: {{ $asset->serial_number ?? 'N/A' }}
                        </span>
                    </a>
                    <span class="warn-expiring">
                        {{ $asset->warranty_expiration?->diffForHumans() }}
                    </span>
                </div>
            @empty
                <p class="empty-text">No assets with expiring warranty.</p>
            @endforelse
        </div>

        <div class="report-card">
            <h3>Warranty Expired ({{ $warrantyExpired->count() }})</h3>
            @forelse($warrantyExpired->take(10) as $asset)
                <div class="warn-item">
                    <a href="{{ route('inventory.detail', $asset->asset_id) }}">
                        {{ $asset->item_name }}
                        <span class="sn-text">
                            SN: {{ $asset->serial_number ?? 'N/A' }}
                        </span>
                    </a>
                    <span class="warn-expired">
                        Expired {{ $asset->warranty_expiration?->format('M d, Y') }}
                    </span>
                </div>
            @empty
                <p class="empty-text">No expired warranties.</p>
            @endforelse
        </div>

        <div class="report-card">
            <h3>Recent Disposals</h3>
            @forelse($recentDisposals as $asset)
                <div class="warn-item">
                    <span>
                        <strong class="strong-text">{{ $asset->item_name }}</strong>
                        <span class="sub-text">
                            SN: {{ $asset->serial_number ?? 'N/A' }}
                        </span>
                    </span>
                    <span class="item-status">
                        {{ $asset->status }}
                    </span>
                </div>
            @empty
                <p class="empty-text">No disposals recorded.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
