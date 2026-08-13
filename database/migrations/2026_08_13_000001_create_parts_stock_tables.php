<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parts & Consumables Stock — quantity-based supplies ledger (COA-aligned).
     * Two tables:
     *   - parts_stock            : the part/consumable master list (no serial, qty + unit)
     *   - parts_stock_movements  : audit trail for every stock-in / stock-out
     */
    public function up(): void
    {
        Schema::create('parts_stock', function (Blueprint $table) {
            $table->id();
            $table->string('item_name', 190);
            $table->string('unit', 32);
            $table->string('category', 64)->nullable();
            $table->unsignedInteger('on_hand_qty')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->string('region', 64)->nullable();
            $table->string('branch', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('region');
            $table->index('branch');
            $table->index('category');
            $table->index('is_active');
        });

        Schema::create('parts_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('part_id');
            $table->integer('qty_change');
            $table->string('reason', 190);
            $table->string('reference_type', 32)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            // Audit trail only — this table is append-only (no updated_at)
            $table->timestamp('created_at')->nullable();

            $table->foreign('part_id')->references('id')->on('parts_stock')->onDelete('cascade');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('set null');
            $table->index('part_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        // Drop the child (movements) before the parent (stock) to avoid FK errors.
        Schema::dropIfExists('parts_stock_movements');
        Schema::dropIfExists('parts_stock');
    }
};