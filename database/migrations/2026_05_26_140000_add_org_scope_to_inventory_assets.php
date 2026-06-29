<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_assets', 'branch')) {
                $table->string('branch')->nullable()->after('region');
            }
            if (!Schema::hasColumn('inventory_assets', 'office')) {
                $table->string('office')->nullable()->after('branch');
            }
            if (!Schema::hasColumn('inventory_assets', 'department')) {
                $table->string('department')->nullable()->after('office');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            foreach (['department', 'office', 'branch'] as $column) {
                if (Schema::hasColumn('inventory_assets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
