<?php

namespace App\Actions\Maintenance;

use App\Models\AuditLog;
use App\Models\PreventiveMaintenance;
use App\Models\Request as RequestModel;
use App\Http\Requests\UpdateMaintenanceRequest;
use App\Services\GeneratePMScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateMaintenanceTicketAction
{
    /**
     * Update an existing Preventive Maintenance request ticket.
     *
     * @param  \App\Http\Requests\UpdateMaintenanceRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(UpdateMaintenanceRequest $request, $id)
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

            if (!$user->can('updateMaintenance', $trackingRequest)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'You are not allowed to update this request.'], 403);
            }

            $data = $this->filterMaintenanceInput($request);

            $mappedData = $this->mapLegacyData($data);

            if (($mappedData['for_disposal'] ?? null) === 'YES') {
                $mappedData['disposal_asset_id'] = ($mappedData['disposal_asset_id'] ?? null)
                    ?: $maintenance->disposal_asset_id;

                if (empty($mappedData['disposal_asset_id'])) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Please select the specific asset to tag for disposal.',
                    ], 422);
                }
            }

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

            // DISPOSAL LOGIC: If for_disposal is checked and an asset is selected, mark it as For Disposal
            if (!empty($mappedData['for_disposal']) && $mappedData['for_disposal'] === 'YES') {
                $disposalAssetId = $mappedData['disposal_asset_id'] ?? null;

                if ($disposalAssetId) {
                    $disposalAsset = \App\Models\InventoryAsset::find($disposalAssetId);

                    if ($disposalAsset && $disposalAsset->status !== 'Scrapped') {
                        // Remove user assignment since asset is being turned over to Supply Officer
                        $previousUserId = $disposalAsset->assigned_to_user;
                        $disposalAsset->status = 'For Disposal';
                        $disposalAsset->assigned_to_user = null;
                        $disposalAsset->save();

                        // Log to inventory history
                        \App\Models\InventoryHistory::create([
                            'asset_id' => $disposalAsset->asset_id,
                            'action' => 'IT Recommended For Disposal (PM)',
                            'performed_by' => $user->id,
                            'previous_user_id' => $previousUserId,
                            'new_user_id' => null,
                            'remarks' => "Asset recommended for disposal via PM request {$trackingRequest->request_number}. Assignment removed - turned over to Supply Officer.",
                        ]);

                        // Audit log
                        \App\Models\AuditLog::log(
                            "Recommended Asset For Disposal",
                            "Inventory",
                            "Recommended asset {$disposalAsset->property_number} for disposal via PM request {$trackingRequest->request_number}",
                            $trackingRequest->office
                        );

                        // Notify supply officer/admin about the disposal (only those with can_supply = true)
                        $admins = \App\Models\User::where('can_supply', true)
                            ->where('is_active', true)
                            ->get();
                        foreach ($admins as $admin) {
                            \App\Models\Notification::send(
                                $admin->id,
                                $trackingRequest->id,
                                'Asset Tagged for Disposal',
                                "Asset [{$disposalAsset->item_name} | SN: {$disposalAsset->serial_number}] marked for disposal via PM request {$trackingRequest->request_number}. Please process."
                            );
                        }
                    }
                }
            }

            // Automatic Status Logic
            $oldStatus = $trackingRequest->status;
            $newStatus = $oldStatus;

            if ($user->role === 'it' || $user->role === 'super_admin') {
                // Auto-assign to the user submitting if currently unassigned
                if (is_null($trackingRequest->assigned_to)) {
                    $trackingRequest->assigned_to = $user->id;
                    $trackingRequest->save(); // Persist the assignment to the database

                    // Also log the auto-assignment
                    \App\Models\AuditLog::log(
                        'Assigned PM Request',
                        'Requests',
                        "Auto-assigned {$trackingRequest->request_number} to " . ($user->role === 'super_admin' ? 'Super Admin' : 'IT') . " user #{$user->id} upon form submission",
                        $trackingRequest->office
                    );
                }

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
            if ($newStatus === RequestModel::STATUS_COMPLETED) {

                // --- Manual PM: single linked asset ---
                if ($trackingRequest->linked_asset_id) {
                    $asset = \App\Models\InventoryAsset::find($trackingRequest->linked_asset_id);
                    if ($asset) {
                        $asset->last_pm_date     = now();
                        $asset->next_pm_due_date = $this->resolveNextPmDate($trackingRequest);
                        $asset->save();
                    }
                }

                // --- Auto-generated (bundled) PM: update ALL active assets assigned to user ---
                // Auto-generated PMs have linked_asset_id = null because one PM covers all assets.
                // We query all active assets for the user and stamp them with PM dates.
                elseif ($trackingRequest->is_auto_generated && $trackingRequest->user_id) {
                    $nextDate   = $this->resolveNextPmDate($trackingRequest);
                    $userAssets = \App\Models\InventoryAsset::where('assigned_to_user', $trackingRequest->user_id)
                        ->where('status', 'Active')
                        ->get();

                    foreach ($userAssets as $asset) {
                        $asset->last_pm_date     = now();
                        $asset->next_pm_due_date = $nextDate;
                        $asset->save();
                    }
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
                    $schedule = \App\Models\PMSchedule::find($trackingRequest->pm_schedule_id);
                    if ($schedule) {
                        app(\App\Services\GeneratePMScheduleService::class)->checkAndAdvance($schedule);
                    }
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

    /**
     * Resolve next PM due date for a completed request.
     * Uses the schedule's frequency if available, falls back to 3 months.
     */
    private function resolveNextPmDate(\App\Models\Request $trackingRequest): string
    {
        if ($trackingRequest->pm_schedule_id) {
            $schedule = \App\Models\PMSchedule::find($trackingRequest->pm_schedule_id);
            if ($schedule && $schedule->is_active) {
                return $schedule->calculateNextDate();
            }
        }
        return now()->addMonths(3)->toDateString();
    }

    private function saveSignature($base64Data, $type, $name)
    {
        return \App\Support\RequestHelpers::saveSignature($base64Data, $type, $name);
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
            'disposal_asset_id', 'disposalAssetId',
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

        // Map disposal_asset_id directly
        if (array_key_exists('disposal_asset_id', $data)) {
            $mapped['disposal_asset_id'] = $data['disposal_asset_id'];
        } elseif (array_key_exists('disposalAssetId', $data)) {
            $mapped['disposal_asset_id'] = $data['disposalAssetId'];
        }

        return $mapped;
    }
}
