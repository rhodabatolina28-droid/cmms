@php
    $pageTitle = 'Generation History — ' . $pmSchedule->schedule_name;
@endphp

@extends('layouts.app')

@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('styles')
<style nonce="{{ $cspNonce }}">
    .history-container { max-width:800px; margin:0 auto; padding:0 20px; }
    .history-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
    .back-link { display:inline-flex; align-items:center; gap:6px; color:#64748b; text-decoration:none; font-size:13px; font-weight:600; }
    .history-title { margin:0; font-size:18px; color:#1e293b; font-weight:800; }
    .history-card { background:white; border-radius:12px; border:1px solid #e2e8f0; overflow:hidden; }
    .empty-state { text-align:center; padding:40px; color:#94a3b8; }
    .empty-text { font-size:14px; }
    .history-table { width:100%; border-collapse:collapse; }
    .tr-header { background:#f8fafc; border-bottom:1px solid #e2e8f0; }
    .th-history { padding:12px 16px; text-align:left; font-size:12px; color:#64748b; text-transform:uppercase; font-weight:700; }
    .tr-row { border-bottom:1px solid #f1f5f9; }
    .td-history { padding:12px 16px; font-size:13px; color:#475569; }
    .td-history-pad { padding:12px 16px; }
    .td-history-count { padding:12px 16px; font-size:14px; font-weight:700; color:#0038A8; }
    .badge-action { display:inline-block; padding:2px 10px; background:#dbeafe; color:#1e40af; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; }
</style>
@endsection

@section('content')
<div class="history-container">
    <div class="history-header">
        <a href="{{ route('pm-schedules.show', $pmSchedule->id) }}" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Schedule
        </a>
        <h2 class="history-title">Generation History</h2>
    </div>

    <div class="history-card">
        @if($history->isEmpty())
            <div class="empty-state">
                <i class="fa-solid fa-clock-rotate-left" style="font-size:32px;color:#cbd5e1;display:block;margin-bottom:12px;"></i>
                <p class="empty-text" style="font-weight:700;color:#475569;margin-bottom:6px;">No generations yet</p>
                <p style="font-size:12px;color:#94a3b8;">Generation history will appear here once PM tickets are created from this schedule.</p>
            </div>
        @else
            <table class="history-table">
                <thead>
                    <tr class="tr-header">
                        <th class="th-history">Date</th>
                        <th class="th-history">Action</th>
                        <th class="th-history">Requests Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($history as $entry)
                        <tr class="tr-row">
                            <td class="td-history">{{ $entry->generated_at->format('M d, Y \a\t h:i A') }}</td>
                            <td class="td-history-pad">
                                <span class="badge-action">{{ $entry->action }}</span>
                            </td>
                            <td class="td-history-count">{{ $entry->generated_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
