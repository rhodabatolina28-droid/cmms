<?php

namespace App\Actions\Maintenance;

use App\Models\AuditLog;
use App\Models\PreventiveMaintenance;
use App\Models\Request as RequestModel;
use App\Http\Requests\StoreLinkedAssetRequest;
use App\Support\RequestAuthorization;
use App\Support\RequestHelpers;
use App\Services\RequestNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CreateMaintenanceTicketAction
{
    /**
     * Create a new Preventive Maintenance request ticket.
     *
     * @param  \App\Http\Requests\StoreLinkedAssetRequest  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(StoreLinkedAssetRequest $request, $user)
    {
        if (!RequestAuthorization::canCreateMaintenanceTicket($user)) {
            return response()->json(['success' => false, 'message' => 'PM is now managed via schedules by your ICT Unit. Contact your Super Admin.'], 403);
        }

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

    private function extractTaskFieldNames(\Illuminate\Http\Request $request): array
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

    private function filterMaintenanceInput(\Illuminate\Http\Request $request): array
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

        // Map disposal_asset_id directly
        if (array_key_exists('disposal_asset_id', $data)) {
            $mapped['disposal_asset_id'] = $data['disposal_asset_id'];
        } elseif (array_key_exists('disposalAssetId', $data)) {
            $mapped['disposal_asset_id'] = $data['disposalAssetId'];
        }

        return $mapped;
    }

    private function saveSignature($base64Data, $type, $name)
    {
        return RequestHelpers::saveSignature($base64Data, $type, $name);
    }

    private function generateRequestNumber($type)
    {
        return RequestHelpers::generateRequestNumber($type);
    }
}
