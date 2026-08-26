<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Purchase Request - PR document for parts procurement.
 * Table: purchase_requests
 *
 * REVISED FLOW (2026-08-25): draft -> submitted -> finalized.
 * The CMMS creates/prints/tracks the PR document only; actual procurement
 * (bidding, supplier, delivery) happens outside the system (BAC/Procurement).
 *
 * Legacy statuses from the old internal workflow (pending / approved /
 * received / cancelled) remain readable on old records, shown with a
 * "(legacy)" tag. New documents never use them.
 */
class PurchaseRequest extends Model
{
    // ---- Current flow statuses ------------------------------------------
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_FINALIZED = 'finalized';

    // ---- Legacy statuses (old records only, read-only display) ----------
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    /** Statuses produced by the current document flow. */
    public const CURRENT_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_FINALIZED,
    ];

    protected $table = 'purchase_requests';

    protected $fillable = [
        'pr_number',
        'requisition_id',
        'status',
        'items',
        'purpose',
        'total_amount',
        'fund_cluster',
        'responsibility_center',
        'office_unit',
        'remarks',
        'requested_by',
        'created_by',
        'finalized_by',
        'finalized_at',
    ];

    protected $casts = [
        'items' => 'array',
        'total_amount' => 'decimal:2',
        'finalized_at' => 'datetime',
    ];

    // ---- Relationships -------------------------------------------------

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class, 'requisition_id');
    }

    /** Who needs the parts (IT requester or requisition requester). */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** Who created the PR document in CMMS. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Supply officer who finalized the document (ready to print). */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    // ---- Flags ---------------------------------------------------------

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    public function isLegacyStatus(): bool
    {
        return ! in_array($this->status, self::CURRENT_STATUSES, true);
    }
}
