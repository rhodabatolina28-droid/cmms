<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\User;

class UpdateSuperAdminUserRequest extends FormRequest
{
    // Authorization (super_admin role + abortIfOutsideOfficeScope) is handled in the controller.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from SuperAdminController::updateUser() lines 357-367.
        // The unique-ignore ID is resolved from the route parameter, identical to $user->id in the original.
        $userId = $this->route('id');

        return [
            'full_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $userId,
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
