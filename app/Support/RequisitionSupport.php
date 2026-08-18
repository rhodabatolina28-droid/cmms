<?php

namespace App\Support;

use App\Models\Requisition;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * IT → Supply requisitions: idempotency, scope, and status workflow helpers.
 */
class RequisitionSupport
{
    private static ?bool $submissionColumnExists = null;

    public static function hasSubmissionIdColumn(): bool
    {
        if (self::$submissionColumnExists === null) {
            self::$submissionColumnExists = Schema::hasColumn('requisitions', 'submission_id');
        }

        return self::$submissionColumnExists;
    }

    /**
     * @return array{success: true, message: string, requisition_id: int, already_submitted: true}|null
     */
    public static function findExistingSubmission(
        RequestModel $ticket,
        User $itUser,
        ?string $submissionId
    ): ?array {
        $pending = Requisition::where('request_id', $ticket->id)
            ->where('requested_by', $itUser->id)
            ->where('status', Requisition::STATUS_PENDING)
            ->first();

        if ($pending) {
            return [
                'success' => true,
                'message' => 'This ticket already has a pending request with Supply. Wait for their action before submitting again.',
                'requisition_id' => $pending->id,
                'already_submitted' => true,
            ];
        }

        if (!self::hasSubmissionIdColumn() || !$submissionId) {
            return null;
        }

        $duplicate = Requisition::where('request_id', $ticket->id)
            ->where('requested_by', $itUser->id)
            ->where('submission_id', $submissionId)
            ->first();

        if (!$duplicate) {
            return null;
        }

        return [
            'success' => true,
            'message' => 'Parts request was already submitted.',
            'requisition_id' => $duplicate->id,
            'already_submitted' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function buildCreatePayload(RequestModel $ticket, User $itUser, array $validated): array
    {
        $payload = [
            'request_id' => $ticket->id,
            'requested_by' => $itUser->id,
            'status' => Requisition::STATUS_PENDING,
            'items' => $validated['items'],
            'remarks' => $validated['remarks'] ?? null,
        ];

        $submissionId = $validated['submission_id'] ?? null;
        if (self::hasSubmissionIdColumn() && $submissionId) {
            $payload['submission_id'] = $submissionId;
        }

        return $payload;
    }

    /**
     * IT or Super Admin (acting as IT) can submit requisition if:
     * - Assigned to the ticket
     * - Ticket is ICT, or an eligible PM (type Preventive Maintenance) with a linked asset
     * - Ticket is not completed/cancelled
     */
    public static function canItSubmitForTicket(User $itUser, RequestModel $ticket): bool
    {
        $isAssignedWorker = $itUser->role === 'it' || $itUser->role === 'super_admin';

        return $isAssignedWorker
            && ($ticket->type === 'ICT' || ($ticket->type === 'Preventive Maintenance' && $ticket->linked_asset_id))
            && (int) $ticket->assigned_to === (int) $itUser->id
            && $ticket->status !== RequestModel::STATUS_COMPLETED
            && $ticket->status !== RequestModel::STATUS_CANCELLED;
    }

    /**
     * Resolve the destination required for a ticket-based parts issue.
     *
     * IT requests a part for a ticket, but the issued unit belongs to the
     * custodian of the ticket's linked asset. Keep this validation central so
     * submission and Supply Issue apply the same safety rule.
     *
     * @return array{valid:bool,message:?string,asset:mixed,custodian:mixed}
     */
    public static function ticketIssueContext(RequestModel $ticket): array
    {
        $ticket->loadMissing('linkedAsset.assignedUser');
        $asset = $ticket->linkedAsset;

        if (! $asset) {
            return [
                'valid' => false,
                'message' => 'A linked asset is required before parts can be requested or issued for this ticket.',
                'asset' => null,
                'custodian' => null,
            ];
        }

        $custodian = $asset->assignedUser;
        if (! $custodian) {
            return [
                'valid' => false,
                'message' => 'Assign a custodian to the linked asset before parts can be requested or issued.',
                'asset' => $asset,
                'custodian' => null,
            ];
        }

        return [
            'valid' => true,
            'message' => null,
            'asset' => $asset,
            'custodian' => $custodian,
        ];
    }

    public static function validateSupplyAction(string $action, string $currentStatus): ?string
    {
        return match ($action) {
            'approve' => $currentStatus !== Requisition::STATUS_PENDING
                ? 'Only pending requests can be approved.'
                : null,
            'reject' => !in_array($currentStatus, [Requisition::STATUS_PENDING, Requisition::STATUS_APPROVED], true)
                ? 'This request can no longer be rejected.'
                : null,
            'issue' => $currentStatus !== Requisition::STATUS_APPROVED
                ? 'Approve the request first, then use Issue when parts are released.'
                : null,
            default => 'Invalid action.',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            Requisition::STATUS_PENDING => 'Waiting for Supply',
            Requisition::STATUS_APPROVED => 'Approved — awaiting issue',
            Requisition::STATUS_ISSUED => 'Parts released',
            Requisition::STATUS_REJECTED => 'Rejected',
            default => ucfirst($status),
        };
    }
}
