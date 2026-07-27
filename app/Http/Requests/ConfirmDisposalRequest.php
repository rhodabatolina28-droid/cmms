<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmDisposalRequest extends FormRequest
{
    // Authorization (canProcessSupply + scope + status checks) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from InventoryController::confirmDisposal() lines 533-535.
        return [
            'remarks' => 'nullable|string|max:500',
        ];
    }
}
