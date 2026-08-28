<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Receipt / proof-of-purchase uploads for Purchase Requests.
 * Stricter than asset attachments: pdf/jpg/jpeg/png only (user-decided scope).
 */
class UploadPrAttachmentRequest extends FormRequest
{
    // Authorization (owner / supply / super admin, pre-delivery) is handled
    // inside UploadPrAttachmentAction::canUpload().
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'  => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png|mimetypes:application/pdf,image/jpeg,image/png',
            'label' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes'   => 'Receipts must be a PDF, JPG or PNG file.',
            'file.max'     => 'Receipt files may not exceed 10MB.',
        ];
    }
}
