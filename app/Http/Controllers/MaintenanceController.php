<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\PreventiveMaintenance;
use App\Models\Request as RequestModel;
use App\Http\Requests\StoreMaintenanceRequest;
use App\Http\Requests\StoreLinkedAssetRequest;
use App\Http\Requests\AssignItRequest;
use App\Http\Requests\UpdateMaintenanceRequest;
use App\Support\RequestAuthorization;
use App\Services\RequestNotificationService;
use App\Actions\Maintenance\UpdateMaintenanceTicketAction;
use App\Actions\Maintenance\AssignMaintenanceTicketAction;
use App\Actions\Maintenance\DownloadMaintenancePdfAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class MaintenanceController extends Controller
{


    public function index()
    {
        $user = Auth::user();

        // Block access for regular users and admin/supply officer - PM is for IT/Super Admin only
        if ($user->role === 'user' || $user->role === 'admin' || $user->role === 'supply_officer') {
            abort(403, 'Preventive Maintenance requests are managed by IT personnel and Super Admin only.');
        }

        $query = RequestModel::with(['user', 'maintenanceRequest', 'assignedTo'])
            ->where('type', 'Preventive Maintenance')
            ->where('status', '!=', RequestModel::STATUS_SCHEDULED);

        if ($user->role === 'it') {
            $query->where('assigned_to', $user->id);
        } elseif ($user->role === 'super_admin') {
            // Super Admin sees all PM requests in their branch (since PM skips division admin review)
            // No need to check division_admin_review_status anymore
            if ($user->branch) {
                $query->where('branch', $user->branch);
            }
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        if (request()->wantsJson() || request()->expectsJson()) {
            return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
        }

        return view('requests.maintenance.index', compact('requests'));
    }

    /**
     * Dedicated PM Tasks page for IT personnel.
     * Shows only PM work orders assigned to the current IT user.
     */
    public function pmTasks()
    {
        $user = Auth::user();

        $query = RequestModel::with(['user', 'maintenanceRequest', 'assignedTo'])
            ->where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true);

        // Filter by status if provided
        if ($status = request('status')) {
            $query->where('status', $status);
        }

        // IT sees PMs assigned to them OR unassigned PMs in their branch
        if ($user->role === 'it') {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere(function ($sub) use ($user) {
                      $sub->whereNull('assigned_to');
                      if ($user->branch) {
                          $sub->where('branch', $user->branch);
                      }
                  });
            });
        } elseif ($user->role === 'super_admin' && $user->branch) {
            $query->where('branch', $user->branch);
        }

        $pmTasks = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('requests.maintenance.pm-tasks', compact('pmTasks'));
    }

    public function scheduled()
    {
        $user = Auth::user();
        $query = RequestModel::with(['user', 'maintenanceRequest', 'linkedAsset', 'assignedTo'])
            ->where('type', 'Preventive Maintenance')
            ->where('status', RequestModel::STATUS_SCHEDULED);

        if ($user->role === 'it') {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere(function ($sub) use ($user) {
                      $sub->whereNull('assigned_to');
                      if ($user->branch) {
                          $sub->where('branch', $user->branch);
                      }
                  });
            });
        } elseif ($user->role === 'super_admin') {
            if ($user->branch) {
                $query->where('branch', $user->branch);
            }
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('requests.maintenance.scheduled', compact('requests'));
    }

    public function start($id)
    {
        $trackingRequest = RequestModel::findOrFail($id);
        $user = Auth::user();

        if (!RequestAuthorization::canUpdateMaintenanceTicket($user, $trackingRequest)) {
            abort(403, 'You cannot start this PM task.');
        }

        // Only the assigned person can start — regardless of role
        if ($trackingRequest->status === RequestModel::STATUS_SCHEDULED
            && (int) $trackingRequest->assigned_to === (int) $user->id) {
            $trackingRequest->update(['status' => RequestModel::STATUS_ONGOING]);
        }

        return redirect()->route('maintenance.edit', $id);
    }

    public function create()
    {
        $user = Auth::user();
        if (!RequestAuthorization::canCreateMaintenanceTicket($user)) {
            abort(403, 'PM is now managed via schedules by your ICT Unit. Contact your Super Admin.');
        }

        $flags = RequestAuthorization::maintenanceFormFlags($user);
        $myAssets = \App\Models\InventoryAsset::whereNotIn('status', ['For Repair', 'For Disposal', 'Scrapped'])
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

    public function store(StoreLinkedAssetRequest $request)
    {
        $user = Auth::user();

        return (new \App\Actions\Maintenance\CreateMaintenanceTicketAction)->execute($request, $user);
    }

    public function show($id)
    {
        $trackingRequest = RequestModel::with('assignedTo')->findOrFail($id);

        $this->checkTicketAccess($trackingRequest);

        $maintenance = $this->resolveMaintenanceDetail($trackingRequest);

        $user = Auth::user();
        $forceView = !RequestAuthorization::canUpdateMaintenanceTicket($user, $trackingRequest);

        return view('requests.maintenance.form', $this->maintenanceFormViewData($trackingRequest, $maintenance, $forceView));
    }

    public function edit($id)
    {
        $trackingRequest = RequestModel::with('assignedTo')->findOrFail($id);

        $this->checkTicketAccess($trackingRequest);

        $user = Auth::user();
        if (!RequestAuthorization::canUpdateMaintenanceTicket($user, $trackingRequest)
            && !RequestAuthorization::canViewMaintenanceTicket($user, $trackingRequest)) {
            abort(403, 'You cannot edit this maintenance request.');
        }

        $maintenance = $this->resolveMaintenanceDetail($trackingRequest);

        return view('requests.maintenance.form', $this->maintenanceFormViewData(
            $trackingRequest,
            $maintenance,
            !RequestAuthorization::canUpdateMaintenanceTicket($user, $trackingRequest)
        ));
    }

    public function assignIt(AssignItRequest $request, $id)
    {
        return (new AssignMaintenanceTicketAction)->execute($request, $id);
    }

    public function update(UpdateMaintenanceRequest $request, $id)
    {
        return (new UpdateMaintenanceTicketAction)->execute($request, $id);
    }

    public function downloadPdf($id)
    {
        return (new DownloadMaintenancePdfAction)->execute($id);
    }

    /**
     * Resolve (or repair) the PreventiveMaintenance record for a tracking request.
     * If detail_id is null (old/broken auto-generated records), create one on-the-fly
     * and save the link so future views don't crash.
     */
    private function resolveMaintenanceDetail(RequestModel $trackingRequest): PreventiveMaintenance
    {
        if ($trackingRequest->detail_id) {
            $pm = PreventiveMaintenance::find($trackingRequest->detail_id);
            if ($pm) {
                return $pm;
            }
        }

        // detail_id is null or the record is missing — auto-create and link it
        $pm = PreventiveMaintenance::create([
            'form_no'           => $trackingRequest->request_number,
            'end_user_name'     => $trackingRequest->requestor_name ?? 'Auto-generated',
            'end_user_division' => $trackingRequest->office ?? '',
            'maintenance_date'  => $trackingRequest->created_at?->toDateString() ?? now()->toDateString(),
        ]);

        $trackingRequest->update(['detail_id' => $pm->id]);

        return $pm;
    }

    private function checkTicketAccess($trackingRequest): void
    {
        if (!RequestAuthorization::canViewMaintenanceTicket(Auth::user(), $trackingRequest)) {
            abort(403, 'Unauthorized access to this request.');
        }
    }

    private function maintenanceFormViewData(RequestModel $trackingRequest, PreventiveMaintenance $maintenance, bool $forceView = false): array
    {
        $user = Auth::user();
        $flags = RequestAuthorization::maintenanceFormFlags($user, $trackingRequest, $forceView);

        // Fetch assets for the original requestor (so the dropdown is correct even when admin/IT views)
        $requestorId = $trackingRequest->user_id;
        $myAssets = \App\Models\InventoryAsset::where('assigned_to_user', $requestorId)->get();
        $linkedPmAsset = null;
        if ($trackingRequest->linked_asset_id) {
            $linkedPmAsset = \App\Models\InventoryAsset::find($trackingRequest->linked_asset_id);
            if ($linkedPmAsset && !$myAssets->contains('asset_id', $linkedPmAsset->asset_id)) {
                $myAssets->push($linkedPmAsset);
            }
        }

        if ($maintenance->disposal_asset_id) {
            $disposalAsset = \App\Models\InventoryAsset::find($maintenance->disposal_asset_id);
            if ($disposalAsset && !$myAssets->contains('asset_id', $disposalAsset->asset_id)) {
                $myAssets->push($disposalAsset);
            }
        }

        $endUser = User::find($requestorId);

        $data = array_merge([
            'request'    => $trackingRequest,
            'maintenance' => $maintenance,
            'myAssets'   => $myAssets,
            'linkedPmAsset' => $linkedPmAsset,
            'endUser'    => $endUser,
        ], $flags);

        if (!empty($flags['canAssignIt'])) {
            $data['itPersonnel'] = RequestAuthorization::itPersonnelInAdminScope($user);

            // Super Admin can always handle tickets themselves or assign to IT
            if ($user->role === 'super_admin') {
                $data['canSelfAssign'] = true;
            }
        }

        return $data;
    }

    public function disposalTag($id)
    {
        $user = Auth::user();
        
        // Only IT and Super Admin can access the disposal tag
        if (!in_array($user->role, ['it', 'super_admin'])) {
            abort(403, 'Only IT personnel and Super Admin can access the disposal tag.');
        }

        $trackingRequest = RequestModel::with(['linkedAsset', 'user'])->findOrFail($id);
        $this->checkTicketAccess($trackingRequest);
        
        $maintenance = PreventiveMaintenance::findOrFail($trackingRequest->detail_id);

        if (!$maintenance->disposal_asset_id) {
            abort(404, 'No disposal asset linked to this request.');
        }

        $asset = \App\Models\InventoryAsset::find($maintenance->disposal_asset_id);
        if (!$asset) {
            abort(404, 'Disposal asset not found.');
        }

        if ($asset->status !== \App\Enums\AssetStatus::FOR_DISPOSAL && $asset->status !== 'For Disposal') {
            abort(403, 'This asset has not been marked For Disposal yet.');
        }

        $pdf = Pdf::loadView('pdf.disposal-tag', [
            'request' => $trackingRequest,
            'asset'   => $asset,
            'reason'  => $maintenance->disposal_reason ?? 'Not specified',
            'itUser'  => Auth::user()
        ])->setPaper('a4', 'portrait');

        if (ob_get_length()) {
            ob_end_clean();
        }

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="DisposalTag-' . $trackingRequest->request_number . '.pdf"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        if ($user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Only Super Admins can delete requests.'], 403);
        }

        try {
            DB::beginTransaction();
            $trackingRequest = RequestModel::findOrFail($id);
            if (!\App\Support\RequestAuthorization::ticketInSuperAdminBranch($user, $trackingRequest)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Request is outside your branch scope.'], 403);
            }
            $maintenance = $trackingRequest->detail_id
                ? PreventiveMaintenance::find($trackingRequest->detail_id)
                : null;

            // Due to Soft Deletes implementation, we do NOT delete signature files from storage anymore.
            // This ensures signatures remain intact if the request is restored.

            if ($maintenance) {
                $maintenance->delete();
            }
            $trackingRequest->delete();

            AuditLog::log("Deleted PM Request", "Requests", "Deleted PM request {$trackingRequest->request_number}", $trackingRequest->office);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Maintenance request deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
