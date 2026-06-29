<style nonce="{{ $cspNonce }}">
        .ww-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .ww-title { margin: 0; font-size: 14px; font-weight: 800; color: #1e293b; }
        .ww-title-icon { color: #0038A8; }
        .ww-alert-count { font-size: 11px; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 3px 10px; border-radius: 10px; }
        .ww-empty { text-align: center; color: #94a3b8; padding: 20px; font-size: 13px; }
        .ww-empty-icon { color: #10b981; }
        .ww-section-expiring { font-size: 11px; font-weight: 700; color: #ca8a04; text-transform: uppercase; margin-bottom: 8px; }
        .ww-section-expired { font-size: 11px; font-weight: 700; color: #dc2626; text-transform: uppercase; margin: 12px 0 8px; }
        .ww-link { display: block; text-decoration: none; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .ww-link:last-of-type { border-bottom: none; }
        .ww-item-name { color: #1e293b; }
        .ww-item-detail { color: #64748b; font-size: 12px; display: block; }
    </style>
<div class="queue-panel">
    <div class="ww-flex">
        <h3 class="ww-title">
            <i class="fa-solid fa-shield-halved ww-title-icon"></i> Warranty Alerts
        </h3>
        @isset($warrantyExpiring)
        <span class="ww-alert-count">
            {{ $warrantyExpiring->count() + ($warrantyExpired->count() ?? 0) }} alert(s)
        </span>
        @endisset
    </div>

    @if(!isset($warrantyExpiring) || ($warrantyExpiring->isEmpty() && empty($warrantyExpired)))
        <p class="ww-empty">
            <i class="fa-solid fa-circle-check ww-empty-icon"></i> No warranty alerts at this time.
        </p>
    @else
        @if(isset($warrantyExpiring) && $warrantyExpiring->isNotEmpty())
            <div class="ww-section-expiring">
                <i class="fa-solid fa-clock"></i> Expiring Within 30 Days
            </div>
            @foreach($warrantyExpiring->take(5) as $asset)
                @php $isSupplyOrSuper = Auth::user()->canProcessSupply() || Auth::user()->role === 'super_admin'; @endphp
                <a href="{{ $isSupplyOrSuper ? route('inventory.detail', $asset->asset_id) : route('profile.assets') }}" 
                   class="ww-link">
                    <strong class="ww-item-name">{{ $asset->item_name }}</strong>
                    <span class="ww-item-detail">
                        SN: {{ $asset->serial_number ?? 'N/A' }} &middot; Expires {{ $asset->warranty_expiration?->format('M d, Y') }}
                    </span>
                </a>
            @endforeach
        @endif

        @if(!empty($warrantyExpired) && $warrantyExpired->isNotEmpty())
            <div class="ww-section-expired">
                <i class="fa-solid fa-circle-exclamation"></i> Expired
            </div>
            @foreach($warrantyExpired->take(5) as $asset)
                @php $isSupplyOrSuper = Auth::user()->canProcessSupply() || Auth::user()->role === 'super_admin'; @endphp
                <a href="{{ $isSupplyOrSuper ? route('inventory.detail', $asset->asset_id) : route('profile.assets') }}" 
                   class="ww-link">
                    <strong class="ww-item-name">{{ $asset->item_name }}</strong>
                    <span class="ww-item-detail">
                        SN: {{ $asset->serial_number ?? 'N/A' }} &middot; Expired {{ $asset->warranty_expiration?->format('M d, Y') }}
                    </span>
                </a>
            @endforeach
        @endif
    @endif
</div>
