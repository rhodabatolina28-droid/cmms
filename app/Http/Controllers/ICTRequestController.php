<?php

namespace App\Http\Controllers;

use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use App\Http\Requests\UpdateIctStatusRequest;
use App\Http\Requests\AssignItRequest;
use App\Http\Requests\ReviewIctRequest;
use App\Http\Requests\StoreLinkedAssetRequest;
use App\Http\Requests\UpdateIctRequest;
use App\Support\RequestAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ICTRequestController extends Controller
{


    // Show requests based on role (ICT only for users/admin, ICT+PM for IT/super_admin)
    public function index()
    {
        $user = Auth::user();
        $query = RequestModel::with(['user', 'repairRequest', 'assignedTo']);

        if ($user->role === 'user') {
            // Regular users: ICT only (no PM)
            $query->where('type', 'ICT')->where('user_id', $user->id);
            $requests = $query->orderBy('created_at', 'desc')->paginate(20);
            if (request()->wantsJson() || request()->expectsJson()) {
                return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
            }
            return view('requests.index', compact('requests'));
        } elseif ($user->role === 'it') {
            // IT: ICT only, assigned to them
            $query->where('type', 'ICT')
                  ->where('assigned_to', $user->id);
            $requests = $query->orderBy('created_at', 'desc')->paginate(20);
            if (request()->wantsJson() || request()->expectsJson()) {
                return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
            }
            return view('requests.index', compact('requests'));
        } elseif ($user->role === 'admin' || $user->role === 'supply_officer' || $user->role === 'super_admin') {
            if ($user->role === 'admin' || $user->role === 'supply_officer') {
                // Admin/Supply Officer: ICT only (no PM)
                $query->where('type', 'ICT')->whereHas('user', function($q) use ($user) {
                    if ($user->branch) {
                        $q->where('branch', $user->branch);
                    }
                    if ($user->office) {
                        $q->where('office', $user->office);
                    }
                });
                $requests = $query->orderBy('created_at', 'desc')->paginate(20);
                if (request()->wantsJson() || request()->expectsJson()) {
                    return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
                }
                return view('admin.requests.index', compact('requests'));
            } else {
                // Super Admin: ICT only (PM is in PM Schedule module)
                $requests = $query->where('type', 'ICT')
                    ->where('division_admin_review_status', 'Approved')
                    ->whereHas('user', function ($q) use ($user) {
                        if ($user->branch) {
                            $q->where('branch', $user->branch);
                        }
                        // Super Admin manages entire branch - no division filter
                    })
                    ->orderBy('created_at', 'desc')
                    ->paginate(20);
                if (request()->wantsJson() || request()->expectsJson()) {
                    return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
                }
                return view('super-admin.requests.index', compact('requests'));
            }
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate(20);
        if (request()->wantsJson() || request()->expectsJson()) {
            return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
        }
        return view('requests.index', compact('requests'));
    }

    public function updateStatus(UpdateIctStatusRequest $request)
    {
        return (new \App\Actions\ICT\QuickUpdateStatusAction)->execute($request);
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        if (!RequestAuthorization::canCreateIctTicket($user)) {
            abort(403, 'Only end-users can create new ICT requests.');
        }

        $flags = RequestAuthorization::ictFormFlags($user, null, false, null);

        // Load assets based on role
        if (in_array($user->role, ['it', 'super_admin'], true)) {
            // IT/SuperAdmin can create tickets for any asset in their scope
            $myAssets = \App\Models\InventoryAsset::whereNotIn('status', ['For Repair', 'For Disposal', 'Scrapped'])
                ->where(function ($q) use ($user) {
                    if ($user->branch) {
                        $q->where('branch', $user->branch);
                    }
                })
                ->get();
        } else {
            $myAssets = \App\Models\InventoryAsset::where('assigned_to_user', $user->id)
                ->whereNotIn('status', ['For Repair', 'For Disposal', 'Scrapped'])
                ->get();
        }
        $hasAssignedAssets = $myAssets->isNotEmpty();

        $preselectedAssetId = $request->query('asset_id');
        if ($preselectedAssetId) {
            $preselectedAssetId = (int) $preselectedAssetId;
            // Only pre-select if asset is in the loaded list
            if (!$myAssets->contains('asset_id', $preselectedAssetId)) {
                $preselectedAssetId = null;
            }
        }

        // Build ICT assets map for JS auto-fill on asset selection
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

    // Show EXISTING request for editing
    public function show($id)
    {
        $trackingRequest = RequestModel::with('assignedTo')->findOrFail($id);
        
        $this->checkTicketAccess($trackingRequest);

        $repairRequest = RepairRequest::findOrFail($trackingRequest->detail_id);

        $user = Auth::user();
        $forceView = !RequestAuthorization::canUpdateIctTicket($user, $trackingRequest);

        return view('requests.ict.form', $this->ictFormViewData($trackingRequest, $repairRequest, $forceView));
    }

    public function edit($id)
    {
        $trackingRequest = RequestModel::with('assignedTo')->findOrFail($id);
        
        $this->checkTicketAccess($trackingRequest);

        $user = Auth::user();
        if (!RequestAuthorization::canUpdateIctTicket($user, $trackingRequest) && !RequestAuthorization::canViewIctTicket($user, $trackingRequest)) {
            abort(403, 'You cannot edit this request.');
        }

        $repairRequest = RepairRequest::findOrFail($trackingRequest->detail_id);

        return view('requests.ict.form', $this->ictFormViewData(
            $trackingRequest,
            $repairRequest,
            !RequestAuthorization::canUpdateIctTicket($user, $trackingRequest)
        ));
    }

    /** Job Order ticket hub — separate from ICT repair form. */
    public function ticket($id)
    {
        $trackingRequest = RequestModel::with(['assignedTo', 'user'])->findOrFail($id);
        $this->checkTicketAccess($trackingRequest);

        if ($trackingRequest->type !== 'ICT') {
            abort(404);
        }

        $user = Auth::user();
        $requisitions = \App\Models\Requisition::with(['requester', 'reviewer'])
            ->where('request_id', $trackingRequest->id)
            ->orderByDesc('created_at')
            ->get();

        $hasMyPendingParts = $requisitions->contains(
            fn ($r) => $r->status === \App\Models\Requisition::STATUS_PENDING
                && (int) $r->requested_by === (int) $user->id
        );

        return view('requests.ict.ticket', [
            'request' => $trackingRequest,
            'requisitions' => $requisitions,
            'canRequestPartsOnTicket' => in_array($user->role, ['it', 'super_admin'])
                && \App\Support\RequisitionSupport::canItSubmitForTicket($user, $trackingRequest)
                && !$hasMyPendingParts,
            'canOpenIctForm' => RequestAuthorization::canViewIctTicket($user, $trackingRequest),
            'canEditIctForm' => RequestAuthorization::canUpdateIctTicket($user, $trackingRequest),
        ]);
    }

    public function assignIt(AssignItRequest $request, $id)
    {
        $trackingRequest = RequestModel::with('assignedTo')->findOrFail($id);
        $this->checkTicketAccess($trackingRequest);

        return (new \App\Actions\ICT\AssignItTicketAction)->execute($request, $trackingRequest);
    }

    public function review(ReviewIctRequest $request, $id)
    {
        return (new \App\Actions\ICT\ReviewIctTicketAction)->execute($request, $id);
    }

    public function downloadPdf($id)
    {
        $trackingRequest = RequestModel::findOrFail($id);
        $this->checkTicketAccess($trackingRequest);

        return (new \App\Actions\ICT\DownloadIctPdfAction)->execute($trackingRequest);
    }

    // Create NEW request (user submits Section 1 only)
    public function store(StoreLinkedAssetRequest $request)
    {
        $user = Auth::user();

        return (new \App\Actions\ICT\CreateIctTicketAction)->execute($request, $user);
    }

    // Update EXISTING request (role-based sections)
    public function update(UpdateIctRequest $request, $id)
    {

        try {
            DB::beginTransaction();
            $trackingRequest = RequestModel::with('repairRequest')->findOrFail($id);

            $savedSigFiles = [];
            $trackingRequest = RequestModel::findOrFail($id);
            
            if ($trackingRequest->status === 'Completed') {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'This request is already completed and cannot be modified.'], 403);
            }
            
            // Optimistic Locking Check
            if ($request->has('last_updated_at') && (string)$trackingRequest->updated_at !== $request->last_updated_at) {
                DB::rollBack();
                return response()->json([
                    'success' => false, 
                    'message' => 'Conflict Error: Another user has updated this request while you were viewing it. Please refresh the page.'
                ], 409);
            }
            
            $repairRequest = RepairRequest::findOrFail($trackingRequest->detail_id);
            
            $user = Auth::user();

            if (!RequestAuthorization::canUpdateIctTicket($user, $trackingRequest)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'You are not allowed to update this request.'], 403);
            }

            if ($user->role === 'user') {
                // RESUBMIT: User resubmitting a rejected request
                if ($trackingRequest->status === RequestModel::STATUS_REJECTED
                    && (int) $trackingRequest->user_id === (int) $user->id) {

                    $response = (new \App\Actions\ICT\ResubmitIctTicketAction)->execute(
                        $request, $trackingRequest, $repairRequest, $user
                    );
                    DB::commit();
                    return $response;
                }

                // User Acceptance
                $response = (new \App\Actions\ICT\SignIctAcceptanceAction)->execute(
                    $request, $trackingRequest, $repairRequest, $user
                );
                DB::commit();
                return $response;
            } elseif ($user->role === 'it' || $user->role === 'super_admin') {
                // IT/SuperAdmin Update
                $response = (new \App\Actions\ICT\TechnicianUpdateIctTicketAction)->execute(
                    $request, $trackingRequest, $repairRequest, $user
                );
                DB::commit();
                return $response;
            } else {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Unauthorized Action'], 403);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            foreach ($savedSigFiles ?? [] as $sigPath) {
                if ($sigPath && Storage::disk('public')->exists($sigPath)) {
                    Storage::disk('public')->delete($sigPath);
                }
            }
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function checkTicketAccess($trackingRequest): void
    {
        if (!RequestAuthorization::canViewIctTicket(Auth::user(), $trackingRequest)) {
            abort(403, 'Unauthorized access to this request.');
        }
    }

    private function ictFormViewData(RequestModel $trackingRequest, RepairRequest $repairRequest, bool $forceView = false): array
    {
        $user = Auth::user();
        $flags = RequestAuthorization::ictFormFlags($user, $trackingRequest, $forceView, $repairRequest);

        $requestorId = $trackingRequest->user_id;
        $myAssets = \App\Models\InventoryAsset::where('assigned_to_user', $requestorId)
            ->whereNotIn('status', ['For Repair', 'For Disposal', 'Scrapped'])
            ->get();
        if ($trackingRequest->linked_asset_id) {
            $linkedAsset = \App\Models\InventoryAsset::find($trackingRequest->linked_asset_id);
            if ($linkedAsset && !$myAssets->contains('asset_id', $linkedAsset->asset_id)) {
                $myAssets->push($linkedAsset);
            }
        }

        $linkedAssetData = null;
        if ($trackingRequest->linked_asset_id) {
            $linkedAsset = \App\Models\InventoryAsset::find($trackingRequest->linked_asset_id);
            if ($linkedAsset) {
                $linkedAssetData = [
                    'serial_number'   => $linkedAsset->serial_number,
                    'property_number' => $linkedAsset->property_number,
                    'par_number'      => $linkedAsset->par_number,
                    'item_name'       => $linkedAsset->item_name,
                    'category'        => $linkedAsset->category,
                    'specifications'  => $linkedAsset->specifications ?? [],
                    'date_acquired'   => $linkedAsset->date_acquired
                        ? \Carbon\Carbon::parse($linkedAsset->date_acquired)->format('Y-m-d')
                        : null,
                ];
            }
        }

        // Build ICT assets map for JS auto-fill on asset selection
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

        $data = array_merge([
            'request' => $trackingRequest,
            'repairRequest' => $repairRequest,
            'myAssets' => $myAssets,
            'linkedAssetData' => $linkedAssetData,
            'ictAssetsMap' => $ictAssetsMap,
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

    public function recommendDisposal($id)
    {
        $trackingRequest = RequestModel::with('linkedAsset')->findOrFail($id);
        $this->checkTicketAccess($trackingRequest);

        $user = Auth::user();

        return (new \App\Actions\ICT\RecommendAssetDisposalAction)->execute($trackingRequest, $user);
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

        return (new \App\Actions\ICT\PrintDisposalTagAction)->execute($trackingRequest);
    }

    public function destroy($id)
    {
        return (new \App\Actions\ICT\DeleteIctTicketAction)->execute($id);
    }
}
