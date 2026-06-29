<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class ProfileController extends Controller
{
    public function index()
    {
        return view('profile.index', [
            'user' => Auth::user()
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'position'  => 'nullable|string|max:255',
            'password'  => 'nullable|string|min:8|regex:/[A-Z]/|regex:/[0-9]/|confirmed',
        ]);

        $user->full_name = $request->full_name;
        $user->email     = $request->email;
        $user->position  = $request->input('position');

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
            $request->session()->regenerate();
        }

        $user->save();

        return redirect()->back()->with('success', 'Profile updated successfully.');
    }
}
