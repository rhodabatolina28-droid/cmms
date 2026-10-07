{{--
    1" x 1" (25.4mm) QR sticker — QR Print + Scan Hub Plan §9.5.
    Shared by:
      - inventory/qr-sticker.blade.php (single print — has the print() script)
      - inventory/qr-batch.blade.php print grid (fetched via ?fragment=1)
    Rules: 1 sticker per SET (parent only); components point at their parent;
    the SET flag carries NO count — live count lives on the scan hub, so adding
    components never invalidates a printed sticker (zero reprint, §9.7).
    Keep the .sticker CSS in sync between qr-sticker and qr-batch.
--}}
@php
    $isComponent = !empty($asset->parent_asset_id);
    // LIVE components only (§9.7): a disposed/scrapped child must not keep the
    // "SET" flag alive on a printed sticker — the scan hub panel already excludes them.
    $componentsCount = $isComponent ? 0 : $asset->components()
        ->whereNotIn('status', ['For Disposal', 'Scrapped'])
        ->count();
@endphp
<div class="sticker{{ $isComponent ? ' sticker-component' : ($componentsCount > 0 ? ' sticker-set' : '') }}">
    <div class="qr">{!! $asset->qr_code !!}</div>
    <div class="s-id">#{{ $asset->asset_id }}</div>
    <div class="s-name">{{ $asset->item_name }}</div>
    @if($isComponent)
        <div class="s-flag">Component of #{{ $asset->parent_asset_id }}</div>
    @elseif($componentsCount > 0)
        <div class="s-flag">▣ SET — scan for list</div>
    @elseif($asset->serial_number)
        <div class="s-serial">{{ $asset->serial_number }}</div>
    @endif
</div>
