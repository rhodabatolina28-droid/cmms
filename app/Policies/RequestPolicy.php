<?php

namespace App\Policies;

use App\Models\Request as RequestModel;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\RequestHelpers;

class RequestPolicy
{
    use HandlesAuthorization;

    public function createIct(User $user): bool
    {
        return in_array($user->role, ['user', 'it', 'super_admin'], true);
    }


    public function createMaintenance(User $user): bool
    {
        return in_array($user->role, ['it', 'super_admin']);
    }

    /** Asset must be assigned to the submitting end-user (or was recently assigned within 30 days). */

    public function editIctTechnician(User $user, ?RequestModel $ticket = null): bool
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
            
            // If NOT assigned, NO ONE can edit (must be assigned first via the Assign IT panel)
            return false;
        }

        return false;
    }

    /** Only Super Admin can assign ICT or PM tickets to IT personnel. Division Admin cannot assign IT. */

    public function assignTicket(User $user, RequestModel $ticket): bool
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

    public function editIctEndUser(User $user, ?RequestModel $ticket = null): bool
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

    public function editIctAcceptance(User $user, ?RequestModel $ticket = null): bool
    {
        if (!$ticket) {
            return false;
        }
        return (int) $ticket->user_id === (int) $user->id;
    }

    /** Section 4 (Service Provider) is optional — IT fills only when repair goes to an external vendor. */

    public function signAcceptance(User $user, RequestModel $ticket, ?RepairRequest $repair): bool
    {
        if (!$this->editIctAcceptance($user, $ticket) || !$repair) {
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


    public function viewIct(User $user, RequestModel $ticket): bool
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
                || \App\Support\RequestHelpers::wasEverAssignedToIt($user, $ticket);
        }

        if ($user->isDivisionAdmin()) {
            // Division admin (admin or supply_officer) can view tickets in their own division scope
            if (\App\Support\RequestHelpers::ticketInAdminScope($user, $ticket)) {
                return true;
            }

            if ($user->canProcessSupply()) {
                // Supply admin can view tickets that have requisitions in their scope
                if (\App\Support\RequestHelpers::ticketHasRequisitionInSupplyScope($user, $ticket)) {
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


    public function updateIct(User $user, RequestModel $ticket): bool
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

        // Use the same logic as editIctTechnician
        if ($this->editIctTechnician($user, $ticket)) {
            return true;
        }

        if ($user->role === 'user') {
            // User can sign acceptance (normal flow)
            if ($this->editIctAcceptance($user, $ticket)) {
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


    public function editMaintenanceTechnician(User $user, ?RequestModel $ticket = null): bool
    {
        if ($ticket) {
            // If assigned to someone, ONLY that person can edit
            if ($ticket->assigned_to) {
                return (int) $ticket->assigned_to === (int) $user->id;
            }
            
            // If NOT assigned, NO ONE can edit (must be assigned first via the Assign IT panel)
            return false;
        }

        return false;
    }


    public function viewMaintenance(User $user, RequestModel $ticket): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role === 'user') {
            return (int) $ticket->user_id === (int) $user->id;
        }

        if ($user->role === 'it') {
            // IT can view tickets currently or historically assigned to them
            if ((int) $ticket->assigned_to === (int) $user->id || \App\Support\RequestHelpers::wasEverAssignedToIt($user, $ticket)) {
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
            // Admin/Supply Officer can view PM tickets linked to assets in their branch scope
            if ($ticket->linked_asset_id) {
                $asset = \App\Models\InventoryAsset::find($ticket->linked_asset_id);
                if ($asset && (!$user->branch || $asset->branch === $user->branch)) {
                    return true;
                }
            }
            // Allow viewing if the ticket's user is in the admin's scope
            if ($ticket->user && $user->office && $ticket->user->office === $user->office) {
                return true;
            }
            return false;
        }

        return false;
    }


    public function updateMaintenance(User $user, RequestModel $ticket): bool
    {
        if ($ticket->status === RequestModel::STATUS_COMPLETED) {
            return false;
        }

        // Use the same logic as editMaintenanceTechnician
        if ($this->editMaintenanceTechnician($user, $ticket)) {
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


}
