<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreICTRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['user', 'it', 'admin', 'super_admin']);
    }

    public function rules(): array
    {
        return [
            // End User Section (Required)
            'endUserLastName' => 'required|string|max:100',
            'endUserFirstName' => 'required|string|max:100',
            'endUserMiddleName' => 'nullable|string|max:100',
            'endUserSex' => 'required|in:MALE,FEMALE',
            'divisionOffice' => 'required|string|max:200',
            'endUserEmail' => 'required|email|max:255',
            'employeeNo' => 'required|string|max:50',
            'repairDescription' => 'required|string|max:1000',
            'endUserSignature' => 'required|string|max:200000',
            'endUserPrintedName' => 'nullable|string|max:200',
            'endUserDate' => 'required|date_format:Y-m-d',

            // IT Personnel Section (Optional)
            'itReceivedLastName' => 'nullable|string|max:100',
            'itReceivedFirstName' => 'nullable|string|max:100',
            'itReceivedMiddleName' => 'nullable|string|max:100',
            'initialDiagnosis' => 'nullable|string|max:1000',
            'repairType' => 'nullable|array',
            'repairType.*' => 'string',
            'itRemarks' => 'nullable|string|max:1000',

            // Service Provider Section (Optional)
            'companyName' => 'nullable|string|max:200',
            'companyPhone' => 'nullable|string|max:20',
            'companyEmail' => 'nullable|email|max:255',
            'companyAddress' => 'nullable|string|max:500',
            'technicianLastName' => 'nullable|string|max:100',
            'technicianFirstName' => 'nullable|string|max:100',
            'technicianSignature' => 'nullable|string|max:200000',
            'serviceDate' => 'nullable|date_format:Y-m-d',
            'pulloutDate' => 'nullable|date_format:Y-m-d',

            // Tracking Section (Optional)
            'dateReceived' => 'nullable|date_format:Y-m-d',
            'serviceScheduleDate' => 'nullable|date_format:Y-m-d',
        ];
    }

    public function messages(): array
    {
        return [
            'endUserLastName.required' => 'Last name is required',
            'endUserEmail.email' => 'Please enter a valid email address',
            'endUserDate.required' => 'End user date is required',
            'serviceDate.date_format' => 'Service date must be in YYYY-MM-DD format',
        ];
    }
}
