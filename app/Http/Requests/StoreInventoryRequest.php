<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        // Only supply officers (can_supply), admins, and super_admins can create inventory items
        return auth()->check() && (
            in_array($user->role, ['admin', 'super_admin']) ||
            ($user->role === 'it' && $user->can_supply)
        );
    }

    public function rules(): array
    {
        return [
            'category' => 'required|string|max:100',
            'item_name' => 'required|string|max:200',
            'serial_number' => 'nullable|string|max:100|unique:inventory_assets,serial_number',
            'property_number' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'specifications' => 'nullable|string',
            'assigned_to_user' => 'nullable|exists:users,id',
            'region' => 'nullable|string|max:50',
            'branch' => 'nullable|string|max:255',
            'office' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            // Scrapped and For Disposal are system-only statuses (set by repair/disposal workflow)
            'status' => 'required|in:Active,Spare,Defective,For Repair',
            'date_acquired' => 'nullable|date',
            'warranty_expiration' => 'nullable|date',
            'acquisition_cost' => 'nullable|numeric|min:0',
            'end_of_useful_life' => 'nullable|date',
            'asset_notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => 'Category is required',
            'item_name.required' => 'Item name is required',
            'serial_number.unique' => 'This serial number already exists',
            'assigned_to_user.exists' => 'Selected user does not exist',
        ];
    }
}
