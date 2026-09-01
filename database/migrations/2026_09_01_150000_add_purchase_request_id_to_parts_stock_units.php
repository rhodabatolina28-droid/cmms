<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link each received physical unit back to the Purchase Request that
     * bought it, so the (view-only) delivery record can show the exact
     * serial + property numbers per quantity — for ANY destination
     * (stock-in AND installed-on-asset).
     *
     * Backfill strategy for pre-existing rows:
     *  - Installed units already carry request_id (the job order) — resolve
     *    the PR through purchase_requests.request_id.
     *  - Stock-in units are matched through the PR's parts_stock_movements
     *    entry (same part, created within ±2s of the unit).
     */
    public function up(): void
    {
        Schema::table('parts_stock_units', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_request_id')->nullable()->after('request_id');
            $table->foreign('purchase_request_id')
                ->references('id')->on('purchase_requests')
                ->nullOnDelete();
            $table->index('purchase_request_id');
        });

        // 1) Installed units: via the job order link on the PR.
        DB::statement("
            UPDATE parts_stock_units u
            JOIN purchase_requests pr ON pr.request_id = u.request_id
            SET u.purchase_request_id = pr.id
            WHERE u.request_id IS NOT NULL
              AND u.purchase_request_id IS NULL
        ");

        // 2) Stock-in units: via the PR stock-in movement of the same part
        //    recorded at (practically) the same moment as the unit.
        DB::statement("
            UPDATE parts_stock_units u
            JOIN parts_stock_movements m
              ON m.part_id = u.part_id
             AND m.reference_type = 'purchase_request'
             AND m.created_at BETWEEN u.created_at - INTERVAL 3 SECOND
                                  AND u.created_at + INTERVAL 3 SECOND
            SET u.purchase_request_id = m.reference_id
            WHERE u.request_id IS NULL
              AND u.purchase_request_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('parts_stock_units', function (Blueprint $table) {
            $table->dropForeign(['purchase_request_id']);
            $table->dropColumn('purchase_request_id');
        });
    }
};
