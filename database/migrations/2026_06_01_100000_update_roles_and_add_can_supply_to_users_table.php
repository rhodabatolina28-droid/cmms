<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add can_supply column
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'can_supply')) {
                $table->boolean('can_supply')->default(0)->after('role');
            }
        });

        // 2. Migrate existing supply_officer users
        DB::table('users')->where('role', 'supply_officer')->update([
            'role' => 'admin',
            'can_supply' => 1,
            'department' => 'Administrative Division',
        ]);

        // 3. Update ENUM if MySQL
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'super_admin', 'it') NOT NULL DEFAULT 'user'"
            );
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 32)->default('user')->change();
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE users MODIFY COLUMN role ENUM('user', 'admin', 'super_admin', 'it', 'supply_officer') NOT NULL DEFAULT 'user'"
            );
        }

        // Revert can_supply=1 users back to supply_officer (Lossy operation)
        DB::table('users')->where('role', 'admin')->where('can_supply', 1)->update([
            'role' => 'supply_officer'
        ]);

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'can_supply')) {
                $table->dropColumn('can_supply');
            }
        });
    }
};
