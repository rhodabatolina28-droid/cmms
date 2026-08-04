<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePMGenerationScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pm_schedule_id' => 'required|exists:pm_schedules,id',
            'scheduled_date' => 'required|date|after:today',
            'remarks'        => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'pm_schedule_id.required' => 'A PM schedule must be selected.',
            'pm_schedule_id.exists'   => 'The selected PM schedule does not exist.',
            'scheduled_date.required' => 'A scheduled date is required.',
            'scheduled_date.date'     => 'The scheduled date must be a valid date.',
            'scheduled_date.after'    => 'The scheduled date must be in the future.',
        ];
    }
}