<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Request as RequestModel;
use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class PersonnelController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = User::query();

        if ($user->role === 'admin') {
            // Division admin: scope to their branch AND office (division-level)
            if ($user->branch) {
                $query->where('branch', $user->branch);
            }
            if ($user->office) {
                $query->where('office', $user->office);
            }
        } elseif ($user->role === 'super_admin') {
            // Super Admin: branch-wide, never filter by office/division
            if ($user->branch) {
                $query->where('branch', $user->branch);
            }
        }

        $personnel = $query->orderBy('full_name', 'asc')->paginate(20);

        return view('admin.personnel.index', compact('personnel'));
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        $actor = Auth::user();
        
        // Security check - scope to branch
        if ($actor->branch && $user->branch !== $actor->branch) {
            return response()->json(['error' => 'Unauthorized - outside branch'], 403);
        }

        // Admin (including supply officer): division-specific lang sa personnel management
        if ($actor->role === 'admin' && $actor->office && $user->office !== $actor->office) {
            return response()->json(['error' => 'Unauthorized - outside division'], 403);
        }

        $assets = InventoryAsset::with('assignedUser')->where('assigned_to_user', $user->id)->limit(50)->get();
        $requests = RequestModel::with('user')->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $stats = [
            'total' => $requests->count(),
            'completed' => $requests->where('status', 'Completed')->count(),
            'pending' => $requests->where('status', 'Pending')->count(),
            'rejected' => $requests->where('status', 'Rejected')->count(),
        ];

        return response()->json([
            'user' => $user,
            'assets' => $assets,
            'requests' => $requests,
            'stats' => $stats
        ]);
    }

    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        $actor = Auth::user();
        
        if ($actor->role === 'admin') {
            if ($user->office !== $actor->office) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        } elseif ($actor->role === 'super_admin') {
            // Super Admin: branch-wide, no office restriction
            if ($actor->branch && $user->branch !== $actor->branch) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $wasActive = $user->is_active;
        $user->is_active = !$user->is_active;
        $user->save();

        if ($wasActive && !$user->is_active) {
            $this->deallocateAssignedAssets($user, Auth::id());
        }

        return response()->json([
            'success' => true,
            'is_active' => $user->is_active,
            'message' => 'Personnel status updated successfully'
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

    public function store(Request $request)
    {
        $actor = Auth::user();
        
        $validated = $request->validate([
            'full_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'role'       => 'required|in:' . implode(',', array_diff(config('roles.list', ['user','admin','it']), ['super_admin'])),
            'position'   => 'nullable|string',
            'branch'     => 'nullable|string',
            'office'     => 'nullable|string',
            'department' => 'nullable|string',
            'region'     => 'nullable|string',
            'password'   => 'required|min:8|regex:/[A-Z]/|regex:/[0-9]/'
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = true;

        // Admin can ONLY create users in their own division (common sense)
        if ($actor->role === 'admin') {
            $validated['region'] = $actor->region;
            $validated['branch'] = $actor->branch;
            $validated['office'] = $actor->office;  // FORCE same division
            $validated['department'] = $actor->department;
        }

        User::create($validated);

        return response()->json(['success' => true, 'message' => 'Personnel created successfully']);
    }
}
