<?php

namespace App\Actions\SuperAdmin;

use App\Models\User;
use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class ToggleUserStatusAction
{
    /**
     * Update user status (Active/Inactive).
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute($id)
    {
        $user = User::findOrFail($id);
        $this->abortIfOutsideOfficeScope($user);

        // Prevent super admin from disabling their own account
        if ($user->id === Auth::id()) {
            return response()->json(['success' => false, 'message' => 'You cannot disable your own account'], 403);
        }

        $wasActive = $user->is_active;
        $user->is_active = !$user->is_active;
        $user->save();

        if ($wasActive && !$user->is_active) {
            $this->deallocateAssignedAssets($user, Auth::id());
        }

        AuditLog::log(
            "Toggled User Status",
            "User Management",
            "Changed status of {$user->full_name} to " . ($user->is_active ? 'Active' : 'Inactive'),
            $user->office
        );

        return response()->json([
            'success' => true,
            'message' => 'Status updated',
            'is_active' => $user->is_active
        ]);
    }

    /**
     * Abort if the target user is outside the actor's region/branch scope.
     */
    private function abortIfOutsideOfficeScope(User $user): void
    {
        $actor = Auth::user();

        // Super Admin is region-scoped (prevents cross-region data leaks)
        if ($actor->region && $user->region !== $actor->region) {
            abort(403, 'This user is outside your region scope.');
        }

        // Super Admin is office-scoped (branch level only)
        if ($actor->branch && $user->branch !== $actor->branch) {
            abort(403, 'This user is outside your branch scope.');
        }

        // Super Admin manages entire branch - no division check needed
    }

    /**
     * Deallocate all assets assigned to a deactivated user.
     */
    private function deallocateAssignedAssets(User $user, ?int $performedBy): void
    {
        InventoryAsset::where('assigned_to_user', $user->id)
            ->chunk(50, function ($assets) use ($user, $performedBy) {
                foreach ($assets as $asset) {
                    $previousStatus = $asset->status;
                    $previousUserId = $asset->assigned_to_user;

                    $asset->assigned_to_user = null;
                    $asset->save();

                    InventoryHistory::create([
                        'asset_id'         => $asset->asset_id,
                        'action'           => 'Auto Deallocation',
                        'performed_by'     => $performedBy,
                        'previous_user_id' => $previousUserId,
                        'new_user_id'      => null,
                        'previous_status'  => $previousStatus,
                        'new_status'       => $asset->status,
                        'remarks'          => "Asset automatically deallocated because user {$user->full_name} was deactivated.",
                    ]);
                }
            });
    }
}
