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
            $specific = User::where('role', 'admin')
                ->where('branch', $userBranch)
                ->where('department', $userDepartment)
                ->where('office', $userOffice)
                ->where('is_active', true)
                ->get();
            $allAdmins = $allAdmins->concat($specific);
        }

        if ($userDepartment && $userOffice) {
            $broader = User::where('role', 'admin')
                ->where('department', $userDepartment)
                ->where('office', $userOffice)
                ->where('is_active', true)
                ->get();
            $allAdmins = $allAdmins->concat($broader);
        }

        if ($userOffice) {
            $broadest = User::where('role', 'admin')
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
            ->when($user->office, fn ($query) => $query->where('office', $user->office))
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
        }
    }

    /**
     * @return Collection<int, User>
     */
    public static function cascadeSupplyOfficersForUser(User $requestor): Collection
    {
        $userBranch = $requestor->branch ?? null;
        $userOffice = $requestor->office ?? null;
        $userDepartment = $requestor->department ?? null;

        $allOfficers = collect();

        // Additive: notify ALL matching levels
        if ($userBranch && $userDepartment && $userOffice) {
            $specific = User::where('role', 'supply_officer')
                ->where('branch', $userBranch)
                ->where('department', $userDepartment)
                ->where('office', $userOffice)
                ->where('is_active', true)
                ->get();
            $allOfficers = $allOfficers->concat($specific);
        }

        if ($userDepartment && $userOffice) {
            $broader = User::where('role', 'supply_officer')
                ->where('department', $userDepartment)
                ->where('office', $userOffice)
                ->where('is_active', true)
                ->get();
            $allOfficers = $allOfficers->concat($broader);
        }

        if ($userOffice) {
            $broadest = User::where('role', 'supply_officer')
                ->where('office', $userOffice)
                ->where('is_active', true)
                ->get();
            $allOfficers = $allOfficers->concat($broadest);
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
