<?php

namespace App\Actions\ICT;

use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use App\Models\AuditLog;
use App\Http\Requests\UpdateIctRequest;
use App\Services\RequestNotificationService;
use App\Support\RequestHelpers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResubmitIctTicketAction
{
    /**
     * Resubmit a rejected ICT request ticket (user flow).
     *
     * @param  \App\Http\Requests\UpdateIctRequest  $request
     * @param  \App\Models\Request  $trackingRequest
     * @param  \App\Models\RepairRequest  $repairRequest
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(UpdateIctRequest $request, $trackingRequest, $repairRequest, $user)
    {
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

        $savedSigFiles = [];

        if (isset($mappedData['end_user_signature']) && str_contains($mappedData['end_user_signature'], 'data:image')) {
            $mappedData['end_user_signature'] = RequestHelpers::saveSignature(
                $mappedData['end_user_signature'], 'ict_enduser',
                ($mappedData['end_user_first_name'] ?? 'User') . '_' . ($mappedData['end_user_last_name'] ?? 'Request')
            );
            $savedSigFiles[] = $mappedData['end_user_signature'];
        }

        try {
            DB::beginTransaction();

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
            RequestNotificationService::notifyAdminsOfNewRequest(
                $trackingRequest, $user, RequestNotificationService::typeLabel($trackingRequest->type)
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Request resubmitted successfully.',
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
