<?php

namespace App\Actions\PurchaseRequest;

use App\Models\AuditLog;
use App\Models\PrAttachment;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Uploads a receipt / proof-of-purchase file onto a Purchase Request.
 *
 * Rules (user-decided):
 *  - pdf/jpg/jpeg/png only, max ~10MB (validated in UploadPrAttachmentRequest).
 *  - Allowed uploaders: PR owner, Supply Officer, Super Admin.
 *  - IMMUTABLE once the PR is delivered - no new uploads after delivery either.
 */
class UploadPrAttachmentAction
{
    /** Who may attach receipts to this PR. */
    public function canUpload(PurchaseRequest $purchaseRequest, User $user): bool
    {
        if ($purchaseRequest->isDelivered()) {
            return false; // audit protection: sealed once delivered
        }

        // Before finalize: owner manages their own documents; Supply/Admin
        // can always manage across the queue.
        return $purchaseRequest->isOwnedBy($user)
            || $user->canProcessSupply();
    }

    public function execute(PurchaseRequest $purchaseRequest, User $user, UploadedFile $file, string $label = '')
    {
        if (! $this->canUpload($purchaseRequest, $user)) {
            return response()->json([
                'success' => false,
                'message' => $purchaseRequest->isDelivered()
                    ? 'Attachments are sealed once the request is delivered.'
                    : 'You are not allowed to attach files to this purchase request.',
            ], 403);
        }

        $filepath = $file->store("pr-attachments/{$purchaseRequest->id}", 'public');

        $attachment = PrAttachment::create([
            'purchase_request_id' => $purchaseRequest->id,
            'filename'            => $file->getClientOriginalName(),
            'filepath'            => $filepath,
            'filetype'            => $file->getMimeType(),
            'label'               => $label,
            'uploaded_by'         => $user->id,
        ]);

        AuditLog::log(
            'PR Receipt Uploaded',
            'Purchase Request',
            "Uploaded '{$attachment->filename}' to {$purchaseRequest->pr_number}."
        );

        return response()->json([
            'success'   => true,
            'message'   => 'Receipt uploaded.',
            'attachment'=> $attachment->load('uploader'),
        ]);
    }
}
