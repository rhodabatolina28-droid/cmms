<?php

namespace App\Services;

use App\Models\Requisition;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Cascading admin notification (region → office → department) + shared helpers.
 */
class RequestNotificationService
{
    public static function typeLabel(?string $type): string
    {
        return $type === 'ICT' ? 'ICT Repair' : 'Preventive Maintenance';
    }

    public static function notifyAdminsOfNewRequest(RequestModel $request, User $requestor, string $typeLabel): void
    {
        $recipients = self::cascadeDivisionAdminsForUser($requestor);

        $requestorName = strtoupper($requestor->full_name ?? 'USER');
        $message = "New {$typeLabel} from {$requestorName} ({$request->request_number}) in your division. Please review.";

        foreach ($recipients as $recipient) {
            \App\Models\Notification::send(
                $recipient->id,
                $request->id,
                "New {$typeLabel} for Review",
                $message
            );
        }
    }

    public static function notifySuperAdminOfForwardedRequest(RequestModel $request, User $divisionAdmin): void
    {
        $recipients = self::cascadeSuperAdminsForUser($divisionAdmin);
        
        $typeLabel = self::typeLabel($request->type);
        $adminName = strtoupper($divisionAdmin->full_name ?? 'ADMIN');
        $message = "Division Admin {$adminName} forwarded {$typeLabel} request {$request->request_number}. Please assign IT.";

        foreach ($recipients as $recipient) {
            \App\Models\Notification::send(
                $recipient->id,
                $request->id,
                "Forwarded {$typeLabel}",
                $message
            );
        }
    }

    public static function notifySuperAdminsOfNewPmRequest(RequestModel $request, User $requestor): void
    {
        $recipients = self::cascadeSuperAdminsForUser($requestor);
        
        $requestorName = strtoupper($requestor->full_name ?? 'USER');
        $message = "New Preventive Maintenance from {$requestorName} ({$request->request_number}). Please assign IT personnel.";

        foreach ($recipients as $recipient) {
            \App\Models\Notification::send(
                $recipient->id,
                $request->id,
                "New Preventive Maintenance",
                $message
            );
        }
    }

    public static function notifyItAssigned(RequestModel $request, User $itUser): void
    {
        $label = self::typeLabel($request->type);
        \App\Models\Notification::send(
            $itUser->id,
            $request->id,
            'Job Order Assigned',
            "You have been assigned {$label} request {$request->request_number}. Please open the ticket and complete the technical sections."
        );
    }

    /** End-user who filed the ticket — when admin assigns IT and status moves to Ongoing. */
    public static function notifyRequestorItAssigned(RequestModel $request, User $itUser): void
    {
        if (!$request->user_id || (int) $request->user_id === (int) $itUser->id) {
            return;
        }

        $label = self::typeLabel($request->type);
        $status = $request->status ?? RequestModel::STATUS_ONGOING;

        self::notifyRequestorTicketStatus(
            $request,
            $status,
            "IT personnel {$itUser->full_name} has been assigned to work on your ticket."
        );
    }

    /** End-user notification when ticket status changes (assign, awaiting parts, back to ongoing). */
    public static function notifyRequestorTicketStatus(RequestModel $request, string $status, string $detail): void
    {
        if (!$request->user_id) {
            return;
        }

        $label = self::typeLabel($request->type);

        \App\Models\Notification::send(
            (int) $request->user_id,
            $request->id,
            'Request Updated',
            "Your {$label} request {$request->request_number} is now {$status}. {$detail}"
        );
    }

    public static function notifyRequestorOfRejection(RequestModel $request, string $reason): void
    {
        if (!$request->user_id) {
            return;
        }

        $label = self::typeLabel($request->type);
        \App\Models\Notification::send(
            (int) $request->user_id,
            $request->id,
            'Request Rejected',
            "Your {$label} request {$request->request_number} was rejected. Reason: " . ($reason ?: 'No reason provided.')
        );
    }

    public static function notifyRequestorOfCancellation(RequestModel $request): void
    {
        if (!$request->user_id) {
            return;
        }

        $label = self::typeLabel($request->type);
        \App\Models\Notification::send(
            (int) $request->user_id,
            $request->id,
            'Request Cancelled',
            "Your {$label} request {$request->request_number} has been cancelled."
        );
    }

    /** Notify the asset custodian recorded when Supply releases a part. */
    public static function notifyAssetCustodianOfPartsIssue(RequestModel $ticket, int $custodianId): void
    {
        // IT already receives the requisition workflow notification. Do not duplicate it
        // when the ticket requester is also the asset custodian.
        if ($custodianId < 1 || (int) $ticket->user_id === $custodianId) {
            return;
        }

        $asset = $ticket->linkedAsset;
        if (! $asset) {
            return;
        }

        \App\Models\Notification::send(
            $custodianId,
            $ticket->id,
            'Parts Issued to Asset',
            "Parts were issued and recorded under asset {$asset->item_name} for ticket {$ticket->request_number}."
        );
    }

    public static function notifyNewAssetCustodian(\App\Models\InventoryAsset $asset, int $custodianId): void
    {
        \App\Models\Notification::send(
            $custodianId,
            null,
            'Asset Assigned',
            "Asset {$asset->item_name} (PAR: {$asset->par_number}) has been assigned to you."
        );
    }

