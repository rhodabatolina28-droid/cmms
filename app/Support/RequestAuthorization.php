<?php

namespace App\Support;

use App\Models\InventoryAsset;
use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use App\Models\User;

/**
 * Central role logic for ICT / PM tickets (Phase 1 — before full Phase 2 UI).
 */
class RequestAuthorization
{
    public static function canCreateIctTicket(User $user): bool
    {
        return in_array($user->role, ['user', 'it', 'super_admin'], true);
    }

    public static function canCreateMaintenanceTicket(User $user): bool
    {
        return in_array($user->role, ['it', 'super_admin']);
    }

    /** Asset must be assigned to the submitting end-user (or was recently assigned within 30 days). */
    public static function assetAssignedToUser(User $user, int|string $assetId): bool
    {
        if ($assetId === '' || $assetId === null) {
            return false;
        }

        $asset = InventoryAsset::where('asset_id', (int) $assetId)->first();
        
        if (!$asset) {
            return false;
        }
        
        // Check current assignment
        if ((int) $asset->assigned_to_user === (int) $user->id) {
            return true;
        }
        
        // Check historical assignment within last 30 days
        $recentAssignment = \App\Models\InventoryHistory::where('asset_id', (int) $assetId)
            ->where('new_user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->exists();
            
        return $recentAssignment;
    }

    public static function linkedAssetValidationError(User $user, mixed $assetId): ?string
    {
        if ($assetId === null || $assetId === '') {
            return 'Please select an accountable device or asset from your inventory.';
        }

        if (! self::assetAssignedToUser($user, $assetId)) {
            return 'The selected asset is not assigned to you. Contact your Administrative supply admin to update your accountable equipment.';
        }

        $asset = InventoryAsset::where('asset_id', (int) $assetId)->first();
        
        if (!$asset) {
            return 'The selected asset does not exist.';
        }

        // Block requests for disposed/scrapped assets
        if (in_array($asset->status, ['For Disposal', 'Scrapped', 'Disposed'], true)) {
            return "This asset is marked as '{$asset->status}' and cannot be used for new requests.";
        }

        return null;
    }

    public static function userHasAssignedAssets(User $user): bool
    {
        return InventoryAsset::where('assigned_to_user', $user->id)->exists();
    }

    /** Sections 2–5 + service provider — assigned IT only (Phase 2 / Option 3). */
    public static function canEditIctTechnicianSections(User $user, ?RequestModel $ticket = null): bool
    {
        if ($ticket) {
            // If it's an ICT ticket, it MUST be approved by division admin first
            if ($ticket->type === 'ICT' && $ticket->division_admin_review_status !== 'Approved') {
                return false;
            }

            // If assigned to someone, ONLY that person can edit
            if ($ticket->assigned_to) {
                return (int) $ticket->assigned_to === (int) $user->id;
            }
            
            // If NOT assigned, NO ONE can edit (must be assigned first)
            return false;
        }

        return false;
    }

    /** Only Super Admin can assign ICT or PM tickets to IT personnel. Division Admin cannot assign IT. */
    public static function canAssignTicket(User $user, RequestModel $ticket): bool
    {
        if (in_array($ticket->status, [RequestModel::STATUS_COMPLETED, RequestModel::STATUS_AWAITING_SIGNATURE], true)) {
            return false;
        }

        // Only super_admin can assign IT personnel; division admin cannot.
        if ($user->role !== 'super_admin') {
            return false;
        }

        if (!in_array($ticket->type, ['ICT', 'Preventive Maintenance'], true)) {
            return false;
        }

        if ($ticket->type === 'ICT' && $ticket->division_admin_review_status !== 'Approved') {
            return false;
        }

        // Super Admin may assign IT personnel
        return true;
    }

    /** Only Super Admin can view/assign IT personnel. Division Admin cannot see the IT list. */
    public static function itPersonnelInAdminScope(User $admin): \Illuminate\Support\Collection
    {
        // Only super_admin can see and assign IT personnel
        if ($admin->role !== 'super_admin') {
            return collect();
        }

        $query = User::query()
            ->where('role', 'it')
            ->where('is_active', true);

        // Filter IT personnel by Super Admin's branch scope
        if ($admin->branch) {
            $query->where('branch', $admin->branch);
        }

        $itPersonnel = $query->orderBy('full_name')->get();
        
        // Add Super Admin himself to the list (so they can assign themselves if no IT available)
        $superAdminOption = User::find($admin->id);
        if ($superAdminOption) {
            $itPersonnel->prepend($superAdminOption);
        }
        
        return $itPersonnel;
    }

    public static function itUserInAdminScope(User $admin, User $itUser): bool
    {
        if ($itUser->role !== 'it' || !$itUser->is_active) {
            return false;
        }

        if ($admin->role === 'super_admin') {
            if ($admin->office && $itUser->office !== $admin->office) {
                return false;
            }
            if ($admin->branch && $itUser->branch !== $admin->branch) {
                return false;
            }

            return true;
        }
        if ($admin->branch && $itUser->branch !== $admin->branch) {
            return false;
        }
        if ($admin->office && $itUser->office !== $admin->office) {
            return false;
        }
        if ($admin->department && $itUser->department !== $admin->department) {
            return false;
        }

        return true;
    }

    /** Section 1 on new ticket, or end-user block when creating. */
    public static function canEditIctEndUserSection(User $user, ?RequestModel $ticket = null): bool
    {
        if ($user->role === 'user') {
            if (!$ticket) {
                return true;
            }
            
            // Check ownership
            if ((int) $ticket->user_id !== (int) $user->id) {
                return false;
            }
            
            // AWAITING_SIGNATURE: end-user can only sign Section 6.
            // Section 1 should be completely locked.
            if ($ticket->status === RequestModel::STATUS_AWAITING_SIGNATURE) {
                return false;
            }
            
            // Allow editing if rejected (user can fix and resubmit)
            if ($ticket->status === RequestModel::STATUS_REJECTED) {
                return true;
            }
            
            // Lock editing after submission (any status except rejected)
            if ($ticket->status !== null) {
                return false;
            }
            
            return true;
        }

        return false;
    }

    /** Section 6 acceptance — end-user only (view always allowed on form). */
    public static function canEditIctAcceptance(User $user, RequestModel $ticket): bool
    {
        return $user->role === 'user' && (int) $ticket->user_id === (int) $user->id;
    }

    /** Section 4 (Service Provider) is optional — IT fills only when repair goes to an external vendor. */
    public static function isServiceProviderSectionInUse(?RepairRequest $repair): bool
    {
        if (!$repair) {
            return false;
        }

        $types = json_decode($repair->repair_type ?? '[]', true) ?: [];

        if (in_array('REFERRED TO SERVICE PROVIDER', $types, true)) {
            return true;
        }

        return !empty($repair->company_name)
            || !empty($repair->service_date)
            || !empty($repair->pullout_date)
            || !empty($repair->technician_signature)
            || !empty($repair->technician_printed_name)
            || !empty($repair->action_taken);
    }

    /**
     * End-user signs Section 6 only after IT Section 5 (after-repair + IT signature).
     * Section 4 is optional and never required for user acceptance.
     */
    public static function canSignIctAcceptance(User $user, RequestModel $ticket, ?RepairRequest $repair): bool
    {
        if (!self::canEditIctAcceptance($user, $ticket) || !$repair) {
            return false;
        }

        if ($ticket->status === RequestModel::STATUS_COMPLETED) {
            return false;
        }

        if (!empty($repair->end_user_acceptance_signature)) {
            return false;
        }

        if (empty($repair->after_repair_status)) {
            return false;
        }

        return !empty($repair->it_personnel_signature);
    }

    public static function ictAcceptanceBlockReason(?RepairRequest $repair): ?string
    {
        if (!$repair || !empty($repair->end_user_acceptance_signature)) {
            return null;
        }

        if (empty($repair->after_repair_status) || empty($repair->it_personnel_signature)) {
            return 'Disabled until IT completes Section 5 (after-repair status and IT personnel signature). Refresh the page after IT saves.';
        }

        return null;
    }

    public static function canViewIctTicket(User $user, RequestModel $ticket): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role === 'user') {
            return (int) $ticket->user_id === (int) $user->id;
        }

