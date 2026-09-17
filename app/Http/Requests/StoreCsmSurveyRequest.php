<?php

namespace App\Http\Requests;

use App\Services\CsmStatsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        // D9.31: SQD answers are locked to the exact ARTA scale labels the
        // form posts — free-text answers can no longer pollute CSM averages.
        $rules = [
            'request_id'  => 'required|exists:requests,id',
            'consent'     => 'required|in:yes',
            'email'       => 'nullable|email|max:255',
            'age'         => 'required|integer|min:18|max:99',
            'sex'         => 'required|string|in:Male,Female',
            'cc1'         => 'required|array|size:1',
            'cc2'         => 'required|array|size:1',
            'cc3'         => 'required|array|size:1',
            'suggestions' => 'nullable|string|max:5000',
        ];

        foreach (CsmStatsService::SQD_COLUMNS as $column) {
            $rules[$column] = ['required', 'string', Rule::in(CsmStatsService::validationLabels())];
        }

        return $rules;
    }
}
