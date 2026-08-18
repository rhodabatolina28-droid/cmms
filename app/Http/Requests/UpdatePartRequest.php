<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->canProcessSupply();
    }

    public function rules(): array
    {
        return [
            'item_name' => 'required|string|max:190',
            'unit' => 'required|string|max:32',
            'category' => 'nullable|string|max:64',
            'reorder_level' => 'nullable|integer|min:0',
            'region' => 'nullable|string|max:64',
            'branch' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
            'requires_unit_tracking' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'item_name.required' => 'Item name is required',
            'unit.required' => 'Unit is required',
            'reorder_level.min' => 'Reorder level cannot be negative',
        ];
    }
}
