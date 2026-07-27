<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCsmSurveyRequest extends FormRequest
{
    // Authorization (role === 'user' + no existing survey) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from CsmController::store() lines 46-64.
        return [
            'request_id'  => 'required|exists:requests,id',
            'consent'     => 'required|in:yes',
            'age'         => 'required|integer|min:18|max:99',
            'sex'         => 'required|string|in:Male,Female',
            'cc1'         => 'required|array|size:1',
            'cc2'         => 'required|array|size:1',
            'cc3'         => 'required|array|size:1',
            'sqd1'        => 'required|string',
            'sqd2'        => 'required|string',
            'sqd3'        => 'required|string',
            'sqd4'        => 'required|string',
            'sqd5'        => 'required|string',
            'sqd6'        => 'required|string',
            'sqd7'        => 'required|string',
            'sqd8'        => 'required|string',
            'sqd9'        => 'required|string',
            'suggestions' => 'nullable|string|max:5000',
        ];
    }
}
