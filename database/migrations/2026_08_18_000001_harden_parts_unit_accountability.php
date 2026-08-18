<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts_stock', function (Blueprint $table) {
            $table->boolean('requires_unit_tracking')->default(false)->after('is_active');
        });

        // The existing system documentation identifies memory and storage as
        // accountable component categories. Other items remain configurable
        // per item rather than being forced by a category-name rule.
        DB::table('parts_stock')
            ->whereIn('category', ['Memory', 'Storage'])
            ->update(['requires_unit_tracking' => true]);

        Schema::table('parts_stock_units', function (Blueprint $table) {
            $table->index('asset_id');
            $table->index('request_id');
            $table->unique(['part_id', 'serial_number']);
            $table->unique(['part_id', 'property_number']);
            $table->foreign('asset_id')
                ->references('asset_id')
                ->on('inventory_assets')
                ->nullOnDelete();
            $table->foreign('request_id')
                ->references('id')
                ->on('requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('parts_stock_units', function (Blueprint $table) {
            $table->dropForeign(['asset_id']);
            $table->dropForeign(['request_id']);
            $table->dropUnique(['part_id', 'serial_number']);
            $table->dropUnique(['part_id', 'property_number']);
            $table->dropIndex(['asset_id']);
            $table->dropIndex(['request_id']);
        });

        Schema::table('parts_stock', function (Blueprint $table) {
            $table->dropColumn('requires_unit_tracking');
        });
    }
};
