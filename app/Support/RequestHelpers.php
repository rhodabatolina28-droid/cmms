<?php

namespace App\Support;

use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\InventoryAsset;
use App\Models\RepairRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Shared helper methods to eliminate duplicate code across controllers and services.
 * All methods are static so they can be called from any class.
 */
class RequestHelpers
{
    /**
     * Generate a unique request number for ICT or PM tickets.
     * Format: {PREFIX}-{REGION}-{BRANCHCODE}-{YEAR}-{NUMBER}
     *
     * @param string $type 'ICT' or 'PM'
     * @param User|null $actorUser Used in cron context where Auth::user() is null
     */
    public static function generateRequestNumber(string $type, ?User $actorUser = null): string
    {
        $prefix = $type === 'ICT' ? 'REQ' : 'PM';
        $year = date('Y');

        $user = $actorUser ?? Auth::user();
        $region = strtoupper($user->region ?? 'SYS');
        $branchCode = self::getBranchCode($user->branch);

        $searchPrefix = "{$prefix}-{$region}-{$branchCode}-{$year}";

        // Use MySQL advisory lock to prevent race conditions
        $lockName = "request_number_{$region}_{$branchCode}_{$year}";
        $lockTimeout = 10;

        $acquired = DB::select("SELECT GET_LOCK(?, ?) AS acquired", [$lockName, $lockTimeout]);

        if (!($acquired[0]->acquired ?? false)) {
            Log::warning("Could not acquire advisory lock for request number generation (prefix: {$searchPrefix}). Proceeding without lock.");
        }

        try {
            $last = RequestModel::withTrashed()
                ->where('request_number', 'LIKE', "{$searchPrefix}-%")
                ->orderByDesc('request_number')
                ->value('request_number');

            $next = 1;
            if ($last) {
                $parts = explode('-', $last);
                $next = (int) end($parts) + 1;
            }

            return "{$prefix}-{$region}-{$branchCode}-{$year}-" . str_pad($next, 4, '0', STR_PAD_LEFT);
        } finally {
            DB::select("SELECT RELEASE_LOCK(?)", [$lockName]);
        }
    }

    /**
     * Convert a branch name to a short code for request numbering.
     */
    public static function getBranchCode(?string $branch): string
    {
        if (!$branch) {
            return 'SYS';
        }

        $branchUpper = strtoupper($branch);

        $mapping = [
            'RCMB' => 'RCMB',
            'NATIONAL CAPITAL REGION' => 'NCR',
            'NCR' => 'NCR',
            'REGION I' => 'RI',
            'REGION II' => 'RII',
            'REGION III' => 'RIII',
            'REGION IV-A' => 'R4A',
            'REGION IV-B' => 'R4B',
            'REGION V' => 'RV',
            'REGION VI' => 'RVI',
            'REGION VII' => 'RVII',
            'REGION VIII' => 'RVIII',
            'REGION IX' => 'RIX',
            'REGION X' => 'RX',
            'REGION XI' => 'RXI',
            'REGION XII' => 'RXII',
            'REGION XIII' => 'RXIII',
            'CAR' => 'CAR',
            'BARMM' => 'BARMM',
        ];

        foreach ($mapping as $keyword => $code) {
            if (str_contains($branchUpper, $keyword)) {
                return $code;
            }
        }

        $clean = preg_replace('/[^A-Z0-9]/', '', $branchUpper);
        return substr($clean, 0, 4) ?: 'SYS';
    }

