<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignItRequest extends FormRequest
{
    // Authorization is handled in the controller before this Form Request fires.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from ICTRequestController::assignIt() line 277-279
        // and MaintenanceController::assignIt() line 320-322.
        // Both controllers use the identical single-field rule.
        return [
            'assigned_to' => 'nullable|exists:users,id',
        ];
    }
}
