<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preventive_maintenance', function (Blueprint $table) {
            $table->unsignedBigInteger('disposal_asset_id')->nullable()->after('for_disposal');
            $table->foreign('disposal_asset_id')->references('asset_id')->on('inventory_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('preventive_maintenance', function (Blueprint $table) {
            $table->dropForeign(['disposal_asset_id']);
            $table->dropColumn('disposal_asset_id');
        });
    }
};