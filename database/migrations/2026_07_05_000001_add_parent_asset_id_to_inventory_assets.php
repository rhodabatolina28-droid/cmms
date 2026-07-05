<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds self-referential parent_asset_id to inventory_assets so a "Complete Set"
 * CSV row can be split into separate accountable records (CPU parent + Monitor
 * child) while preserving the government-standard shared PAR number.
 *
 * Additive + nullable: existing assets keep parent_asset_id = NULL (singletons).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->foreignId('parent_asset_id')
                ->nullable()
                ->after('asset_id')
                ->constrained('inventory_assets', 'asset_id')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            // Drop FK + column. Index name follows Laravel's table+column convention.
            $table->dropForeign(['parent_asset_id']);
            $table->dropColumn('parent_asset_id');
        });
    }
};
