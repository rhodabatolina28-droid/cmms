<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIctStatusRequest extends FormRequest
{
    // Authorization is handled in the controller (role check before this runs).
    // Safe Default Rule: return true so no accidental lockouts occur.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from ICTRequestController::updateStatus() lines 94-98.
        // No rules changed, no logic added.
        return [
            'id'      => 'required|exists:requests,id',
            'status'  => 'required|string|in:Pending,Ongoing,Completed,Rejected,Cancelled',
            'remarks' => 'nullable|string|max:2000',
        ];
    }
}
