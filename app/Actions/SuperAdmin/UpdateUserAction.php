<?php

namespace App\Actions\SuperAdmin;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UpdateUserAction
{
    /**
     * Update an existing user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $this->abortIfOutsideOfficeScope($user);

        $validated = $request->validated();

        // Convert supply_officer to admin with can_supply=1 (one role per user in government setup)
        if ($validated['role'] === 'supply_officer') {
            $dept = strtoupper($validated['department'] ?? '');
            $office = strtoupper($validated['office'] ?? '');
            $isAdminDept = in_array($dept, ['ADMINISTRATIVE DIVISION', 'ADMINISTRATIVE', 'ADMINISTRATIVE DEPARTMENT']) ||
                          in_array($office, ['ADMINISTRATIVE DIVISION', 'ADMINISTRATIVE', 'ADMINISTRATIVE DEPARTMENT']);
            if (!$isAdminDept) {
                return response()->json([
                    'success' => false,
                    'message' => 'Supply Officer role can only be assigned to users in the Administrative Division/Department.',
                ], 422);
            }
            $validated['role'] = 'admin';
            $validated['can_supply'] = true;
        } else {
            $validated['can_supply'] = $request->boolean('can_supply');
        }

        $user->update($validated);

        AuditLog::log(
            "Updated User Account",
            "User Management",
            "Updated account for {$user->full_name} ({$user->email}) with role {$user->role} in {$user->office}",
            $user->office
        );

        return response()->json(['success' => true, 'message' => 'User updated successfully']);
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
}
