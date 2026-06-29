<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ===== Default accounts (all roles) =====
        User::create([
            'full_name' => 'Super Admin',
            'email' => 'superadmin@cmms.test',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        User::create([
            'full_name' => 'Test Super Admin',
            'email' => 'test.superadmin@cmms.test',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'position' => 'IT Manager',
            'region' => 'NCR',
            'branch' => 'RCMB',
            'office' => 'Research and Information Division',
            'department' => 'INTERNAL DEPARTMENT',
            'can_supply' => false,
            'is_active' => true,
        ]);

        User::create([
            'full_name' => 'IT Staff',
            'email' => 'it@cmms.test',
            'password' => Hash::make('password'),
            'role' => 'it',
            'is_active' => true,
        ]);

        User::create([
            'full_name' => 'Division Admin',
            'email' => 'admin@cmms.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'can_supply' => true,
            'is_active' => true,
        ]);

        User::create([
            'full_name' => 'End User',
            'email' => 'user@cmms.test',
            'password' => Hash::make('password'),
            'role' => 'user',
            'is_active' => true,
        ]);

        // ===== Sample users via factory =====
        User::factory(10)->create();
    }
}
