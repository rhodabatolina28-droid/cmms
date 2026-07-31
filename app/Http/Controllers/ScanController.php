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

        // USER role
        if ($user->role === 'user') {
            $error = \App\Support\RequestHelpers::linkedAssetValidationError($user, $asset->asset_id);
            if ($error) {
                return response("
                    <div style='font-family:Arial;text-align:center;padding:60px 20px;max-width:400px;margin:0 auto;'>
                        <h2 style='color:#dc2626;'>Asset Not Assigned</h2>
                        <p style='color:#64748b;font-size:14px;'>This asset is not assigned to you.</p>
                        <p style='color:#64748b;font-size:13px;'>Contact your Supply Admin if this is a mistake.</p>
                        <a href='" . url('/') . "' style='display:inline-block;margin-top:20px;padding:10px 24px;background:#0038A8;color:white;text-decoration:none;border-radius:6px;font-size:14px;'>Go to Dashboard</a>
                    </div>
                ");
            }
            return redirect()->route('ict.create', ['asset_id' => $id]);
        }

        // IT role → standalone asset info page
        if ($user->role === 'it' || $user->role === 'super_admin') {
            // Scope check: asset must be in user's branch (or no branch restriction)
            if ($user->branch && $asset->branch !== $user->branch) {
                return response("
                    <div style='font-family:Arial;text-align:center;padding:60px 20px;max-width:400px;margin:0 auto;'>
                        <h2 style='color:#dc2626;'>Asset Out of Scope</h2>
                        <p style='color:#64748b;font-size:14px;'>This asset belongs to another branch.</p>
                        <a href='" . url('/') . "' style='display:inline-block;margin-top:20px;padding:10px 24px;background:#0038A8;color:white;text-decoration:none;border-radius:6px;font-size:14px;'>Go to Dashboard</a>
                    </div>
                ");
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