    /**
     * Save a base64 signature image to storage/app/public/signatures/.
     */
    public static function saveSignature(?string $base64Data, string $type, string $name): ?string
    {
        if (empty($base64Data) || !str_contains($base64Data, 'data:image')) {
            return null;
        }

        try {
            $image = str_replace('data:image/png;base64,', '', $base64Data);
            $image = str_replace(' ', '+', $image);

            $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '', str_replace(' ', '_', $name));
            if (empty($safeName)) {
                $safeName = 'signature';
            }

            $filename = $type . '_' . $safeName . '_' . time() . '.png';
            $filepath = 'signatures/' . $filename;

            Storage::disk('public')->put($filepath, base64_decode($image));

            return $filepath;
        } catch (\Exception $e) {
            Log::error('Signature save failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if the authenticated user can access a given ticket.
     */
    public static function checkTicketAccess($trackingRequest): void
    {
        if (!Auth::user()->can('viewIct', $trackingRequest)) {
            abort(403, 'Unauthorized access to this request.');
        }
    }

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

    /** PM technician / checklist sections — assigned IT only (must be assigned first). */

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

        $canEditTechnician = !$viewOnly && $user->can('editIctTechnician', $ticket);
        $canAssignIt = $ticket && $user->can('assignTicket', $ticket) && $ticket->type === 'ICT';
        
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
        $modelToCheck = $ticket ?: RequestModel::class;

        // Permissions based on policy
        $canEditEndUser = !$viewOnly && $user->can('editIctEndUser', $modelToCheck);
        $canEditAcceptance = !$viewOnly && $user->can('editIctAcceptance', $modelToCheck);
        $canEditIctPersonnel = !$viewOnly && $user->can('editIctPersonnel', $modelToCheck);
        $canEditITSection = !$viewOnly && $user->can('editIctSection', $modelToCheck);
        $canEditServiceProv = !$viewOnly && $user->can('editIctServiceProvider', $modelToCheck);

        $canSignAcceptance = $ticket && $repair && $user->can('signAcceptance', [$ticket, $repair]);

        return [
            'isAdmin' => $canEditTechnician,
            'canEditTechnician' => $canEditTechnician,
            'canEditEndUser' => $canEditEndUser,
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

        $canEditTechnician = !$viewOnly && $user->can('editMaintenanceTechnician', $ticket);

        $canAssignIt = $ticket && $user->can('assignTicket', $ticket) && $ticket->type === 'Preventive Maintenance';
        
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

        $modelToCheck = $ticket ?: RequestModel::class;

        return [
            'isAdmin' => $canEditTechnician,
            'canEditTechnician' => $canEditTechnician,
            'canEditEndUser' => !$viewOnly && ($user->can('editIctEndUser', $modelToCheck) || $canEditPmEndUser),
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


    public static function scopeRequisitionsForSupplyOfficer(User $supply, $query)
    {
        if ($supply->role === 'super_admin') {
            return $query;
        }

        // Supply Officers are in Administrative office but handle supply for the entire branch
        // So we only filter by branch, not office
        return $query->whereExists(function ($sub) use ($supply) {
            $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                ->from('users')
                ->whereColumn('users.id', 'requisitions.requested_by')
                ->where(function ($q) use ($supply) {
                    if ($supply->branch) {
                        $q->where('users.branch', $supply->branch);
                    }
                });
        });
    }

    /**
     * ICT tickets that have requisitions in the Supply Officer's scope.
     * Only shows tickets where IT (assigned_to) requested parts and the requisition is in their branch.
     * Supply Officers are in Administrative office but handle supply for the entire branch.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Request>  $query
     */

    public static function scopeIctTicketsForSupplyAdmin(User $supply, $query)
    {
        // Both ICT and PM-generated tickets can carry parts requests, so both
        // belong in the Job Orders tab (PM mirrors superAdminRequisitionIndex rules).
        if ($supply->role === 'super_admin') {
            // Super admin: show all ICT/PM tickets that have requisitions
            return $query->where(fn ($q) => $q->where('type', 'ICT')
                ->orWhere('type', 'Preventive Maintenance'))
                ->whereHas('requisitions');
        }

        // Supply Officer: show ICT/PM tickets that have requisitions in their branch
        // (Supply Officers are in Administrative office but handle supply for the entire branch)
        return $query->where(fn ($q) => $q->where('type', 'ICT')
            ->orWhere('type', 'Preventive Maintenance'))
            ->whereHas('requisitions', function ($q) use ($supply) {
                $q->whereExists(function ($sub) use ($supply) {
                    $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('users')
                        ->whereColumn('users.id', 'requisitions.requested_by')
                        ->where(function ($uq) use ($supply) {
                            // Only filter by branch - Supply Officers handle supply for the entire branch
                            if ($supply->branch) {
                                $uq->where('users.branch', $supply->branch);
                            }
                        });
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
