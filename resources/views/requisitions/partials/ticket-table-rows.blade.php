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
        @if($t->requisitions->isNotEmpty())
            @php $latest = $t->requisitions->sortByDesc('created_at')->first(); @endphp
            <a href="{{ route('requisitions.show', $latest->id) }}" class="cmms-btn-secondary">Latest REQ</a>
        @endif
        <a href="{{ route('ict.show', $t->id) }}" class="cmms-btn-secondary" target="_blank">Job order</a>
    </td>
</tr>
@endforeach
