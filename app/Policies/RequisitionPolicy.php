<?php

namespace App\Policies;

use App\Models\Requisition;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Support\RequestHelpers;

class RequisitionPolicy
{
    use HandlesAuthorization;

    public function manage(User $supply, \App\Models\Requisition $requisition): bool
    {
        if (!$supply->canProcessSupply()) {
            return false;
        }

        $requester = $requisition->requester;
        if (!$requester) {
            return false;
        }

        return \App\Support\RequestHelpers::userInSupplyAdminScope($supply, $requester);
    }


    public function view(User $user, \App\Models\Requisition $requisition): bool
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
            return $this->manage($user, $requisition);
        }

        if ($user->role === 'admin') {
            return \App\Support\RequestHelpers::ticketInAdminScope($user, $ticket);
        }

        if ($user->role === 'user') {
            return (int) $ticket->user_id === (int) $user->id;
        }

        return false;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Requisition>  $query
     */

}
