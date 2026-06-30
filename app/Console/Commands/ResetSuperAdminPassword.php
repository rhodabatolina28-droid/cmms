<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetSuperAdminPassword extends Command
{
    protected $signature = 'admin:reset-password {email?} {password?}';
    protected $description = 'Reset password for super admin or any user';

    public function handle()
    {
        $email = $this->argument('email') ?: 'superadmin@cmms.com';
        $password = $this->argument('password') ?: 'admin123';

        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error("User with email {$email} not found!");
            return 1;
        }

        $user->password = Hash::make($password);
        $user->save();

        $this->info("Password updated successfully for {$user->full_name} ({$email})");
        
        // Verify
        if (Hash::check($password, $user->password)) {
            $this->info("✓ Password verification: PASSED");
        } else {
            $this->error("✗ Password verification: FAILED");
        }

        return 0;
    }
}