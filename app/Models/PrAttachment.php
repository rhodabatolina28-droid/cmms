<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Receipt / proof-of-purchase file attached to a Purchase Request.
 * Table: pr_attachments
 *
 * Required (at least one) before receiving PRs below the ₱10k small-purchase
 * threshold; optional but recommended for procurement-track PRs.
 * Immutable once the PR is delivered — no delete, ever (audit protection).
 */
class PrAttachment extends Model
{
    protected $table = 'pr_attachments';

    protected $fillable = [
        'purchase_request_id',
        'filename',
        'filepath',
        'filetype',
        'label',
        'uploaded_by',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
