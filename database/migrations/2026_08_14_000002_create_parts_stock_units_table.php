<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-unit tracking ng serialized parts (per-piece custodian).
     * parts_stock = quantity summary; bawat physical item = isang row dito.
     */
    public function up(): void
    {
        Schema::create('parts_stock_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('part_id');
            $table->string('serial_number', 190)->nullable();
            $table->string('property_number', 64)->nullable();
            $table->decimal('unit_value', 14, 2)->nullable();
            $table->string('status', 20)->default('in_stock'); // in_stock | issued | scrapped
            $table->unsignedBigInteger('issued_to')->nullable(); // custodian (user)
            $table->unsignedBigInteger('asset_id')->nullable(); // Phase 5 — naka-install sa asset
            $table->unsignedBigInteger('request_id')->nullable(); // Phase 5 — ginamit sa request
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->foreign('part_id')->references('id')->on('parts_stock')->onDelete('cascade');
            $table->foreign('issued_to')->references('id')->on('users')->onDelete('set null');

            $table->index(['part_id', 'status']);
            $table->index('serial_number');
            $table->index('property_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_stock_units');
    }
};