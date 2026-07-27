<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewImportRequest extends FormRequest
{
    // Authorization (canWriteInventory check) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from InventoryController::previewImport() lines 219-221.
        return [
            'file' => 'required|file|max:20480|mimes:csv,txt',
        ];
    }
}
