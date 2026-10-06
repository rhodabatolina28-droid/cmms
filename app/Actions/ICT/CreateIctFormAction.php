<?php

namespace App\Actions\ICT;

use App\Models\InventoryAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CreateIctFormAction
{
    /**
     * Show the ICT request creation form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View
     */
    public function execute(Request $request)
    {
        $user = Auth::user();
        if (!$user->can('createIct', \App\Models\Request::class)) {
            abort(403, 'Only end-users can create new ICT requests.');
        }

        $flags = \App\Support\RequestHelpers::ictFormFlags($user, null, false, null);

        if (in_array($user->role, ['it', 'super_admin'], true)) {
            $myAssets = InventoryAsset::whereNotIn('status', ['For Repair', 'For Disposal', 'Scrapped'])
                ->where(function ($q) use ($user) {
                    if ($user->branch) {
                        $q->where('branch', $user->branch);
                    }
                })
                ->get();
        } else {
            $myAssets = InventoryAsset::where('assigned_to_user', $user->id)
                ->whereNotIn('status', ['For Repair', 'For Disposal', 'Scrapped'])
                ->get();
        }
        $preselectedAssetId = $request->query('asset_id');
        if ($preselectedAssetId) {
            $preselectedAssetId = (int) $preselectedAssetId;
            if (! $myAssets->contains('asset_id', $preselectedAssetId)) {
                // The asset is missing from the dropdown — normally because the
                // status filter above (For Repair / For Disposal / Scrapped)
                // removed it. Keep it preselected ONLY when the user may still
                // link it — same rules as RequestHelpers::linkedAssetValidationError:
                // disposal statuses blocked ('For Repair' is NOT), ownership
                // required for end-users, branch scope for IT/System Admin.
                $asset = InventoryAsset::where('asset_id', $preselectedAssetId)->first();
                $canLink = $asset
                    && ! in_array($asset->status, ['For Disposal', 'Scrapped', 'Disposed'], true)
                    && (in_array($user->role, ['it', 'super_admin'], true)
                        ? (! $user->branch || $asset->branch === $user->branch)
                        : \App\Support\RequestHelpers::assetAssignedToUser($user, $asset->asset_id));

                if ($canLink) {
                    // Re-add so the <option> exists in the dropdown and the
                    // auto-fill map has data (Bug 3b, Oct 2026).
                    $myAssets->push($asset);
                } else {
                    $preselectedAssetId = null;
                }
            }
        }

        $hasAssignedAssets = $myAssets->isNotEmpty();

        $ictAssetsMap = [];
        foreach ($myAssets as $asset) {
            $ictAssetsMap[$asset->asset_id] = [
                'serial_number'   => $asset->serial_number,
                'property_number' => $asset->property_number,
                'par_number'      => $asset->par_number,
                'date_acquired'   => $asset->date_acquired
                    ? \Carbon\Carbon::parse($asset->date_acquired)->format('Y-m-d')
                    : null,
                'item_name'       => $asset->item_name,
                'category'        => $asset->category,
            ];
        }

        return view('requests.ict.form', array_merge([
            'request' => null,
            'repairRequest' => null,
            'myAssets' => $myAssets,
            'hasAssignedAssets' => $hasAssignedAssets,
            'preselectedAssetId' => $preselectedAssetId,
            'linkedAssetData' => null,
            'ictAssetsMap' => $ictAssetsMap,
        ], $flags));
    }
}
