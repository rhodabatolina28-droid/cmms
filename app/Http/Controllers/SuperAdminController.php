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

class SuperAdminController extends Controller
{
    /**
     * Display a listing of users for management.
     */
    public function auditLogs()
    {
        $actor = Auth::user();
        // Super Admin sees ALL audit logs across all offices/divisions
        $logs = \App\Models\AuditLog::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('super-admin.audit-logs.index', compact('logs'));
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
        
        // Super Admin is office-scoped (branch level only)
        // They should see ALL users in their branch, not filtered by division
        $users = User::query()
            ->when($actor->branch, fn ($query) => $query->where('branch', $actor->branch))
            // Super Admin manages entire branch - no division filter
            ->orderBy('full_name', 'asc')
            ->paginate(20);

        return view('super-admin.users.index', compact('users'));
    }

    /**
     * Store a newly created user.
     * Super Admin must explicitly assign office/division — no auto-fill from actor scope.
     */
    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'full_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|min:8|regex:/[A-Z]/|regex:/[0-9]/',
            'role'       => 'required|in:' . implode(',', config('roles.list', ['user','admin','super_admin','it'])),
            'region'     => 'nullable|string',
            'position'   => 'nullable|string',
            'branch'     => 'nullable|string',
            'office'     => 'required|string|max:255',
            'department' => 'nullable|string',
            'can_supply' => 'nullable|boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = true;
        
        // Auto-set can_supply=1 for supply_officer role
        if ($validated['role'] === 'supply_officer') {
            $validated['can_supply'] = true;
        } else {
            $validated['can_supply'] = $request->boolean('can_supply');
        }

        // Always inherit region and branch from the creating super admin
        $validated['region'] = Auth::user()->region;
        $validated['branch'] = Auth::user()->branch;

        $user = User::create($validated);

        AuditLog::log(
            "Created User Account",
            "User Management",
            "Created account for {$user->full_name} ({$user->email}) with role {$user->role} in {$user->office}",
            $user->office
        );

        return response()->json(['success' => true, 'message' => 'User created successfully']);
    }

    /**
     * Update an existing user.
     */
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $this->abortIfOutsideOfficeScope($user);

        $validated = $request->validate([
            'full_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'role'       => 'required|in:' . implode(',', config('roles.list', ['user','admin','super_admin','it'])),
            'region'     => 'nullable|string',
            'position'   => 'nullable|string',
            'branch'     => 'nullable|string',
            'office'     => 'required|string|max:255',
            'department' => 'nullable|string',
            'can_supply' => 'nullable|boolean',
        ]);

        // Supply Officer validation: must be in Administrative Division/Department
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
     * Update user status (Active/Inactive).
     */
    public function toggleUserStatus($id)
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
        // Define the cutoff date: logs older than 1 year
        // NOTE: For capstone demonstration purposes, you can change subYear() to subDays(30) or subMonths(6)
        $cutoffDate = \Carbon\Carbon::now()->subYear();
        $actor = Auth::user();
        
        // Super Admin archives logs for their entire branch.
        // Note: audit_logs uses the 'region' column to store branch/office scope info.
        $oldLogs = AuditLog::with('user')
            ->when($actor->branch, fn ($query) => $query->where('region', $actor->branch))
            ->where('created_at', '<', $cutoffDate)
            ->limit(5000)
            ->get();
            
        if ($oldLogs->isEmpty()) {
            return back()->with('error', 'No logs older than 1 year found for archiving.');
        }

        // Write CSV to temp file first so data is safely captured before DB delete
        $tempPath = tempnam(sys_get_temp_dir(), 'audit_') . '.csv';
        $tempFile = fopen($tempPath, 'w');
        fputcsv($tempFile, ['ID', 'Date', 'Office', 'Action', 'Module', 'Description', 'User', 'User ID']);

        foreach ($oldLogs as $log) {
            fputcsv($tempFile, [
                $log->id,
                $log->created_at->format('Y-m-d H:i:s'),
                $log->region ?? 'N/A',
                $log->action,
                $log->module,
                $log->description,
                $log->user ? $log->user->full_name : 'System/Unknown',
                $log->user_id
            ]);
        }
        fclose($tempFile);

        // Delete the logs from DB (Super Admin scope: entire branch).
        // Note: audit_logs uses the 'region' column to store branch/office scope info.
        AuditLog::query()
            ->when($actor->branch, fn ($query) => $query->where('region', $actor->branch))
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        // Log the archiving action itself
        AuditLog::log(
            "Archived Old Logs", 
            "System", 
            "Exported and deleted " . $oldLogs->count() . " audit logs older than 1 year.",
            "System"
        );

        $csvFileName = 'audit_logs_archive_' . now()->format('Ymd_His') . '.csv';
        return response()->download($tempPath, $csvFileName, [
            'Content-Type' => 'text/csv',
        ])->deleteFileAfterSend(true);
    }

    private function abortIfOutsideOfficeScope(User $user): void
    {
        $actor = Auth::user();

        // Super Admin is office-scoped (branch level only)
        if ($actor->branch && $user->branch !== $actor->branch) {
            abort(403, 'This user is outside your branch scope.');
        }

        // Super Admin manages entire branch - no division check needed
    }
}
