<?php

namespace App\Actions\Maintenance;

use App\Models\InventoryAsset;
use App\Support\RequestAuthorization;
use Illuminate\Support\Facades\Auth;

class CreateMaintenanceFormAction
{
    /**
     * Show the PM creation form.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function execute()
    {
        $user = Auth::user();
        if (!RequestAuthorization::canCreateMaintenanceTicket($user)) {
            abort(403, 'PM is now managed via schedules by your ICT Unit. Contact your Super Admin.');
        }

        $flags = RequestAuthorization::maintenanceFormFlags($user);
        $myAssets = InventoryAsset::whereNotIn('status', ['For Repair', 'For Disposal', 'Scrapped'])
            ->where(function ($q) use ($user) {
                if (in_array($user->role, ['it', 'super_admin'], true)) {
                    if ($user->branch) {
                        $q->where('branch', $user->branch);
                    }
                } else {
                    $q->where('assigned_to_user', $user->id);
                }
            })
            ->get();

        return view('requests.maintenance.form', array_merge([
            'request'   => null,
            'maintenance' => null,
            'myAssets'  => $myAssets,
            'linkedPmAsset' => null,
            'endUser'   => $user,
        ], $flags));
    }
}
