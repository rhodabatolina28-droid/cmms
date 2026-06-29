<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->index('branch', 'inventory_assets_branch_index');
            $table->index('office', 'inventory_assets_office_index');
            $table->index('department', 'inventory_assets_department_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('branch', 'users_branch_index');
            $table->index('office', 'users_office_index');
            $table->index('department', 'users_department_index');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->dropIndex('inventory_assets_branch_index');
            $table->dropIndex('inventory_assets_office_index');
            $table->dropIndex('inventory_assets_department_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_branch_index');
            $table->dropIndex('users_office_index');
            $table->dropIndex('users_department_index');
        });
    }
};
