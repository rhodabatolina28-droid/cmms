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
    public function auditLogs()
    {
        return view('super-admin.audit-logs.index');
    }

    /**
     * AJAX endpoint — returns paginated, filtered master list of requests with stats.
     */
    public function requestsData(Request $request)
    {
        $actor = Auth::user();

        // ── 1) Unfiltered stats ──
        $baseQuery = \App\Models\Request::where('type', 'ICT')
            ->where('division_admin_review_status', 'Approved')
            ->whereHas('user', function ($q) use ($actor) {
                if ($actor->branch) {
                    $q->where('branch', $actor->branch);
                }
            });

        $stats = [
            'total'     => (clone $baseQuery)->count(),
            'pending'   => (clone $baseQuery)->where('status', 'Pending')->count(),
            'ongoing'   => (clone $baseQuery)->where('status', 'Ongoing')->count(),
            'completed' => (clone $baseQuery)->where('status', 'Completed')->count(),
        ];

        // ── 2) Filtered + paginated query ──
        // Build base query WITHOUT with() to avoid N+1 on cloned stats queries
        $baseFiltered = \App\Models\Request::where('type', 'ICT')
            ->where('division_admin_review_status', 'Approved')
            ->whereHas('user', function ($q) use ($actor) {
                if ($actor->branch) {
                    $q->where('branch', $actor->branch);
                }
            });

        // Search
        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $baseFiltered->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(request_number) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(requestor_name) LIKE ?', ["%{$search}%"])
                  ->orWhereHas('user', fn ($uq) => $uq->whereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"]));
            });
        }

        // Department filter
        if ($department = $request->input('department')) {
            $baseFiltered->whereHas('user', fn ($q) => $q->where('department', $department));
        }

        // Division/Office filter
        if ($division = $request->input('division')) {
            $baseFiltered->where('office', $division);
        }

        // Status filter
        if ($status = $request->input('status')) {
            $baseFiltered->where('status', $status);
        }

        // My Assigned filter
        $myAssigned = $request->boolean('my_assigned');
        if ($myAssigned) {
            $baseFiltered->where('assigned_to', $actor->id);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $page    = max((int) $request->input('page', 1), 1);

        // Apply with() ONLY to the paginated data query, not to stats clones
        $requests = (clone $baseFiltered)->with(['assignedTo:id,full_name'])
            ->orderBy('created_at', 'desc')
            ->select(['id', 'request_number', 'description', 'requestor_name', 'office', 'assigned_to', 'status', 'created_at'])
            ->paginate($perPage, ['*'], 'page', $page);

        // Check if any filter is active
        $hasFilters = $request->filled('search') || $request->filled('department') ||
                      $request->filled('division') || $request->filled('status') ||
                      $myAssigned;

        // When filters are active, compute filtered stats from baseFiltered (NO with() = no N+1)
        $filteredStats = $hasFilters ? [
            'total'     => $requests->total(),
            'pending'   => (clone $baseFiltered)->where('status', 'Pending')->count(),
            'ongoing'   => (clone $baseFiltered)->where('status', 'Ongoing')->count(),
            'completed' => (clone $baseFiltered)->where('status', 'Completed')->count(),
        ] : $stats;

        return response()->json([
            'success'      => true,
            'requests'     => $requests->items(),
            'total'        => $requests->total(),
            'per_page'     => $requests->perPage(),
            'current_page' => $requests->currentPage(),
            'last_page'    => $requests->lastPage(),
            'stats'        => $stats,
            'filtered_stats' => $filteredStats,
        ]);
    }

    /**
     * AJAX endpoint — returns paginated, filtered audit logs with stats.
     */
    public function auditLogsData(Request $request)
    {
        $actor = Auth::user();

        // ── 1) Unfiltered stats ──
        $baseQuery = AuditLog::query();

        $stats = [
            'total'    => (clone $baseQuery)->count(),
            'auth'     => (clone $baseQuery)->where('module', 'Auth')->count(),
            'inventory' => (clone $baseQuery)->where('module', 'Inventory')->count(),
            'requests' => (clone $baseQuery)->where('module', 'Requests')->count(),
            'users'    => (clone $baseQuery)->where('module', 'User Management')->count(),
        ];

        // ── 2) Filtered + paginated query ──
        // Build base query WITHOUT with() to avoid N+1 on cloned stats queries
        $baseFiltered = AuditLog::query();

        // Search
        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $baseFiltered->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(action) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(module) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(details) LIKE ?', ["%{$search}%"])
                  ->orWhereHas('user', fn ($uq) => $uq->whereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"]));
            });
        }

        // Module filter
        if ($module = $request->input('module')) {
            $baseFiltered->where('module', $module);
        }

        $perPage = min((int) $request->input('per_page', 50), 100);
        $page    = max((int) $request->input('page', 1), 1);

        // Apply with() ONLY to the paginated data query
        $logs = (clone $baseFiltered)->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Check if filters are active
        $hasFilters = $request->filled('search') || $request->filled('module');

        // Use baseFiltered (NO with()) for stats queries to avoid N+1
        $filteredStats = $hasFilters ? [
            'total'    => $logs->total(),
            'auth'     => (clone $baseFiltered)->where('module', 'Auth')->count(),
            'inventory' => (clone $baseFiltered)->where('module', 'Inventory')->count(),
            'requests' => (clone $baseFiltered)->where('module', 'Requests')->count(),
            'users'    => (clone $baseFiltered)->where('module', 'User Management')->count(),
        ] : $stats;

        return response()->json([
            'success'      => true,
            'logs'         => $logs->items(),
            'total'        => $logs->total(),
            'per_page'     => $logs->perPage(),
            'current_page' => $logs->currentPage(),
            'last_page'    => $logs->lastPage(),
            'stats'        => $stats,
            'filtered_stats' => $filteredStats,
        ]);
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
        $actor = Auth::user();

        // ── 1) Unfiltered stats (single query with conditional counts) ──
        $baseQuery = User::query()
            ->when($actor->branch, fn ($q) => $q->where('branch', $actor->branch));

        $stats = [
            'total'    => (clone $baseQuery)->count(),
            'active'   => (clone $baseQuery)->where('is_active', 1)->count(),
            'inactive' => (clone $baseQuery)->where('is_active', 0)->count(),
        ];

        // ── 2) Filtered + paginated query ──
        $query = User::query()
            ->when($actor->branch, fn ($q) => $q->where('branch', $actor->branch));

        // Search
        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(full_name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$search}%"]);
            });
        }

        // Department filter
        if ($department = $request->input('department')) {
            $query->where('department', $department);
        }

        // Division/Office filter
        if ($division = $request->input('division')) {
            $query->where('office', $division);
        }

        // Role filter
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('is_active', $status === 'active' ? 1 : 0);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $page    = max((int) $request->input('page', 1), 1);

        $users = $query->orderBy('full_name', 'asc')
            ->select(['id', 'full_name', 'email', 'role', 'office', 'department', 'is_active'])
            ->paginate($perPage, ['*'], 'page', $page);

        // Check if any filter is active
        $hasFilters = $request->filled('search') || $request->filled('department') ||
                      $request->filled('division') || $request->filled('role') ||
                      $request->filled('status');

        // When filters are active, use paginator's total for filtered stats
        // (already counted by paginate)
        $filteredStats = $hasFilters ? [
            'total'    => $users->total(),
            'active'   => (clone $query)->where('is_active', 1)->count(),
            'inactive' => (clone $query)->where('is_active', 0)->count(),
        ] : $stats;

        return response()->json([
            'success'      => true,
            'users'        => $users->items(),
            'total'        => $users->total(),
            'per_page'     => $users->perPage(),
            'current_page' => $users->currentPage(),
            'last_page'    => $users->lastPage(),
            'stats'        => $stats,
            'filtered_stats' => $filteredStats,
        ]);
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
