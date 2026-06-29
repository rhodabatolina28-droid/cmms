<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('inventory_assets', 'branch')
            || !Schema::hasColumn('inventory_assets', 'office')
            || !Schema::hasColumn('inventory_assets', 'department')) {
            return;
        }

        DB::table('inventory_assets')
            ->join('users', 'inventory_assets.assigned_to_user', '=', 'users.id')
            ->whereNotNull('inventory_assets.assigned_to_user')
            ->update([
                'inventory_assets.branch' => DB::raw('users.branch'),
                'inventory_assets.office' => DB::raw('users.office'),
                'inventory_assets.department' => DB::raw('users.department'),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasColumn('inventory_assets', 'branch')
            || !Schema::hasColumn('inventory_assets', 'office')
            || !Schema::hasColumn('inventory_assets', 'department')) {
            return;
        }

        DB::table('inventory_assets')->update([
            'branch' => null,
            'office' => null,
            'department' => null,
        ]);
    }
};
