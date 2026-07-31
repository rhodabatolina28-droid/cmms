    {{-- Upload Modal --}}
    @if(Auth::user()->canProcessSupply())
    @include('inventory.partials._modal_upload')

    {{-- Confirm Scrapped Modal (Supply Officer only, for assets tagged For Disposal by IT) --}}
    @if(Auth::user()->canProcessSupply() && $asset->status === 'For Disposal')
    <div id="confirmScrappedModal" class="scrap-overlay">
        <div class="scrap-box">
            <div class="scrap-header">
                <div class="scrap-header-title"><i class="fa-solid fa-circle-check"></i> Confirm Asset Disposal</div>
                <button class="close-scrapped-btn scrap-close-btn"><i class="fa-solid fa-times"></i></button>
            </div>
            <div class="scrap-body">
                <div class="scrap-warning">
                    <strong><i class="fa-solid fa-triangle-exclamation"></i> This action is permanent.</strong><br>
                    Confirming will set this asset to <strong>Scrapped</strong> and lock it permanently. Only do this after the physical disposal is done.
                </div>
                <div class="scrap-info">
                    <div class="scrap-info-label">Asset Being Scrapped</div>
                    <div class="scrap-asset-name">{{ $asset->item_name }}</div>
                    <div class="scrap-asset-details">SN: {{ $asset->serial_number ?? 'N/A' }} Â· PAR: {{ $asset->par_number ?? 'N/A' }} Â· Prop#: {{ $asset->property_number ?? 'N/A' }}</div>
                </div>
                <div class="scrap-field">
                    <label class="scrap-info-label">Remarks (optional)</label>
                    <textarea id="scrappedRemarks" rows="3" placeholder="e.g. Physically disposed via waste/scrap on June 5, 2025..."
                        class="scrap-textarea"></textarea>
                </div>
                <div class="scrap-actions">
                    <button class="close-scrapped-btn btn-scrap-cancel">Cancel</button>
                    <button id="confirmScrappedBtn" class="btn-scrap-confirm">
                        <i class="fa-solid fa-circle-check"></i> Confirm \u2014 Mark as Scrapped
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endif
