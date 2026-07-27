<?php

namespace App\Actions\ICT;

use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use App\Models\AuditLog;
use App\Http\Requests\UpdateIctRequest;
use App\Support\RequestAuthorization;
use App\Support\RequestHelpers;
use App\Services\RequestNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TechnicianUpdateIctTicketAction
{
    /**
     * Handle IT/SuperAdmin updating a ICT request ticket (Flow 3).
     *
     * @param  \App\Http\Requests\UpdateIctRequest  $request
     * @param  \App\Models\Request  $trackingRequest
     * @param  \App\Models\RepairRequest  $repairRequest
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(UpdateIctRequest $request, $trackingRequest, $repairRequest, $user)
    {
        try {
            DB::beginTransaction();

            $data = $request->only(RequestAuthorization::ictTechnicianFieldKeys());

            $mappedData = $this->mapLegacyData($data);
            $oldRepairTypes = json_decode($repairRequest->repair_type ?? '[]', true) ?: [];
            $hadItSignatureBefore = !empty($repairRequest->it_personnel_signature);

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
                    $mappedData[$field] = RequestHelpers::saveSignature($mappedData[$field], $prefix, 'Update');
                    $savedSigFiles[] = $mappedData[$field];
                }
            }

            $repairRequest->update($mappedData);

            $oldStatus = $trackingRequest->status;
            $newStatus = $oldStatus;

            // IT-specific status transitions
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
            } elseif (!empty($mappedData['after_repair_status']) && $mappedData['after_repair_status'] === 'FOR REPAIR') {
                // FOR REPAIR: keep the asset assigned to user, just update status
                $asset = $trackingRequest->linkedAsset;
                if ($asset && $asset->status !== \App\Enums\AssetStatus::FOR_REPAIR) {
                    $asset->status = \App\Enums\AssetStatus::FOR_REPAIR;
                    // Keep assigned_to_user intact - asset is still with the user
                    $asset->save();

                    \App\Models\InventoryHistory::create([
                        'asset_id' => $asset->asset_id,
                        'action' => 'IT Marked For Repair',
                        'previous_user_id' => $asset->assigned_to_user,
                        'new_user_id' => $asset->assigned_to_user,
                        'performed_by' => $user->id,
                        'remarks' => "Asset marked for repair via ICT Request form {$trackingRequest->request_number}. Asset remains with user.",
                    ]);

                    AuditLog::log(
                        "Marked Asset For Repair",
                        "Inventory",
                        "Marked asset {$asset->property_number} for repair via ICT request {$trackingRequest->request_number}",
                        $trackingRequest->office
                    );
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

            // User acceptance check (skipped for IT role — only applies when user signs)
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

            $redirect = route('ict.edit', $trackingRequest->id);
            $message = 'ICT Request updated successfully';
            $printUrl = null;

            if (($mappedData['after_repair_status'] ?? '') === 'FOR DISPOSAL') {
                $redirect = route('ict.edit', $trackingRequest->id);
                $printUrl = route('ict.disposal-tag', $trackingRequest->id);
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
