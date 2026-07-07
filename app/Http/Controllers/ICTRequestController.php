<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use App\Models\AuditLog;
use App\Http\Requests\StoreICTRequest;
use App\Support\RequestAuthorization;
use App\Services\RequestNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ICTRequestController extends Controller
{


    // Show ALL requests for the user (ICT and Preventive)
    public function index()
    {
        $user = Auth::user();
        $query = RequestModel::with(['user', 'repairRequest', 'assignedTo']);

        if ($user->role === 'user') {
            $query->where('user_id', $user->id);
            $requests = $query->orderBy('created_at', 'desc')->paginate(20);
            if (request()->wantsJson() || request()->expectsJson()) {
                return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
            }
            return view('requests.index', compact('requests'));
        } elseif ($user->role === 'it') {
            $query->where('assigned_to', $user->id);
            $requests = $query->orderBy('created_at', 'desc')->paginate(20);
            if (request()->wantsJson() || request()->expectsJson()) {
                return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
            }
            return view('requests.index', compact('requests'));
        } elseif ($user->role === 'admin' || $user->role === 'supply_officer' || $user->role === 'super_admin') {
            if ($user->role === 'admin' || $user->role === 'supply_officer') {
                // Admin is division-scoped - sees requests from their division only
                $query->whereHas('user', function($q) use ($user) {
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
                // Super Admin is office-scoped (branch level) - sees all approved requests in their branch
                $requests = $query->where('division_admin_review_status', 'Approved')
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

    public function updateStatus(Request $request)
    {
        $admin = Auth::user();
        if ($admin->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'id' => 'required|exists:requests,id',
            'status' => 'required|string|in:Pending,Ongoing,Completed,Rejected,Cancelled',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $trackingRequest = RequestModel::with('assignedTo')->findOrFail($validated['id']);

        if (!RequestAuthorization::canAdminQuickUpdateStatus($admin, $trackingRequest, $validated['status'])) {
            $hint = empty($trackingRequest->assigned_to) && $validated['status'] === RequestModel::STATUS_ONGOING
                ? ' Assign IT personnel first (View ticket → Assign IT).'
                : ($validated['status'] === RequestModel::STATUS_COMPLETED
                    ? ' Completed status is set by the end-user (ICT acceptance) or IT technician signature (PM), not via quick update.'
                    : '');

            return response()->json([
                'success' => false,
                'message' => 'This status change is not allowed.' . $hint,
            ], 422);
        }

        $trackingRequest->update([
            'status' => $validated['status'],
            'remarks' => $validated['remarks'],
        ]);

        $typeLabel = $trackingRequest->type === 'ICT' ? 'ICT request' : 'maintenance request';

        \App\Models\Notification::send(
            $trackingRequest->user_id,
            $trackingRequest->id,
            "Request {$validated['status']}",
            "Your {$typeLabel} {$trackingRequest->request_number} has been updated to {$validated['status']}."
        );

        AuditLog::log(
            'Updated Request Status',
            'Requests',
            "Quick-updated {$trackingRequest->request_number} to {$validated['status']}",
            $trackingRequest->office
        );

        return response()->json(['success' => true, 'message' => 'Request status updated successfully']);
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

    public function assignIt(Request $request, $id)
    {
        $trackingRequest = RequestModel::with('assignedTo')->findOrFail($id);
        $this->checkTicketAccess($trackingRequest);

        $admin = Auth::user();
        if (!RequestAuthorization::canAssignTicket($admin, $trackingRequest)) {
            return response()->json(['success' => false, 'message' => 'You cannot assign this request.'], 403);
        }

        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $itId = $validated['assigned_to'] ?? null;
        
        // Super Admin can assign himself if no IT available or IT not present
        if ($itId) {
            $itUser = User::findOrFail($itId);
            
            // Allow Super Admin to assign himself
            if ((int) $itId === (int) $admin->id) {
                // Super Admin assigning himself - allowed
                if ($admin->role !== 'super_admin') {
                    return response()->json(['success' => false, 'message' => 'Only Super Admin can assign themselves.'], 422);
                }
            } else {
                // Assigning someone else - check if IT role and in scope
                if ($itUser->role !== 'it') {
                    return response()->json(['success' => false, 'message' => 'Selected user must have IT role.'], 422);
                }
                
                if ($admin->role !== 'super_admin' && !RequestAuthorization::itUserInAdminScope($admin, $itUser)) {
                    return response()->json(['success' => false, 'message' => 'Selected IT personnel is not in your scope.'], 422);
                }
            }
        }

        $previousId = $trackingRequest->assigned_to;
        $updates = ['assigned_to' => $itId];
        if ($itId && $trackingRequest->status === RequestModel::STATUS_PENDING) {
            $updates['status'] = RequestModel::STATUS_ONGOING;
        }
        $trackingRequest->update($updates);
        $trackingRequest->refresh();

        if ($itId && (int) $previousId !== (int) $itId) {
            $itUser = User::findOrFail($itId);
            RequestNotificationService::notifyItAssigned($trackingRequest, $itUser);
            RequestNotificationService::notifyRequestorItAssigned($trackingRequest, $itUser);

            AuditLog::log(
                'Assigned ICT Request',
                'Requests',
                "Assigned {$trackingRequest->request_number} to " . ($itUser->role === 'super_admin' ? 'Super Admin' : 'IT') . " user #{$itId}",
                $trackingRequest->office
            );
        } elseif (!$itId && $previousId) {
            AuditLog::log(
                'Unassigned ICT Request',
                'Requests',
                "Removed IT assignment from {$trackingRequest->request_number}",
                $trackingRequest->office
            );
        }

        return response()->json([
            'success' => true,
            'message' => $itId ? 'Personnel assigned successfully.' : 'Assignment cleared.',
            'assigned_name' => $itId ? User::find($itId)?->full_name : null,
        ]);
    }

    public function review(Request $request, $id)
    {
        $trackingRequest = RequestModel::findOrFail($id);
        
        $admin = Auth::user();
        if (!$admin->isDivisionAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only Division Admins can review requests.'], 403);
        }

        // Verify the request belongs to the admin's scope (division or branch for supply admin)
        $ticketUser = $trackingRequest->user;
        if (!$ticketUser) {
            return response()->json(['success' => false, 'message' => 'Cannot verify request ownership.'], 403);
        }
        
        // Supply admin (Administrative) can review all tickets in branch
        // Regular division admin can only review tickets from their own division
        if ($admin->canProcessSupply()) {
            if ($admin->branch && $ticketUser->branch !== $admin->branch) {
                return response()->json(['success' => false, 'message' => 'This request is outside your branch scope.'], 403);
            }
        } else {
            if ($ticketUser->office !== $admin->office) {
                return response()->json(['success' => false, 'message' => 'This request is outside your division scope.'], 403);
            }
        }

        // Prevent re-review
        if ($trackingRequest->division_admin_review_status !== null) {
            return response()->json(['success' => false, 'message' => 'This request has already been reviewed.'], 422);
        }

        $validated = $request->validate([
            'status' => 'required|in:Approved,Rejected',
            'notes' => 'nullable|string|max:1000'
        ]);

        $trackingRequest->update([
            'division_admin_review_status' => $validated['status'],
            'division_admin_notes' => $validated['notes'],
            'reviewed_by_admin_id' => $admin->id,
            'reviewed_at' => now(),
        ]);

        if ($validated['status'] === 'Approved') {
            RequestNotificationService::notifySuperAdminOfForwardedRequest($trackingRequest, $admin);
        } else {
            // If rejected, update the main status to Rejected as well
            $trackingRequest->update(['status' => RequestModel::STATUS_REJECTED]);
            
            \App\Models\Notification::send(
                $trackingRequest->user_id,
                $trackingRequest->id,
                'Request Rejected',
                "Your ICT Request {$trackingRequest->request_number} was rejected by your Division Admin. Reason: " . ($validated['notes'] ?: 'No reason provided.')
            );
        }

        AuditLog::log(
            'Division Admin Review',
            'Requests',
            "Division Admin reviewed {$trackingRequest->request_number} (Status: {$validated['status']})",
            $trackingRequest->office
        );

        return response()->json([
            'success' => true,
            'message' => "Request {$validated['status']} successfully."
        ]);
    }

    public function downloadPdf($id)
    {
        $trackingRequest = RequestModel::findOrFail($id);
        
        $this->checkTicketAccess($trackingRequest);

        $repairRequest = RepairRequest::findOrFail($trackingRequest->detail_id);
        
        $pdf = Pdf::loadView('pdf.ict-form', [
            'request' => $trackingRequest,
            'repairRequest' => $repairRequest
        ]);

        if (ob_get_length()) {
            ob_end_clean();
        }
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $trackingRequest->request_number . '.pdf"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    // Create NEW request (user submits Section 1 only)
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!RequestAuthorization::canCreateIctTicket($user)) {
            return response()->json(['success' => false, 'message' => 'Only end-users can create ICT requests.'], 403);
        }

        $request->validate([
            'linked_asset_id' => 'required|integer|exists:inventory_assets,asset_id',
        ]);

        if ($assetError = RequestAuthorization::linkedAssetValidationError($user, $request->input('linked_asset_id'))) {
            return response()->json(['success' => false, 'message' => $assetError], 422);
        }

        try {
            DB::beginTransaction();

            $data = $user->role === 'user'
                ? $request->only([
                    'endUserLastName', 'end_user_last_name', 'last_name',
                    'endUserFirstName', 'end_user_first_name', 'first_name',
                    'endUserMiddleName', 'end_user_middle_name', 'middle_name',
                    'endUserSex', 'sex',
                    'divisionOffice', 'division',
                    'endUserEmail', 'email',
                    'employeeNo', 'employee_no',
                    'repairDescription', 'description',
                    'endUserSignature', 'end_user_signature',
                    'endUserPrintedName', 'end_user_printed_name',
                    'endUserDate', 'date_requested',
                    'articleSerialNo', 'article_serial_no',
                    'propertyNo', 'property_no',
                ])
                : $request->only($this->ictAllowedKeys());

            $mappedData = $this->mapLegacyData($data);

            // Handle End-User signature
            $savedSigFiles = [];
            if (isset($mappedData['end_user_signature']) && str_contains($mappedData['end_user_signature'], 'data:image')) {
                $mappedData['end_user_signature'] = $this->saveSignature(
                    $mappedData['end_user_signature'],
                    'ict_enduser',
                    ($mappedData['end_user_first_name'] ?? 'User') . '_' . ($mappedData['end_user_last_name'] ?? 'Request')
                );
                $savedSigFiles[] = $mappedData['end_user_signature'];
            }

            // Create repair request
            $repairRequest = RepairRequest::create($mappedData);

            // Generate request number
            $requestNumber = $this->generateRequestNumber('ICT');

            // Create tracking request
            $trackingRequest = RequestModel::create([
                'user_id' => Auth::id(),
                'request_number' => $requestNumber,
                'type' => 'ICT',
                'requestor_name' => ($mappedData['end_user_first_name'] ?? '') . ' ' . ($mappedData['end_user_last_name'] ?? ''),
                'description' => $mappedData['repair_description'] ?? '',
                'branch' => Auth::user()->branch,
                'region' => Auth::user()->region,
                'office' => $mappedData['division_office'] ?? '',
                'status' => RequestModel::STATUS_PENDING,
                'detail_id' => $repairRequest->id,
                'linked_asset_id' => $request->input('linked_asset_id'),
            ]);

            // Populate service_request_no immediately so it is never NULL in database
            $repairRequest->update(['service_request_no' => $requestNumber]);

            AuditLog::log(
                "Created ICT Request", 
                "Requests", 
                "Created new ICT request {$requestNumber} for " . Auth::user()->full_name,
                $trackingRequest->office
            );

            DB::commit();

            // Notify Specific Admins using the Cascading Logic
            RequestNotificationService::notifyAdminsOfNewRequest($trackingRequest, $user, 'ICT Request');

            return response()->json([
                'success' => true,
                'message' => 'ICT request submitted successfully',
                'request_number' => $requestNumber,
                'redirect' => route($user->dashboardRouteName()),
            ]);
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

    // Update EXISTING request (role-based sections)
    public function update(Request $request, $id)
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

            $request->validate([
                'endUserLastName' => 'nullable|string|max:255',
                'end_user_last_name' => 'nullable|string|max:255',
                'endUserFirstName' => 'nullable|string|max:255',
                'end_user_first_name' => 'nullable|string|max:255',
                'endUserMiddleName' => 'nullable|string|max:255',
                'end_user_middle_name' => 'nullable|string|max:255',
                'endUserSex' => 'nullable|string|max:10',
                'sex' => 'nullable|string|max:10',
                'divisionOffice' => 'nullable|string|max:255',
                'division' => 'nullable|string|max:255',
                'endUserEmail' => 'nullable|email|max:255',
                'email' => 'nullable|email|max:255',
                'employeeNo' => 'nullable|string|max:100',
                'employee_no' => 'nullable|string|max:100',
                'repairDescription' => 'nullable|string|max:5000',
                'description' => 'nullable|string|max:5000',
                'endUserSignature' => 'nullable|string',
                'end_user_signature' => 'nullable|string',
                'endUserPrintedName' => 'nullable|string|max:255',
                'end_user_printed_name' => 'nullable|string|max:255',
                'endUserDate' => 'nullable|date',
                'date_requested' => 'nullable|date',
                'linked_asset_id' => 'nullable|integer|exists:inventory_assets,asset_id',
                'last_updated_at' => 'nullable|string|max:50',
                'endUserAcceptanceSignature' => 'nullable|string',
                'end_user_acceptance_signature' => 'nullable|string',
                'endUserAcceptancePrintedName' => 'nullable|string|max:255',
                'end_user_acceptance_printed_name' => 'nullable|string|max:255',
                'endUserAcceptanceDate' => 'nullable|date',
                'end_user_acceptance_date' => 'nullable|date',
                // IT / technician fields
                'itReceivedLastName' => 'nullable|string|max:255',
                'it_received_last_name' => 'nullable|string|max:255',
                'itReceivedFirstName' => 'nullable|string|max:255',
                'it_received_first_name' => 'nullable|string|max:255',
                'itReceivedMiddleName' => 'nullable|string|max:255',
                'it_received_middle_name' => 'nullable|string|max:255',
                'initialDiagnosis' => 'nullable|string|max:5000',
                'initial_diagnosis' => 'nullable|string|max:5000',
                'repairType' => 'nullable',
                'repair_type' => 'nullable',
                'itRemarks' => 'nullable|string|max:5000',
                'it_remarks' => 'nullable|string|max:5000',
                'technicianSignature' => 'nullable|string',
                'technician_signature' => 'nullable|string',
                'technicianPrintedName' => 'nullable|string|max:255',
                'technician_printed_name' => 'nullable|string|max:255',
                'technicianDate' => 'nullable|date',
                'technician_date' => 'nullable|date',
                'itPersonnelSignature' => 'nullable|string',
                'it_personnel_signature' => 'nullable|string',
                'itPersonnelPrintedName' => 'nullable|string|max:255',
                'it_personnel_printed_name' => 'nullable|string|max:255',
                'itPersonnelDate' => 'nullable|date',
                'it_personnel_date' => 'nullable|date',
                'afterRepairStatus' => 'nullable|string|max:100',
                'after_repair_status' => 'nullable|string|max:100',
                'findingsRemarks' => 'nullable|string|max:5000',
                'findings_remarks' => 'nullable|string|max:5000',
                'serviceRequestNo' => 'nullable|string|max:100',
                'service_request_no' => 'nullable|string|max:100',
                'rid' => 'nullable|string|max:100',
                'dateReceived' => 'nullable|date',
                'date_received' => 'nullable|date',
                'serviceScheduleDate' => 'nullable|date',
                'service_schedule_date' => 'nullable|date',
                'propertyNo' => 'nullable|string|max:100',
                'property_no' => 'nullable|string|max:100',
                'articleSerialNo' => 'nullable|string|max:100',
                'article_serial_no' => 'nullable|string|max:100',
                'companyName' => 'nullable|string|max:255',
                'company_name' => 'nullable|string|max:255',
                'companyPhone' => 'nullable|string|max:100',
                'company_phone' => 'nullable|string|max:100',
                'companyEmail' => 'nullable|email|max:255',
                'company_email' => 'nullable|email|max:255',
                'companyAddress' => 'nullable|string|max:500',
                'company_address' => 'nullable|string|max:500',
                'actionTaken' => 'nullable|string|max:5000',
                'action_taken' => 'nullable|string|max:5000',
                'technicianLastName' => 'nullable|string|max:255',
                'technician_last_name' => 'nullable|string|max:255',
                'technicianFirstName' => 'nullable|string|max:255',
                'technician_first_name' => 'nullable|string|max:255',
                'technicianMiddleName' => 'nullable|string|max:255',
                'technician_middle_name' => 'nullable|string|max:255',
                'afterServiceDate' => 'nullable|date',
                'after_service_date' => 'nullable|date',
            ]);

            if ($user->role === 'user') {
                // RESUBMIT: User resubmitting a rejected request
                if ($trackingRequest->status === RequestModel::STATUS_REJECTED
                    && (int) $trackingRequest->user_id === (int) $user->id) {
                    
                    $data = $request->only([
                        'endUserLastName', 'end_user_last_name',
                        'endUserFirstName', 'end_user_first_name',
                        'endUserMiddleName', 'end_user_middle_name',
                        'endUserSex', 'sex',
                        'divisionOffice', 'division',
                        'endUserEmail', 'email',
                        'employeeNo', 'employee_no',
                        'repairDescription', 'description',
                        'endUserSignature', 'end_user_signature',
                        'endUserPrintedName', 'end_user_printed_name',
                        'endUserDate', 'date_requested',
                        'linked_asset_id',
                        'last_updated_at',
                        'articleSerialNo', 'article_serial_no',
                        'propertyNo', 'property_no',
                    ]);

                    $mappedData = $this->mapLegacyData($data);

                    if (isset($mappedData['end_user_signature']) && str_contains($mappedData['end_user_signature'], 'data:image')) {
                        $mappedData['end_user_signature'] = $this->saveSignature(
                            $mappedData['end_user_signature'], 'ict_enduser',
                            ($mappedData['end_user_first_name'] ?? 'User') . '_' . ($mappedData['end_user_last_name'] ?? 'Request')
                        );
                        $savedSigFiles[] = $mappedData['end_user_signature'];
                    }

                    $repairRequest->update($mappedData);

                    // Reset ticket to Pending and clear division review
                    $trackingRequest->update([
                        'status' => RequestModel::STATUS_PENDING,
                        'division_admin_review_status' => null,
                        'division_admin_notes' => null,
                        'reviewed_by_admin_id' => null,
                        'reviewed_at' => null,
                        'remarks' => null,
                    ]);

                    if ($request->filled('linked_asset_id')) {
                        $trackingRequest->update(['linked_asset_id' => $request->input('linked_asset_id')]);
                    }

                    AuditLog::log('Resubmitted ICT Request', 'Requests',
                        "User resubmitted rejected ICT request {$trackingRequest->request_number}",
                        $trackingRequest->office);

                    \App\Models\Notification::send(
                        $trackingRequest->user_id, $trackingRequest->id,
                        'Request Resubmitted',
                        "Your ICT Request {$trackingRequest->request_number} has been resubmitted and is now Pending review."
                    );

                    // Re-notify division admins of the resubmitted request
                    \App\Services\RequestNotificationService::notifyAdminsOfNewRequest(
                        $trackingRequest, $user, \App\Services\RequestNotificationService::typeLabel($trackingRequest->type)
                    );

                    DB::commit();
                    return response()->json([
                        'success' => true,
                        'message' => 'Request resubmitted successfully.',
                        'redirect' => route($user->dashboardRouteName()),
                    ]);
                }

                if (!RequestAuthorization::canSignIctAcceptance($user, $trackingRequest, $repairRequest)) {
                    DB::rollBack();
                    $reason = RequestAuthorization::ictAcceptanceBlockReason($repairRequest)
                        ?? 'You cannot sign acceptance until IT has completed the required sections and signatures.';

                    return response()->json(['success' => false, 'message' => $reason], 422);
                }

                $data = $request->only([
                    'endUserAcceptanceSignature', 'end_user_acceptance_signature',
                    'endUserAcceptancePrintedName', 'end_user_acceptance_printed_name',
                    'endUserAcceptanceDate', 'end_user_acceptance_date',
                    'last_updated_at',
                ]);

                if (empty($data['endUserAcceptanceSignature']) && empty($data['end_user_acceptance_signature'])) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'Acceptance signature is required.'], 422);
                }
            } elseif ($user->role === 'it' || $user->role === 'super_admin') {
                $data = $request->only(RequestAuthorization::ictTechnicianFieldKeys());
            } else {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Unauthorized Action'], 403);
            }
            
            $mappedData = $this->mapLegacyData($data);
            $oldRepairTypes = [];
            $hadItSignatureBefore = !empty($repairRequest->it_personnel_signature);
            if (in_array($user->role, ['it', 'super_admin'])) {
                $oldRepairTypes = json_decode($repairRequest->repair_type ?? '[]', true) ?: [];
            }

            // Handle Signatures in Update (Issue 4: Storage Leak Fix)
            $sigFields = [
                'end_user_signature' => 'ict_enduser',
                'technician_signature' => 'ict_tech',
                'it_personnel_signature' => 'ict_itpersonnel',
                'end_user_acceptance_signature' => 'ict_acceptance'
            ];

            $savedSigFiles = [];
            foreach ($sigFields as $field => $prefix) {
                if (isset($mappedData[$field]) && str_contains($mappedData[$field], 'data:image')) {
                    // Delete old signature file if it exists to prevent disk bloat
                    if (!empty($repairRequest->$field) && Storage::disk('public')->exists($repairRequest->$field)) {
                        Storage::disk('public')->delete($repairRequest->$field);
                    }
                    $mappedData[$field] = $this->saveSignature($mappedData[$field], $prefix, 'Update');
                    $savedSigFiles[] = $mappedData[$field];
                }
            }

            $repairRequest->update($mappedData);

            $oldStatus = $trackingRequest->status;
            $newStatus = $oldStatus;

            if (in_array($user->role, ['it', 'super_admin'])) {
                $repairRequest->refresh();
                $newRepairTypes = json_decode($repairRequest->repair_type ?? '[]', true) ?: [];
                $newlyReferredToSp = in_array('REFERRED TO SERVICE PROVIDER', $newRepairTypes, true)
                    && !in_array('REFERRED TO SERVICE PROVIDER', $oldRepairTypes, true);

                if ($newlyReferredToSp) {
                    RequestNotificationService::notifySupplyOfficersOfReferredIct($trackingRequest, $user);
                    if (!in_array($oldStatus, [
                        RequestModel::STATUS_COMPLETED,
                        RequestModel::STATUS_CANCELLED,
                        RequestModel::STATUS_AWAITING_PARTS,
                    ], true)) {
                        $newStatus = RequestModel::STATUS_REFERRED_EXTERNAL;
                    }
                }

                // When IT signs → AWAITING_SIGNATURE + notify user
                if (!empty($mappedData['it_personnel_signature'])
                    && str_contains((string) $mappedData['it_personnel_signature'], 'signatures/')
                    && !$hadItSignatureBefore
                    && $trackingRequest->user_id) {
                    
                    if (!in_array($oldStatus, [RequestModel::STATUS_AWAITING_SIGNATURE, RequestModel::STATUS_COMPLETED, RequestModel::STATUS_CANCELLED], true)) {
                        $newStatus = RequestModel::STATUS_AWAITING_SIGNATURE;
                    }
                    
                    \App\Models\Notification::send(
                        $trackingRequest->user_id,
                        $trackingRequest->id,
                        'Ready for Signature',
                        "IT has completed the repair for your ICT request {$trackingRequest->request_number}. Please open the ticket and sign the Service Acceptance section (Section 6)."
                    );
                }

                // If it was Pending and admin starts filling technical fields, set to Ongoing
                if ($newStatus === $oldStatus && $oldStatus === RequestModel::STATUS_PENDING && (
                    !empty($mappedData['it_received_last_name']) || 
                    !empty($mappedData['initial_diagnosis']) ||
                    !empty($mappedData['service_request_no']) ||
                    !empty($mappedData['repair_type'])
                )) {
                    $newStatus = RequestModel::STATUS_ONGOING;
                }
                
                // IF AFTER REPAIR STATUS IS FOR DISPOSAL — set asset to For Disposal now (locked)
                // Supply officer notification fires later when ticket is Completed (via Request observer)
                if (!empty($mappedData['after_repair_status']) && $mappedData['after_repair_status'] === 'FOR DISPOSAL') {
                    $asset = $trackingRequest->linkedAsset;
                    if ($asset && !in_array($asset->status, [\App\Enums\AssetStatus::FOR_DISPOSAL, \App\Enums\AssetStatus::SCRAPPED, 'Disposed', 'Pending'])) {
                        // Remove user assignment since asset is being turned over to Supply Officer
                        $previousUserId = $asset->assigned_to_user;
                        $asset->status = \App\Enums\AssetStatus::FOR_DISPOSAL;
                        $asset->assigned_to_user = null;
                        $asset->save();

                        \App\Models\InventoryHistory::create([
                            'asset_id' => $asset->asset_id,
                            'action' => 'IT Recommended For Disposal',
                            'previous_user_id' => $previousUserId,
                            'new_user_id' => null,
                            'performed_by' => $user->id,
                            'remarks' => "Asset recommended for disposal via ICT Request form {$trackingRequest->request_number}. Assignment removed - turned over to Supply Officer.",
                        ]);

                        AuditLog::log(
                            "Recommended Asset For Disposal",
                            "Inventory",
                            "Recommended asset {$asset->property_number} for disposal via ICT request {$trackingRequest->request_number}",
                            $trackingRequest->office
                        );
                        // NOTE: No supply notification here — fires on ticket Completion in Request::booted()
                    }
                }

                // Even if Admin marks it as "COMPLETED" or "FOR DISPOSAL" in the form,
                // the system status MUST remain ONGOING.
                // It only becomes fully COMPLETED when the End-User signs the acceptance.
                if ($newStatus === $oldStatus
                    && !empty($mappedData['after_repair_status'])
                    && $oldStatus !== RequestModel::STATUS_COMPLETED
                    && $oldStatus !== RequestModel::STATUS_REFERRED_EXTERNAL) {
                    $newStatus = RequestModel::STATUS_ONGOING;
                }
            }
            // User is signing acceptance
            if ($user->role === 'user' && !empty($mappedData['end_user_acceptance_signature'])) {
                $newStatus = RequestModel::STATUS_COMPLETED;
            }

            if ($newStatus !== $oldStatus) {
                $trackingRequest->update(['status' => $newStatus]);
                
                // Notify User of Status Change
                $ictNotificationMessage = $newStatus === RequestModel::STATUS_COMPLETED
                    ? "Your ICT Request {$trackingRequest->request_number} has been completed. Please complete the required satisfaction survey."
                    : "Your ICT Request {$trackingRequest->request_number} status has been updated to {$newStatus}.";

                \App\Models\Notification::send(
                    $trackingRequest->user_id,
                    $trackingRequest->id,
                    "Request {$newStatus}",
                    $ictNotificationMessage
                );
            } else {
                // General update notification if not status change
                if (Auth::user()->id !== $trackingRequest->user_id) {
                    \App\Models\Notification::send(
                        $trackingRequest->user_id,
                        $trackingRequest->id,
                        'Request Updated',
                        "IT personnel has updated the details of your request {$trackingRequest->request_number}."
                    );
                }
            }

            AuditLog::log(
                "Updated ICT Request", 
                "Requests", 
                "Updated ICT request {$trackingRequest->request_number} (Status: {$trackingRequest->status})",
                $trackingRequest->office
            );

            DB::commit();

            $redirect = route('ict.edit', $id);
            $message = 'ICT Request updated successfully';
            $printUrl = null;

            if (
                $user->role === 'user'
                && !empty($mappedData['end_user_acceptance_signature'])
                && $newStatus === RequestModel::STATUS_COMPLETED
            ) {
                $redirect = route('csm.create', $trackingRequest->id);
                $message = 'Request completed! Please complete the required satisfaction survey.';
            } elseif (in_array($user->role, ['it', 'super_admin']) && ($mappedData['after_repair_status'] ?? '') === 'FOR DISPOSAL') {
                $redirect = route('ict.edit', $id);
                $printUrl = route('ict.disposal-tag', $id);
                $message = 'Request updated and Asset marked FOR DISPOSAL. The Disposal Tag will now open.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect' => $redirect,
                'print_url' => $printUrl,
            ]);
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

    private function saveSignature($base64Data, $type, $name)
    {
        return \App\Support\RequestHelpers::saveSignature($base64Data, $type, $name);
    }

    private function generateRequestNumber($type)
    {
        return \App\Support\RequestHelpers::generateRequestNumber($type);
    }
    
    private function getBranchCode(?string $branch): string
    {
        return \App\Support\RequestHelpers::getBranchCode($branch);
    }

    private function ictAllowedKeys(): array
    {
        // End-user info fields
        $userKeys = [
            'endUserLastName', 'end_user_last_name', 'last_name',
            'endUserFirstName', 'end_user_first_name', 'first_name',
            'endUserMiddleName', 'end_user_middle_name', 'middle_name',
            'endUserSex', 'sex',
            'divisionOffice', 'division',
            'endUserEmail', 'email',
            'employeeNo', 'employee_no',
            'repairDescription', 'description',
            'endUserSignature', 'end_user_signature',
            'endUserPrintedName', 'end_user_printed_name',
            'endUserDate', 'date_requested',
        ];

        // IT / technician fields (reuse existing whitelist)
        $itKeys = \App\Support\RequestAuthorization::ictTechnicianFieldKeys();

        // Additional fields from mapper not covered above
        $otherKeys = [
            'repairCost', 'cost',
            'endUserAcceptanceSignature', 'end_user_acceptance_signature',
            'endUserAcceptancePrintedName', 'end_user_acceptance_printed_name',
            'endUserAcceptanceDate', 'end_user_acceptance_date',
            'linked_asset_id',
        ];

        return array_values(array_unique(array_merge($userKeys, $itKeys, $otherKeys)));
    }

    private function mapLegacyData($data)
    {
        return array_filter([
            // End-user info
            'end_user_last_name' => $data['endUserLastName'] ?? $data['last_name'] ?? null,
            'end_user_first_name' => $data['endUserFirstName'] ?? $data['first_name'] ?? null,
            'end_user_middle_name' => $data['endUserMiddleName'] ?? $data['middle_name'] ?? null,
            'end_user_sex' => $data['endUserSex'] ?? $data['sex'] ?? null,
            'division_office' => $data['divisionOffice'] ?? $data['division'] ?? null,
            'end_user_email' => $data['endUserEmail'] ?? $data['email'] ?? null,
            'employee_no' => $data['employeeNo'] ?? $data['employee_no'] ?? null,
            'repair_description' => $data['repairDescription'] ?? $data['description'] ?? null,
            'end_user_signature' => $data['endUserSignature'] ?? $data['end_user_signature'] ?? null,
            'end_user_printed_name' => $data['endUserPrintedName'] ?? $data['end_user_printed_name'] ?? null,
            'end_user_date' => $data['endUserDate'] ?? $data['date_requested'] ?? null,

            // IT Personnel Section
            'it_received_last_name' => $data['itReceivedLastName'] ?? $data['it_received_last_name'] ?? null,
            'it_received_first_name' => $data['itReceivedFirstName'] ?? $data['it_received_first_name'] ?? null,
            'it_received_middle_name' => $data['itReceivedMiddleName'] ?? $data['it_received_middle_name'] ?? null,
            'initial_diagnosis' => $data['initialDiagnosis'] ?? $data['it_initial_diagnosis'] ?? null,
            'repair_type' => isset($data['repairType']) ? (is_string($data['repairType']) ? $data['repairType'] : json_encode($data['repairType'])) : (isset($data['repair_type']) ? (is_string($data['repair_type']) ? $data['repair_type'] : json_encode($data['repair_type'])) : null),
            'it_remarks' => $data['itRemarks'] ?? $data['it_remarks'] ?? null,
            
            'service_request_no' => $data['serviceRequestNo'] ?? $data['service_request_no'] ?? null,
            'rid' => $data['rid'] ?? null,
            'date_received' => $data['dateReceived'] ?? $data['date_received'] ?? null,
            'service_schedule_date' => $data['serviceScheduleDate'] ?? $data['service_schedule_date'] ?? null,
            'property_no' => $data['propertyNo'] ?? $data['property_no'] ?? null,
            'article_serial_no' => $data['articleSerialNo'] ?? $data['article_serial_no'] ?? null,
            'office_date_acquired' => $data['officeDateAcquired'] ?? $data['office_date_acquired'] ?? null,

            // Service Provider
            'service_date' => $data['serviceDate'] ?? $data['service_provider_date'] ?? null,
            'pullout_date' => $data['pulloutDate'] ?? $data['pullout_date'] ?? null,
            'company_name' => $data['companyName'] ?? $data['company_name'] ?? null,
            'company_phone' => $data['companyPhone'] ?? $data['company_phone'] ?? null,
            'company_email' => $data['companyEmail'] ?? $data['company_email'] ?? null,
            'company_address' => $data['companyAddress'] ?? $data['company_address'] ?? null,
            'action_taken' => $data['actionTaken'] ?? $data['action_taken'] ?? null,
            
            'technician_last_name' => $data['technicianLastName'] ?? $data['technician_last_name'] ?? null,
            'technician_first_name' => $data['technicianFirstName'] ?? $data['technician_first_name'] ?? null,
            'technician_middle_name' => $data['technicianMiddleName'] ?? $data['technician_middle_name'] ?? null,
            'technician_signature' => $data['technicianSignature'] ?? $data['technician_signature'] ?? null,
            'technician_printed_name' => $data['technicianPrintedName'] ?? $data['technician_printed_name'] ?? null,
            'technician_date' => $data['technicianDate'] ?? $data['technician_signature_date'] ?? null,

            // After Repair
            'after_repair_status' => $data['afterRepairStatus'] ?? $data['after_repair_status'] ?? null,
            'cost' => $data['repairCost'] ?? $data['cost'] ?? null,
            'after_service_date' => $data['afterServiceDate'] ?? $data['after_service_date'] ?? null,
            'findings_remarks' => $data['findingsRemarks'] ?? $data['findings_remarks'] ?? null,
            'it_personnel_signature' => $data['itPersonnelSignature'] ?? $data['it_personnel_signature'] ?? null,
            'it_personnel_printed_name' => $data['itPersonnelPrintedName'] ?? $data['it_personnel_printed_name'] ?? null,
            'it_personnel_date' => $data['itPersonnelDate'] ?? $data['it_personnel_signature_date'] ?? null,

            // Acceptance
            'end_user_acceptance_signature' => $data['endUserAcceptanceSignature'] ?? $data['end_user_acceptance_signature'] ?? null,
            'end_user_acceptance_printed_name' => $data['endUserAcceptancePrintedName'] ?? $data['end_user_acceptance_printed_name'] ?? null,
            'end_user_acceptance_date' => $data['endUserAcceptanceDate'] ?? $data['end_user_acceptance_date'] ?? null,
        ], function($val) { return $val !== null; });
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
        if ($user->role !== 'it' && $user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (!$trackingRequest->linkedAsset) {
            return redirect()->back()->with('error', 'No asset linked to this request.');
        }

        $asset = $trackingRequest->linkedAsset;
        if (in_array($asset->status, [\App\Enums\AssetStatus::FOR_DISPOSAL, \App\Enums\AssetStatus::SCRAPPED, 'Disposed', 'Pending'])) {
            return redirect()->back()->with('error', 'Asset is already disposed or pending disposal.');
        }

        DB::beginTransaction();
        try {
            // Remove user assignment since asset is being turned over to Supply Officer
            $previousUserId = $asset->assigned_to_user;
            $asset->status = \App\Enums\AssetStatus::FOR_DISPOSAL;
            $asset->assigned_to_user = null;
            $asset->save();

            \App\Models\InventoryHistory::create([
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
            \Illuminate\Support\Facades\Log::error('Disposal recommendation failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to recommend disposal. Please try again.');
        }

        $admins = \App\Models\User::where('role', 'admin')
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
            $superAdmins = \App\Models\User::where('role', 'super_admin')
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

    public function disposalTag($id)
    {
        $user = Auth::user();
        
        // Only IT and Super Admin can access the disposal tag
        if (!in_array($user->role, ['it', 'super_admin'])) {
            abort(403, 'Only IT personnel and Super Admin can access the disposal tag.');
        }

        $trackingRequest = RequestModel::with(['linkedAsset', 'user'])->findOrFail($id);
        $this->checkTicketAccess($trackingRequest);
        
        $repairRequest = RepairRequest::findOrFail($trackingRequest->detail_id);

        if (!$trackingRequest->linkedAsset) {
            abort(404, 'No asset linked to this request.');
        }

        if ($trackingRequest->linkedAsset->status !== \App\Enums\AssetStatus::FOR_DISPOSAL) {
            abort(403, 'This asset has not been marked For Disposal yet.');
        }

        $pdf = Pdf::loadView('pdf.disposal-tag', [
            'request' => $trackingRequest,
            'repairRequest' => $repairRequest,
            'asset' => $trackingRequest->linkedAsset,
            'itUser' => Auth::user()
        ]);

        // Small tag size or A4? Let's use A4 but layout as a card/tag.
        $pdf->setPaper('A4', 'portrait');

        if (ob_get_length()) {
            ob_end_clean();
        }
        
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="DISPOSAL-TAG-' . $trackingRequest->request_number . '.pdf"',
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
            $repairRequest = RepairRequest::findOrFail($trackingRequest->detail_id);
            
            // Due to Soft Deletes implementation, we do NOT delete signature files from storage anymore.
            // This ensures signatures remain intact if the request is restored.

            $repairRequest->delete();
            $trackingRequest->delete();

            AuditLog::log("Deleted ICT Request", "Requests", "Deleted ICT request {$trackingRequest->request_number}", $trackingRequest->office);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'ICT request deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
