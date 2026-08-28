<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    /** Goods physically received & recorded (stock-in or direct-to-asset). NB: NOT the legacy 'received'. */
    public const STATUS_DELIVERED = 'delivered';

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
        self::STATUS_DELIVERED,
    ];

    protected $table = 'purchase_requests';

    protected $fillable = [
        'pr_number',
        'requisition_id',
        'request_id',
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
        'delivered_by',
        'delivered_at',
    ];

    protected $casts = [
        'items' => 'array',
        'total_amount' => 'decimal:2',
        'finalized_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // ---- Relationships -------------------------------------------------

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class, 'requisition_id');
    }

    /** Job order ticket this PR was raised against (asset + custodian traceability). */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class, 'request_id');
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

    /** User who recorded physical receipt of the purchased goods. */
    public function deliverer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(PrAttachment::class, 'purchase_request_id');
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

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /** ₱10k threshold: below = IT/SuperAdmin fast track (they buy & receive); at/above = Supply/Procurement. */
    public function isSmallPurchase(): bool
    {
        return (float) $this->total_amount < 10000;
    }

    /** Did this user originate this PR (requester or document creator)? */
    public function isOwnedBy(User $user): bool
    {
        return in_array($user->id, array_filter([$this->requested_by, $this->created_by]), true);
    }

    public function isLegacyStatus(): bool
    {
        return ! in_array($this->status, self::CURRENT_STATUSES, true);
    }
}
