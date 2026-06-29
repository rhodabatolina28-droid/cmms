<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryHistory extends Model
{
    use HasFactory;

    protected $table = 'inventory_history';

    protected $fillable = [
        'asset_id',
        'action',
        'performed_by',
        'previous_user_id',
        'new_user_id',
        'previous_status',
        'new_status',
        'remarks',
        'transfer_receipt_no',
    ];

    protected $casts = [
        'asset_id' => 'integer',
        'performed_by' => 'integer',
        'previous_user_id' => 'integer',
        'new_user_id' => 'integer',
    ];

    // Relationships
    public function asset()
    {
        return $this->belongsTo(InventoryAsset::class, 'asset_id', 'asset_id');
    }

    public function performedByUser()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function previousUser()
    {
        return $this->belongsTo(User::class, 'previous_user_id');
    }

    public function newUser()
    {
        return $this->belongsTo(User::class, 'new_user_id');
    }
}
