<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $asset->item_name }} | Asset Info</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style nonce="{{ $cspNonce }}">
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            padding: 16px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .card {
            background: white; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            max-width: 480px; width: 100%; overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #0038A8, #1e40af);
            color: white; padding: 24px;
        }
        .header h1 { font-size: 20px; margin-bottom: 4px; }
        .header .sub { font-size: 13px; opacity: 0.8; }
        .header .badge {
            display: inline-block; background: rgba(255,255,255,0.2);
            padding: 3px 12px; border-radius: 20px; font-size: 12px;
            margin-top: 8px; font-weight: 600;
        }
        .body { padding: 20px 24px; }
        .row {
            display: flex; padding: 10px 0; border-bottom: 1px solid #f1f5f9;
        }
        .row:last-child { border-bottom: none; }
        .label {
            width: 130px; font-size: 12px; color: #64748b; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.03em; flex-shrink: 0;
        }
        .value { flex: 1; font-size: 14px; color: #1e293b; font-weight: 500; }
        .status-badge {
            display: inline-block; padding: 2px 10px; border-radius: 12px;
            font-size: 12px; font-weight: 600;
        }
        .status-Serviceable { background: #d1fae5; color: #047857; }
        .status-Unserviceable { background: #fef3c7; color: #b45309; }
        .status-For\\,Disposal, .status-Scrapped { background: #fee2e2; color: #b91c1c; }
        .status-For\\,Repair { background: #dbeafe; color: #1d4ed8; }

        .section-title {
            font-size: 13px; font-weight: 700; color: #0038A8;
            text-transform: uppercase; letter-spacing: 0.03em;
            margin-top: 20px; margin-bottom: 8px; padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }
        .history-item {
            padding: 10px 0; border-bottom: 1px solid #f8fafc;
            font-size: 13px; color: #475569;
        }
        .history-item:last-child { border-bottom: none; }
        .history-date { font-weight: 600; color: #1e293b; }
        .history-empty { color: #94a3b8; font-size: 13px; padding: 10px 0; }

        .pm-info {
            background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px;
            padding: 12px 16px; margin-top: 12px; font-size: 13px;
        }
        .pm-info .pm-label { color: #1e40af; font-weight: 700; font-size: 12px; text-transform: uppercase; }
        .pm-info .pm-value { color: #1e293b; margin-top: 2px; }
        .pm-info .pm-row { display: flex; justify-content: space-between; padding: 4px 0; }
        .schedule-chip {
            display: inline-block; padding: 2px 10px; background: #dbeafe; color: #1e40af;
            border-radius: 16px; font-size: 11px; font-weight: 700; margin: 2px 4px 2px 0;
        }

        .pm-value-muted { color: #64748b; }
        .pm-status-chip { display: inline-block; padding: 2px 10px; background: #fef3c7; color: #92400e; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .pm-link { color: #0038A8; font-weight: 600; text-decoration: none; }
        .pm-due { color: #dc2626; font-size: 11px; }
        .ict-info-box { background: #fff7ed; border-color: #fed7aa; }
        .ict-row-center { align-items: center; }
        .ict-ticket-value { display: flex; align-items: center; gap: 8px; }
        .ict-ticket-link { color: #0038A8; font-weight: 600; text-decoration: none; }
        .ict-status-badge { background: #fef3c7; color: #92400e; font-size: 10px; }
        .history-status-badge { background: #f1f5f9; color: #475569; font-size: 10px; margin-left: 4px; }
        .history-desc { color: #64748b; font-size: 12px; }

        .actions {
            padding: 16px 24px 24px; display: flex; flex-direction: column; gap: 10px;
        }
        .btn {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px; border-radius: 8px; font-size: 14px; font-weight: 600;
            text-decoration: none; border: none; cursor: pointer; transition: all 0.2s;
        }
        .btn-primary { background: #0038A8; color: white; }
        .btn-primary:hover { background: #002d8a; }
        .btn-success { background: #059669; color: white; }
        .btn-success:hover { background: #047857; }
        .btn-outline { background: white; color: #475569; border: 1px solid #cbd5e1; }
        .btn-outline:hover { background: #f8fafc; }

        .back-link {
            align-self: flex-start; margin-bottom: 12px; max-width: 480px; width: 100%;
        }
        .back-link a {
            color: #475569; text-decoration: none; font-size: 13px; font-weight: 600;
            display: flex; align-items: center; gap: 6px;
        }
        .back-link a:hover { color: #0038A8; }

        .footer {
            text-align: center; padding: 20px; font-size: 11px; color: #94a3b8;
            text-transform: uppercase; letter-spacing: 1px; max-width: 480px;
        }

        @media (max-width: 480px) {
            body { padding: 8px; }
            .row { flex-direction: column; gap: 2px; }
            .label { width: auto; }
            .pm-row { flex-wrap: wrap; gap: 4px; }
            .history-item { word-break: break-word; }
        }
    </style>
</head>
<body>
    <div class="back-link">
        <a href="{{ route($user->role === 'super_admin' ? 'dashboard.super-admin' : ($user->canProcessSupply() ? 'dashboard.admin' : 'dashboard.it')) }}"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="card">
        <div class="header">
            <h1><i class="fa-solid fa-qrcode"></i> {{ $asset->item_name }}</h1>
            <div class="sub">Asset ID: {{ $asset->asset_id }}</div>
            <div class="badge">{{ $asset->category }}</div>
        </div>

        <div class="body">
            @if($asset->serial_number)
            <div class="row">
                <span class="label">Serial No.</span>
                <span class="value">{{ $asset->serial_number }}</span>
            </div>
            @endif
            @if($asset->brand)
            <div class="row">
                <span class="label">Brand</span>
                <span class="value">{{ $asset->brand }}</span>
            </div>
            @endif
            @if($asset->model)
            <div class="row">
                <span class="label">Model</span>
                <span class="value">{{ $asset->model }}</span>
            </div>
            @endif
            <div class="row">
                <span class="label">Status</span>
                <span class="value">
                    <span class="status-badge status-{{ str_replace([' ', '/'], ['\\,', '\\,'], $asset->status) }}">{{ $asset->status }}</span>
                </span>
            </div>
            @if($asset->assignedUser)
            <div class="row">
                <span class="label">End User</span>
                <span class="value">{{ $asset->assignedUser->full_name }}@if($asset->assignedUser->office) ({{ $asset->assignedUser->office }})@endif</span>
            </div>
            @endif
            @if($asset->par_number)
            <div class="row">
                <span class="label">PAR No.</span>
                <span class="value">{{ $asset->par_number }}</span>
            </div>
            @endif
            @if($asset->date_acquired)
            <div class="row">
                <span class="label">Date Acquired</span>
                <span class="value">{{ \Carbon\Carbon::parse($asset->date_acquired)->format('M d, Y') }}</span>
            </div>
            @endif

            {{-- PM Schedule Info --}}
            <div class="section-title">
                <i class="fa-solid fa-calendar-check"></i> Preventive Maintenance
            </div>

            <div class="pm-info">
                @if(!$asset->assignedUser)
                    <div class="pm-row">
                        <span class="pm-label">PM Status</span>
                        <span class="pm-value pm-value-muted">Asset not assigned to any user.</span>
                    </div>
                @else
                    @php
                        $assetDate = $asset->date_acquired ? \Carbon\Carbon::parse($asset->date_acquired) : now();
                        $freqMonths = 6; // Semi-annual default
                        if ($upcomingPM && $upcomingPM->pm_schedule_id) {
                            $sched = \App\Models\PMSchedule::find($upcomingPM->pm_schedule_id);
                            if ($sched) {
                                $freqMonths = match($sched->frequency) {
                                    'Monthly' => 1, 'Quarterly' => 3, 'Semi-annual' => 6, 'Annual' => 12,
                                    default => 6
                                };
                            }
                        }
                        $lastDate = $lastPM ? \Carbon\Carbon::parse($lastPM->updated_at) : $assetDate;
                        $nextDate = $lastDate->copy()->addMonths($freqMonths);
                        
                        // Determine PM status
                        $hasUpcoming = $upcomingPM && in_array($upcomingPM->status, ['Scheduled', 'Ongoing']);
                        $hasCompleted = $lastPM && $lastPM->status === 'Completed';
                    @endphp
                    
                    @if($hasUpcoming)
                        {{-- Scenario: May scheduled/ongoing PM --}}
                        <div class="pm-row">
                            <span class="pm-label">PM Status</span>
                            <span class="pm-value">
                                <span class="pm-status-chip">
                                    ⏳ {{ $upcomingPM->status === 'Scheduled' ? 'To Do' : 'In Progress' }}
                                </span>
                            </span>
                        </div>
                        <div class="pm-row">
                            <span class="pm-label">PM ID</span>
                            <span class="pm-value">
                                <a href="{{ route('maintenance.edit', $upcomingPM->id) }}" class="pm-link">
                                    {{ $upcomingPM->display_number ?? $upcomingPM->request_number }}
                                </a>
                            </span>
                        </div>
                        <div class="pm-row">
                            <span class="pm-label">Next PM</span>
                            <span class="pm-value pm-value-muted">Pending completion</span>
                        </div>
                    @elseif($hasCompleted)
                        {{-- Scenario: Completed na ang PM --}}
                        <div class="pm-row">
                            <span class="pm-label">Last PM</span>
                            <span class="pm-value">{{ $lastDate->format('M d, Y') }}</span>
                        </div>
                        <div class="pm-row">
                            <span class="pm-label">Next PM</span>
                            <span class="pm-value">
                                {{ $nextDate->format('M d, Y') }}
                                @if($nextDate->isPast() || $nextDate->isToday())
                                    <span class="pm-due">(Due)</span>
                                @endif
                            </span>
                        </div>
                    @else
                        {{-- Scenario: Wala pang PM ever --}}
                        <div class="pm-row">
                            <span class="pm-label">PM Status</span>
                            <span class="pm-value pm-value-muted">No PM record yet</span>
                        </div>
                        <div class="pm-row">
                            <span class="pm-label">Next PM</span>
                            <span class="pm-value pm-value-muted">TBD</span>
                        </div>
                    @endif
                @endif
            </div>

            {{-- ICT Ticket Info --}}
            @if($ictTicket)
            <div class="section-title">
                <i class="fa-solid fa-ticket"></i> ICT Repair Ticket
            </div>
            <div class="pm-info ict-info-box">
                <div class="pm-row ict-row-center">
                    <span class="pm-label">Ticket</span>
                    <span class="pm-value ict-ticket-value">
                        <a href="{{ route('ict.show', $ictTicket->id) }}" class="ict-ticket-link">
                            #{{ $ictTicket->display_number ?? $ictTicket->request_number }}
                        </a>
                        <span class="status-badge ict-status-badge">{{ $ictTicket->status }}</span>
                    </span>
                </div>
            </div>
            @endif

            {{-- Other Assets of User --}}
            @if($userAssets && $userAssets->count() > 0)
            <div class="section-title">
                <i class="fa-solid fa-layer-group"></i> Other Assets of {{ $asset->assignedUser->full_name ?? 'User' }}
            </div>
            @foreach($userAssets as $ua)
            <div class="history-item" style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <strong>{{ $ua->item_name }}</strong>
                    <span style="font-size:11px;color:#64748b;margin-left:6px;">{{ $ua->category }}</span>
                    <br><span style="font-size:11px;color:#64748b;">S/N: {{ $ua->serial_number ?? 'N/A' }} | PAR: {{ $ua->par_number ?? 'N/A' }}</span>
                </div>
                <span class="status-badge status-{{ str_replace([' ', '/'], ['\\,', '\\,'], $ua->status) }}">{{ $ua->status }}</span>
            </div>
            @endforeach
            @endif

            {{-- Service History --}}
            <div class="section-title">
                <i class="fa-solid fa-clock-rotate-left"></i> Recent Service History
            </div>
            @forelse($history as $h)
            <div class="history-item">
                <span class="history-date">{{ $h->created_at->format('M d, Y') }}</span>
                — {{ $h->type === 'ICT' || $h->type === 'repair' || $h->type === 'ICT Repair' ? 'ICT Repair' : 'Preventive Maintenance' }}
                <span class="status-badge history-status-badge">{{ $h->status }}</span>
                {{-- Show description for ICT repairs --}}
                @if($h->repairRequest && $h->repairRequest->repair_description)
                    <br><span class="history-desc">{{ \Illuminate\Support\Str::limit($h->repairRequest->repair_description, 80) }}</span>
                @endif
            </div>
            @empty
            <div class="history-empty">No service history found.</div>
            @endforelse
        </div>

        <div class="actions">
            @if($upcomingPM && $upcomingPM->type === 'Preventive Maintenance')
                <a href="{{ route('maintenance.edit', $upcomingPM->id) }}" class="btn btn-success">
                    <i class="fa-solid fa-screwdriver-wrench"></i> Conduct PM
                </a>
            @endif
            @if($user->role === 'super_admin')
            <a href="{{ route('super_admin.inventory.detail', $asset->asset_id) }}" class="btn btn-primary">
                <i class="fa-solid fa-eye"></i> View Full Inventory Profile
            </a>
            @endif
            <a href="{{ route($user->role === 'super_admin' ? 'dashboard.super-admin' : ($user->canProcessSupply() ? 'dashboard.admin' : 'dashboard.it')) }}" class="btn btn-outline">
                <i class="fa-solid fa-house"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <div class="footer">
        NCMB ICT Unit &bull; CMMS PORTAL
    </div>
</body>
</html>
