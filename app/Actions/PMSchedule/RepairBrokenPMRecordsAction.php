<?php

namespace App\Actions\PMSchedule;

use App\Models\PreventiveMaintenance;
use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\Auth;

class RepairBrokenPMRecordsAction
{
    /**
     * One-click repair: fix all broken auto-generated PM records.
     * - Creates missing preventive_maintenance rows for requests with null detail_id
     * - Links them properly via detail_id
     * - Backfills branch and division_admin_review_status
     * Safe to run multiple times.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        try {
            $fixed = 0;

            // Step 1: Soft-delete orphaned PM rows with empty/null form_no
            PreventiveMaintenance::where(function ($q) {
                    $q->whereNull('form_no')->orWhere('form_no', '');
                })
                ->each(function ($pm) {
                    $linked = RequestModel::where('detail_id', $pm->id)->exists();
                    if (!$linked) {
                        $pm->delete();
                    }
                });

            // Step 2: Fix auto-generated PM requests that have null detail_id
            $broken = RequestModel::where('type', 'Preventive Maintenance')
                ->where('is_auto_generated', true)
                ->whereNull('detail_id')
                ->get();

            foreach ($broken as $req) {
                $pm = PreventiveMaintenance::withTrashed()
                    ->where('form_no', $req->request_number)
                    ->first();

                if ($pm) {
                    if ($pm->trashed()) {
                        $pm->restore();
                    }
                } else {
                    $pm = PreventiveMaintenance::create([
                        'form_no'           => $req->request_number,
                        'end_user_name'     => $req->requestor_name ?: 'Auto-generated',
                        'end_user_division' => $req->office ?: '',
                        'maintenance_date'  => $req->created_at?->toDateString() ?? now()->toDateString(),
                    ]);
                }

                $req->update(['detail_id' => $pm->id]);
                $fixed++;
            }

            // Step 3: Backfill missing branch from the user who created the request
            RequestModel::where('type', 'Preventive Maintenance')
                ->where('is_auto_generated', true)
                ->whereNull('branch')
                ->with('user')
                ->get()
                ->each(function ($req) {
                    if ($req->user && $req->user->branch) {
                        $req->update(['branch' => $req->user->branch]);
                    }
                });

            // Step 4: Mark all auto-generated PMs as Approved so super_admin can see them
            RequestModel::where('type', 'Preventive Maintenance')
                ->where('is_auto_generated', true)
                ->where(function ($q) {
                    $q->whereNull('division_admin_review_status')
                      ->orWhere('division_admin_review_status', '');
                })
                ->update(['division_admin_review_status' => 'Approved']);

            return response()->json([
                'success' => true,
                'message' => "Repair complete! Fixed {$fixed} broken PM record(s). All auto-generated PMs are now fully accessible.",
                'fixed'   => $fixed,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Repair failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
