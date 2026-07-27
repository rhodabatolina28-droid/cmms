<?php

namespace App\Actions\ICT;

use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use App\Models\AuditLog;
use App\Http\Requests\StoreLinkedAssetRequest;
use App\Support\RequestAuthorization;
use App\Services\RequestNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CreateIctTicketAction
{
    /**
     * Create a new ICT request ticket.
     *
     * @param  \App\Http\Requests\StoreLinkedAssetRequest  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(StoreLinkedAssetRequest $request, $user)
    {
        if (!RequestAuthorization::canCreateIctTicket($user)) {
            return response()->json(['success' => false, 'message' => 'Only end-users can create ICT requests.'], 403);
        }

        if ($assetError = RequestAuthorization::linkedAssetValidationError($user, $request->input('linked_asset_id'))) {
            return response()->json(['success' => false, 'message' => $assetError], 422);
        }

        // Prevent duplicate ICT requests for the same asset if there's an active/open ticket
        $existingRequest = RequestModel::where('linked_asset_id', $request->input('linked_asset_id'))
            ->where('type', 'ICT')
            ->whereIn('status', ['Pending', 'Ongoing', 'Awaiting Signature', 'Referred External'])
            ->exists();

        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'This asset already has an active ICT repair request. Please wait for the current request to be completed before submitting a new one.'
            ], 422);
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
                $mappedData['end_user_signature'] = \App\Support\RequestHelpers::saveSignature(
                    $mappedData['end_user_signature'],
                    'ict_enduser',
                    ($mappedData['end_user_first_name'] ?? 'User') . '_' . ($mappedData['end_user_last_name'] ?? 'Request')
                );
                $savedSigFiles[] = $mappedData['end_user_signature'];
            }

            // Create repair request
            $repairRequest = RepairRequest::create($mappedData);

            // Generate request number
            $requestNumber = \App\Support\RequestHelpers::generateRequestNumber('ICT');

            // Create tracking request
            $trackingRequest = RequestModel::create([
                'user_id' => Auth::id(),
                'request_number' => $requestNumber,
                'type' => 'ICT',
                'requestor_name' => ($mappedData['end_user_first_name'] ?? '') . ' ' . ($mappedData['end_user_last_name'] ?? ''),
                'description' => $mappedData['repair_description'] ?? '',
                'branch' => $user->branch,
                'region' => $user->region,
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
                "Created new ICT request {$requestNumber} for " . $user->full_name,
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
            'cost' => (isset($data['repairCost']) && $data['repairCost'] !== '')
                ? (float) $data['repairCost']
                : ((isset($data['cost']) && $data['cost'] !== '')
                    ? (float) $data['cost']
                    : null),
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
}