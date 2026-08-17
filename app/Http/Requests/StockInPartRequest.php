<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockInPartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->canProcessSupply();
    }

    public function rules(): array
    {
        return [
            'qty' => 'required|integer|min:1',
            'reason' => 'required|string|max:190',
            'reference_type' => 'nullable|string|max:32',
            'reference_id' => 'nullable|integer',
            'units' => 'nullable|array',
            'units.*.serial_number' => 'nullable|string|max:190',
            'units.*.property_number' => 'nullable|string|max:64',
            'units.*.unit_value' => 'nullable|numeric',
        ];
    }

    public function messages(): array
    {
        return [
            'qty.required' => 'Quantity to add is required',
            'qty.min' => 'Quantity must be at least 1',
            'reason.required' => 'A source/remark is required',
        ];
    }
}