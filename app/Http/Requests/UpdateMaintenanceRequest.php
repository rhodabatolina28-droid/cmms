<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaintenanceRequest extends FormRequest
{
    // Authorization (canUpdateMaintenanceTicket + status checks) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from MaintenanceController::update() lines 410-428.
        return [
            'technicianSignature'  => 'nullable|string',
            'technician_signature' => 'nullable|string',
            'endUserSignature'     => 'nullable|string',
            'end_user_signature'   => 'nullable|string',
            'technician_name'      => 'nullable|string|max:255',
            'technicianName'       => 'nullable|string|max:255',
            'end_user_name'        => 'nullable|string|max:255',
            'endUserName'          => 'nullable|string|max:255',
            'technician_date'      => 'nullable|date',
            'technicianDate'       => 'nullable|date',
            'end_user_date'        => 'nullable|date',
            'endUserDate'          => 'nullable|date',
            'end_user_remarks'     => 'nullable|string|max:2000',
            'endUserRemarks'       => 'nullable|string|max:2000',
            'linked_asset_id'      => 'nullable|integer|exists:inventory_assets,asset_id',
            'disposal_asset_id'    => 'nullable|integer|exists:inventory_assets,asset_id',
            'last_updated_at'      => 'nullable|string|max:50',
        ];
    }
}
