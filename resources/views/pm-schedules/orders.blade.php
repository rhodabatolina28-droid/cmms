@php $pageTitle = 'PM Work Orders'; @endphp
@extends('layouts.app')
@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('styles')
<style nonce="{{ $cspNonce }}">
    .orders-card { background:white; border-radius:14px; border:1px solid #e2e8f0; padding:24px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); }
    .orders-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px; }
    .orders-title { font-size:20px; font-weight:800; color:#1e293b; margin:0; }
    .back-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; background:#f1f5f9; color:#475569; border-radius:8px; text-decoration:none; font-size:12px; font-weight:700; }
    .back-btn:hover { background:#e2e8f0; }
    .filter-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
    .filter-btn { padding:6px 14px; border-radius:20px; font-size:11px; font-weight:700; text-decoration:none; border:2px solid #e2e8f0; color:#64748b; background:white; }
    .filter-btn.active { border-color:#0038A8; color:#0038A8; background:#eff6ff; }
    .table-wrap { overflow-x:auto; border-radius:10px; border:1px solid #e2e8f0; }
    .table-orders { width:100%; border-collapse:collapse; min-width:700px; }
    .thead-row { background:linear-gradient(135deg,#f8fafc,#f1f5f9); }
    .th { padding:12px 14px; font-size:10px; font-weight:700; text-transform:uppercase; color:#475569; text-align:left; border-bottom:2px solid #0038A8; }
    .tr { border-bottom:1px solid #f1f5f9; transition:background 0.15s; }
    .tr:hover { background:#f8fafc; }
    .td { padding:11px 14px; font-size:13px; color:#1e293b; }
    .td-num a { color:#0038A8; font-weight:700; text-decoration:none; font-size:12px; }
    .status-pill { display:inline-block; padding:3px 10px; border-radius:20px; font-size:9px; font-weight:800; text-transform:uppercase; }
    .pill-scheduled { background:#fef3c7; color:#92400e; }
    .pill-ongoing { background:#dbeafe; color:#1e40af; }
    .pill-awaiting { background:#e0e7ff; color:#3730a3; }
    .pill-completed { background:#dcfce7; color:#166534; }
    .pill-default { background:#f1f5f9; color:#64748b; }
    .empty-state { text-align:center; padding:40px; color:#94a3b8; }
    .action-btn { display:inline-flex; align-items:center; gap:5px; padding:6px 12px; background:#0038A8; color:white; border-radius:6px; font-size:10px; font-weight:700; text-decoration:none; }
    .action-btn:hover { background:#002d8c; }
    .paginator { margin-top:16px; }
    .count-badge { background:#0038A8; color:white; border-radius:20px; padding:2px 8px; font-size:11px; font-weight:700; margin-left:8px; }
</style>
@endsection

@section('content')
<div class="page-wrap">
    <div class="orders-card">

        <div class="orders-header">
            <div>
                <h1 class="orders-title">
                    PM Work Orders
                    <span class="count-badge">{{ $orders->total() }}</span>
                </h1>
                <p style="margin:4px 0 0;font-size:12px;color:#64748b;">All auto-generated preventive maintenance work orders</p>
            </div>
            <a href="{{ route('pm-schedules.index') }}" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i> Back to PM Schedules
            </a>
        </div>

        {{-- Status Filter --}}
        <div class="filter-bar">
            <a href="{{ route('pm-schedules.orders') }}" class="filter-btn {{ $status === 'all' ? 'active' : '' }}">All</a>
            <a href="{{ route('pm-schedules.orders', ['status' => 'Scheduled']) }}" class="filter-btn {{ $status === 'Scheduled' ? 'active' : '' }}">To Do</a>
            <a href="{{ route('pm-schedules.orders', ['status' => 'Ongoing']) }}" class="filter-btn {{ $status === 'Ongoing' ? 'active' : '' }}">In Progress</a>
            <a href="{{ route('pm-schedules.orders', ['status' => 'Awaiting Signature']) }}" class="filter-btn {{ $status === 'Awaiting Signature' ? 'active' : '' }}">Awaiting Signature</a>
            <a href="{{ route('pm-schedules.orders', ['status' => 'Completed']) }}" class="filter-btn {{ $status === 'Completed' ? 'active' : '' }}">Completed</a>
        </div>

        @if($orders->isEmpty())
            <div class="empty-state">
                <i class="fa-solid fa-clipboard-list" style="font-size:40px;margin-bottom:12px;opacity:0.4;"></i>
                <p style="font-size:14px;font-weight:600;">No work orders found</p>
                <p style="font-size:12px;">
                    @if($status !== 'all')
                        No <strong>{{ $status }}</strong> work orders at this time.
                    @else
                        No PM work orders generated yet.
                    @endif
                </p>
            </div>
        @else
            <div class="table-wrap">
                <table class="table-orders">
                    <thead>
                        <tr class="thead-row">
                            <th class="th">PM #</th>
                            <th class="th">Employee</th>
                            <th class="th">Division</th>
                            <th class="th">Assigned To</th>
                            <th class="th">Date Generated</th>
                            <th class="th">Status</th>
                            <th class="th" style="text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                        <tr class="tr">
                            <td class="td td-num">
                                <a href="{{ route('maintenance.edit', $order->id) }}">{{ $order->request_number }}</a>
                            </td>
                            <td class="td">{{ $order->requestor_name ?? '—' }}</td>
                            <td class="td" style="color:#475569;font-size:12px;">{{ $order->office ?? '—' }}</td>
                            <td class="td">{{ $order->assignedTo?->full_name ?? '—' }}</td>
                            <td class="td" style="font-size:12px;color:#64748b;">{{ $order->created_at->format('M d, Y') }}</td>
                            <td class="td">
                                @php
                                    $pillClass = match($order->status) {
                                        'Scheduled'         => 'pill-scheduled',
                                        'Ongoing'           => 'pill-ongoing',
                                        'Awaiting Signature'=> 'pill-awaiting',
                                        'Completed'         => 'pill-completed',
                                        default             => 'pill-default',
                                    };
                                    $pillLabel = match($order->status) {
                                        'Scheduled' => 'To Do',
                                        'Ongoing'   => 'In Progress',
                                        default     => $order->status,
                                    };
                                @endphp
                                <span class="status-pill {{ $pillClass }}">{{ $pillLabel }}</span>
                            </td>
                            <td class="td" style="text-align:center;">
                                <a href="{{ route('maintenance.edit', $order->id) }}" class="action-btn">
                                    <i class="fa-solid fa-arrow-right"></i>
                                    {{ $order->status === 'Scheduled' ? 'Start' : 'View' }}
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="paginator">
                {{ $orders->links() }}
            </div>
        @endif

    </div>
</div>
@endsection
