@extends('layouts.app')

@section('title', 'Physical Count | NCMB ICT System')
@section('page-title', 'Physical Inventory Count')

@section('styles')
<style nonce="{{ $cspNonce }}">
    .pc-container { width: 100%; margin-top: -10px; animation: fadeInSlide 0.4s ease-out; }
    .polish-card { background: white; border-radius: 10px; border: 1px solid #e2e8f0; overflow: hidden; }
    .card-header-accent { background: #f8fafc; padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .card-body-content { padding: 20px 24px; }
    .btn-primary { background: #0038A8; color: white; border: none; padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
    .btn-primary:hover { background: #002d8c; color: white; }
    .session-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid #f1f5f9; }
    .session-row:last-child { border-bottom: none; }
    .session-status { padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
    .status-ongoing { background: #dcfce7; color: #166534; }
        .status-completed { background: #f1f5f9; color: #475569; }
        .card-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; }
        .icon-box-blue { margin-right: 10px; color: #0038A8; }
        .card-subtitle { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .inline-form { display:inline; }
        .alert-success { background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 18px; }
        .alert-info { background: #eff6ff; border: 1px solid #93c5fd; color: #1e40af; padding: 12px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 18px; }
        .ongoing-box { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .ongoing-label { font-size: 11px; font-weight: 800; color: #15803d; text-transform: uppercase; }
        .ongoing-date { font-size: 14px; font-weight: 700; color: #1e293b; margin-top: 2px; }
        .section-h4 { font-size: 14px; font-weight: 800; color: #1e293b; margin: 0 0 14px; }
        .empty-state { color: #94a3b8; font-size: 13px; text-align: center; padding: 40px 0; }
        .empty-icon { font-size: 24px; display: block; margin-bottom: 10px; }
        .session-name { font-weight: 700; font-size: 14px; color: #1e293b; }
        .session-meta { font-size: 12px; color: #64748b; margin-top: 2px; }
        .session-actions { display: flex; align-items: center; gap: 12px; }
        .view-link { color: #0038A8; font-size: 12px; font-weight: 700; text-decoration: none; }
        .pagination-wrap { margin-top: 16px; }
        @media (max-width: 767px) {
            input[type="checkbox"] { display: none !important; }
            .card-header-accent { flex-direction: column !important; align-items: flex-start !important; gap: 10px !important; }
            .card-header-accent .btn-primary { width: 100% !important; justify-content: center !important; }
            .ongoing-box { flex-direction: column !important; align-items: flex-start !important; gap: 12px !important; }
            .ongoing-box .btn-primary { width: 100% !important; justify-content: center !important; }
            .session-row { flex-direction: column !important; align-items: flex-start !important; gap: 8px !important; }
            .session-actions { width: 100% !important; }
            .session-actions a,
            .session-actions form { width: 100% !important; }
            .session-actions .btn-primary,
            .session-actions .btn-secondary { width: 100% !important; justify-content: center !important; }
        }
    </style>
@endsection

@section('content')
<div class="pc-container">
    <div class="polish-card">
        <div class="card-header-accent">
            <div>
                <h3 class="card-title">
                    <i class="fa-solid fa-clipboard-check icon-box-blue"></i>
                    Physical Count
                </h3>
                <p class="card-subtitle">
                    Record physical inventory counts. Mark assets as Present, Missing, or Damaged.
                </p>
            </div>
            <form method="POST" action="{{ route('physical-count.store') }}" class="inline-form" id="startCountForm">
                @csrf
                <button type="button" id="startCountBtn" class="btn-primary">
                    <i class="fa-solid fa-play"></i> Start New Count
                </button>
            </form>
        </div>

        <div class="card-body-content">
            @if(session('success'))
                <div class="alert-success">
                    <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
                </div>
            @endif
            @if(session('info'))
                <div class="alert-info">
                    <i class="fa-solid fa-circle-info"></i> {{ session('info') }}
                </div>
            @endif

            @if($ongoing)
                <div class="ongoing-box">
                    <div>
                        <span class="ongoing-label">Ongoing Session</span>
                        <div class="ongoing-date">
                            Started {{ $ongoing->started_at->format('M d, Y h:i A') }}
                        </div>
                    </div>
                    <a href="{{ route('physical-count.show', $ongoing->id) }}" class="btn-primary">
                        <i class="fa-solid fa-arrow-right"></i> Continue Count
                    </a>
                </div>
            @endif

            <h4 class="section-h4">Session History</h4>

            @if($sessions->isEmpty())
                <p class="empty-state">
                    <i class="fa-solid fa-box-open empty-icon"></i>
                    No physical count sessions yet. Click "Start New Count" to begin.
                </p>
            @else
                @foreach($sessions as $session)
                    <div class="session-row">
                        <div>
                            <div class="session-name">
                                {{ $session->started_at->format('M d, Y h:i A') }}
                            </div>
                            <div class="session-meta">
                                Started by {{ $session->startedBy->full_name ?? 'Unknown' }}
                                @if($session->status === 'Completed')
                                    &middot; Completed {{ $session->completed_at?->format('M d, Y h:i A') }}
                                @endif
                            </div>
                        </div>
                        <div class="session-actions">
                            <span class="session-status status-{{ $session->status === 'Completed' ? 'completed' : 'ongoing' }}">
                                {{ $session->status }}
                            </span>
                            <a href="{{ route('physical-count.show', $session->id) }}" class="view-link">
                                View <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                @endforeach

                @if($sessions->hasPages())
                    <div class="pagination-wrap">{{ $sessions->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
document.getElementById('startCountBtn')?.addEventListener('click', function() {
    @if($ongoing)
        Swal.fire({
            icon: 'warning',
            title: 'Session Already Active',
            text: 'You have an ongoing physical count session. Please complete it before starting a new one.',
            confirmButtonColor: '#0038A8',
            confirmButtonText: 'Go to Session'
        }).then(function(result) {
            if (result.isConfirmed) {
                window.location.href = '{{ route("physical-count.show", $ongoing->id) }}';
            }
        });
    @else
        Swal.fire({
            icon: 'question',
            title: 'Start Physical Count?',
            text: 'This will begin a new inventory count session. Make sure all supply team members are ready.',
            showCancelButton: true,
            confirmButtonColor: '#0038A8',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="fa-solid fa-play"></i> Start Count',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                document.getElementById('startCountForm').submit();
            }
        });
    @endif
});
</script>
@endsection
