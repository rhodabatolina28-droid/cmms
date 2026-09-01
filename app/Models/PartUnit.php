<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isang physical unit ng isang Part (per-piece custodian).
 * Table: parts_stock_units
 */
class PartUnit extends Model
{
    use HasFactory;

    protected $table = 'parts_stock_units';

    protected $fillable = [
        'part_id',
        'serial_number',
        'property_number',
        'unit_value',
        'status',
        'issued_to',
        'asset_id',
        'request_id',
        'purchase_request_id',
        'issued_at',
    ];

    protected $casts = [
        'unit_value' => 'decimal:2',
        'issued_at' => 'datetime',
        'issued_to' => 'integer',
        'asset_id' => 'integer',
        'request_id' => 'integer',
    ];

    // ---- Relationships -------------------------------------------------

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'part_id');
    }

    public function issuedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_to');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(InventoryAsset::class, 'asset_id', 'asset_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }
}