    public static function notifyAssetCustodianTransfer(\App\Models\InventoryAsset $asset, ?int $previousUserId, ?int $newUserId): void
    {
        if ($previousUserId) {
            \App\Models\Notification::send(
                $previousUserId,
                null,
                'Asset Transfer',
                "Asset {$asset->item_name} (PAR: {$asset->par_number}) is no longer assigned to you."
            );
        }

        if ($newUserId && $newUserId !== $previousUserId) {
            \App\Models\Notification::send(
                $newUserId,
                null,
                'Asset Transfer',
                "Asset {$asset->item_name} (PAR: {$asset->par_number}) has been assigned to you."
            );
        }
    }

    /**
     * @return Collection<int, User>
     */
    public static function cascadeDivisionAdminsForUser(User $requestor): Collection
    {
        $userBranch = $requestor->branch ?? null;
        $userOffice = $requestor->office ?? null;
        $userDepartment = $requestor->department ?? null;

        $allAdmins = collect();

        // Additive: notify ALL matching levels, not just the most specific
        if ($userBranch && $userDepartment && $userOffice) {
            $specific = User::whereIn('role', ['admin', 'supply_officer'])
                ->where('branch', $userBranch)
                ->where('department', $userDepartment)
                ->where('office', $userOffice)
                ->where('is_active', true)
                ->get();
            $allAdmins = $allAdmins->concat($specific);
        }

        if ($userDepartment && $userOffice) {
            $broader = User::whereIn('role', ['admin', 'supply_officer'])
                ->where('department', $userDepartment)
                ->where('office', $userOffice)
                ->where('is_active', true)
                ->get();
            $allAdmins = $allAdmins->concat($broader);
        }

        if ($userOffice) {
            $broadest = User::whereIn('role', ['admin', 'supply_officer'])
                ->where('office', $userOffice)
                ->where('is_active', true)
                ->get();
            $allAdmins = $allAdmins->concat($broadest);
        }

        return $allAdmins->unique('id');
    }

    /**
     * @return Collection<int, User>
     */
    public static function cascadeSuperAdminsForUser(User $user): Collection
    {
        $superAdmins = User::where('role', 'super_admin')
            ->when($user->branch, fn ($query) => $query->where('branch', $user->branch))
            ->where('is_active', true)
            ->get();

        return $superAdmins->unique('id');
    }

    public static function notifySupplyOfficersOfReferredIct(RequestModel $ticket, User $itUser): void
    {
        $recipients = self::cascadeSupplyOfficersForUser($itUser);
        $message = "ICT {$ticket->request_number} was referred to an external Service Provider by {$itUser->full_name}. "
            . 'Coordinate parts/purchases via requisition if needed.';

        foreach ($recipients as $recipient) {
            \App\Models\Notification::send(
                $recipient->id,
                $ticket->id,
                'Referred to Service Provider',
                $message
            );
        }
    }

    public static function notifySupplyOfficersOfRequisition(Requisition $requisition): void
    {
        $ticket = $requisition->ticket;
        $requestor = $requisition->requester;
        if (!$ticket || !$requestor) {
            return;
        }

        $recipients = self::cascadeSupplyOfficersForUser($requestor);
        $message = "New parts requisition for {$ticket->request_number} from {$requestor->full_name}.";

        foreach ($recipients as $recipient) {
            \App\Models\Notification::send(
                $recipient->id,
                $ticket->id,
                'Parts Requisition',
                $message
            );
            // Email is sent automatically via Notification::booted() hook
        }
    }

    public static function notifyItOfRequisitionAction(Requisition $requisition, string $action): void
    {
        $ticket = $requisition->ticket;
        $itUser = $requisition->requested_by ? \App\Models\User::find($requisition->requested_by) : null;
        
        if (!$ticket || !$itUser) {
            return;
        }

        $message = match ($action) {
            'approved' => "Supply approved your parts request for {$ticket->request_number}. They will issue parts when ready.",
            'rejected' => "Supply rejected your parts request for {$ticket->request_number}.",
            'issued' => "Parts were issued for {$ticket->request_number}. You may continue repair work.",
            default => "Requisition #{$requisition->id} for {$ticket->request_number} was {$action}.",
        };

        // Send in-app notification (email is sent automatically via Notification::booted() hook)
        \App\Models\Notification::send(
            $itUser->id,
            $ticket->id,
            'Parts Request — ' . ucfirst($action),
            $message
        );
    }

    /**
     * @return Collection<int, User>
     */
    public static function cascadeSupplyOfficersForUser(User $requestor): Collection
    {
        $userBranch = $requestor->branch ?? null;

        $allOfficers = collect();

        // Supply Officers are in the Administrative office but handle supply for the entire branch
        // So we only filter by branch, not office
        // Include both supply_officer role AND admin with can_supply=1
        if ($userBranch) {
            $officers = User::where(function($q) {
                    $q->where('role', 'supply_officer')
                      ->orWhere(function($sub) {
                          $sub->where('role', 'admin')->where('can_supply', 1);
                      });
                })
                ->where('branch', $userBranch)
                ->where('is_active', true)
                ->get();
            $allOfficers = $allOfficers->concat($officers);
        }

        // If no branch, get all active supply officers
        if (!$userBranch) {
            $officers = User::where(function($q) {
                    $q->where('role', 'supply_officer')
                      ->orWhere(function($sub) {
                          $sub->where('role', 'admin')->where('can_supply', 1);
                      });
                })
                ->where('is_active', true)
                ->get();
            $allOfficers = $allOfficers->concat($officers);
        }

        return $allOfficers->unique('id');
    }

    public static function logLocalEmailPreview(string $to, string $type, string $message, string $requestNumber): void
    {
        if (!app()->environment('local')) {
            return;
        }

        Log::info('[CMMS Email Preview]', [
            'to' => $to,
            'type' => $type,
            'request' => $requestNumber,
            'message' => $message,
        ]);
    }
}
