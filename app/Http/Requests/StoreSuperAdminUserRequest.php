<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSuperAdminUserRequest extends FormRequest
{
    // Authorization (super_admin role) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from SuperAdminController::storeUser() lines 309-320.
        return [
            'full_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|min:8|regex:/[A-Z]/|regex:/[0-9]/',
            'role'       => 'required|in:' . implode(',', config('roles.list', ['user','admin','super_admin','it'])),
            'region'     => 'nullable|string',
            'position'   => 'nullable|string',
            'branch'     => 'nullable|string',
            'office'     => 'required|string|max:255',
            'department' => 'nullable|string',
            'can_supply' => 'nullable|boolean',
        ];
    }
}
