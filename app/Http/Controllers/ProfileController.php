<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Http\Requests\UpdateProfileRequest;

class ProfileController extends Controller
{
    public function index()
    {
        return view('profile.index', [
            'user' => Auth::user()
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = Auth::user();

        $user->full_name = $request->full_name;
        $user->email     = $request->email;
        // D4a guardrail: position is NO LONGER editable here. It is set only by
        // Super Admin (User Management) / Department Admin (Personnel Management).
        // Otherwise any user could type "Director" and jump the IT queue.

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
            $request->session()->regenerate();
        }

        $user->save();

        return redirect()->back()->with('success', 'Profile updated successfully.');
    }
}
