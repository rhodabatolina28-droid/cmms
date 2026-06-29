<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalCount extends Model
{
    protected $table = 'inventory_physical_counts';

    protected $fillable = [
        'session_id',
        'asset_id',
        'counted_by',
        'status',
        'actual_location',
        'remarks',
        'counted_at',
    ];

    protected $casts = [
        'counted_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(PhysicalCountSession::class, 'session_id');
    }

    public function asset()
    {
        return $this->belongsTo(InventoryAsset::class, 'asset_id', 'asset_id');
    }

    public function countedBy()
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
