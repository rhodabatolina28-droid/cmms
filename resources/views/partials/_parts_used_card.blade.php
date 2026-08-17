{{--
    🧰 Parts Used card — shared partial (self-contained, inline styles para gumana sa
    Maintenance at ICT form layouts). Ipinapakita ang parts units na ginamit sa request
    (parts_stock_units.request_id). Phase 5: real data.
--}}
@php
    $partsUsed = (isset($request) && $request) ? collect(\App\Models\PartUnit::with('part:id,item_name')->where('request_id', $request->id)->get()) : collect();
@endphp
<div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; max-width:1100px; margin:18px auto;">
    <div style="background:linear-gradient(135deg,#f8fafc,#f1f5f9); padding:14px 20px; border-bottom:1px solid #e2e8f0; font-weight:800; font-size:14px; color:#1e293b; text-transform:uppercase; letter-spacing:.06em; display:flex; align-items:center; gap:10px;">
        <i class="fa-solid fa-toolbox" style="color:#0038A8;"></i> Parts Used
    </div>
    <div style="padding:20px; color:#94a3b8; font-size:13px;">
        @if($partsUsed->isEmpty())
            Walang parts/consumables pang ginamit sa request na ito.
        @else
            @foreach($partsUsed as $au)
                <div style="display:flex; justify-content:space-between; gap:8px; padding:8px 0; border-bottom:1px solid #f1f5f9; color:#334155;">
                    <strong>{{ $au->part?->item_name ?? 'Part' }}</strong>
                    <span style="color:#64748b; text-align:right;">{{ $au->serial_number ?: '—' }}{{ $au->property_number ? ' · ' . $au->property_number : '' }}</span>
                </div>
            @endforeach
        @endif
    </div>
</div>