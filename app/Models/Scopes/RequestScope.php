<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

class RequestScope
{
    /**
     * Scope the query based on the user's role for ICT requests.
     * 
     * NOTE: This only handles the query filtering. View selection
     * and JSON response formatting remain in the controller.
     *
     * @param  Builder  $query
     * @param  User  $user
     * @return Builder
     */
    public static function visibleToRole(Builder $query, User $user): Builder
    {
        if ($user->role === 'user') {
            // Regular users: ICT only (no PM)
            $query->where('type', 'ICT')->where('user_id', $user->id);
        } elseif ($user->role === 'it') {
            // IT: ICT only, assigned to them
            $query->where('type', 'ICT')
                  ->where('assigned_to', $user->id);
        } elseif ($user->role === 'admin' || $user->role === 'supply_officer') {
            // Admin/Supply Officer: ICT only (no PM)
            $query->where('type', 'ICT')->whereHas('user', function($q) use ($user) {
                if ($user->branch) {
                    $q->where('branch', $user->branch);
                }
                if ($user->office) {
                    $q->where('office', $user->office);
                }
            });
        } elseif ($user->role === 'super_admin') {
            // Super Admin: ICT only (PM is in PM Schedule module)
            $query->where('type', 'ICT')
                ->where('division_admin_review_status', 'Approved')
                ->whereHas('user', function ($q) use ($user) {
                    if ($user->branch) {
                        $q->where('branch', $user->branch);
                    }
                    // Super Admin manages entire branch - no division filter
                });
        }

        return $query;
    }
}