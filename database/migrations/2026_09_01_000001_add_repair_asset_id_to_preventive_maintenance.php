<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preventive_maintenance', function (Blueprint $table) {
            $table->unsignedBigInteger('repair_asset_id')->nullable()->after('for_repair');
            $table->foreign('repair_asset_id')->references('asset_id')->on('inventory_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('preventive_maintenance', function (Blueprint $table) {
            $table->dropForeign(['repair_asset_id']);
            $table->dropColumn('repair_asset_id');
        });
    }
};
