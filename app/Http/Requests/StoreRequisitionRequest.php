<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequisitionRequest extends FormRequest
{
    // Authorization (role check + canItSubmitForTicket) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from RequisitionController::store() lines 153-160.
        return [
            'items'                => 'required|array|min:1',
            'items.*.description'  => 'required|string|max:500',
            'items.*.quantity'     => 'required|integer|min:1',
            'remarks'              => 'nullable|string|max:2000',
            'set_awaiting_parts'   => 'nullable|boolean',
            'submission_id'        => 'nullable|string|max:64',
        ];
    }
}
