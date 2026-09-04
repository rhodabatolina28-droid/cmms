@foreach($tickets as $t)
<tr>
    <td><strong>{{ $t->display_number ?? $t->request_number }}</strong></td>
    <td><span class="cmms-req-tag {{ $t->type === 'Preventive Maintenance' ? 'cmms-req-tag--issue' : 'cmms-req-tag--review' }}">{{ $t->type === 'Preventive Maintenance' ? 'PM' : 'ICT' }}</span></td>
    <td><span class="cmms-ticket-status cmms-ticket-status--{{ strtolower(str_replace(' ', '-', $t->status)) }}">{{ $t->status }}</span></td>
    <td>{{ $t->assignedTo?->full_name ?? '-' }}</td>
    <td>{{ $t->user?->full_name ?? '-' }}</td>
    <td>
        @php
            $byStatus = $t->requisitions->groupBy('status');
        @endphp
        @forelse($byStatus as $st => $group)
            <span class="req-pill cmms-status-{{ strtolower($st) }}">{{ ucfirst($st) }}: {{ $group->count() }}</span>
        @empty
            <span class="text-muted-none">None</span>
        @endforelse
    </td>
    <td class="td-nowrap">
        <div style="display:flex;align-items:center;gap:6px;">
        @if($t->requisitions->isNotEmpty())
            @php $latest = $t->requisitions->sortByDesc('created_at')->first(); @endphp
            <a href="{{ route('requisitions.show', $latest->id) }}" class="act-btn" aria-label="Open latest requisition of {{ $t->display_number ?? $t->request_number }}" title="Open latest requisition">
                <i class="fa-solid fa-file-invoice"></i>
            </a>
        @endif
        <a href="{{ $t->type === 'Preventive Maintenance' ? route('maintenance.show', $t->id) : route('ict.show', $t->id) }}" class="act-btn" target="_blank" aria-label="Open job order record of {{ $t->display_number ?? $t->request_number }}" title="Open job order record">
            <i class="fa-solid fa-file-lines"></i>
        </a>
        </div>
    </td>
</tr>
@endforeach
