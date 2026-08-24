@php
    /** @var \App\Models\Requisition $requisition */
    $variant = $variant ?? 'full';
    $st = strtolower($requisition->status ?? 'pending');
    $isRejected = $st === 'rejected';

    $steps = [
        [
            'key' => 'submitted',
            'label' => 'Request filed',
            'short' => 'Filed',
            'note' => 'Submitted to Property and Supply Office',
        ],
        [
            'key' => 'review',
            'label' => 'Supply review',
            'short' => 'Review',
            'note' => 'Verification of items and justification',
        ],
        [
            'key' => 'approved',
            'label' => 'Approved',
            'short' => 'Approved',
            'note' => 'Cleared for release of property',
        ],
        [
            'key' => 'issued',
            'label' => 'Property released',
            'short' => 'Issued',
            'note' => 'Items released to ICT unit',
        ],
    ];

    $stateFor = function (string $key) use ($st, $isRejected): string {
        if ($isRejected) {
            return match ($key) {
                'submitted' => 'done',
                'review' => 'rejected',
                default => 'cancelled',
            };
        }
        return match ($key) {
            'submitted' => 'done',
            'review' => $st === 'pending' ? 'active' : 'done',
            'approved' => $st === 'approved' ? 'active' : ($st === 'issued' ? 'done' : 'upcoming'),
            'issued' => $st === 'issued' ? 'done' : 'upcoming',
        };
    };

    $dateFor = function (string $key) use ($requisition, $st, $isRejected): ?string {
        if ($key === 'submitted') {
            return $requisition->created_at?->format('d M Y · h:i A');
        }
        if ($key === 'review' && ($isRejected || in_array($st, ['approved', 'issued'], true))) {
            return $requisition->reviewed_at?->format('d M Y · h:i A') ?? null;
        }
        if ($key === 'approved' && in_array($st, ['approved', 'issued'], true)) {
            return $requisition->reviewed_at?->format('d M Y · h:i A') ?? null;
        }
        if ($key === 'issued' && $st === 'issued') {
            return $requisition->reviewed_at?->format('d M Y · h:i A') ?? null;
        }
        return null;
    };

    $actorFor = function (string $key) use ($requisition, $st, $isRejected): ?string {
        if ($key === 'submitted') {
            return $requisition->requester?->full_name;
        }
        if ($key === 'review' && ($isRejected || in_array($st, ['approved', 'issued'], true))) {
            return $requisition->reviewer?->full_name;
        }
        if ($key === 'approved' && in_array($st, ['approved', 'issued'], true)) {
            return $requisition->reviewer?->full_name;
        }
        if ($key === 'issued' && $st === 'issued') {
            return $requisition->reviewer?->full_name;
        }
        return null;
    };

    $currentLabel = match ($st) {
        'pending' => 'Awaiting supply review',
        'approved' => 'Approved — pending release',
        'issued' => 'Completed — property issued',
        'rejected' => 'Disapproved by Supply Office',
        default => ucfirst($st),
    };
@endphp

<div class="cmms-status-tracker cmms-status-tracker--{{ $variant }}" role="list" aria-label="Request processing status">
    @if($variant === 'full')
        <div class="cmms-st-summary">
            <div class="cmms-st-summary-main">
                <span class="cmms-status-badge cmms-status-{{ $st }}">{{ ucfirst($requisition->status) }}</span>
                <span class="cmms-st-current">{{ $currentLabel }}</span>
            </div>
            @if($requisition->ticket)
                <span class="cmms-st-jo-ref">Job order {{ $requisition->ticket->display_number ?? $requisition->ticket->request_number }}
                    @if($requisition->ticket->status)
                        · {{ $requisition->ticket->status }}
                    @endif
                </span>
            @endif
        </div>
    @endif

    <div class="cmms-st-steps">
        @foreach($steps as $index => $step)
            @php
                $state = $stateFor($step['key']);
                $date = $dateFor($step['key']);
                $actor = $actorFor($step['key']);
                $isLast = $index === count($steps) - 1;
            @endphp
            <div class="cmms-st-step is-{{ $state }}" role="listitem">
                @if(!$isLast)
                    <span class="cmms-st-line" aria-hidden="true"></span>
                @endif
                <span class="cmms-st-dot" aria-hidden="true">
                    @if($state === 'done')
                        <span class="cmms-st-dot-inner"><i class="fa-solid fa-check"></i></span>
                    @elseif($state === 'rejected')
                        <span class="cmms-st-dot-inner"><i class="fa-solid fa-xmark"></i></span>
                    @elseif($state === 'active')
                        <span class="cmms-st-dot-inner cmms-st-pulse"></span>
                    @else
                        <span class="cmms-st-dot-inner"></span>
                    @endif
                </span>
                <div class="cmms-st-body">
                    <span class="cmms-st-label">{{ $variant === 'compact' ? $step['short'] : $step['label'] }}</span>
                    @if($variant === 'full')
                        <span class="cmms-st-note">{{ $state === 'rejected' ? 'Disapproved' : $step['note'] }}</span>
                        @if($date)
                            <span class="cmms-st-date">{{ $date }}</span>
                        @elseif($state === 'active')
                            <span class="cmms-st-date cmms-st-date--pending">In progress</span>
                        @elseif($state === 'upcoming' || $state === 'cancelled')
                            <span class="cmms-st-date cmms-st-date--muted">—</span>
                        @endif
                        @if($actor && in_array($state, ['done', 'active', 'rejected'], true))
                            <span class="cmms-st-actor">{{ $state === 'rejected' ? 'By' : ($state === 'done' ? 'By' : 'Assigned') }}: {{ $actor }}</span>
                        @endif
                    @else
                        @if($state === 'active')
                            <span class="cmms-st-date cmms-st-date--pending">Current</span>
                        @elseif($state === 'rejected')
                            <span class="cmms-st-date">Disapproved</span>
                        @elseif($date)
                            <span class="cmms-st-date">{{ $step['key'] === 'submitted' ? $requisition->created_at->format('d M Y') : ($requisition->reviewed_at?->format('d M Y') ?? '') }}</span>
                        @endif
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
