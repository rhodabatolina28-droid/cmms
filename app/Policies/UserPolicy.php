<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine if the user can view the resource.
     */
    public function view(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return $user->office === $model->office;
        }

        return $user->id === $model->id;
    }

    /**
     * Determine if the user is an admin.
     */
    public function isAdmin(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine if the user is a super admin.
     */
    public function isSuperAdmin(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user is a regular user.
     */
    public function isUser(User $user): bool
    {
        return $user->isUser();
    }

    /**
     * Determine if the user can manage users in their region.
     */
    public function manage(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return $user->office === $model->office;
        }

        return false;
    }
}
