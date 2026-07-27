<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRequisitionRequest extends FormRequest
{
    // Authorization (canProcessSupply + canSupplyManageRequisition) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from RequisitionController::review() lines 217-220.
        return [
            'action'  => 'required|in:approve,reject,issue',
            'remarks' => 'nullable|string|max:2000',
        ];
    }
}
