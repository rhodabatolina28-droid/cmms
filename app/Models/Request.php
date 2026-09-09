<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Request extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'requests';

    // D2: aging fields travel with every JSON serialization (Master List API)
    protected $appends = ['age_display', 'aging_bucket', 'should_show_age'];

    // Status Constants
    public const STATUS_SCHEDULED = 'Scheduled';
    public const STATUS_PENDING = 'Pending';
    public const STATUS_ONGOING = 'Ongoing';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_CANCELLED = 'Cancelled';
    public const STATUS_REJECTED = 'Rejected';
    public const STATUS_AWAITING_PARTS = 'Awaiting Parts';
    public const STATUS_AWAITING_SIGNATURE = 'Awaiting Signature';
    public const STATUS_REFERRED_EXTERNAL = 'Referred - External';

    protected $fillable = [
        'user_id',
        'assigned_to',
        'request_number',
        'type',
        'requestor_name',
        'description',
        'region',
        'branch',
        'office',
        'division',
        'department',
        'status',
        'remarks',
        'detail_id',
        'linked_asset_id',
        'is_deleted',
        'division_admin_review_status',
        'division_admin_notes',
        'reviewed_by_admin_id',
        'reviewed_at',
        // PM auto-generation fields
        'is_auto_generated',
        'pm_schedule_id',
        'asset_id',
        'priority',
        'downtime_start',
        'downtime_end',
        'downtime_duration',
        'archive_pdf_path',
        // Date tracking
        'assigned_at',
        'completed_at',
    ];

    public function getRoutePrefix()
    {
        return $this->type === 'Preventive Maintenance' ? 'maintenance' : strtolower($this->type);
    }

    protected $casts = [
        'is_deleted' => 'boolean',
        'is_auto_generated' => 'boolean',
        'reviewed_at' => 'datetime',
        'downtime_start' => 'datetime',
        'downtime_end' => 'datetime',
        'downtime_duration' => 'integer',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Get display format: PM-NCR-RCMB-2026-0001 → PM-2026-0001
    //                        REQ-NCR-RCMB-2026-001 → ICT-2026-001
    // For multi-location: PM-NCR-RCMB-2026-0001 → PM-NCR-RCMB-2026-0001 (full)
    public function getDisplayNumberAttribute(): string
    {
        $parts = explode('-', $this->request_number);
        // Database format: PM-NCR-RCMB-2026-0001 or REQ-NCR-RCMB-2026-001
        // Parts: [0]=PM/REQ, [1]=NCR, [2]=RCMB, [3]=2026, [4]=0001
        $dbPrefix = $parts[0] ?? 'PM';
        $year = $parts[3] ?? date('Y');
        $number = $parts[4] ?? '001';
        
        // Convert REQ to ICT for display
        $displayPrefix = $dbPrefix === 'REQ' ? 'ICT' : $dbPrefix;
        
        // Short format: ICT-2026-0001 or PM-2026-0001
        $short = "{$displayPrefix}-{$year}-{$number}";
        
        // Full format with region and branch: ICT-NCR-RCMB-2026-0001
        $region = $parts[1] ?? '';
        $branch = $parts[2] ?? '';
        $full = "{$displayPrefix}-{$region}-{$branch}-{$year}-{$number}";
        
        // Return short format for display (ICT-2026-0001 or PM-2026-0001)
        return $short;
    }
    
    // Get full display format with region and branch (for multi-location backend)
    public function getFullDisplayNumberAttribute(): string
    {
        $parts = explode('-', $this->request_number);
        $dbPrefix = $parts[0] ?? 'PM';
        $year = $parts[3] ?? date('Y');
        $number = $parts[4] ?? '001';
        
        $displayPrefix = $dbPrefix === 'REQ' ? 'ICT' : $dbPrefix;
        $region = $parts[1] ?? '';
        $branch = $parts[2] ?? '';
        
        return "{$displayPrefix}-{$region}-{$branch}-{$year}-{$number}";
    }

    // Downtime accessors
    public function getFormattedDowntimeDurationAttribute(): string
    {
        if (!$this->attributes['downtime_duration']) return 'N/A';
        $minutes = (int) $this->attributes['downtime_duration'];
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        if ($hours >= 24) {
            $days = floor($hours / 24);
            $hours = $hours % 24;
            return "{$days}d {$hours}h {$mins}m";
        }
        return "{$hours}h {$mins}m";
    }

    public function getIsDowntimeAttribute(): bool
    {
        // X3 (G3): an OPEN window means the asset is still down — regardless of
        // the current status. Awaiting Parts / Awaiting Signature / Referred -
        // External all leave the asset unavailable, so keying on 'Ongoing' alone
        // hid the "currently down" indicator for those states.
        return $this->downtime_start !== null && $this->downtime_end === null;
    }

    // =========================================================================
    // D2 — Ticket Aging accessors (single source of truth for ALL aging UI)
    // =========================================================================

    /**
     * D2-a (F3): total minutes since the ticket was filed — Carbon-3-proof.
     * max(0, ...) clamps future-dated rows (never negative, never "old").
     */
    public function getAgeInMinutesAttribute(): int
    {
        return (int) max(0, $this->created_at->diffInMinutes(now()));
    }

    /**
     * D2-a: unified age buckets — 🟢 fresh (≤24h) · 🟡 1-3d · 🟠 3-7d · 🔴 7d+.
     * Exact boundaries: 1440 → yellow · 4320 → orange · 10080 → red.
     */
    public function getAgingBucketAttribute(): string
    {
        return match (true) {
            $this->age_in_minutes > 10080 => 'red',
            $this->age_in_minutes > 4320  => 'orange',
            $this->age_in_minutes > 1440  => 'yellow',
            default                       => 'green',
        };
    }

    /** D2-a: short human format — "45m" · "2h 30m" · "1d 2h" · "10d 5h". */
    public function getAgeDisplayAttribute(): string
    {
        $minutes = $this->age_in_minutes;

        if ($minutes < 60) {
            return $minutes > 0 ? "{$minutes}m" : '0h';
        }

        $hours = intdiv($minutes, 60);
        $mins  = $minutes % 60;

        if ($hours < 24) {
            return $mins > 0 ? "{$hours}h {$mins}m" : "{$hours}h";
        }

        $days = intdiv($hours, 24);
        $hrs  = $hours % 24;

        return $hrs > 0 ? "{$days}d {$hrs}h" : "{$days}d";
    }

    /**
     * D2-a (F6): THE overdue definition — a Scheduled PM task sitting 7d+.
     * Replaces the duplicated diffInDays(now()) > 7 rules in ListPmTasksAction
     * and pm-tasks.blade.php so stats and row highlighting can never disagree.
     */
    public function getIsAgingOverdueAttribute(): bool
    {
        return $this->status === self::STATUS_SCHEDULED && $this->aging_bucket === 'red';
    }

    /**
     * D2-a (F5): age chips appear on ACTIVE tickets only — Completed/Cancelled/
     * Rejected are history, not alarms.
     */
    public function getShouldShowAgeAttribute(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_ONGOING,
            self::STATUS_SCHEDULED,
            self::STATUS_AWAITING_PARTS,
            self::STATUS_AWAITING_SIGNATURE,
            self::STATUS_REFERRED_EXTERNAL,
        ], true);
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class, 'detail_id');
    }

    public function maintenanceRequest()
    {
        return $this->belongsTo(PreventiveMaintenance::class, 'detail_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function csmSurvey()
    {
        return $this->hasOne(CsmSurvey::class, 'request_id');
    }

    public function linkedAsset()
    {
        return $this->belongsTo(InventoryAsset::class, 'linked_asset_id');
    }

    public function requisitions()
    {
        return $this->hasMany(Requisition::class, 'request_id');
    }

    // Scopes
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeInOffice($query, $office)
    {
        return $query->where('office', $office);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * D4b — High-Official queue-jump lead ordering.
     * Officials (requester's position matches config/priority.php keywords) sort
     * FIRST, then everything else in whatever order follows. LEFT JOIN only —
     * one users row per request, so no row duplication; select(requests.*) keeps
     * the joined columns from clobbering the request attributes.
     */
    public function scopeOfficialsFirst($query)
    {
        $keywords = config('priority.high_official_keywords', []);
        if (empty($keywords)) {
            return $query;
        }

        $bindings = [];
        $conditions = [];
        foreach ($keywords as $keyword) {
            $conditions[] = 'LOWER(official_users.position) LIKE ?';
            $bindings[] = '%' . strtolower($keyword) . '%';
        }

        return $query
            ->leftJoin('users as official_users', 'requests.user_id', '=', 'official_users.id')
            ->select('requests.*')
            ->orderByRaw(
                'CASE WHEN official_users.id IS NOT NULL AND (' . implode(' OR ', $conditions) . ') THEN 0 ELSE 1 END',
                $bindings
            );
    }

    // Helpers
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    protected static function booted()
    {
        static::updated(function ($request) {
            // Cascade reject pending requisitions when ticket is cancelled or rejected
            if ($request->wasChanged('status')) {
                $oldStatus = $request->getOriginal('status');
                $newStatus = $request->status;
                
                if (in_array($newStatus, [self::STATUS_CANCELLED, self::STATUS_REJECTED], true)) {
                    // Auto-reject all pending requisitions for this ticket (batch update)
                    $pendingRequisitionIds = \App\Models\Requisition::where('request_id', $request->id)
                        ->where('status', \App\Models\Requisition::STATUS_PENDING)
                        ->pluck('id');
                    
                    if ($pendingRequisitionIds->isNotEmpty()) {
                        \App\Models\Requisition::whereIn('id', $pendingRequisitionIds)->update([
                            'status' => \App\Models\Requisition::STATUS_REJECTED,
                            'reviewed_by' => \Illuminate\Support\Facades\Auth::id(),
                            'reviewed_at' => now(),
                            'remarks' => "Auto-rejected: Parent ticket {$request->request_number} was {$newStatus}",
                        ]);
                        
                        // Batch notify IT personnel
                        $requestedByUsers = \App\Models\Requisition::whereIn('id', $pendingRequisitionIds)
                            ->whereNotNull('requested_by')
                            ->pluck('requested_by')
                            ->unique()
                            ->filter();
                        
                        foreach ($requestedByUsers as $userId) {
                            \App\Models\Notification::send(
                                $userId,
                                $request->id,
                                'Parts Request Rejected',
                                "Your parts request for {$request->request_number} was auto-rejected because the ticket was {$newStatus}."
                            );
                        }
                    }
                }
            }
                   if ($request->wasChanged('status')) {
                   // X1: capture BEFORE any nested $request->update() below (downtime
                   // window close) — a nested save resets the changed-attributes state
                   // and would silently disable the D6 archive trigger at the bottom.
                   $statusWasChanged = $request->wasChanged('status');
                $assetsToUpdate = collect();
                
                if ($request->linked_asset_id) {
                    $asset = \App\Models\InventoryAsset::find($request->linked_asset_id);
                    if ($asset) {
                        // Always include the linked asset (e.g., the asset picked
                        // via FOR REPAIR) so its status is restored on completion
                        // even if its custodian differs from the ticket's end user.
                        $assetsToUpdate->push($asset);
                    }

                    // Bundled (auto-generated) PMs cover ALL of the user's assets.
                    // A repair-linked asset must not stop the rest from being
                    // restored on completion - previously the bundled branch was
                    // skipped once a linked asset existed, leaving the other
                    // assets stuck at "Under Maintenance" after completion.
                    if ($request->type === 'Preventive Maintenance' && $request->is_auto_generated && $request->user_id) {
                        \App\Models\InventoryAsset::where('assigned_to_user', $request->user_id)
                            ->get()
                            ->each(function ($a) use ($assetsToUpdate) {
                                $assetsToUpdate->push($a);
                            });
                    }
                } elseif ($request->type === 'Preventive Maintenance' && $request->is_auto_generated && $request->user_id) {
                    $assets = \App\Models\InventoryAsset::where('assigned_to_user', $request->user_id)->get();
                    foreach ($assets as $a) {
                        $assetsToUpdate->push($a);
                    }
                }

                $assetsToUpdate = $assetsToUpdate->unique('asset_id');

                if ($assetsToUpdate->isNotEmpty()) {
                    $oldStatus = $request->getOriginal('status');
                    $newStatus = $request->status;
                    
                    $assignedName = 'Unassigned';
                    if ($request->assigned_to) {
                        $assignedUser = \App\Models\User::find($request->assigned_to);
                        if ($assignedUser) {
                            $assignedName = $assignedUser->full_name;
                        }
                    }

                    // Downtime tracking: start when ticket goes Ongoing
                    if ($newStatus === self::STATUS_ONGOING && !$request->downtime_start) {
                        $request->update(['downtime_start' => now()]);
                    }

                    // X1 (B2): close the window ONCE per ticket — OUTSIDE the asset
                    // loop. Previously this sat inside the loop, so on bundled PMs
                    // the first iteration wrote downtime_end and every later asset
                    // was skipped by the !$request->downtime_end guard.
                    // X1 (B1): abs() + (int) — Carbon 3 diffInMinutes() is SIGNED,
                    // so now()->diffInMinutes($past) returned NEGATIVE durations,
                    // which via increment() DECREMENTED asset totals (live data:
                    // DELL XPS8940 = -17,303 min). Calling it as
                    // $start->diffInMinutes(now()) + abs() is version-proof.
                    // X3 (G1): terminal statuses CLOSE the window too — the asset was
                    // genuinely down until the ticket left the active flow. Credit
                    // still follows the ticket type (see the loop below).
                    $downtimeDuration = null;
                    $closesWindow = in_array($newStatus, [
                        self::STATUS_COMPLETED,
                        self::STATUS_CANCELLED,
                        self::STATUS_REJECTED,
                        self::STATUS_REFERRED_EXTERNAL,
                    ], true);
                    if ($closesWindow
                        && $request->downtime_start
                        && !$request->downtime_end) {
                        $downtimeDuration = (int) abs($request->downtime_start->diffInMinutes(now()));
                        $request->update([
                            'downtime_end' => now(),
                            'downtime_duration' => $downtimeDuration,
                        ]);
                    }

                    foreach ($assetsToUpdate as $asset) {
                        $previousStatus = $asset->status;
                        $updated = false;
                        $historyAction = 'System Auto Update';
                        $remarks = '';

                        // X1 (Gov-Option-B): credit the correct bucket by ticket type —
                        // ICT/repair breakdown → total_downtime (SIRA); PM servicing →
                        // total_pm_downtime (Servicio — scheduled, not failure downtime).
                        // Bundled PM credits EVERY asset in the loop (all were down).
                        if ($downtimeDuration !== null) {
                            if ($request->type === 'Preventive Maintenance') {
                                $asset->increment('total_pm_downtime', $downtimeDuration);
                            } else {
                                $asset->increment('total_downtime', $downtimeDuration);
                            }
                        }

                        if ($request->type === 'Preventive Maintenance') {
                            $historyAction = 'PM Status Sync';
                            $remarks = "Automatically updated due to Preventive Maintenance {$request->request_number} status change from {$oldStatus} to {$newStatus}. Assigned to: {$assignedName}";
                            
                            if ($newStatus === self::STATUS_ONGOING && $previousStatus !== 'Under Maintenance') {
                                if (!in_array($previousStatus, [\App\Enums\AssetStatus::FOR_DISPOSAL, \App\Enums\AssetStatus::SCRAPPED], true)) {
                                    $asset->status = 'Under Maintenance';
                                    $asset->save();
                                    $updated = true;
                                }
                            }
                        } else {
                            $historyAction = 'Repair Status Sync';
                            $remarks = "Automatically updated due to Job Order {$request->request_number} status change from {$oldStatus} to {$newStatus}. Assigned to: {$assignedName}";
                            
                            if (in_array($newStatus, [self::STATUS_ONGOING, self::STATUS_AWAITING_PARTS, self::STATUS_AWAITING_SIGNATURE, self::STATUS_REFERRED_EXTERNAL], true)
                                && $previousStatus !== 'For Repair') {
                                if (!in_array($previousStatus, [\App\Enums\AssetStatus::FOR_DISPOSAL, \App\Enums\AssetStatus::SCRAPPED], true)) {
                                    $asset->status = 'For Repair';
                                    $asset->save();
                                    $updated = true;
                                }
                            }
                        }
                        
                        if ($newStatus === self::STATUS_COMPLETED) {
                            // Downtime window is closed + credited ABOVE the loop (X1).

                            $itMarkedForDisposal = false;
                            $repairDetail = null;
                            if ($request->type === 'Preventive Maintenance') {
                                $pmDetail = \App\Models\PreventiveMaintenance::find($request->detail_id);
                                if ($pmDetail && (int)$pmDetail->disposal_asset_id === (int)$asset->asset_id) {
                                    $itMarkedForDisposal = true;
                                }
                            } else {
                                $repairDetail = \App\Models\RepairRequest::find($request->detail_id);
                                if ($repairDetail && $repairDetail->after_repair_status === 'FOR DISPOSAL') {
                                    $itMarkedForDisposal = true;
                                }
                            }

                            if ($itMarkedForDisposal) {
                                $supplyOfficerIds = \App\Models\User::where(function($q) {
                                        $q->where('role', 'supply_officer')
                                          ->orWhere(function($sub) {
                                              $sub->where('role', 'admin')->where('can_supply', 1);
                                          });
                                    })
                                    ->where('is_active', true)
                                    ->when($asset->branch, fn($q) => $q->where('branch', $asset->branch))
                                    ->pluck('id');

                                foreach ($supplyOfficerIds as $officerId) {
                                    \App\Models\Notification::send(
                                        $officerId,
                                        $request->id,
                                        'Asset Tagged for Disposal',
                                        "Ticket {$request->request_number} is completed. Asset [{$asset->item_name} | SN: {$asset->serial_number}] has been tagged for disposal by IT. Please prepare to print the disposal tag."
                                    );
                                }
                                
                                // Auto unassign and set to For Disposal
                                if (!in_array($previousStatus, [\App\Enums\AssetStatus::FOR_DISPOSAL, \App\Enums\AssetStatus::SCRAPPED], true)) {
                                    $asset->status = \App\Enums\AssetStatus::FOR_DISPOSAL;
                                    $asset->assigned_to_user = null;
                                    $asset->save();
                                    
                                    $updated = true;
                                    $historyAction = 'Asset Surrendered';
                                    $remarks = "Asset automatically unassigned and marked for disposal via ticket {$request->request_number}.";
                                }
                            } else {
                                // Normal completion — asset is back to Active
                                if (!in_array($previousStatus, [\App\Enums\AssetStatus::FOR_DISPOSAL, \App\Enums\AssetStatus::SCRAPPED], true) && $previousStatus !== 'Active') {
                                    $asset->status = 'Active';
                                    $asset->save();
                                    $updated = true;
                                }
                            }
                        }

                        // Accumulate repair cost when ticket is completed
                        if ($newStatus === self::STATUS_COMPLETED && isset($repairDetail)) {
                            $rawCost = $repairDetail->getRawOriginal('cost') ?? ($repairDetail->getAttributes()['cost'] ?? null);
                            if ($rawCost !== null && $rawCost !== '' && is_numeric($rawCost)) {
                                $asset->increment('total_maintenance_cost', (float) $rawCost);
                            }
                        }

                        if ($updated) {
                            // Check for duplicates to prevent double logging from multiple save calls or race conditions
                            $recentLog = \App\Models\InventoryHistory::where('asset_id', $asset->asset_id)
                                ->where('action', $historyAction)
                                ->where('new_status', $asset->status)
                                ->where('created_at', '>=', now()->subSeconds(10))
                                ->first();

                            if (!$recentLog) {
                                \App\Models\InventoryHistory::create([
                                    'asset_id' => $asset->asset_id,
                                    'action' => $historyAction,
                                    'performed_by' => \Illuminate\Support\Facades\Auth::id(),
                                    'previous_user_id' => $asset->assigned_to_user, // Fix Unassigned -> User issue
                                    'new_user_id' => $asset->assigned_to_user,
                                    'previous_status' => $previousStatus,
                                    'new_status' => $asset->status,
                                    'remarks' => $remarks,
                                ]);
                            }
                        }
                    }
                }
                // D6: auto-archive the FINAL PDF copy when a ticket completes.
                // Post-commit (DB::afterCommit) so DomPDF never runs inside the
                // transaction / holds row locks. One archive per ticket (guard).
                if ($statusWasChanged
                    && $request->status === self::STATUS_COMPLETED
                    && !$request->archive_pdf_path) {
                    \Illuminate\Support\Facades\DB::afterCommit(function () use ($request) {
                        try {
                            \App\Actions\Ticket\ArchiveTicketPdfAction::generate($request->fresh());
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning('Ticket archive PDF failed for ' . ($request->request_number ?? $request->id) . ': ' . $e->getMessage());
                        }
                    });
                }
            }           
        });
    }
}