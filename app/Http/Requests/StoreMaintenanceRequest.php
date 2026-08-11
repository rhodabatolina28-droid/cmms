<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['user', 'it', 'admin', 'super_admin']);
    }

    public function rules(): array
    {
        return [
            // End User (Required)
            'endUserName' => 'required|string|max:200',
            'endUserSignature' => 'required|string|max:200000',
            'endUserDate' => 'nullable|date_format:Y-m-d',

            // Technician (Optional)
            'technicianName' => 'nullable|string|max:200',
            'technicianSignature' => 'nullable|string|max:200000',
            'technicianDate' => 'nullable|date_format:Y-m-d',
            'problemDescription' => 'nullable|string|max:1000',
            'diagnosis' => 'nullable|string|max:1000',

            // Location
            'endUserFloor' => 'nullable|string|max:100',
            'endUserDivision' => 'nullable|string|max:200',

            // Recommendations
            'forDisposal' => 'nullable|boolean',
            'disposalReason' => 'nullable|string|max:500',
            'forRepair' => 'nullable|boolean',
            'repairParts' => 'nullable|string|max:500',

            // Device Info
            'desktopBrand' => 'nullable|string|max:100',
            'desktopModel' => 'nullable|string|max:100',
            'desktopYearPurchased' => 'nullable|digits:4|min:2000',
            'laptopBrand' => 'nullable|string|max:100',
            'laptopYearPurchased' => 'nullable|digits:4|min:2000',

            // Dates
            'maintenanceDate' => 'nullable|date_format:Y-m-d',
            'dateReceived' => 'nullable|date_format:Y-m-d',
            'serviceScheduleDate' => 'nullable|date_format:Y-m-d',

            // Maintenance Tasks
            'desktopCaseCleanup' => 'nullable|string',
            'desktopDataBackup' => 'nullable|string',
            'laptopWindowsUpdate' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'endUserName.required' => 'End user name is required',
            'endUserSignature.required' => 'End user signature is required',
            'desktopYearPurchased.digits' => 'Year purchased must be a valid 4-digit year',
        ];
    }
}
