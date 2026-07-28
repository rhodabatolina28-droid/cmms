<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreSuperAdminUserRequest;
use App\Http\Requests\UpdateSuperAdminUserRequest;
use App\Actions\SuperAdmin\GetRequestsDataAction;
use App\Actions\SuperAdmin\GetAuditLogsDataAction;
use App\Actions\SuperAdmin\GetUsersDataAction;
use App\Actions\SuperAdmin\StoreUserAction;
use App\Actions\SuperAdmin\UpdateUserAction;
use App\Actions\SuperAdmin\ToggleUserStatusAction;
use App\Actions\SuperAdmin\ArchiveLogsAction;

class SuperAdminController extends Controller
{
    public function auditLogs()
    {
        return view('super-admin.audit-logs.index');
    }

    /**
     * AJAX endpoint — returns paginated, filtered master list of requests with stats.
     */
    public function requestsData(Request $request)
    {
        return (new GetRequestsDataAction)->execute($request);
    }

    /**
     * AJAX endpoint — returns paginated, filtered audit logs with stats.
     */
    public function auditLogsData(Request $request)
    {
        return (new GetAuditLogsDataAction)->execute($request);
    }

    public function users(Request $request)
    {
        $actor = Auth::user();

        // Handle AJAX request for single user data (for edit modal)
        if ($request->has('get_user')) {
            $user = User::findOrFail($request->get('get_user'));
            $this->abortIfOutsideOfficeScope($user);

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'region' => $user->region,
                    'branch' => $user->branch,
                    'department' => $user->department,
                    'office' => $user->office,
                ]
            ]);
        }

        return view('super-admin.users.index');
    }

    /**
     * AJAX endpoint — returns paginated, filtered user list with stats.
     */
    public function usersData(Request $request)
    {
        return (new GetUsersDataAction)->execute($request);
    }

    /**
     * Store a newly created user.
     * Super Admin must explicitly assign office/division — no auto-fill from actor scope.
     */
    public function storeUser(StoreSuperAdminUserRequest $request)
    {
        return (new StoreUserAction)->execute($request);
    }

    /**
     * Update an existing user.
     */
    public function updateUser(UpdateSuperAdminUserRequest $request, $id)
    {
        return (new UpdateUserAction)->execute($request, $id);
    }

    /**
     * Update user status (Active/Inactive).
     */
    public function toggleUserStatus($id)
    {
        return (new ToggleUserStatusAction)->execute($id);
    }

    /**
     * Reset user password to random temporary password.
     */
    public function resetPassword($id)
    {
        $user = User::findOrFail($id);
        $this->abortIfOutsideOfficeScope($user);

        $tempPassword = \Illuminate\Support\Str::random(12);
        $user->password = Hash::make($tempPassword);
        $user->save();

        // Force logout all active sessions for this user
        DB::table('sessions')->where('user_id', $user->id)->delete();

        AuditLog::log(
            "Reset User Password",
            "User Management",
            "Reset password for {$user->full_name}",
            $user->office
        );

        return response()->json([
            'success' => true,
            'temp_password' => $tempPassword,
            'message' => "Password has been reset. Inform the user to change it upon next login.",
        ]);
    }

    /**
     * Delete a user (Archive).
     */
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $this->abortIfOutsideOfficeScope($user);

        return response()->json([
            'success' => false,
            'message' => 'To maintain CMMS audit integrity, users cannot be permanently deleted. Please deactivate the account instead.'
        ], 403);
    }

    /**
     * Archive old audit logs to CSV and delete them from the database.
     */
    public function archiveLogs()
    {
        return (new ArchiveLogsAction)->execute();
    }

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
