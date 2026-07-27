<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadAttachmentRequest extends FormRequest
{
    // Authorization (canProcessSupply + scope check) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from InventoryController::uploadAttachment() lines 630-633.
        return [
            'file'  => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,zip|mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/jpeg,image/png,image/gif,application/zip',
            'label' => 'nullable|string|max:100',
        ];
    }
}
