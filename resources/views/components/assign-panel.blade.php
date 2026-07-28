@props([
    'show' => false,
    'text' => '',
    'itPersonnel' => [],
    'currentAssignee' => null,
    'name' => 'assigned_to',
])

@if($show)
<div class="assign-panel">
    <div class="assign-panel-label">
        <i class="fa-solid fa-user-gear"></i> Assign IT Personnel
    </div>
    <p class="assign-panel-text">{{ $text }}</p>
    <div class="assign-flex">
        <select name="{{ $name }}" class="assign-select">
            @foreach($itPersonnel as $it)
                <option value="{{ $it->id }}" {{ ($currentAssignee ?? null)?->id === $it->id ? 'selected' : '' }}>
                    {{ $it->full_name }}
                </option>
            @endforeach
        </select>
        <button type="submit" class="assign-btn">Assign</button>
    </div>
    @if($currentAssignee)
        <div class="assign-current">Currently assigned: {{ $currentAssignee->full_name }}</div>
    @endif
</div>
@endif
