<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_assets';
    protected $primaryKey = 'asset_id';
    
    protected $appends = ['is_depreciated', 'warranty_status'];

    protected $fillable = [
        'asset_id',
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
    ];

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
