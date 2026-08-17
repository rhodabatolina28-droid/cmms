<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockOutPartRequest extends FormRequest
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
            'unit_ids' => 'nullable|array',
            'unit_ids.*' => 'integer',
            'issued_to' => 'nullable|integer',
            'asset_id' => 'nullable|integer',
            'request_id' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'qty.required' => 'Quantity to issue is required',
            'qty.min' => 'Quantity must be at least 1',
            'reason.required' => 'A purpose/remark is required',
        ];
    }
}