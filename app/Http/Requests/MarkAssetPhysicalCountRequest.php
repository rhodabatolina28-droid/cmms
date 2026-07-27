<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkAssetPhysicalCountRequest extends FormRequest
{
    // Authorization (canProcessSupply + session status) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from PhysicalCountController::markAsset() lines 198-202.
        return [
            'asset_id' => 'required|exists:inventory_assets,asset_id',
            'status'   => 'required|in:Present,Missing,Damaged',
            'remarks'  => 'nullable|string|max:500',
        ];
    }
}