        if ($user->role === 'it') {
            // IT can view tickets currently assigned to them OR historically assigned
            return (int) $ticket->assigned_to === (int) $user->id
                || self::wasEverAssignedToIt($user, $ticket);
        }

        if ($user->isDivisionAdmin()) {
            // Division admin (admin or supply_officer) can view tickets in their own division scope
            if (self::ticketInAdminScope($user, $ticket)) {
                return true;
            }

            if ($user->canProcessSupply()) {
                // Supply admin can view tickets that have requisitions in their scope
                if (self::ticketHasRequisitionInSupplyScope($user, $ticket)) {
                    return true;
                }
                // Supply admin can also view tickets linked to assets in their branch
                // (e.g., viewing repair history from asset detail page)
                if ($ticket->linked_asset_id) {
                    $asset = \App\Models\InventoryAsset::find($ticket->linked_asset_id);
                    if ($asset && (!$user->branch || $asset->branch === $user->branch)) {
                        return true;
                    }
                }
            }

            return false;
        }

        return false;
    }

    public static function canUpdateIctTicket(User $user, RequestModel $ticket): bool
    {
        if ($ticket->status === RequestModel::STATUS_COMPLETED) {
            return false;
        }

        // Block IT/super_admin from editing once ticket is awaiting user sign-off
        if ($ticket->status === RequestModel::STATUS_AWAITING_SIGNATURE) {
            if ($user->role === 'user' && (int) $ticket->user_id === (int) $user->id) {
                return true; // user can sign acceptance
            }
            return false; // IT/super_admin cannot edit
        }

        // Use the same logic as canEditIctTechnicianSections
        if (self::canEditIctTechnicianSections($user, $ticket)) {
            return true;
        }

        if ($user->role === 'user') {
            // User can sign acceptance (normal flow)
            if (self::canEditIctAcceptance($user, $ticket)) {
                return true;
            }
            // User can resubmit if rejected
            if ($ticket->status === RequestModel::STATUS_REJECTED
                && (int) $ticket->user_id === (int) $user->id) {
                return true;
            }
        }

        return false;
    }

    public static function ticketInSuperAdminBranch(User $superAdmin, RequestModel $ticket): bool
    {
        if (!$superAdmin->branch) {
            return true;
        }
        $ticketUser = $ticket->user;
        if ($ticketUser && $ticketUser->branch !== $superAdmin->branch) {
            return false;
        }
        // If ticket has no user (orphaned), allow super admin access
        return true;
    }

    /** PM technician / checklist sections — assigned IT only (Option 3). */
    public static function canEditMaintenanceTechnician(User $user, ?RequestModel $ticket = null): bool
    {
        if ($ticket) {
            // If assigned to someone, ONLY that person can edit
            if ($ticket->assigned_to) {
                return (int) $ticket->assigned_to === (int) $user->id;
            }
            
            // If NOT assigned, Super Admin can edit (acting as IT) — must be same branch
            if ($user->role === 'super_admin') {
                return self::ticketInSuperAdminBranch($user, $ticket);
            }
        }

        return false;
    }

    public static function canViewMaintenanceTicket(User $user, RequestModel $ticket): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role === 'user') {
            return (int) $ticket->user_id === (int) $user->id;
        }

        if ($user->role === 'it') {
            // IT can view tickets currently or historically assigned to them
            if ((int) $ticket->assigned_to === (int) $user->id || self::wasEverAssignedToIt($user, $ticket)) {
                return true;
            }

            // IT can VIEW (read-only) PM tickets in their branch scope.
            // This supports the QR scan flow where IT scans an asset and sees its active PM.
            // Note: old auto-generated records may have ticket->branch = null, so we check
            // multiple fallbacks: ticket branch → asset branch → no restriction if IT has no branch.

            // If IT has no branch restriction, they can see all
            if (!$user->branch) {
                return true;
            }

            // Check ticket branch first
            if ($ticket->branch) {
                return $ticket->branch === $user->branch;
            }

            // Ticket branch is null (old record) — check linked asset
            if ($ticket->linked_asset_id) {
                $asset = InventoryAsset::find($ticket->linked_asset_id);
                if ($asset) {
                    // If asset has no branch either, allow IT to view (can't determine scope)
                    return !$asset->branch || $asset->branch === $user->branch;
                }
            }

            // Auto-generated PM with no branch data — allow IT to view
            // (Scan controller already validated branch scope before sending IT here)
            if (!empty($ticket->is_auto_generated)) {
                return true;
            }

            return false;
        }

        if ($user->role === 'admin') {
            return false; // Division Admin should not see PM tickets
        }

        return false;
    }

    public static function canUpdateMaintenanceTicket(User $user, RequestModel $ticket): bool
    {
        if ($ticket->status === RequestModel::STATUS_COMPLETED) {
            return false;
        }

        // Use the same logic as canEditMaintenanceTechnician
        if (self::canEditMaintenanceTechnician($user, $ticket)) {
            return true;
        }

        if ($user->role === 'admin') {
            return false;
        }

        if ($user->role === 'user') {
            // User can only update when AWAITING_SIGNATURE (sign off) or REJECTED (resubmit)
            if (!in_array($ticket->status, [RequestModel::STATUS_AWAITING_SIGNATURE, RequestModel::STATUS_REJECTED], true)) {
                return false;
            }
            return (int) $ticket->user_id === (int) $user->id;
        }

        return false;
    }

    public static function ictFormFlags(
        User $user,
        ?RequestModel $ticket = null,
        bool $forceView = false,
        ?RepairRequest $repair = null
    ): array {
        // Regular admin: view only (cannot edit technician sections)
        // Supply officer: can view and review (forward to Super Admin)
        $isRegularAdmin = $user->role === 'admin' && !$user->canProcessSupply();
        $viewOnly = $forceView
            || $isRegularAdmin
            || ($ticket && $ticket->status === RequestModel::STATUS_COMPLETED);

        $canEditTechnician = !$viewOnly && self::canEditIctTechnicianSections($user, $ticket);
        $canAssignIt = $ticket && self::canAssignTicket($user, $ticket) && $ticket->type === 'ICT';
        
        // Division admin review: own division only
        // Supply admin (Administrative): can review any ticket in their branch
        $canReviewAsDivisionAdmin = false;
        if ($ticket && $user->isDivisionAdmin() && !$ticket->division_admin_review_status) {
            if ($user->canProcessSupply()) {
                // Supply admin reviews all tickets in branch
                $ticketUser = $ticket->user;
                $canReviewAsDivisionAdmin = !$ticketUser || !$user->branch || $ticketUser->branch === $user->branch;
            } else {
                $canReviewAsDivisionAdmin = self::ticketInAdminScope($user, $ticket);
            }
        }
        $canSignAcceptance = $ticket && $repair && self::canSignIctAcceptance($user, $ticket, $repair);

        return [
            'isAdmin' => $canEditTechnician,
            'canEditTechnician' => $canEditTechnician,
            'canEditEndUser' => !$viewOnly && self::canEditIctEndUserSection($user, $ticket),
            'canAssignIt' => $canAssignIt,
            'canReviewAsDivisionAdmin' => $canReviewAsDivisionAdmin,
            'canSignAcceptance' => $canSignAcceptance,
            'acceptanceBlockReason' => ($user->role === 'user' && $ticket && $repair)
                ? self::ictAcceptanceBlockReason($repair)
                : null,
            'isView' => $viewOnly,
            'viewMode' => $viewOnly,
        ];
    }

    public static function maintenanceFormFlags(User $user, ?RequestModel $ticket = null, bool $forceView = false): array
    {
        $viewOnly = $forceView
            || $user->role === 'admin'
            || ($ticket && $ticket->status === RequestModel::STATUS_COMPLETED);

        $canEditTechnician = !$viewOnly && self::canEditMaintenanceTechnician($user, $ticket);

        $canAssignIt = $ticket && self::canAssignTicket($user, $ticket) && $ticket->type === 'Preventive Maintenance';
        
        // Division admin review: own division only
        // Supply admin (Administrative): can review any ticket in their branch
        $canReviewAsDivisionAdmin = false;
        if ($ticket && $user->isDivisionAdmin() && !$ticket->division_admin_review_status) {
            if ($user->canProcessSupply()) {
                $ticketUser = $ticket->user;
                $canReviewAsDivisionAdmin = !$ticketUser || !$user->branch || $ticketUser->branch === $user->branch;
            } else {
                $canReviewAsDivisionAdmin = self::ticketInAdminScope($user, $ticket);
            }
        }

        // For PM tickets, IT/super_admin can capture end-user signature in one step
        $canEditPmEndUser = !$viewOnly
            && $ticket
            && $ticket->type === 'Preventive Maintenance'
            && in_array($user->role, ['it', 'super_admin'], true)
            && in_array($ticket->status, [RequestModel::STATUS_SCHEDULED, RequestModel::STATUS_ONGOING, RequestModel::STATUS_AWAITING_SIGNATURE], true);

        return [
            'isAdmin' => $canEditTechnician,
            'canEditTechnician' => $canEditTechnician,
            'canEditEndUser' => !$viewOnly && (self::canEditIctEndUserSection($user, $ticket) || $canEditPmEndUser),
            'canAssignIt' => $canAssignIt,
            'canReviewAsDivisionAdmin' => $canReviewAsDivisionAdmin,
            'isView' => $viewOnly,
            'viewMode' => $viewOnly,
        ];
    }

    public static function ticketInAdminScope(User $admin, RequestModel $ticket): bool
    {
        $ticketUser = $ticket->user;

        if ($ticketUser) {
            // Branch must always match
            if ($admin->branch && $ticketUser->branch !== $admin->branch) {
                return false;
            }

            // Supply Officer (Administrative) can see tickets from their own office
            // Regular Admin: must match office (division)
            if ($admin->canProcessSupply()) {
                // Supply Officer: check if ticket user is in their office (Administrative)
                if ($admin->office && $ticketUser->office !== $admin->office) {
                    return false;
                }
            } else {
                // Regular Admin: must match office (division)
                if ($admin->office && $ticketUser->office !== $admin->office) {
                    return false;
                }
            }

            return true;
        }

        return true;
    }

    /** Admin quick status from Department Request list (not a substitute for IT form work). */
    public static function canAdminQuickUpdateStatus(User $admin, RequestModel $ticket, string $newStatus): bool
    {
        if (!$admin->isDivisionAdmin()) {
            return false;
        }

        if (in_array($ticket->status, [RequestModel::STATUS_COMPLETED, RequestModel::STATUS_AWAITING_SIGNATURE], true)) {
            return false;
        }

        if (!self::ticketInAdminScope($admin, $ticket)) {
            return false;
        }

        if (in_array($ticket->type, ['ICT', 'Preventive Maintenance'], true)) {
            if ($newStatus === RequestModel::STATUS_COMPLETED) {
                return false;
            }

            if ($newStatus === RequestModel::STATUS_ONGOING && empty($ticket->assigned_to)) {
                return false;
            }
            // Cannot force AWAITING_SIGNATURE via quick update
            if ($newStatus === RequestModel::STATUS_AWAITING_SIGNATURE) {
                return false;
            }
        }

        return true;
    }

    public static function ticketInUserScope(User $user, RequestModel $ticket): bool
    {
        $ticketUser = $ticket->user;
        if (!$ticketUser) {
            return true;
        }

        if ($user->branch && $ticketUser->branch !== $user->branch) {
            return false;
        }
        if ($user->office && $ticketUser->office !== $user->office) {
            return false;
        }
        if ($user->department && $ticketUser->department !== $user->department) {
            return false;
        }

        return true;
    }

    /** IT requester (or any user) within the same branch / office scope as the Administrative supply admin. */
    public static function userInSupplyAdminScope(User $supply, User $subject): bool
    {
        if ($supply->role === 'super_admin') {
            return true;
        }

        if (!$supply->canProcessSupply()) {
            return false;
        }

        // Super Admin acting as IT/requester is always visible to any supply officer
        // regardless of branch — they operate office-wide
        if ($subject->role === 'super_admin') {
            return true;
        }

        // Regular IT/users: must be in the same branch as the supply officer
        if ($supply->branch && $subject->branch !== $supply->branch) {
            return false;
        }

        return true;
    }
    
    /**
     * Check if IT personnel was ever assigned to this ticket (historical access).
     */
    public static function wasEverAssignedToIt(User $itUser, RequestModel $ticket): bool
    {
        if ($itUser->role !== 'it') {
            return false;
        }

        // Currently assigned
        if ((int) $ticket->assigned_to === (int) $itUser->id) {
            return true;
        }

        // Check audit logs for assignment history (using correct column names)
        return \App\Models\AuditLog::where('action', 'LIKE', '%Assign%')
            ->where('module', 'Requests')
            ->where('details', 'LIKE', '%' . $ticket->request_number . '%')
            ->where('details', 'LIKE', '%' . $itUser->full_name . '%')
            ->exists();
    }

    public static function ticketHasRequisitionInSupplyScope(User $supply, RequestModel $ticket): bool
    {
        if (!$supply->canProcessSupply()) {
            return false;
        }

        $query = \App\Models\Requisition::query()->where('request_id', $ticket->id);
        self::scopeRequisitionsForSupplyOfficer($supply, $query);

        return $query->exists();
    }

    public static function canSupplyManageRequisition(User $supply, \App\Models\Requisition $requisition): bool
    {
        if (!$supply->canProcessSupply()) {
            return false;
        }

        $requester = $requisition->requester;
        if (!$requester) {
            return false;
        }

        return self::userInSupplyAdminScope($supply, $requester);
    }

    public static function canViewRequisition(User $user, \App\Models\Requisition $requisition): bool
    {
        $ticket = $requisition->ticket;
        if (!$ticket) {
            return false;
        }

        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role === 'it') {
            return (int) $requisition->requested_by === (int) $user->id
                || (int) $ticket->assigned_to === (int) $user->id;
        }

        if ($user->canProcessSupply()) {
            return self::canSupplyManageRequisition($user, $requisition);
        }

        if ($user->role === 'admin') {
            return self::ticketInAdminScope($user, $ticket);
        }

        if ($user->role === 'user') {
            return (int) $ticket->user_id === (int) $user->id;
        }

        return false;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Requisition>  $query
     */
    public static function scopeRequisitionsForSupplyOfficer(User $supply, $query)
    {
        if ($supply->role === 'super_admin') {
            return $query;
        }

        // Use whereExists with a raw subquery for better performance vs whereHas
        // Super admin requesters are always visible; regular users must match branch + office
        return $query->whereExists(function ($sub) use ($supply) {
            $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                ->from('users')
                ->whereColumn('users.id', 'requisitions.requested_by')
                ->where(function ($q) use ($supply) {
                    if ($supply->branch) {
                        $q->where('users.branch', $supply->branch);
                    }
                    if ($supply->office) {
                        $q->where('users.office', $supply->office);
                    }
                });
        });
    }

    /**
     * ICT tickets assigned to IT personnel within the Administrative supply admin's office scope.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Request>  $query
     */
    public static function scopeIctTicketsForSupplyAdmin(User $supply, $query)
    {
        if ($supply->role === 'super_admin') {
            return $query->where('type', 'ICT');
        }

        return $query->where('type', 'ICT')
            ->whereExists(function ($sub) use ($supply) {
                $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'requests.assigned_to')
                    ->where(function ($q) use ($supply) {
                        if ($supply->branch) {
                            $q->where('users.branch', $supply->branch);
                        }
                        if ($supply->office) {
                            $q->where('users.office', $supply->office);
                        }
                    });
            });
    }
    /** Field whitelist for IT personnel update (Sections 2–5, no acceptance). */
    public static function ictTechnicianFieldKeys(): array
    {
        return [
            'itReceivedLastName', 'it_received_last_name',
            'itReceivedFirstName', 'it_received_first_name',
            'itReceivedMiddleName', 'it_received_middle_name',
            'initialDiagnosis', 'initial_diagnosis',
            'repairType', 'repair_type',
            'itRemarks', 'it_remarks',
            'serviceRequestNo', 'service_request_no',
            'rid',
            'dateReceived', 'date_received',
            'serviceScheduleDate', 'service_schedule_date',
            'propertyNo', 'property_no',
            'articleSerialNo', 'article_serial_no',
            'officeDateAcquired', 'office_date_acquired',
            'technicianSignature', 'technician_signature',
            'technicianPrintedName', 'technician_printed_name',
            'technicianDate', 'technician_date',
            'serviceDate', 'service_date',
            'pulloutDate', 'pullout_date',
            'companyName', 'company_name',
            'companyPhone', 'company_phone',
            'companyEmail', 'company_email',
            'companyAddress', 'company_address',
            'actionTaken', 'action_taken',
            'technicianLastName', 'technician_last_name',
            'technicianFirstName', 'technician_first_name',
            'technicianMiddleName', 'technician_middle_name',
            'afterRepairStatus', 'after_repair_status',
            'afterServiceDate', 'after_service_date',
            'findingsRemarks', 'findings_remarks',
            'itPersonnelSignature', 'it_personnel_signature',
            'itPersonnelPrintedName', 'it_personnel_printed_name',
            'itPersonnelDate', 'it_personnel_date',
            'last_updated_at',
        ];
    }
}
