<?php

namespace App\Actions\SuperAdmin;

use App\Models\User;
use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Models\AuditLog;
use App\Enums\AssetStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateUserAction
{
    private static $ORG_SCOPE_FIELDS = ['region', 'branch', 'office', 'department'];

    public function execute(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $this->abortIfOutsideOfficeScope($user);

        $validated = $request->validated();

        if ($validated['role'] === 'supply_officer') {
            $dept = strtoupper($validated['department'] ?? '');
            $office = strtoupper($validated['office'] ?? '');
            $isAdminDept = in_array($dept, ['ADMINISTRATIVE DIVISION', 'ADMINISTRATIVE', 'ADMINISTRATIVE DEPARTMENT']) ||
                          in_array($office, ['ADMINISTRATIVE DIVISION', 'ADMINISTRATIVE', 'ADMINISTRATIVE DEPARTMENT']);
            if (!$isAdminDept) {
                return response()->json(['success' => false, 'message' => 'Supply Officer role can only be assigned to users in the Administrative Division/Department.'], 422);
            }
            $validated['role'] = 'admin';
            $validated['can_supply'] = true;
        } else {
            $validated['can_supply'] = $request->boolean('can_supply');
        }

        $previous = [
            'region' => $user->region,
            'branch' => $user->branch,
            'office' => $user->office,
            'department' => $user->department,
        ];

        $user->update($validated);

        $scopeChanged = false;
        foreach (self::$ORG_SCOPE_FIELDS as $field) {
            if ((string) ($previous[$field] ?? '') !== (string) ($user->$field ?? '')) {
                $scopeChanged = true;
                break;
            }
        }

        if ($scopeChanged) {
            DB::transaction(function () use ($user, $previous) {
                InventoryAsset::where('assigned_to_user', $user->id)
                    ->whereNotIn('status', AssetStatus::LOCKED)
                    ->chunkById(50, function ($assets) use ($user, $previous) {
                        foreach ($assets as $asset) {
                            $this->syncAssetOrgScope($asset, $user, $previous);
                        }
                    });
            });
        }

        AuditLog::log(
            "Updated User Account",
            "User Management",
            "Updated account for {$user->full_name} ({$user->email}) with role {$user->role} in {$user->office}" .
                ($scopeChanged ? ' · org scope synced to assigned assets' : ''),
            $user->office
        );

        return response()->json(['success' => true, 'message' => 'User updated successfully']);
    }

    private function syncAssetOrgScope(InventoryAsset $asset, User $user, array $previous): void
    {
        $changed = false;
        foreach (self::$ORG_SCOPE_FIELDS as $field) {
            if ((string) ($asset->$field ?? '') !== (string) ($user->$field ?? '')) {
                $changed = true;
                break;
            }
        }

        if ($changed) {
            $asset->update([
                'region' => $user->region,
                'branch' => $user->branch,
                'office' => $user->office,
                'department' => $user->department,
            ]);

            InventoryHistory::create([
                'asset_id' => $asset->asset_id,
                'action' => 'Org Scope Updated',
                'performed_by' => Auth::id(),
                'previous_user_id' => $asset->assigned_to_user,
                'new_user_id' => $asset->assigned_to_user,
                'remarks' => "Org scope synced: {$previous['office']} → {$user->office} for user #{$user->id}.",
            ]);
        }

        if (!$asset->parent_asset_id) {
            $components = $asset->components()->whereNotIn('status', AssetStatus::LOCKED)->get();
            foreach ($components as $component) {
                $this->syncAssetOrgScope($component, $user, $previous);
            }
        }
    }

    private function abortIfOutsideOfficeScope(User $user): void
    {
        $actor = Auth::user();
        if ($actor->region && $user->region !== $actor->region) {
            abort(403, 'This user is outside your region scope.');
        }
        if ($actor->branch && $user->branch !== $actor->branch) {
            abort(403, 'This user is outside your branch scope.');
        }
    }
}