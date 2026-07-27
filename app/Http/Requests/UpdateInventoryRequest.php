<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryRequest extends FormRequest
{
    // Authorization (canWriteInventory + assetInInventoryScope + status lock checks)
    // is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from InventoryController::update() lines 378-398.
        // No rules changed, no fields added or removed.
        return [
            'item_name'           => 'required|string|max:255',
            'serial_number'       => 'required|string|max:255',
            'property_number'     => 'nullable|string|max:255',
            'brand'               => 'nullable|string|max:255',
            'model'               => 'nullable|string|max:255',
            'status'              => 'required|in:Active,Spare,Defective,For Repair',
            // Note: Scrapped and For Disposal are system-only — set by repair/disposal workflow
            'assigned_to_user'    => 'nullable|exists:users,id',
            'category'            => 'nullable|string',
            'specifications'      => 'nullable',
            'remarks'             => 'nullable|string',
            'branch'              => 'nullable|string|max:255',
            'office'              => 'nullable|string|max:255',
            'department'          => 'nullable|string|max:255',
            'date_acquired'       => 'nullable|date',
            'warranty_expiration' => 'nullable|date',
            'acquisition_cost'    => 'nullable|numeric|min:0',
            'end_of_useful_life'  => 'nullable|date',
            'asset_notes'         => 'nullable|string|max:1000',
        ];
    }
}
