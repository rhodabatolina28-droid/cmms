<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLinkedAssetRequest extends FormRequest
{
    // Authorization (canCreateIctTicket / canCreateMaintenanceTicket) is handled
    // in the controller before this Form Request fires.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from:
        //   ICTRequestController::store()         line 444-446
        //   MaintenanceController::store()        line 174-176
        // Both controllers had the identical single-rule validate call.
        return [
            'linked_asset_id' => 'required|integer|exists:inventory_assets,asset_id',
        ];
    }
}
