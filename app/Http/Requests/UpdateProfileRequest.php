<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    // No additional authorization needed — auth middleware handles login requirement.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Extracted verbatim from ProfileController::update() lines 23-28.
        // The unique-ignore ID uses Auth::id() — same source as the original $user->id.
        return [
            'full_name' => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users,email,' . $this->user()->id,
            'position'  => 'nullable|string|max:255',
            'password'  => 'nullable|string|min:8|regex:/[A-Z]/|regex:/[0-9]/|confirmed',
        ];
    }
}
