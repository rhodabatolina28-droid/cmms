{{-- Phase C5 - receive form (rendered on the dedicated "Record Delivery" page;
     access is guarded by PurchaseRequestController::receiveForm()). --}}
@php
    $needsReceipt = ! $purchaseRequest->isDelivered()
        && $purchaseRequest->attachments()->doesntExist();
@endphp
<div id="receive-panel" class="rx-panel" role="region" aria-labelledby="rx-title">
    <div class="rx-step-head">

        <div>
            <h2 id="rx-title">{{ !empty($viewOnly) ? 'Received items' : 'Log items & destinations' }}</h2>
            <span class="st-sub">{{ !empty($viewOnly) ? 'Items that arrived and their destination on this delivery' : 'Match each purchased line to an inventory item and say where it went' }}</span>
        </div>
    </div>

    @if(!empty($viewOnly))
        {{-- Read-only: what actually arrived, per physical piece — serial +
            property numbers come from parts_stock_units (linked to this PR),
            so both stock-in and installed-on-asset pieces are shown. --}}
        @php
            $unitsByPart = \App\Models\PartUnit::query()
                ->where('purchase_request_id', $purchaseRequest->id)
                ->with(['part', 'asset'])
                ->get()
                ->groupBy('part_id');
        @endphp
        <div style="display:flex; flex-direction:column; gap:10px;">
            @foreach($purchaseRequest->items ?? [] as $idx => $item)
                @php
                    $qty = max(1, (int)($item['quantity'] ?? 1));
                    // Units recorded for this line: prefer the stored part_id
                    // on the item; fall back to matching by item name for
                    // older PRs (or "create new" lines).
                    $units = collect();
                    if (!empty($item['part_id']) && isset($unitsByPart[$item['part_id']])) {
                        $units = $unitsByPart[$item['part_id']]->values();
                    } else {
                        $matchName = trim((string)($item['description'] ?? ''));
                        if ($matchName !== '') {
                            $units = $unitsByPart
                                ->first(fn ($group) => optional($group->first()->part)->item_name === $matchName, collect())
                                ->values();
                        }
                    }
                    $installed = $units->firstWhere('status', 'issued');
                    $stocked = $units->firstWhere('status', 'in_stock');
                    $destLabel = $installed
                        ? 'Installed on asset' . ($installed->asset ? ' · ' . ($installed->asset->asset_code ?? ('#' . $installed->asset->asset_id)) : '')
                        : ($stocked ? 'Add to inventory (stock)' : null);
                    // Show the per-piece grid whenever individual units (serial
                    // + property) were actually recorded — regardless of the
                    // part's tracking flag, the recorded numbers are the truth.
                    $hasUnits = $units->isNotEmpty();
                @endphp
                <div class="rx-line" style="margin:0;">
                    <div class="rx-line-top">
                        <span class="rx-line-no">{{ $idx + 1 }}</span>
                        <span class="rx-line-desc">{{ $item['description'] ?? ('Item ' . ($idx + 1)) }} <em>&times;{{ $qty }} {{ $item['unit'] ?? '' }}</em></span>
                        @if($destLabel)
                            <span style="font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; background:{{ $installed ? '#eff6ff' : '#f0fdf4' }}; color:{{ $installed ? '#0038A8' : '#15803d' }}; border-radius:999px; padding:3px 10px; flex:none;">
                                <i class="fa-solid {{ $installed ? 'fa-screwdriver-wrench' : 'fa-boxes-stacked' }}" style="margin-right:4px;"></i>{{ $destLabel }}
                            </span>
                        @endif
                    </div>
                    <div class="rx-cols" style="margin-top:8px;">
                        @if(isset($item['unit_cost']) && $item['unit_cost'] !== null && $item['unit_cost'] !== '')
                            <div><span class="rx-field-label">Unit cost</span><span class="rx-view-val">&#8369; {{ number_format((float) $item['unit_cost'], 2) }}</span></div>
                        @endif
                        @if($hasUnits)
                            <div><span class="rx-field-label">Tracked pieces</span><span class="rx-view-val">{{ $units->count() }} of {{ $qty }} recorded</span></div>
                        @endif
                    </div>
                    @if($hasUnits)
                        <div class="rx-unit-grid show" style="margin-top:10px;">
                            <div class="rx-unit-title"><i class="fa-solid fa-barcode" style="margin-right:5px;"></i>Serial / property numbers per piece ({{ $units->count() }}/{{ $qty }})</div>
                            @foreach($units as $u)
                                <div class="rx-unit-row">
                                    <div class="rx-unit-cell"><span class="rx-field-label">Serial no.</span><span class="rx-view-val">{{ $u->serial_number ?: '—' }}</span></div>
                                    <div class="rx-unit-cell"><span class="rx-field-label">Property no.</span><span class="rx-view-val">{{ $u->property_number ?: '—' }}</span></div>
                                </div>
                            @endforeach
                            @if($units->count() < $qty)
                                <p class="note" style="color:#b45309;">{{ $qty - $units->count() }} piece(s) were recorded without serial/property details.</p>
                            @endif
                        </div>
                    @elseif($units->isEmpty() && $installed === null && $stocked === null)
                        <p class="note" style="margin-top:8px;">No individual units were recorded for this line (consumable or received before unit tracking).</p>
                    @endif
                </div>
            @endforeach
        </div>
        @if(empty($purchaseRequest->items))
            <p class="rx-hint">No items were recorded on this purchase request.</p>
        @endif
    @else
    <form method="POST" action="{{ route('purchase_requests.receive', $purchaseRequest->id) }}">
        @csrf
        @foreach($purchaseRequest->items as $idx => $item)
            @php $qty = max(1, (int)($item['quantity'] ?? 1)); @endphp
            <div class="rx-line" data-line="{{ $idx }}">
                <div class="rx-line-top">
                    <span class="rx-line-no">{{ $idx + 1 }}</span>
                    <span class="rx-line-desc">{{ $item['description'] ?? ('Item ' . ($idx + 1)) }} <em>&times;{{ $qty }}</em></span>
                </div>
                <div class="rx-cols">
                    <div>
                        <label class="rx-field-label" for="part-{{ $idx }}">Inventory item</label>
                        <select id="part-{{ $idx }}" class="rx-input" name="lines[{{ $idx }}][part_id]" required aria-label="Match part for line {{ $idx + 1 }}" onchange="rxPartChoice(this)">
                            <option value="">&mdash; select from Parts list &mdash;</option>
                            @foreach(($partsList ?? []) as $p)
                                <option value="{{ $p['id'] }}" data-tracked="{{ !empty($p['requires_unit_tracking']) ? '1' : '0' }}">{{ $p['item_name'] }} ({{ $p['unit'] }}){{ $p['requires_unit_tracking'] ? ' &middot; serialized' : '' }}</option>
                            @endforeach
                                <option value="new" data-tracked="1">Not in the list? Create new&hellip;</option>
                        </select>
                        <div class="rx-newpart" data-newpart hidden>
                            <div class="rx-field-label" style="margin-top:8px;">New item details</div>
                            <input type="text" class="rx-input" name="lines[{{ $idx }}][new_part_name]" value="{{ old("lines.$idx.new_part_name", $item['description'] ?? '') }}" placeholder="Item name (prefilled from PR)" maxlength="190" aria-label="New item name for line {{ $idx + 1 }}">
                            <select class="rx-input" name="lines[{{ $idx }}][new_part_unit]" aria-label="Unit of measure for new item line {{ $idx + 1 }}" style="margin-top:6px;">
                                @foreach(['pcs', 'box', 'set', 'pair', 'pack'] as $u)
                                    <option value="{{ $u }}" {{ ($item['unit'] ?? 'pcs') === $u ? 'selected' : '' }}>{{ $u }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <span class="rx-field-label">Where did it go?</span>
                        <div class="rx-dest" role="radiogroup" aria-label="Destination for line {{ $idx + 1 }}">
                            <label data-d="stock-in">
                                <input type="radio" name="lines[{{ $idx }}][destination]" value="stock-in" required onchange="rxToggleUnits(this)">
                                <span><span class="t">Add to inventory</span><span class="d">Stored in Parts &amp; Consumables &mdash; issued later to whoever needs it.</span></span>
                            </label>
                            <label data-d="direct-asset" @class(['is-disabled' => !(isset($linkedAsset) && $linkedAsset)])>
                                <input type="radio" name="lines[{{ $idx }}][destination]" value="direct-asset" required onchange="rxToggleUnits(this)" @disabled(!(isset($linkedAsset) && $linkedAsset))>
                                <span><span class="t">Install on asset</span><span class="d">Straight onto {{ isset($linkedAsset) && $linkedAsset ? ($linkedAsset->asset_code ?? ('#' . $linkedAsset->asset_id)) : 'the linked asset (unavailable)' }}.</span></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="rx-unit-grid" data-units>
                    <div class="rx-unit-title"><i class="fa-solid fa-barcode" style="margin-right:5px;"></i>Serial / property numbers per piece ({{ $qty }})</div>
                    @for($u = 0; $u < $qty; $u++)
                        <div class="rx-unit-row">
                            <input type="text" class="rx-input" name="lines[{{ $idx }}][units][{{ $u }}][serial_number]" placeholder="Serial no. (e.g. KR8220Y2BS)" maxlength="100" aria-label="Serial number unit {{ $u + 1 }}">
                            <input type="text" class="rx-input" name="lines[{{ $idx }}][units][{{ $u }}][property_number]" placeholder="Property no. (e.g. 2026-0445)" maxlength="100" aria-label="Property number unit {{ $u + 1 }}">
                        </div>
                    @endfor
                    <p class="note">Required for serialized items &mdash; these become permanent tracking IDs on the asset's history.</p>
                </div>
            </div>
        @endforeach
        <div class="rx-confirm-bar">
            <span class="txt">Confirming closes <b>{{ $purchaseRequest->pr_number }}</b> as <b>Delivered</b> &mdash; this cannot be undone.</span>
            <button type="submit" class="rxb rxb-green" aria-label="Confirm delivery of all items">
                <i class="fa-solid fa-circle-check"></i>Confirm delivery
            </button>
        </div>
    </form>
    @endif
</div>
