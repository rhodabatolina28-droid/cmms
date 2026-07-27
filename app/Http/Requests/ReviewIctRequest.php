<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewIctRequest extends FormRequest
{
    // Authorization (isDivisionAdmin check + scope check) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from ICTRequestController::review() lines 372-375.
        return [
            'status' => 'required|in:Approved,Rejected',
            'notes'  => 'nullable|string|max:1000',
        ];
    }
}
