<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        
        if ($driver === 'mysql') {
            // Add 'supply_officer' to the existing ENUM
            DB::statement(
                "ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'supply_officer', 'super_admin', 'it') NOT NULL DEFAULT 'user'"
            );
        } else {
            // For non-MySQL databases, convert to string
            Schema::table('users', function ($table) {
                $table->string('role', 32)->default('user')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        
        if ($driver === 'mysql') {
            // Remove 'supply_officer' from ENUM (revert to previous state)
            DB::statement(
                "ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'super_admin', 'it') NOT NULL DEFAULT 'user'"
            );
        }
    }
};