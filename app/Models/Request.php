<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Request extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'requests';

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
    ];

    public function getRoutePrefix()
    {
        return $this->type === 'Preventive Maintenance' ? 'maintenance' : strtolower($this->type);
    }

    protected $casts = [
        'is_deleted' => 'boolean',
        'is_auto_generated' => 'boolean',
        'reviewed_at' => 'datetime',
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
            
            if ($request->wasChanged('status') && $request->linked_asset_id) {
                $oldStatus = $request->getOriginal('status');
                $newStatus = $request->status;

                $asset = \App\Models\InventoryAsset::find($request->linked_asset_id);
                if ($asset && (int) $asset->assigned_to_user === (int) $request->user_id) {
                    $previousStatus = $asset->status;
                    $updated = false;
                    $remarks = "Automatically updated due to Job Order {$request->request_number} status change from {$oldStatus} to {$newStatus}";

                    if (in_array($newStatus, [self::STATUS_ONGOING, self::STATUS_AWAITING_PARTS, self::STATUS_AWAITING_SIGNATURE, self::STATUS_REFERRED_EXTERNAL], true)
                        && $previousStatus !== 'For Repair') {
                        // Don't overwrite locked disposal statuses
                        if (!in_array($previousStatus, [\App\Enums\AssetStatus::FOR_DISPOSAL, \App\Enums\AssetStatus::SCRAPPED], true)) {
                            $asset->status = 'For Repair';
                            $asset->save();
                            $updated = true;
                        }
                    } elseif ($newStatus === self::STATUS_COMPLETED) {
                        // Check if IT marked the repair result as FOR DISPOSAL
                        $repairDetail = \App\Models\RepairRequest::where('id', $request->detail_id)->first();
                        $itMarkedForDisposal = $repairDetail && $repairDetail->after_repair_status === 'FOR DISPOSAL';

                        if ($itMarkedForDisposal) {
                            // Asset is already For Disposal — do NOT downgrade to Defective.
                            // Fire the supply notification now that the ticket is fully completed.
                            // Notify supply_officer OR admin with can_supply=1 (Administrative Division handles supply)
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
                                    "Ticket {$request->request_number} is completed. Asset [{$asset->item_name} | SN: {$asset->serial_number}] has been tagged for disposal by IT. Please process and update the asset status when physical disposal is done."
                                );
                            }
                            $updated = false; // No status change needed
                        } else {
                            // Normal completion — asset is back to Active
                            // But don't overwrite For Disposal or Scrapped
                            if (!in_array($previousStatus, [\App\Enums\AssetStatus::FOR_DISPOSAL, \App\Enums\AssetStatus::SCRAPPED], true) && $previousStatus !== 'Active') {
                                $asset->status = 'Active';
                                $asset->save();
                                $updated = true;
                            }
                        }
                    }

                    // Accumulate repair cost when ticket is completed
                    if ($newStatus === self::STATUS_COMPLETED && $repairDetail && $repairDetail->cost) {
                        $asset->increment('total_maintenance_cost', $repairDetail->cost);
                    }

                    if ($updated) {
                        \App\Models\InventoryHistory::create([
                            'asset_id' => $asset->asset_id,
                            'action' => 'Asset Status Auto-Sync',
                            'performed_by' => \Illuminate\Support\Facades\Auth::id(),
                            'new_user_id' => $asset->assigned_to_user,
                            'previous_status' => $previousStatus,
                            'new_status' => $asset->status,
                            'remarks' => $remarks,
                        ]);
                    }
                }
            }
        });
    }
}