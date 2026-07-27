<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommitImportRequest extends FormRequest
{
    // Authorization (canWriteInventory check) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from InventoryController::commitImport() lines 249-251.
        return [
            'token' => 'required|string|uuid',
        ];
    }
}
