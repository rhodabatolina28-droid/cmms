<?php

namespace App\Actions\ICT;

use App\Models\User;
use App\Models\Request as RequestModel;
use App\Models\AuditLog;
use App\Models\InventoryHistory;
use App\Enums\AssetStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecommendAssetDisposalAction
{
    /**
     * Recommend an asset for disposal via an ICT request ticket.
     *
     * @param  \App\Models\Request  $trackingRequest
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function execute($trackingRequest, $user)
    {
        if ($user->role !== 'it' && $user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (!$trackingRequest->linkedAsset) {
            return redirect()->back()->with('error', 'No asset linked to this request.');
        }

        $asset = $trackingRequest->linkedAsset;
        if (in_array($asset->status, [AssetStatus::FOR_DISPOSAL, AssetStatus::SCRAPPED, 'Disposed', 'Pending'])) {
            return redirect()->back()->with('error', 'Asset is already disposed or pending disposal.');
        }

        DB::beginTransaction();
        try {
            // Remove user assignment since asset is being turned over to Supply Officer
            $previousUserId = $asset->assigned_to_user;
            $asset->status = AssetStatus::FOR_DISPOSAL;
            $asset->assigned_to_user = null;
            $asset->save();

            InventoryHistory::create([
                'asset_id' => $asset->asset_id,
                'action' => 'IT Recommended For Disposal',
                'previous_user_id' => $previousUserId,
                'new_user_id' => null,
                'performed_by' => $user->id,
                'remarks' => "Asset recommended for disposal via ICT Request {$trackingRequest->request_number}. Assignment removed - turned over to Supply Officer.",
            ]);

            AuditLog::log(
                "Recommended Asset For Disposal", 
                "Inventory", 
                "Recommended asset {$asset->property_number} for disposal via ICT request {$trackingRequest->request_number}",
                $trackingRequest->office
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Disposal recommendation failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to recommend disposal. Please try again.');
        }

        $admins = User::where('role', 'admin')
            ->where('can_supply', true)
            ->where('is_active', true)
            ->when($asset->branch, fn ($q) => $q->where('branch', $asset->branch))
            ->get();

        foreach ($admins as $admin) {
            \App\Models\Notification::send(
                $admin->id,
                $trackingRequest->id,
                'Asset Tagged for Disposal',
                "ICT recommended asset [{$asset->item_name} | SN: {$asset->serial_number}] for disposal via ticket {$trackingRequest->request_number}. Please process and update the asset status when physical disposal is done."
            );
        }

        if ($user->branch) {
            $superAdmins = User::where('role', 'super_admin')
                ->where('is_active', true)
                ->where('branch', $user->branch)
                ->get();

            foreach ($superAdmins as $superAdmin) {
                \App\Models\Notification::send(
                    $superAdmin->id,
                    $trackingRequest->id,
                    'Asset Tagged for Disposal',
                    "ICT recommended asset [{$asset->item_name} | SN: {$asset->serial_number}] for disposal via ticket {$trackingRequest->request_number}."
                );
            }
        }

        return redirect()->back()->with('success', 'Asset has been marked For Disposal. You can now print the Disposal Tag.');
    }
}