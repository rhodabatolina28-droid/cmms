<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePMScheduleRequest extends FormRequest
{
    // Authorization (super_admin role) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from PMScheduleController::store() lines 174-178.
        return [
            'schedule_name'   => 'required|string|max:255|unique:pm_schedules',
            'division_filter' => 'nullable|string|max:50',
            'frequency'       => 'required|in:Monthly,Quarterly,Semi-annual,Annual',
        ];
    }
}
