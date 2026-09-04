<?php

namespace App\Http\Controllers;

use App\Models\InventoryAsset;
use App\Models\PMSchedule;
use App\Models\Request as RequestModel;
use App\Support\RequestHelpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScanController extends Controller
{
    public function redirect($id)
    {
        // Guest → login, then back here (pass redirect= param so AuthController can route back)
        if (!Auth::check()) {
            session(['qr_redirect_asset_id' => (int) $id]);
            return redirect()->route('login', ['redirect' => url('/r/' . $id)]);
        }

        $asset = InventoryAsset::with('assignedUser')->findOrFail($id);

        // Get all other assets of the same user
        $userAssets = collect();
        if ($asset->assigned_to_user) {
            $userAssets = InventoryAsset::with('assignedUser')
                ->where('assigned_to_user', $asset->assigned_to_user)
                ->where('asset_id', '!=', $id)
                ->whereNotIn('status', ['For Disposal', 'Scrapped'])
                ->orderBy('category')
                ->orderBy('item_name')
                ->get();
        }

        $user = Auth::user();

        // USER role — preview page with the scanned asset + other assets (tap to select)
        if ($user->role === 'user') {
            $error = \App\Support\RequestHelpers::linkedAssetValidationError($user, $asset->asset_id);
            if ($error) {
                return view('scan.notice', [
                    'title'    => 'Asset Not Assigned',
                    'message'  => 'This asset is not assigned to you. Contact your Supply Admin if this is a mistake.',
                    'icon'     => 'fa-triangle-exclamation',
                ]);
            }
            return view('scan.scan-preview', [
                'asset'       => $asset,
                'otherAssets' => $userAssets,
            ]);
        }

        // IT role → standalone asset info page
        if ($user->role === 'it' || $user->role === 'super_admin') {
            // Scope check: asset must be in user's branch (or no branch restriction)
            if ($user->branch && $asset->branch !== $user->branch) {
                return view('scan.notice', [
                    'title'   => 'Asset Out of Scope',
                    'message' => 'This asset belongs to another branch.',
                    'icon'    => 'fa-location-crosshairs',
                ]);
            }

            // Repair history: get ALL requests for this asset's user (bundled PM)
            $assetUserId = $asset->assigned_to_user;
            $repairHistory = RequestModel::with(['repairRequest', 'maintenanceRequest'])
                ->where(function ($q) use ($id, $assetUserId) {
                    $q->where('linked_asset_id', $id)
                      ->orWhere(function ($sub) use ($assetUserId) {
                          $sub->where('type', 'Preventive Maintenance')
                              ->where('is_auto_generated', true)
                              ->where('user_id', $assetUserId);
                      });
                })
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            try {
                // Check if this user's division is the current focus (priority)
                $focusDivision = null;
                $activeSchedule = PMSchedule::active()->first();
                if ($activeSchedule) {
                    $pmService = new \App\Services\GeneratePMScheduleService;
                    $queueStatus = $pmService->getQueueStatus($activeSchedule);
                    $focusDivision = $queueStatus['focus_division'] ?? null;
                }

                // Find upcoming PM for the USER who owns this asset (bundled PM)
                $upcomingPM = null;
                $showPmActions = false;
                if ($assetUserId && $focusDivision && $asset->assignedUser) {
                    // Only show PM actions if this user's division IS the current focus
                    $userDivision = $asset->assignedUser->office;
                    $showPmActions = $userDivision && str_contains(strtoupper($userDivision), strtoupper($focusDivision));

                    if ($showPmActions) {
                        $upcomingPM = RequestModel::where('user_id', $assetUserId)
                            ->where('type', 'Preventive Maintenance')
                            ->where('is_auto_generated', true)
                            ->whereIn('status', [RequestModel::STATUS_SCHEDULED, RequestModel::STATUS_ONGOING, RequestModel::STATUS_AWAITING_SIGNATURE])
                            ->latest()
                            ->first();
                    }
                }

                // If no upcoming PM found, check last completed PM for reference
                $lastPM = null;
                if ($assetUserId && !$upcomingPM) {
                    $lastPM = RequestModel::where('user_id', $assetUserId)
                        ->where('type', 'Preventive Maintenance')
                        ->where('is_auto_generated', true)
                        ->where('status', 'Completed')
                        ->latest()
                        ->first();
                }

                // Check for non-PM ICT repair tickets for this exact asset
                $ictTicket = RequestModel::where('linked_asset_id', $id)
                    ->where('type', '!=', 'Preventive Maintenance')
                    ->whereNotNull('type')
                    ->latest()
                    ->first();

            } catch (\Exception $e) {
                $activeSchedule = null;
                $focusDivision = null;
                $upcomingPM = null;
                $lastPM = null;
                $ictTicket = null;
                $showPmActions = false;
            }

            return view('scan.asset-info', [
                'asset'          => $asset,
                'history'        => $repairHistory,
                'user'           => $user,
                'upcomingPM'     => $upcomingPM,
                'lastPM'         => $lastPM ?? null,
                'assetUser'      => $asset->assignedUser,
                'focusDivision'  => $focusDivision,
                'showPmActions'  => $showPmActions ?? false,
                'ictTicket'      => $ictTicket ?? null,
                'userAssets'     => $userAssets,
            ]);
        }

        // Supply/Admin → show scan info page too (with other assets of user)
        if ($user->canProcessSupply()) {
            return view('scan.asset-info', [
                'asset'          => $asset,
                'history'        => collect(),
                'user'           => $user,
                'upcomingPM'     => null,
                'lastPM'         => null,
                'assetUser'      => $asset->assignedUser,
                'focusDivision'  => null,
                'showPmActions'  => false,
                'ictTicket'      => null,
                'userAssets'     => $userAssets,
            ]);
        }

        // Fallback (admin without supply, etc.)
        return redirect()->route('inventory.detail', $id);
    }
}
