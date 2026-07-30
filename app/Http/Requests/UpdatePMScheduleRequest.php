<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePMScheduleRequest extends FormRequest
{
    // Authorization (super_admin role) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from PMScheduleController::update() lines 317-321.
        // The unique ignore uses route-model binding to replicate the exact inline behavior.
        $pmSchedule = $this->route('pm_schedule');

        return [
            'schedule_name'   => 'required|string|max:255|unique:pm_schedules,schedule_name,' . $pmSchedule->id,
            'division_filter' => 'nullable|string|max:50',
            'frequency'       => 'required|in:Monthly,Quarterly,Semi-annual,Annual',
        ];
    }
}
