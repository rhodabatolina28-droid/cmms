<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail for a single stock-in (+) or stock-out (−) on a Part.
 * Table: parts_stock_movements (append-only; there is no updated_at).
 */
class PartMovement extends Model
{
    use HasFactory;

    protected $table = 'parts_stock_movements';

    /**
     * This table is append-only — Eloquent should never touch updated_at.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'part_id',
        'qty_change',
        'reason',
        'reference_type',
        'reference_id',
        'performed_by',
    ];

    protected $casts = [
        'part_id' => 'integer',
        'qty_change' => 'integer',
        'reference_id' => 'integer',
        'performed_by' => 'integer',
    ];

    // ---- Relationships -------------------------------------------------

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'part_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}