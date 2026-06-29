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
        Schema::table('requests', function (Blueprint $table) {
            if (!Schema::hasColumn('requests', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('inventory_assets', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_assets', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('repair_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('repair_requests', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('preventive_maintenance', function (Blueprint $table) {
            if (!Schema::hasColumn('preventive_maintenance', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('repair_requests', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('preventive_maintenance', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
