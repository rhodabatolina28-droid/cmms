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
        Schema::dropIfExists('supply_items');
    }

    public function down(): void
    {
        Schema::create('supply_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code')->unique();
            $table->string('item_name');
            $table->string('category');
            $table->integer('stock_quantity')->default(0);
            $table->integer('reorder_level')->default(5);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->string('unit_of_measure')->default('piece');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
