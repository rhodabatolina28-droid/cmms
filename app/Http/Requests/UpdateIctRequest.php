<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIctRequest extends FormRequest
{
    // Authorization (canUpdateIctTicket + status checks) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from ICTRequestController::update() lines 585-674.
        // No rules changed, no fields added or removed.
        return [
            'endUserLastName'                   => 'nullable|string|max:255',
            'end_user_last_name'                => 'nullable|string|max:255',
            'endUserFirstName'                  => 'nullable|string|max:255',
            'end_user_first_name'               => 'nullable|string|max:255',
            'endUserMiddleName'                 => 'nullable|string|max:255',
            'end_user_middle_name'              => 'nullable|string|max:255',
            'endUserSex'                        => 'nullable|string|max:10',
            'sex'                               => 'nullable|string|max:10',
            'divisionOffice'                    => 'nullable|string|max:255',
            'division'                          => 'nullable|string|max:255',
            'endUserEmail'                      => 'nullable|email|max:255',
            'email'                             => 'nullable|email|max:255',
            'employeeNo'                        => 'nullable|string|max:100',
            'employee_no'                       => 'nullable|string|max:100',
            'repairDescription'                 => 'nullable|string|max:5000',
            'description'                       => 'nullable|string|max:5000',
            'endUserSignature'                  => 'nullable|string',
            'end_user_signature'                => 'nullable|string',
            'endUserPrintedName'                => 'nullable|string|max:255',
            'end_user_printed_name'             => 'nullable|string|max:255',
            'endUserDate'                       => 'nullable|date',
            'date_requested'                    => 'nullable|date',
            'linked_asset_id'                   => 'nullable|integer|exists:inventory_assets,asset_id',
            'last_updated_at'                   => 'nullable|string|max:50',
            'endUserAcceptanceSignature'        => 'nullable|string',
            'end_user_acceptance_signature'     => 'nullable|string',
            'endUserAcceptancePrintedName'      => 'nullable|string|max:255',
            'end_user_acceptance_printed_name'  => 'nullable|string|max:255',
            'endUserAcceptanceDate'             => 'nullable|date',
            'end_user_acceptance_date'          => 'nullable|date',
            // IT / technician fields
            'itReceivedLastName'                => 'nullable|string|max:255',
            'it_received_last_name'             => 'nullable|string|max:255',
            'itReceivedFirstName'               => 'nullable|string|max:255',
            'it_received_first_name'            => 'nullable|string|max:255',
            'itReceivedMiddleName'              => 'nullable|string|max:255',
            'it_received_middle_name'           => 'nullable|string|max:255',
            'initialDiagnosis'                  => 'nullable|string|max:5000',
            'initial_diagnosis'                 => 'nullable|string|max:5000',
            'repairType'                        => 'nullable',
            'repair_type'                       => 'nullable',
            'itRemarks'                         => 'nullable|string|max:5000',
            'it_remarks'                        => 'nullable|string|max:5000',
            'technicianSignature'               => 'nullable|string',
            'technician_signature'              => 'nullable|string',
            'technicianPrintedName'             => 'nullable|string|max:255',
            'technician_printed_name'           => 'nullable|string|max:255',
            'technicianDate'                    => 'nullable|date',
            'technician_date'                   => 'nullable|date',
            'itPersonnelSignature'              => 'nullable|string',
            'it_personnel_signature'            => 'nullable|string',
            'itPersonnelPrintedName'            => 'nullable|string|max:255',
            'it_personnel_printed_name'         => 'nullable|string|max:255',
            'itPersonnelDate'                   => 'nullable|date',
            'it_personnel_date'                 => 'nullable|date',
            'afterRepairStatus'                 => 'nullable|string|max:100',
            'after_repair_status'               => 'nullable|string|max:100',
            'findingsRemarks'                   => 'nullable|string|max:5000',
            'findings_remarks'                  => 'nullable|string|max:5000',
            'serviceRequestNo'                  => 'nullable|string|max:100',
            'service_request_no'               => 'nullable|string|max:100',
            'rid'                               => 'nullable|string|max:100',
            'dateReceived'                      => 'nullable|date',
            'date_received'                     => 'nullable|date',
            'serviceScheduleDate'               => 'nullable|date',
            'service_schedule_date'             => 'nullable|date',
            'propertyNo'                        => 'nullable|string|max:100',
            'property_no'                       => 'nullable|string|max:100',
            'articleSerialNo'                   => 'nullable|string|max:100',
            'article_serial_no'                 => 'nullable|string|max:100',
            'companyName'                       => 'nullable|string|max:255',
            'company_name'                      => 'nullable|string|max:255',
            'companyPhone'                      => 'nullable|string|max:100',
            'company_phone'                     => 'nullable|string|max:100',
            'companyEmail'                      => 'nullable|email|max:255',
            'company_email'                     => 'nullable|email|max:255',
            'companyAddress'                    => 'nullable|string|max:500',
            'company_address'                   => 'nullable|string|max:500',
            'actionTaken'                       => 'nullable|string|max:5000',
            'action_taken'                      => 'nullable|string|max:5000',
            'technicianLastName'                => 'nullable|string|max:255',
            'technician_last_name'              => 'nullable|string|max:255',
            'technicianFirstName'               => 'nullable|string|max:255',
            'technician_first_name'             => 'nullable|string|max:255',
            'technicianMiddleName'              => 'nullable|string|max:255',
            'technician_middle_name'            => 'nullable|string|max:255',
            'afterServiceDate'                  => 'nullable|date',
            'after_service_date'                => 'nullable|date',
        ];
    }
}
