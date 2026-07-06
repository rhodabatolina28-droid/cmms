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
            $table->index('category', 'inventory_assets_category_index');
            $table->index('created_at', 'inventory_assets_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->dropIndex('inventory_assets_category_index');
            $table->dropIndex('inventory_assets_created_at_index');
        });
    }
};
