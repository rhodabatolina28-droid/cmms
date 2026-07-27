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
        $user->position  = $request->input('position');

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
            $request->session()->regenerate();
        }

        $user->save();

        return redirect()->back()->with('success', 'Profile updated successfully.');
    }
}
