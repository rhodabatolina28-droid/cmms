<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\PreventiveMaintenance;
use App\Models\Request as RequestModel;
use App\Http\Requests\StoreMaintenanceRequest;
use App\Support\RequestAuthorization;
use App\Services\RequestNotificationService;
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
        $query = RequestModel::with(['user', 'maintenanceRequest', 'assignedTo'])
            ->where('type', 'Preventive Maintenance')
            ->where('status', '!=', RequestModel::STATUS_SCHEDULED);

        if ($user->role === 'user') {
            $query->where('user_id', $user->id);
        } elseif ($user->role === 'it') {
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

        // IT sees ONLY PMs assigned to them
        if ($user->role === 'it') {
            $query->where('assigned_to', $user->id);
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
                      $sub->whereNull('assigned_to')
                          ->whereHas('linkedAsset', function ($asset) use ($user) {
                              if ($user->branch) {
                                  $asset->where('branch', $user->branch);
                              }
                          });
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

        if ($trackingRequest->status === RequestModel::STATUS_SCHEDULED) {
            $trackingRequest->update(['status' => RequestModel::STATUS_ONGOING]);

            if ($trackingRequest->assigned_to !== $user->id) {
                $trackingRequest->update(['assigned_to' => $user->id]);
            }
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
        ], $flags));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!RequestAuthorization::canCreateMaintenanceTicket($user)) {
            return response()->json(['success' => false, 'message' => 'PM is now managed via schedules by your ICT Unit. Contact your Super Admin.'], 403);
        }

        $request->validate([
            'linked_asset_id' => 'required|integer|exists:inventory_assets,asset_id',
        ]);

        // Only check asset assignment for user role — IT/super_admin can create PM for any asset
        if ($user->role === 'user') {
            if ($assetError = RequestAuthorization::linkedAssetValidationError($user, $request->input('linked_asset_id'))) {
                return response()->json(['success' => false, 'message' => $assetError], 422);
            }
        }

        try {
            DB::beginTransaction();
            $data = $this->filterMaintenanceInput($request);

            $techSigData = '';
            $userSigData = $data['endUserSignature'] ?? $data['end_user_signature'] ?? '';

            if ($user->role !== 'user') {
                $techSigData = $data['technicianSignature'] ?? $data['technician_signature'] ?? '';
            }

            $techSig = $this->saveSignature($techSigData, 'maint_tech', $data['technician_name'] ?? $data['technicianName'] ?? 'Unknown');
            $userSig = $this->saveSignature($userSigData, 'maint_user', $data['end_user_name'] ?? $data['endUserName'] ?? 'Unknown');
            $savedSigFiles = array_filter([$techSig, $userSig]);

            $mappedData = $this->mapLegacyData($data);
            if ($user->role === 'user') {
                $mappedData = $this->stripMaintenanceAdminFields($mappedData);
            }

            $tasksObj = [];
            if ($user->role !== 'user') {
                $checklistKeywords = ['Cleanup', 'Backup', 'Restore', 'Update', 'Temp', 'Recycle', 'Defrag', 'CheckDisk', 'Scan', 'Virus', 'Defender', 'Startup', 'Level', 'Quality', 'Toner', 'Updated', 'Charging', 'Overload'];
                foreach ($data as $key => $value) {
                    foreach ($checklistKeywords as $kw) {
                        if (str_contains(strtolower($key), strtolower($kw))) {
                            $tasksObj[$key] = $value;
                            break;
                        }
                    }
                }
            }

            $maintenance = PreventiveMaintenance::create(array_merge($mappedData, [
                'technician_signature' => $techSig,
                'end_user_signature' => $userSig,
                'maintenance_tasks_json' => json_encode($tasksObj),
            ]));

            // Generate request number
            $requestNumber = $this->generateRequestNumber('Preventive Maintenance');
            $maintenance->update([
                'form_no' => $requestNumber,
                'service_request_no' => $requestNumber
            ]);

            // Create tracking request
            $trackingRequest = RequestModel::create([
                'user_id' => Auth::id(),
                'request_number' => $requestNumber,
                'type' => 'Preventive Maintenance',
                'requestor_name' => $data['end_user_name'] ?? $data['endUserName'] ?? 'Unknown',
                'description' => $data['problem_description'] ?? $data['problemDescription'] ?? '',
                'region' => Auth::user()->region,
                'branch' => Auth::user()->branch,
                'office' => $user->office ?? $data['end_user_division'] ?? $data['endUserDivision'] ?? '',
                'status' => RequestModel::STATUS_SCHEDULED,
                'detail_id' => $maintenance->id,
                'linked_asset_id' => $request->input('linked_asset_id'),
            ]);

            // Notify Super Admins directly for PMs (bypassing Division Admin review)
            RequestNotificationService::notifySuperAdminsOfNewPmRequest($trackingRequest, $user);

            AuditLog::log(
                "Created PM Request", 
                "Requests", 
                "Created new Preventive Maintenance request {$requestNumber} for " . Auth::user()->full_name,
                $trackingRequest->office
            );

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Maintenance form submitted successfully',
                'request_number' => $requestNumber,
                'id' => $maintenance->id,
                'redirect' => route('maintenance.index')
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
                'Assigned PM Request',
                'Requests',
                "Assigned {$trackingRequest->request_number} to " . ($itUser->role === 'super_admin' ? 'Super Admin' : 'IT') . " user #{$itId}",
                $trackingRequest->office
            );
        } elseif (!$itId && $previousId) {
            AuditLog::log(
                'Unassigned PM Request',
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

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();
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
            
            $maintenance = PreventiveMaintenance::findOrFail($trackingRequest->detail_id);
            
            $user = Auth::user();

            if (!RequestAuthorization::canUpdateMaintenanceTicket($user, $trackingRequest)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'You are not allowed to update this request.'], 403);
            }

            $request->validate([
                'technicianSignature' => 'nullable|string',
                'technician_signature' => 'nullable|string',
                'endUserSignature' => 'nullable|string',
                'end_user_signature' => 'nullable|string',
                'technician_name' => 'nullable|string|max:255',
                'technicianName' => 'nullable|string|max:255',
                'end_user_name' => 'nullable|string|max:255',
                'endUserName' => 'nullable|string|max:255',
                'technician_date' => 'nullable|date',
                'technicianDate' => 'nullable|date',
                'end_user_date' => 'nullable|date',
                'endUserDate' => 'nullable|date',
                'end_user_remarks' => 'nullable|string|max:2000',
                'endUserRemarks' => 'nullable|string|max:2000',
                'linked_asset_id' => 'nullable|integer|exists:inventory_assets,asset_id',
                'last_updated_at' => 'nullable|string|max:50',
            ]);

            $data = $this->filterMaintenanceInput($request);

            $mappedData = $this->mapLegacyData($data);

            // Handle signatures if provided
            $techSigData = $data['technicianSignature'] ?? $data['technician_signature'] ?? '';
            $userSigData = $data['endUserSignature'] ?? $data['end_user_signature'] ?? '';

            $savedSigFiles = [];
            if ($techSigData && str_contains($techSigData, 'data:image')) {
                if (!empty($maintenance->technician_signature) && Storage::disk('public')->exists($maintenance->technician_signature)) {
                    Storage::disk('public')->delete($maintenance->technician_signature);
                }
                $mappedData['technician_signature'] = $this->saveSignature($techSigData, 'maint_tech', $data['technician_name'] ?? $data['technicianName'] ?? 'Unknown');
                $savedSigFiles[] = $mappedData['technician_signature'];
            }
            if ($userSigData && str_contains($userSigData, 'data:image')) {
                if (!empty($maintenance->end_user_signature) && Storage::disk('public')->exists($maintenance->end_user_signature)) {
                    Storage::disk('public')->delete($maintenance->end_user_signature);
                }
                $mappedData['end_user_signature'] = $this->saveSignature($userSigData, 'maint_user', $data['end_user_name'] ?? $data['endUserName'] ?? 'Unknown');
                $savedSigFiles[] = $mappedData['end_user_signature'];
            }

            // Handle tasks JSON bundling dynamically in update
            $tasksObj = [];
            $checklistKeywords = ['Cleanup', 'Backup', 'Restore', 'Update', 'Temp', 'Recycle', 'Defrag', 'CheckDisk', 'Scan', 'Virus', 'Defender', 'Startup', 'Level', 'Quality', 'Toner', 'Updated', 'Charging', 'Overload'];
            
            foreach ($data as $key => $value) {
                foreach ($checklistKeywords as $kw) {
                    if (str_contains(strtolower($key), strtolower($kw))) {
                        $tasksObj[$key] = $value;
                        break;
                    }
                }
            }
            if (!empty($tasksObj)) {
                $mappedData['maintenance_tasks_json'] = json_encode($tasksObj);
            }

            $maintenance->update($mappedData);
            
            // Automatic Status Logic
            $oldStatus = $trackingRequest->status;
            $newStatus = $oldStatus;

            if ($user->role === 'it' || $user->role === 'super_admin') {
                if (in_array($oldStatus, [RequestModel::STATUS_PENDING, RequestModel::STATUS_SCHEDULED])) {
                    $newStatus = RequestModel::STATUS_ONGOING;
                }

                $hasNewSig = !empty($mappedData['technician_signature']);
                $hasNewEndUserSig = !empty($mappedData['end_user_signature']);

                // Both IT and end-user signed → COMPLETED
                if ($hasNewSig && $hasNewEndUserSig) {
                    $newStatus = RequestModel::STATUS_COMPLETED;
                }
            }

            // Shared completion: update asset PM dates when status becomes COMPLETED
            if ($newStatus === RequestModel::STATUS_COMPLETED && $trackingRequest->linked_asset_id) {
                $asset = \App\Models\InventoryAsset::find($trackingRequest->linked_asset_id);
                if ($asset) {
                    $asset->last_pm_date = now();
                    if ($trackingRequest->pm_schedule_id) {
                        $schedule = \App\Models\PMSchedule::find($trackingRequest->pm_schedule_id);
                        if ($schedule && $schedule->is_active) {
                            $asset->next_pm_due_date = $schedule->calculateNextDate();
                        } else {
                            $asset->next_pm_due_date = now()->addMonths(3);
                        }
                    } else {
                        $asset->next_pm_due_date = now()->addMonths(3);
                    }
                    $asset->save();
                }
            }

            if ($newStatus !== $oldStatus) {
                $trackingRequest->update(['status' => $newStatus]);

                // Notify User
                $notificationMessage = $newStatus === RequestModel::STATUS_COMPLETED
                    ? "Your Maintenance Request {$trackingRequest->request_number} has been completed. Please log in and complete the required satisfaction survey."
                    : "Your Maintenance Request {$trackingRequest->request_number} status has been updated to {$newStatus}.";

                \App\Models\Notification::send(
                    $trackingRequest->user_id,
                    $trackingRequest->id,
                    "Request {$newStatus}",
                    $notificationMessage
                );
            } else if (Auth::user()->id !== $trackingRequest->user_id) {
                // General update notification if not status change
                \App\Models\Notification::send(
                    $trackingRequest->user_id,
                    $trackingRequest->id,
                    'Request Updated',
                    "IT personnel has updated the details of your maintenance request {$trackingRequest->request_number}."
                );
            }

            AuditLog::log(
                "Updated PM Request", 
                "Requests", 
                "Updated Preventive Maintenance request {$trackingRequest->request_number} (Status: {$trackingRequest->status})",
                $trackingRequest->office
            );

            DB::commit();

            // Auto-advance PM cycle AFTER commit — so checkAndAdvance() can read
            // the committed Completed status when checking division progress.
            if ($newStatus === RequestModel::STATUS_COMPLETED
                && $trackingRequest->is_auto_generated
                && $trackingRequest->pm_schedule_id) {
                try {
                    app(\App\Services\GeneratePMScheduleService::class)->checkAndAdvance();
                } catch (\Exception $e) {
                    Log::warning("PM auto-advance failed after completing {$trackingRequest->request_number}: {$e->getMessage()}");
                }
            }

            return response()->json([
                'success' => true, 
                'message' => 'Maintenance record updated successfully',
                'redirect' => route('maintenance.edit', $id)
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
        if (empty($base64Data) || !str_contains($base64Data, 'data:image')) {
            return null;
        }

        try {
            $image = str_replace('data:image/png;base64,', '', $base64Data);
            $image = str_replace(' ', '+', $image); // Resolve common spacing/plus urlencoding issue
            // Bulletproof sanitization without class dependencies to prevent path traversal (removes slashes, dots, etc.)
            $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '', str_replace(' ', '_', $name));
            if (empty($safeName)) {
                $safeName = 'signature';
            }
            $filename = $type . '_' . $safeName . '_' . time() . '.png';
            $filepath = 'signatures/' . $filename;
            
            // Save to storage/app/public (served via storage symlink)
            Storage::disk('public')->put($filepath, base64_decode($image));
            
            return $filepath;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Signature save failed: ' . $e->getMessage());
            return null;
        }
    }

    private function generateRequestNumber($type)
    {
        $prefix = $type === 'ICT' ? 'REQ' : 'PM';
        $year = date('Y');

        $user = \Illuminate\Support\Facades\Auth::user();
        $region = strtoupper($user->region ?? 'SYS');
        $branchCode = strtoupper($user->branch ?? 'SYS');
        $branchCode = preg_replace('/[^A-Z0-9]/', '', $branchCode);

        // Use pessimistic locking inside a transaction to prevent race conditions
        // where two simultaneous requests could get the same request number.
        return DB::transaction(function () use ($prefix, $year, $region, $branchCode) {
            $searchPrefix = "{$prefix}-{$region}-{$branchCode}-{$year}";
            
            $lastRequest = RequestModel::where('request_number', 'like', "{$searchPrefix}-%")
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            if ($lastRequest) {
                $parts = explode('-', $lastRequest->request_number);
                $lastNumber = intval(end($parts));
                $number = $lastNumber + 1;
            } else {
                $number = 1;
            }

            return sprintf("%s-%s-%s-%s-%03d", $prefix, $region, $branchCode, $year, $number);
        });
    }

    public function downloadPdf($id)
    {
        $trackingRequest = RequestModel::findOrFail($id);
 
        $this->checkTicketAccess($trackingRequest);
 
        $maintenance = PreventiveMaintenance::findOrFail($trackingRequest->detail_id);
        $tasks = json_decode($maintenance->maintenance_tasks_json ?? '{}', true) ?: [];

        $pdf = Pdf::loadView('pdf.maintenance-form', [
            'request' => $trackingRequest,
            'pm' => $maintenance,
            'tasks' => $tasks,
        ])->setPaper('legal', 'portrait');

        if (ob_get_length()) {
            ob_end_clean();
        }
        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="PM-' . $trackingRequest->request_number . '.pdf"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Whitelist request input by role (store + end-user update).
     */
    private function maintenanceBaseKeys(): array
    {
        return [
            // Technician section
            'technician_name', 'technicianName',
            'technician_date', 'technicianDate',
            'problem_description', 'problemDescription',
            'diagnosis',
            'technicianSignature', 'technician_signature',

            // End-user section
            'end_user_name', 'endUserName',
            'end_user_printed_name', 'endUserPrintedName',
            'end_user_division', 'endUserDivision',
            'end_user_signature_date', 'endUserSignatureDate',
            'endUserSignature', 'end_user_signature',
            'end_user_remarks', 'endUserRemarks',

            // Recommendations
            'disposal_reason', 'disposalReason',
            'repair_parts', 'repairParts',
            'for_disposal', 'forDisposal',
            'for_repair', 'forRepair',

            // Desktop specs (camelCase from form)
            'desktopBrand', 'desktopModel', 'desktopPno', 'computerName',
            'monitor1Pno', 'monitor1Brand', 'monitor1Model',
            'monitor2Pno', 'monitor2Brand', 'monitor2Model',
            'printer1Pno', 'printer1Brand', 'printer1Model',
            'printer2Pno', 'printer2Brand', 'printer2Model',
            'upsPno', 'upsBrand', 'upsModel',
            'scannerPno', 'scannerBrand', 'scannerModel',
            'laptopPno', 'laptopBrand', 'laptopModel', 'laptopComputerName',
            'webcamBrand', 'webcamModel', 'webcamPno',
            'speakersBrand', 'speakersModel', 'speakersPno',
            'earphoneBrand', 'earphoneModel',
            'otherModelPno', 'other_equipment_model_pno',

            // Desktop specs (short form from form)
            'dtCpu', 'dtRam', 'dtGpu', 'dtOs', 'dtHd1', 'dtHd2', 'dtOffice', 'dtYear',

            // Laptop specs (short form from form)
            'ltCpu', 'ltRam', 'ltGpu', 'ltOs', 'ltHd1', 'ltHd2', 'ltOffice',

            // Printer/earphone specials
            'p1Inkjet', 'p1Laserjet', 'p2Inkjet', 'p2Laserjet',
            'earphoneSpecs', 'earphone_specs',

            // Legacy/snake_case variants for backward compat
            'desktop_brand', 'desktop_model', 'desktop_pno', 'desktop_computer_name',
            'monitor1_pno', 'monitor1_brand', 'monitor1_model',
            'monitor2_pno', 'monitor2_brand', 'monitor2_model',
            'printer1_pno', 'printer1_brand', 'printer1_model',
            'printer2_pno', 'printer2_brand', 'printer2_model',
            'ups_pno', 'ups_brand', 'ups_model',
            'scanner_pno', 'scanner_brand', 'scanner_model',
            'laptop_pno', 'laptop_brand', 'laptop_model', 'laptop_computer_name',
            'webcam_brand', 'webcam_model', 'webcam_pno',
            'speakers_brand', 'speakers_model', 'speakers_pno',
            'earphone_brand', 'earphone_model',
            'desktop_cpu', 'desktop_ram', 'desktop_gpu', 'desktop_os',
            'desktop_hd1', 'desktop_hd2', 'desktop_office', 'desktop_year_purchased',
            'laptop_cpu', 'laptop_ram', 'laptop_gpu', 'laptop_os',
            'laptop_hd1', 'laptop_hd2', 'laptop_office', 'laptop_year_purchased',
            'dt_cpu', 'dt_ram', 'dt_gpu', 'dt_os', 'dt_hd1', 'dt_hd2', 'dt_office', 'dt_year',
            'lt_cpu', 'lt_ram', 'lt_gpu', 'lt_os', 'lt_hd1', 'lt_hd2', 'lt_office', 'lt_year',

            // System / linking
            'linked_asset_id', 'last_updated_at',
        ];
    }

    private function extractTaskFieldNames(Request $request): array
    {
        $checklistKeywords = ['Cleanup', 'Backup', 'Restore', 'Update', 'Temp', 'Recycle', 'Defrag', 'CheckDisk', 'Scan', 'Virus', 'Defender', 'Startup', 'Level', 'Quality', 'Toner', 'Updated', 'Charging', 'Overload'];
        $taskKeys = [];
        foreach ($request->all() as $key => $value) {
            foreach ($checklistKeywords as $kw) {
                if (str_contains(strtolower($key), strtolower($kw))) {
                    $taskKeys[] = $key;
                    break;
                }
            }
        }
        return $taskKeys;
    }

    private function filterMaintenanceInput(Request $request): array
    {
        $user = Auth::user();

        if ($user->role === 'user') {
            return $request->only([
                'end_user_name', 'endUserName',
                'end_user_division', 'endUserDivision',
                'end_user_printed_name', 'endUserPrintedName',
                'end_user_signature_date', 'endUserSignatureDate',
                'endUserSignature', 'end_user_signature',
            ]);
        }

        // IT and super_admin: whitelist of known fields + dynamically detected task fields
        return $request->only(array_merge(
            $this->maintenanceBaseKeys(),
            $this->extractTaskFieldNames($request)
        ));
    }

    /**
     * Remove admin-only columns if any slipped through mapping.
     */
    private function stripMaintenanceAdminFields(array $mappedData): array
    {
        $adminKeys = [
            'technician_name', 'technician_date', 'problem_description', 'diagnosis',
            'for_disposal', 'disposal_reason', 'for_repair', 'repair_parts',
            'desktop_brand', 'desktop_model', 'desktop_pno', 'desktop_computer_name',
            'monitor1_pno', 'monitor1_brand', 'monitor1_model',
            'monitor2_pno', 'monitor2_brand', 'monitor2_model',
            'printer1_pno', 'printer1_brand', 'printer1_model', 'printer1_type',
            'printer2_pno', 'printer2_brand', 'printer2_model', 'printer2_type',
            'ups_pno', 'ups_brand', 'ups_model',
            'scanner_pno', 'scanner_brand', 'scanner_model',
            'laptop_pno', 'laptop_brand', 'laptop_model', 'laptop_computer_name',
            'webcam_brand', 'webcam_model', 'webcam_pno',
            'speakers_brand', 'speakers_model', 'speakers_pno',
            'earphone_brand', 'earphone_model', 'earphone_brand_model',
            'other_equipment_model_pno',
            'desktop_cpu', 'desktop_ram', 'desktop_gpu', 'desktop_os',
            'desktop_hd1', 'desktop_hd2', 'desktop_office', 'desktop_year_purchased',
            'laptop_cpu', 'laptop_ram', 'laptop_gpu', 'laptop_os',
            'laptop_hd1', 'laptop_hd2', 'laptop_office', 'laptop_year_purchased',
        ];

        return array_diff_key($mappedData, array_flip($adminKeys));
    }

    private function mapLegacyData($data)
    {
        $mapped = [];
        $mappings = [
            'technician_name' => ['technician_name', 'technicianName'],
            'technician_date' => ['technician_date', 'technicianDate'],
            'problem_description' => ['problem_description', 'problemDescription'],
            'diagnosis' => ['diagnosis'],
            'end_user_name' => ['end_user_name', 'endUserName'],
            'end_user_printed_name' => ['end_user_printed_name', 'endUserPrintedName'],
            'end_user_division' => ['end_user_division', 'endUserDivision'],
            'end_user_signature_date' => ['end_user_signature_date', 'endUserSignatureDate'],
            'disposal_reason' => ['disposal_reason', 'disposalReason'],
            'repair_parts' => ['repair_parts', 'repairParts'],
            
            // Device Info
            'desktop_brand' => ['desktop_brand', 'desktopBrand'],
            'desktop_model' => ['desktop_model', 'desktopModel'],
            'desktop_pno' => ['desktop_pno', 'desktopPno'],
            'desktop_computer_name' => ['desktop_computer_name', 'computerName'],
            
            'monitor1_pno' => ['monitor1_pno', 'monitor1Pno'],
            'monitor1_brand' => ['monitor1_brand', 'monitor1Brand'],
            'monitor1_model' => ['monitor1_model', 'monitor1Model'],
            'monitor2_pno' => ['monitor2_pno', 'monitor2Pno'],
            'monitor2_brand' => ['monitor2_brand', 'monitor2Brand'],
            'monitor2_model' => ['monitor2_model', 'monitor2Model'],
            
            'printer1_pno' => ['printer1_pno', 'printer1Pno'],
            'printer1_brand' => ['printer1_brand', 'printer1Brand'],
            'printer1_model' => ['printer1_model', 'printer1Model'],
            
            'printer2_pno' => ['printer2_pno', 'printer2Pno'],
            'printer2_brand' => ['printer2_brand', 'printer2Brand'],
            'printer2_model' => ['printer2_model', 'printer2Model'],
            
            'ups_pno' => ['ups_pno', 'upsPno'],
            'ups_brand' => ['ups_brand', 'upsBrand'],
            'ups_model' => ['ups_model', 'upsModel'],
            
            'scanner_pno' => ['scanner_pno', 'scannerPno'],
            'scanner_brand' => ['scanner_brand', 'scannerBrand'],
            'scanner_model' => ['scanner_model', 'scannerModel'],
            
            'laptop_pno' => ['laptop_pno', 'laptopPno'],
            'laptop_brand' => ['laptop_brand', 'laptopBrand'],
            'laptop_model' => ['laptop_model', 'laptopModel'],
            'laptop_computer_name' => ['laptop_computer_name', 'laptopComputerName'],
            
            'webcam_brand' => ['webcam_brand', 'webcamBrand'],
            'webcam_model' => ['webcam_model', 'webcamModel'],
            'webcam_pno' => ['webcam_pno', 'webcamPno'],
            
            'speakers_brand' => ['speakers_brand', 'speakersBrand'],
            'speakers_model' => ['speakers_model', 'speakersModel'],
            'speakers_pno' => ['speakers_pno', 'speakersPno'],
            
            'earphone_brand' => ['earphone_brand', 'earphoneBrand'],
            'earphone_model' => ['earphone_model', 'earphoneModel'],
            
            'other_equipment_model_pno' => ['other_equipment_model_pno', 'otherModelPno', 'other_model_pno'],
            
            // Device Specs
            'desktop_cpu' => ['desktop_cpu', 'dt_cpu', 'dtCpu'],
            'desktop_ram' => ['desktop_ram', 'dt_ram', 'dtRam'],
            'desktop_gpu' => ['desktop_gpu', 'dt_gpu', 'dtGpu'],
            'desktop_os' => ['desktop_os', 'dt_os', 'dtOs'],
            'desktop_hd1' => ['desktop_hd1', 'dt_hd1', 'dtHd1'],
            'desktop_hd2' => ['desktop_hd2', 'dt_hd2', 'dtHd2'],
            'desktop_office' => ['desktop_office', 'dt_office', 'dtOffice'],
            'desktop_year_purchased' => ['desktop_year_purchased', 'dt_year', 'dtYear'],
            
            'laptop_cpu' => ['laptop_cpu', 'lt_cpu', 'ltCpu'],
            'laptop_ram' => ['laptop_ram', 'lt_ram', 'ltRam'],
            'laptop_gpu' => ['laptop_gpu', 'lt_gpu', 'ltGpu'],
            'laptop_os' => ['laptop_os', 'lt_os', 'ltOs'],
            'laptop_hd1' => ['laptop_hd1', 'lt_hd1', 'ltHd1'],
            'laptop_hd2' => ['laptop_hd2', 'lt_hd2', 'ltHd2'],
            'laptop_office' => ['laptop_office', 'lt_office', 'ltOffice'],
            'laptop_year_purchased' => ['laptop_year_purchased', 'lt_year', 'ltYear'],
        ];

        foreach ($mappings as $dbField => $inputKeys) {
            foreach ($inputKeys as $key) {
                if (array_key_exists($key, $data)) {
                    $mapped[$dbField] = $data[$key];
                    break;
                }
            }
        }

        // Special handling for legacy/checkbox custom mappings:
        if (array_key_exists('earphone_specs', $data) || array_key_exists('earphoneSpecs', $data)) {
            $mapped['earphone_brand_model'] = $data['earphone_specs'] ?? $data['earphoneSpecs'] ?? null;
        }

        if (isset($data['p1_inkjet']) || isset($data['p1_laserjet']) || isset($data['p1Inkjet']) || isset($data['p1Laserjet'])) {
            $mapped['printer1_type'] = (isset($data['p1_inkjet']) || isset($data['p1Inkjet'])) ? 'inkjet' : 'laserjet';
        }
        if (isset($data['p2_inkjet']) || isset($data['p2_laserjet']) || isset($data['p2Inkjet']) || isset($data['p2Laserjet'])) {
            $mapped['printer2_type'] = (isset($data['p2_inkjet']) || isset($data['p2Inkjet'])) ? 'inkjet' : 'laserjet';
        }

        if (array_key_exists('for_disposal', $data) || array_key_exists('forDisposal', $data)) {
            $mapped['for_disposal'] = (isset($data['for_disposal']) || isset($data['forDisposal'])) ? 'YES' : 'NO';
        }
        if (array_key_exists('for_repair', $data) || array_key_exists('forRepair', $data)) {
            $mapped['for_repair'] = (isset($data['for_repair']) || isset($data['forRepair'])) ? 'YES' : 'NO';
        }

        return $mapped;
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

        $data = array_merge([
            'request'    => $trackingRequest,
            'maintenance' => $maintenance,
            'myAssets'   => $myAssets,
            'linkedPmAsset' => $linkedPmAsset,
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
