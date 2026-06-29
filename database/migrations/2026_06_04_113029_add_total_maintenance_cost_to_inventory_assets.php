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
            $table->decimal('total_maintenance_cost', 10, 2)->default(0)->after('acquisition_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->dropColumn('total_maintenance_cost');
        });
    }
};
