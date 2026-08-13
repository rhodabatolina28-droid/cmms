<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Purchase Request (RA 9184) — procurement document for short parts stock.
 * Table: purchase_requests
 *
 * Flow: pending → approved → received / cancelled.
 */
class PurchaseRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'purchase_requests';

    protected $fillable = [
        'pr_number',
        'requisition_id',
        'status',
        'items',
        'remarks',
        'requested_by',
        'approved_by',
        'approved_at',
        'received_by',
        'received_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'items' => 'array',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ---- Relationships -------------------------------------------------

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class, 'requisition_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // ---- Flags ---------------------------------------------------------

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}