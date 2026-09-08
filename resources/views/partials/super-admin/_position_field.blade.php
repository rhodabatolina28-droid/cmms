{{-- D4c — Position / Designation dropdown with Department → Division → Position cascade.
     Included with $prefix ('editUser' | 'newUser'). IDs generated:
       {prefix}Position        — the select (no name; value synced to the hidden input)
       {prefix}PositionManual  — free-text revealed by "Other / Not Listed"
       {prefix}PositionValue   — hidden input[name=position] actually submitted
     Options rendered from config/priority.php::position_catalog. Filtering is
     done by _user_scripts.blade.php via data-position-dept / data-position-office. --}}
@php $positionCatalog = config('priority.position_catalog'); @endphp
<div class="form-group" style="margin-bottom:0;">
    <label class="form-label-gov">Position / Designation</label>
    <select id="{{ $prefix }}Position" class="form-input-gov">
        <option value="">— None / Not set —</option>
        @foreach ($positionCatalog['always'] as $title)
            <option value="{{ $title }}">{{ $title }}</option>
        @endforeach
        @foreach ($positionCatalog['by_department'] as $dept => $deptTitles)
            @foreach ($deptTitles as $title)
                <option value="{{ $title }}" data-position-dept="{{ $dept }}">{{ $title }}</option>
            @endforeach
        @endforeach
        @foreach ($positionCatalog['by_office'] as $office => $officeTitles)
            @foreach ($officeTitles as $title)
                <option value="{{ $title }}" data-position-office="{{ $office }}">{{ $title }}</option>
            @endforeach
        @endforeach
        <option value="__other__">— Other / Not Listed —</option>
    </select>
    <input type="text" id="{{ $prefix }}PositionManual" class="form-input-gov" placeholder="Type the position (e.g. Computer Programmer I)" style="display:none; margin-top:8px;">
    <input type="hidden" name="position" id="{{ $prefix }}PositionValue">
    <p class="form-help">Leave as "None" if not yet known — settable anytime. Official titles (Director / Chief / State Auditor) are high-official positions; use "Other" for regular staff.</p>
</div>
