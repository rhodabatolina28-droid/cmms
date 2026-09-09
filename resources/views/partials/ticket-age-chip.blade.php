{{-- D2: bucket-colored ticket age chip — include with ['req' => $req].
     Shows ONLY on active tickets (F5) — terminal statuses are history, not alarms. --}}
@if($req->should_show_age)
@php
    [$ageBg, $ageFg] = match($req->aging_bucket) {
        'red'    => ['#fee2e2', '#991b1b'],
        'orange' => ['#ffedd5', '#9a3412'],
        'yellow' => ['#fef9c3', '#854d0e'],
        default  => ['#ecfdf5', '#047857'],
    };
@endphp
<span style="display:inline-flex;align-items:center;gap:4px;margin-top:4px;padding:2px 9px;border-radius:10px;font-size:10px;font-weight:700;background:{{ $ageBg }};color:{{ $ageFg }};white-space:nowrap;">
    <i class="fa-regular fa-clock"></i> {{ $req->age_display }}
</span>
@endif
