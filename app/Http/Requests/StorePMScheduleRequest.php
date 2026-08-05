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
        return [
            'schedule_name'      => 'required|string|max:255|unique:pm_schedules',
            'division_filter'    => 'nullable|string|max:50',
            'frequency'          => 'required|in:Monthly,Quarterly,Semi-annual,Annual',
            'next_scheduled_date'=> 'required|date|after:today',
        ];
    }

    public function messages(): array
    {
        return [
            'next_scheduled_date.required' => 'The start date is required.',
            'next_scheduled_date.after'    => 'The start date must be a future date.',
        ];
    }

    protected function passedValidation(): void
    {
        // Reject weekends (Saturday = 6, Sunday = 0)
        $date = \Carbon\Carbon::parse($this->next_scheduled_date);
        if ($date->isWeekend()) {
            $dayName = $date->format('l');
            throw \Illuminate\Validation\ValidationException::withMessages([
                'next_scheduled_date' => "The start date cannot be a {$dayName}. Please choose a weekday (Monday to Friday).",
            ]);
        }
    }
}
