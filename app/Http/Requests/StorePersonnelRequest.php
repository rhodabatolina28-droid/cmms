<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonnelRequest extends FormRequest
{
    // Authorization (actor role check) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from PersonnelController::store() lines 141-151.
        // The dynamic role list uses the same config() call as the original.
        return [
            'full_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'role'       => 'required|in:' . implode(',', array_diff(config('roles.list', ['user','admin','it']), ['super_admin', 'supply_officer'])),
            'position'   => 'nullable|string',
            'branch'     => 'nullable|string',
            'office'     => 'nullable|string',
            'department' => 'nullable|string',
            'region'     => 'nullable|string',
            'password'   => 'required|min:8|regex:/[A-Z]/|regex:/[0-9]/',
        ];
    }
}
