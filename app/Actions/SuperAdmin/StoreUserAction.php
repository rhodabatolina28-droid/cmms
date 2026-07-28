<?php

namespace App\Actions\SuperAdmin;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class StoreUserAction
{
    /**
     * Store a newly created user.
     * Super Admin must explicitly assign office/division — no auto-fill from actor scope.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Request $request)
    {
        $validated = $request->validated();

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = true;

        // Convert supply_officer to admin with can_supply=1 (one role per user in government setup)
        if ($validated['role'] === 'supply_officer') {
            $validated['role'] = 'admin';
            $validated['can_supply'] = true;
        } else {
            $validated['can_supply'] = $request->boolean('can_supply');
        }

        // Always inherit region and branch from the creating super admin
        $validated['region'] = Auth::user()->region;
        $validated['branch'] = Auth::user()->branch;

        $user = User::create($validated);

        AuditLog::log(
            "Created User Account",
            "User Management",
            "Created account for {$user->full_name} ({$user->email}) with role {$user->role} in {$user->office}",
            $user->office
        );

        return response()->json(['success' => true, 'message' => 'User created successfully']);
    }
}
