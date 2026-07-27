<?php

namespace App\Models\Scopes;

use App\Models\User;
use App\Models\InventoryAsset;
use Illuminate\Database\Eloquent\Builder;

class InventoryScope
{
    /**
     * Scope the inventory query based on the actor's role and organizational scope.
     */
    public static function scopeAssetsToActor(Builder $query, User $actor): void
    {
        $query->where('region', $actor->region);

        if ($actor->canProcessSupply()) {
            if ($actor->branch) {
                $query->where('branch', $actor->branch);
            }
            return;
        }

        if (!$actor->branch && !$actor->office && !$actor->department) {
            return;
        }

        $query->where(function ($q) use ($actor) {
            // Assigned assets: match via the assigned user's org scope
            $q->where(function ($assetScope) use ($actor) {
                if ($actor->branch) {
                    $assetScope->where('branch', $actor->branch);
                }
                if ($actor->office) {
                    $assetScope->where('office', $actor->office);
                }
                if ($actor->department) {
                    $assetScope->where('department', $actor->department);
                }
            })->orWhereHas('assignedUser', function ($userScope) use ($actor) {
                if ($actor->branch) {
                    $userScope->where('branch', $actor->branch);
                }
                if ($actor->office) {
                    $userScope->where('office', $actor->office);
                }
                if ($actor->department) {
                    $userScope->where('department', $actor->department);
                }
            })->orWhere(function ($unassigned) use ($actor) {
                // Unassigned assets: require STRICT AND match on all org fields.
                // Prevents unassigned assets from leaking across divisions.
                $unassigned->whereNull('assigned_to_user');
                if ($actor->branch) {
                    $unassigned->where('branch', $actor->branch);
                }
                if ($actor->office) {
                    $unassigned->where(function ($o) use ($actor) {
                        $o->where('office', $actor->office)
                          ->orWhereNull('office');
                    });
                }
                if ($actor->department) {
                    $unassigned->where(function ($d) use ($actor) {
                        $d->where('department', $actor->department)
                          ->orWhereNull('department');
                    });
                }
            });
        });
    }

    /**
     * Check if an asset is within the actor's view scope.
     */
    public static function assetInActorViewScope(User $actor, InventoryAsset $asset): bool
    {
        if ($actor->canProcessSupply()) {
            if ($asset->region !== $actor->region) {
                return false;
            }
            if ($actor->branch && $asset->branch !== $actor->branch) {
                return false;
            }
            return true;
        }

        if (!$actor->branch && !$actor->office && !$actor->department) {
            return true;
        }

        $assetMatches = (!$actor->branch || $asset->branch === $actor->branch)
            && (!$actor->office || $asset->office === $actor->office)
            && (!$actor->department || $asset->department === $actor->department);

        if ($assetMatches) {
            return true;
        }

        if (!$asset->assigned_to_user) {
            return false;
        }

        $assignedUser = User::find($asset->assigned_to_user);

        return $assignedUser ? self::subjectInActorOrgScope($actor, $assignedUser) : false;
    }

    /**
     * Check if asset is within inventory write scope.
     */
    public static function assetInInventoryScope(User $user, InventoryAsset $asset): bool
    {
        if (! $user->canProcessSupply() || ! self::assetInActorViewScope($user, $asset)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a subject user is within the actor's organizational scope.
     */
    public static function userInInventoryScope(User $actor, User $subject, ?string $assetRegion = null): bool
    {
        if (! $actor->canProcessSupply()) {
            return false;
        }

        return self::subjectInActorOrgScope($actor, $subject);
    }

    /**
     * Check organizational scope match between actor and subject.
     */
    public static function subjectInActorOrgScope(User $actor, User $subject): bool
    {
        if ($actor->branch && $subject->branch !== $actor->branch) {
            return false;
        }
        
        // Administrative supply admin manages entire branch inventory (office-wide)
        // They don't need office/division match - only branch matters
        if ($actor->canProcessSupply()) {
            return true;
        }
        
        if ($actor->office && $subject->office !== $actor->office) {
            return false;
        }

        return true;
    }

    /**
     * Apply organizational scope fields to inventory data based on actor/asset.
     */
    public static function applyInventoryOrgScope(array &$data, User $actor, ?InventoryAsset $asset = null): void
    {
        $assignedUser = !empty($data['assigned_to_user']) ? User::find($data['assigned_to_user']) : null;

        if ($assignedUser) {
            $data['region'] = $assignedUser->region;
            $data['branch'] = $assignedUser->branch;
            $data['office'] = $assignedUser->office;
            $data['department'] = $assignedUser->department;
            return;
        }

        if ($asset) {
            $data['region'] = $asset->region;
            $data['branch'] = $asset->branch;
            $data['office'] = $asset->office;
            $data['department'] = $asset->department;
            return;
        }

        // Default to actor's scope for completely unassigned assets.
        $data['region'] = $actor->region;
        $data['branch'] = $actor->branch;
        $data['office'] = $actor->office;
        $data['department'] = $actor->department;
    }

    /**
     * Validate that the assigned user is within the actor's inventory assignment scope.
     */
    public static function validateAssignedUserScope(User $actor, mixed $assignedUserId, ?string $assetRegion): ?string
    {
        if (empty($assignedUserId)) {
            return null;
        }

        $assignedUser = User::find($assignedUserId);
        if (!$assignedUser) {
            return 'Selected user does not exist.';
        }

        if (! self::userInInventoryScope($actor, $assignedUser, $assetRegion)) {
            return 'Selected custodian is outside your inventory assignment scope.';
        }

        return null;
    }
}