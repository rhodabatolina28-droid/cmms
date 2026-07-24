<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_assets';

    /**
     * Boot the model and enforce status integrity.
     * Active assets MUST have an assigned user. If unassigned, auto-convert to Spare.
     * Spare assets with an assigned user auto-convert to Active.
     * This prevents data inconsistency across all locations (regions/branches).
     */
    protected static function booted(): void
    {
        static::saving(function ($asset) {
            if ($asset->status === 'Disposed') {
                $asset->status = 'For Disposal';
                $asset->assigned_to_user = null;
            }

            // Never override locked statuses
            $preservedStatuses = ['Defective', 'For Repair', 'Scrapped', 'For Disposal', 'Under Maintenance'];

            if (!in_array($asset->status, $preservedStatuses, true)) {
                // Active without user = Spare
                if (empty($asset->assigned_to_user) && $asset->status === 'Active') {
                    $asset->status = 'Spare';
                }
                // Spare with user = Active
                if (!empty($asset->assigned_to_user) && $asset->status === 'Spare') {
                    $asset->status = 'Active';
                }
            }
        });
    }
    protected $primaryKey = 'asset_id';
    
    protected $appends = ['is_depreciated', 'warranty_status', 'formatted_downtime'];

    protected $fillable = [
        'asset_id',
        'parent_asset_id',
        'category',
        'item_name',
        'serial_number',
        'property_number',
        'par_number',
        'brand',
        'model',
        'specifications',
        'assigned_to_user',
        'region',
        'branch',
        'office',
        'department',
        'status',
        'date_added',
        'date_acquired',
        'warranty_expiration',
        'acquisition_cost',
        'total_maintenance_cost',
        'end_of_useful_life',
        'asset_notes',
        'last_pm_date',
        'next_pm_due_date',
        'pm_schedule_id',
        'total_downtime',
    ];

    protected $casts = [
        'specifications'         => 'array',
        'date_added'             => 'date',
        'date_acquired'          => 'date',
        'warranty_expiration'    => 'date',
        'end_of_useful_life'     => 'date',
        'acquisition_cost'       => 'decimal:2',
        'total_maintenance_cost' => 'decimal:2',
        'last_pm_date'           => 'date',
        'next_pm_due_date'       => 'date',
        'pm_schedule_id'         => 'integer',
        'total_downtime'         => 'integer',
    ];

    // Downtime display accessor — returns human-readable string
    // Named 'formatted_downtime' NOT 'total_downtime' to avoid conflicting with increment()
    // which needs the raw integer from the cast, not a formatted string.
    public function getFormattedDowntimeAttribute(): string
    {
        $minutes = (int) ($this->attributes['total_downtime'] ?? 0);
        if ($minutes == 0) return '0h';
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        if ($hours >= 24) {
            $days = floor($hours / 24);
            $hours = $hours % 24;
            return "{$days}d {$hours}h {$mins}m";
        }
        return "{$hours}h {$mins}m";
    }

    public function downtimeTickets()
    {
        return $this->hasMany(Request::class, 'linked_asset_id', 'asset_id')
            ->whereNotNull('downtime_duration')
            ->orderByDesc('created_at');
    }

    // Relationships
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to_user');
    }

    public function history()
    {
        return $this->hasMany(InventoryHistory::class, 'asset_id', 'asset_id');
    }

    public function attachments()
    {
        return $this->hasMany(\App\Models\AssetAttachment::class, 'asset_id', 'asset_id');
    }

    /**
     * Self-referential set linkage.
     * A "Complete Set" parent asset (CPU) owns component children (Monitor).
     * Shared PAR number ties the set together per government PAR standard.
     */
    public function parentAsset()
    {
        return $this->belongsTo(self::class, 'parent_asset_id', 'asset_id');
    }

    public function components()
    {
        return $this->hasMany(self::class, 'parent_asset_id', 'asset_id');
    }

    /** All ICT and PM requests linked to this asset */
    public function repairRequests()
    {
        return $this->hasMany(\App\Models\Request::class, 'linked_asset_id', 'asset_id');
    }

    /** Warranty status */
    public function getWarrantyStatusAttribute(): string
    {
        if (!$this->warranty_expiration) return 'No Warranty Info';
        if ($this->warranty_expiration->isPast()) return 'Expired';
        // Check if expiring within 30 days from today
        if (now()->diffInDays($this->warranty_expiration, false) <= 30) return 'Expiring Soon';
        return 'Active';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeSpare($query)
    {
        return $query->where('status', 'Spare');
    }

    public function scopeInRegion($query, $region)
    {
        return $query->where('region', $region);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to_user', $userId);
    }

    // Helpers
    public function isActive()
    {
        return $this->status === 'Active';
    }

    public function isAssigned()
    {
        return !is_null($this->assigned_to_user);
    }

    public function getIsDepreciatedAttribute()
    {
        if (!$this->date_acquired) {
            return false;
        }
        // Most IT equipment has a 5-year depreciation lifecycle
        return \Carbon\Carbon::parse($this->date_acquired)->addYears(5)->isPast();
    }
}
